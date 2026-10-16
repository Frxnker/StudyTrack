<?php
/**
 * Calendarios iCal (.ics) sin librerías: descarga segura y lectura de eventos.
 *
 *  1. Descarga (ical_download)
 *     - Solo http:// y https:// (webcal:// se convierte en https://).
 *     - 10 s como máximo, 2 MB como mucho y hasta 3 redirecciones.
 *     - Protección SSRF: antes de conectar se resuelve el nombre del servidor y se
 *       rechaza si apunta a localhost, a una red privada o a una dirección
 *       link-local. Esa IP comprobada es la que usa cURL (CURLOPT_RESOLVE), así
 *       que un cambio de DNS entre la comprobación y la conexión no sirve de nada.
 *       Cada redirección se vuelve a comprobar igual.
 *
 *  2. Lectura (ical_parse), según RFC 5545 simplificado
 *     - Despliega las líneas plegadas y lee los VEVENT: UID, SUMMARY, DESCRIPTION,
 *       LOCATION, DTSTART, DTEND, CATEGORIES y STATUS.
 *     - Fechas de día completo (VALUE=DATE), en UTC (sufijo Z), con TZID o
 *       "flotantes" (hora local del calendario); todas se pasan a APP_TIMEZONE.
 *     - Desescapa \n \, \; y \\ en los textos.
 */

declare(strict_types=1);

const ICAL_MAX_BYTES     = 2 * 1024 * 1024; // 2 MB
const ICAL_TIMEOUT       = 10;              // segundos
const ICAL_MAX_REDIRECTS = 3;

/* =============================================================================
 * 1. Descarga segura
 * ========================================================================== */

/**
 * Comprueba y normaliza la URL de un calendario: webcal:// → https://.
 * Lanza RuntimeException con un mensaje para el usuario si no se admite.
 */
function ical_normalize_url(string $url): string
{
    $url = trim($url);
    if (preg_match('#^webcals?://#i', $url) === 1) {
        $url = 'https://' . preg_replace('#^webcals?://#i', '', $url);
    }

    $parts = parse_url($url);
    if ($parts === false || !isset($parts['scheme'], $parts['host']) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('La dirección no es una URL válida.');
    }
    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        throw new RuntimeException('Solo se admiten direcciones http://, https:// o webcal://.');
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('La URL no puede llevar usuario ni contraseña.');
    }

    return $url;
}

/**
 * Resuelve el servidor de una URL y devuelve una IP pública para conectar.
 * Si alguna de sus IPs es local o privada, lanza RuntimeException (SSRF).
 */
function ical_resolve_public_ip(string $host): string
{
    $host = trim($host, '[]'); // IPv6 escrita como [2001:db8::1]

    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $ips = [$host];
    } elseif (preg_match('/^(?:0x[0-9a-f]+|[0-9.]+)$/i', $host) === 1) {
        // IPs escritas de forma abreviada u ofuscada: 127.1, 2130706433, 0x7f000001…
        throw new RuntimeException('Por seguridad no se permiten direcciones locales ni de red privada.');
    } else {
        $lower = strtolower(rtrim($host, '.'));
        if ($lower === 'localhost' || str_ends_with($lower, '.localhost')) {
            throw new RuntimeException('Por seguridad no se permiten direcciones locales ni de red privada.');
        }
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
        if ($ips === []) {
            throw new RuntimeException("No se encuentra el servidor «{$host}». Revisa la dirección.");
        }
    }

    foreach ($ips as $ip) {
        if (!ical_ip_is_public($ip)) {
            throw new RuntimeException('Por seguridad no se permiten direcciones locales ni de red privada.');
        }
    }

    return $ips[0];
}

/**
 * ¿Es una IP pública? Falso para localhost, redes privadas, link-local,
 * direcciones reservadas, multidifusión y rangos de documentación.
 */
function ical_ip_is_public(string $ip): bool
{
    // IPv4 dentro de IPv6 (::ffff:127.0.0.1): se comprueba la parte IPv4
    if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $match) === 1) {
        $ip = $match[1];
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false; // 10/8, 172.16/12, 192.168/16, 127/8, 169.254/16, 0/8, ::1, fe80::/10…
    }

    $blocked = [
        '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24',
        '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', 'fc00::/7', 'fe80::/10', 'ff00::/8', '2001:db8::/32',
    ];
    foreach ($blocked as $cidr) {
        if (ical_ip_in_cidr($ip, $cidr)) {
            return false;
        }
    }

    return true;
}

/**
 * ¿Está la IP dentro del rango CIDR? Sirve para IPv4 e IPv6.
 */
function ical_ip_in_cidr(string $ip, string $cidr): bool
{
    [$subnet, $bits] = explode('/', $cidr);
    $ipBin     = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }

    $bits  = (int) $bits;
    $bytes = intdiv($bits, 8);
    if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
        return false;
    }
    if ($bits % 8 === 0) {
        return true;
    }
    $mask = chr((0xFF << (8 - $bits % 8)) & 0xFF);

    return ($ipBin[$bytes] & $mask) === ($subnetBin[$bytes] & $mask);
}

/**
 * Descarga un calendario y devuelve su contenido. Lanza RuntimeException con
 * un mensaje para el usuario si algo falla (URL no admitida, error de red…).
 */
function ical_download(string $url): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Falta la extensión cURL de PHP (actívala en php.ini).');
    }

    $url = ical_normalize_url($url);

    for ($redirects = 0; ; $redirects++) {
        $parts  = parse_url($url);
        $scheme = strtolower($parts['scheme']);
        $host   = $parts['host'];
        $port   = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $ip     = ical_resolve_public_ip($host);

        $body   = '';
        $tooBig = false;
        $handle = curl_init($url);
        $options = [
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,        // las redirecciones se comprueban aquí, una a una
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => ICAL_TIMEOUT,
            CURLOPT_PROXY          => '',           // sin proxy: se conecta a la IP comprobada
            CURLOPT_ENCODING       => '',           // acepta gzip; el límite se aplica a lo descomprimido
            CURLOPT_USERAGENT      => APP_NAME . '/' . APP_VERSION . ' (sincronización de calendarios)',
            CURLOPT_HTTPHEADER     => ['Accept: text/calendar, text/plain;q=0.9, */*;q=0.5'],
            CURLOPT_WRITEFUNCTION  => static function ($curl, string $chunk) use (&$body, &$tooBig): int {
                if (strlen($body) + strlen($chunk) > ICAL_MAX_BYTES) {
                    $tooBig = true;
                    return 0; // corta la descarga
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ];
        // Conectar exactamente a la IP comprobada (si el host es un nombre)
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) === false) {
            $address = str_contains($ip, ':') ? "[{$ip}]" : $ip;
            $options[CURLOPT_RESOLVE] = ["{$host}:{$port}:{$address}"];
        }
        curl_setopt_array($handle, $options);

        $ok       = curl_exec($handle);
        $status   = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $location = (string) curl_getinfo($handle, CURLINFO_REDIRECT_URL);
        $errno    = curl_errno($handle);
        $error    = curl_error($handle);

        if ($tooBig) {
            throw new RuntimeException('El calendario ocupa más de 2 MB.');
        }
        if ($ok === false) {
            throw new RuntimeException(ical_curl_message($errno, $error));
        }
        if ($status >= 300 && $status < 400 && $location !== '') {
            if ($redirects >= ICAL_MAX_REDIRECTS) {
                throw new RuntimeException('La dirección redirige demasiadas veces.');
            }
            $url = ical_normalize_url($location);
            continue;
        }
        if ($status !== 200) {
            throw new RuntimeException(match (true) {
                $status === 401, $status === 403 => "El servidor no permite descargar el calendario (HTTP {$status}). Comprueba que la URL es la de exportación y que no ha caducado.",
                $status === 404 => 'No se encuentra el calendario en esa dirección (HTTP 404).',
                default         => "El servidor respondió con un error (HTTP {$status}).",
            });
        }

        return $body;
    }
}

/**
 * Mensaje comprensible para los errores de cURL más habituales.
 */
function ical_curl_message(int $errno, string $error): string
{
    return match ($errno) {
        CURLE_OPERATION_TIMEDOUT    => 'El servidor tardó demasiado en responder (más de ' . ICAL_TIMEOUT . ' s).',
        CURLE_COULDNT_RESOLVE_HOST  => 'No se encuentra el servidor. Revisa la dirección.',
        CURLE_COULDNT_CONNECT       => 'No se pudo conectar con el servidor.',
        CURLE_SSL_CONNECT_ERROR, 60 => 'No se pudo establecer una conexión segura (certificado SSL).',
        default                     => 'No se pudo descargar el calendario (' . $error . ').',
    };
}

/* =============================================================================
 * 2. Lectura de eventos
 * ========================================================================== */

/**
 * Lee un calendario iCal. Devuelve:
 *   ['nombre' => X-WR-CALNAME o null, 'eventos' => [evento, …]]
 * Cada evento: uid, titulo, descripcion, lugar, categorias[], estado, recurrente,
 *   inicio y fin (['fecha' => 'Y-m-d', 'hora' => 'H:i' o null]; fin puede ser null).
 * Los eventos sin DTSTART se descartan. Lanza RuntimeException si no es un iCal.
 */
function ical_parse(string $content): array
{
    // BOM, saltos de línea de cualquier sistema y líneas plegadas (continúan con espacio o tabulador)
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? '';
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $content = preg_replace('/\n[ \t]/', '', $content) ?? '';

    if (preg_match('/^BEGIN:VCALENDAR\s*$/mi', $content) !== 1) {
        throw new RuntimeException('La dirección no devuelve un calendario iCal válido.');
    }

    $calendarName = null;
    $calendarZone = null;   // X-WR-TIMEZONE: zona de las horas "flotantes"
    $events       = [];
    $event        = null;   // evento que se está leyendo
    $nested       = 0;      // componentes dentro del evento (VALARM…), que se ignoran

    foreach (explode("\n", $content) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $property = ical_parse_line($line);
        if ($property === null) {
            continue;
        }
        [$name, $params, $value] = $property;

        if ($name === 'BEGIN') {
            if (strtoupper($value) === 'VEVENT' && $event === null) {
                $event = ['categorias' => [], 'props' => []];
            } elseif ($event !== null) {
                $nested++;
            }
            continue;
        }
        if ($name === 'END') {
            if ($event !== null && $nested > 0) {
                $nested--;
            } elseif ($event !== null && strtoupper($value) === 'VEVENT') {
                $built = ical_build_event($event, $calendarZone);
                if ($built !== null) {
                    $events[] = $built;
                }
                $event = null;
            }
            continue;
        }

        if ($event === null) {
            // Propiedades del calendario
            if ($name === 'X-WR-CALNAME') {
                $calendarName = ical_unescape($value);
            } elseif ($name === 'X-WR-TIMEZONE') {
                $calendarZone = trim($value);
            }
            continue;
        }
        if ($nested > 0) {
            continue;
        }

        if ($name === 'CATEGORIES') {
            foreach (preg_split('/(?<!\\\\),/', $value) ?: [] as $category) {
                $category = trim(ical_unescape($category));
                if ($category !== '') {
                    $event['categorias'][] = $category;
                }
            }
        } else {
            $event['props'][$name] = ['params' => $params, 'value' => $value];
        }
    }

    return ['nombre' => $calendarName, 'eventos' => $events];
}

/**
 * Separa una línea "NOMBRE;PARAM=VALOR:valor" en [NOMBRE, [PARAM => VALOR], valor].
 * El ":" que separa el valor es el primero que no está entre comillas.
 */
function ical_parse_line(string $line): ?array
{
    $length   = strlen($line);
    $inQuotes = false;
    for ($i = 0; $i < $length; $i++) {
        if ($line[$i] === '"') {
            $inQuotes = !$inQuotes;
        } elseif ($line[$i] === ':' && !$inQuotes) {
            break;
        }
    }
    if ($i >= $length) {
        return null;
    }

    $segments = preg_split('/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', substr($line, 0, $i)) ?: [];
    $name     = strtoupper(trim((string) array_shift($segments)));
    $params   = [];
    foreach ($segments as $segment) {
        [$key, $paramValue] = array_pad(explode('=', $segment, 2), 2, '');
        $params[strtoupper(trim($key))] = trim($paramValue, '"');
    }

    return [$name, $params, substr($line, $i + 1)];
}

/**
 * Desescapa un texto iCal: \n o \N → salto de línea, \, → , \; → ; y \\ → \
 */
function ical_unescape(string $text): string
{
    return preg_replace_callback(
        '/\\\\([\\\\;,nN])/',
        static fn (array $m): string => ($m[1] === 'n' || $m[1] === 'N') ? "\n" : $m[1],
        $text
    ) ?? $text;
}

/**
 * Construye el evento final a partir de sus propiedades. Null si no tiene DTSTART.
 */
function ical_build_event(array $raw, ?string $calendarZone): ?array
{
    $props = $raw['props'];
    $start = isset($props['DTSTART']) ? ical_parse_date($props['DTSTART']['value'], $props['DTSTART']['params'], $calendarZone) : null;
    if ($start === null) {
        return null;
    }
    $end = isset($props['DTEND']) ? ical_parse_date($props['DTEND']['value'], $props['DTEND']['params'], $calendarZone) : null;

    $text  = static fn (string $key): string => isset($props[$key]) ? trim(ical_unescape($props[$key]['value'])) : '';
    $title = $text('SUMMARY');
    $uid   = $text('UID');

    return [
        // Sin UID (no debería pasar) se crea uno estable para no duplicarlo
        'uid'         => $uid !== '' ? $uid : 'sin-uid-' . sha1($title . '|' . $start['fecha'] . '|' . $start['hora']),
        'titulo'      => $title !== '' ? $title : '(Sin título)',
        'descripcion' => $text('DESCRIPTION'),
        'lugar'       => $text('LOCATION'),
        'categorias'  => $raw['categorias'],
        'estado'      => strtoupper($text('STATUS')),
        'recurrente'  => isset($props['RRULE']),
        'inicio'      => $start,
        'fin'         => $end,
    ];
}

/**
 * Convierte DTSTART/DTEND a la zona de la aplicación.
 * Devuelve ['fecha' => 'Y-m-d', 'hora' => 'H:i' o null (día completo)] o null si no se entiende.
 */
function ical_parse_date(string $value, array $params, ?string $calendarZone): ?array
{
    $value = trim($value);

    // Día completo: VALUE=DATE o solo AAAAMMDD
    if (($params['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $value) === 1) {
        $date = DateTimeImmutable::createFromFormat('!Ymd', substr($value, 0, 8));
        return $date ? ['fecha' => $date->format('Y-m-d'), 'hora' => null] : null;
    }

    if (preg_match('/^(\d{8}T\d{4})(\d{2})?(Z?)$/i', $value, $m) !== 1) {
        return null;
    }
    $local = new DateTimeZone(APP_TIMEZONE);

    if (strtoupper($m[3]) === 'Z') {
        $zone = new DateTimeZone('UTC');                                  // UTC
    } else {
        $zone = ical_timezone($params['TZID'] ?? null)                     // TZID=Europe/Madrid
             ?? ical_timezone($calendarZone)                               // hora "flotante"
             ?? $local;
    }

    $date = DateTimeImmutable::createFromFormat('!Ymd\THis', $m[1] . ($m[2] !== '' ? $m[2] : '00'), $zone);
    if ($date === false) {
        return null;
    }
    $date = $date->setTimezone($local);

    return ['fecha' => $date->format('Y-m-d'), 'hora' => $date->format('H:i')];
}

/**
 * Zona horaria a partir de un TZID ("Europe/Madrid"). Null si no existe
 * (por ejemplo los nombres de Windows de algunos calendarios de Outlook).
 */
function ical_timezone(?string $tzid): ?DateTimeZone
{
    $tzid = trim((string) $tzid, " \"/");
    if ($tzid === '') {
        return null;
    }
    // Thunderbird: "/mozilla.org/20070129_1/Europe/Madrid" → "Europe/Madrid"
    $candidates = [$tzid, implode('/', array_slice(explode('/', $tzid), -2))];
    foreach (array_unique($candidates) as $candidate) {
        try {
            return new DateTimeZone($candidate);
        } catch (Exception) {
            // probamos el siguiente
        }
    }

    return null;
}
