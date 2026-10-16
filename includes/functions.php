<?php
/**
 * Funciones auxiliares reutilizables en toda la aplicación.
 *
 *  1. Seguridad y salida HTML (e, csrf…)
 *  2. Peticiones y redirecciones
 *  3. Mensajes flash
 *  4. Validación de formularios
 *  5. Texto
 *  6. Fechas
 *  7. Vistas y plantillas
 *  8. Errores
 */

declare(strict_types=1);

/* =============================================================================
 * 1. Seguridad y salida HTML
 * ========================================================================== */

/**
 * Escapa un valor para imprimirlo en HTML de forma segura (evita XSS).
 * Úsala SIEMPRE que muestres datos que vengan del usuario o de la base de datos.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Token CSRF de la sesión: protege los formularios frente a peticiones
 * enviadas desde otras webs en nombre del usuario.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

/**
 * Campo oculto con el token CSRF para incluir en cada <form method="post">.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Comprueba el token CSRF de la petición POST. Si no es válido, corta la petición.
 */
function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';

    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        flash('error', 'El formulario ha caducado. Vuelve a intentarlo.');
        redirect(current_page() . '.php');
    }
}

/**
 * Clave de cifrado de la aplicación (APP_KEY) en binario: 32 bytes.
 * Formato recomendado: "base64:" + 32 bytes aleatorios en base64. Cualquier otro
 * texto de 32 caracteres o más también vale (se deriva con SHA-256).
 * Lanza RuntimeException si falta o no es válida.
 */
function app_key(): string
{
    $key = APP_KEY;

    if (str_starts_with($key, 'base64:')) {
        $raw = base64_decode(substr($key, 7), true);
        if ($raw !== false && strlen($raw) === 32) {
            return $raw;
        }
    } elseif (strlen($key) >= 32) {
        return hash('sha256', $key, true);
    }

    throw new RuntimeException('Falta APP_KEY o no es válida. Créala en config/app.local.php (ver config/app.local.example.php).');
}

function has_app_key(): bool
{
    try {
        app_key();
        return true;
    } catch (RuntimeException) {
        return false;
    }
}

/**
 * Cifra un texto con AES-256-GCM (cifrado autenticado: si alguien lo modifica
 * en la base de datos, no se descifra). Resultado: "v1:" + base64(iv + etiqueta + datos).
 */
function encrypt_secret(string $plain): string
{
    $iv     = random_bytes(12);
    $tag    = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cipher === false) {
        throw new RuntimeException('No se pudo cifrar el dato.');
    }

    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

/**
 * Descifra un texto de encrypt_secret(). Lanza RuntimeException si no se puede
 * (dato manipulado o APP_KEY distinta de la que se usó al cifrar).
 */
function decrypt_secret(string $encoded): string
{
    $raw = str_starts_with($encoded, 'v1:') ? base64_decode(substr($encoded, 3), true) : false;
    if ($raw === false || strlen($raw) < 28) {
        throw new RuntimeException('El dato cifrado no tiene un formato válido.');
    }

    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    if ($plain === false) {
        throw new RuntimeException('No se pudo descifrar la URL: ¿ha cambiado APP_KEY? Vuelve a introducirla.');
    }

    return $plain;
}

/* =============================================================================
 * 2. Peticiones y redirecciones
 * ========================================================================== */

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Redirige a otra página y termina la ejecución.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Valor de texto enviado por POST, sin espacios sobrantes.
 */
function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

/**
 * Valor de texto recibido por GET (filtros, búsquedas, ids…).
 */
function query(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

/**
 * Entero positivo recibido por GET o POST (ids). Devuelve 0 si no es válido.
 */
function request_id(string $key = 'id'): int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? '';

    return is_string($value) && ctype_digit($value) ? (int) $value : 0;
}

/**
 * Nombre del script actual sin extensión: "tasks", "dashboard"…
 */
function current_page(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'), '.php');
}

/* =============================================================================
 * 3. Mensajes flash (se muestran una vez tras una redirección)
 * ========================================================================== */

/**
 * Guarda un mensaje para la siguiente página. Tipos: success, error, warning, info.
 */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Devuelve y elimina los mensajes flash pendientes.
 */
function get_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return $messages;
}

/* =============================================================================
 * 4. Validación de formularios
 * ========================================================================== */

/**
 * Valida un array de datos con reglas sencillas separadas por "|".
 *
 *   $errors = validate($data, [
 *       'email'  => 'required|email|max:150',
 *       'nota'   => 'required|numeric|between:0,10',
 *   ], ['email' => 'correo electrónico']);
 *
 * Reglas: required, email, min:N, max:N, numeric, integer, between:A,B,
 *         date, time, in:a,b,c, color, same:campo, password
 *
 * Devuelve [campo => mensaje]. Si está vacío, los datos son válidos.
 * Los campos opcionales vacíos no se validan.
 */
function validate(array $data, array $rules, array $labels = [], array $messages = []): array
{
    $errors = [];

    foreach ($rules as $field => $ruleString) {
        $raw   = $data[$field] ?? '';
        $value = is_scalar($raw) ? (string) $raw : '';
        $label = $labels[$field] ?? str_replace('_', ' ', $field);

        // Texto con bytes que no son UTF-8 válido (lo enviaría un cliente manipulado)
        if (preg_match('//u', $value) !== 1) {
            $errors[$field] = "El campo {$label} contiene caracteres no válidos.";
            continue;
        }

        foreach (explode('|', $ruleString) as $rule) {
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

            if ($name === 'required') {
                if (trim($value) === '') {
                    $errors[$field] = $messages["$field.required"] ?? "El campo {$label} es obligatorio.";
                    break;
                }
                continue;
            }

            // Campo opcional vacío: no se comprueban el resto de reglas
            if (trim($value) === '') {
                break;
            }

            $error = validation_error($name, $param, $value, $label, $data, $labels);
            if ($error !== null) {
                $errors[$field] = $messages["$field.$name"] ?? $error;
                break;
            }
        }
    }

    return $errors;
}

/**
 * Comprueba una regla concreta. Devuelve el mensaje de error o null si es válida.
 */
function validation_error(string $rule, ?string $param, string $value, string $label, array $data, array $labels): ?string
{
    switch ($rule) {
        case 'email':
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null : "El campo {$label} debe ser un email válido.";

        case 'min':
            return str_length($value) >= (int) $param
                ? null : "El campo {$label} debe tener al menos {$param} caracteres.";

        case 'max':
            return str_length($value) <= (int) $param
                ? null : "El campo {$label} no puede superar los {$param} caracteres.";

        case 'numeric':
            return is_numeric($value) ? null : "El campo {$label} debe ser un número.";

        case 'integer':
            return filter_var($value, FILTER_VALIDATE_INT) !== false
                ? null : "El campo {$label} debe ser un número entero.";

        case 'between':
            [$min, $max] = array_map('floatval', explode(',', (string) $param));
            return is_numeric($value) && (float) $value >= $min && (float) $value <= $max
                ? null : "El campo {$label} debe estar entre " . format_number($min) . ' y ' . format_number($max) . '.';

        case 'date':
            return is_valid_date($value) ? null : "El campo {$label} debe ser una fecha válida.";

        case 'time':
            return is_valid_time($value) ? null : "El campo {$label} debe ser una hora válida.";

        case 'in':
            return in_array($value, explode(',', (string) $param), true)
                ? null : "El valor del campo {$label} no es válido.";

        case 'color':
            return preg_match('/^#[0-9a-f]{6}$/i', $value) === 1
                ? null : "El campo {$label} debe ser un color válido.";

        case 'same':
            $otherLabel = $labels[$param] ?? $param;
            return $value === (string) ($data[$param] ?? '')
                ? null : "El campo {$label} no coincide con {$otherLabel}.";

        case 'password':
            return preg_match('/\p{L}/u', $value) === 1 && preg_match('/\d/', $value) === 1
                ? null : 'La contraseña debe incluir al menos una letra y un número.';

        default:
            throw new InvalidArgumentException("Regla de validación desconocida: {$rule}");
    }
}

function is_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

function is_valid_time(string $value): bool
{
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value) === 1;
}

/**
 * Clases y atributos para marcar un campo con error en las vistas.
 */
function field_class(array $errors, string $field): string
{
    return isset($errors[$field]) ? 'field has-error' : 'field';
}

function field_aria(array $errors, string $field): string
{
    return isset($errors[$field])
        ? ' aria-invalid="true" aria-describedby="' . e($field) . '-error"'
        : '';
}

function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field__error" id="' . e($field) . '-error">'
         . '<i class="ri-error-warning-fill" aria-hidden="true"></i>' . e($errors[$field]) . '</p>';
}

/* =============================================================================
 * 5. Texto
 * ========================================================================== */

/**
 * Longitud en caracteres (no bytes): "Programación" cuenta 12.
 */
function str_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : (int) preg_match_all('/./us', $value);
}

function str_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function str_upper(string $value): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

/**
 * Primeros caracteres de un texto (respetando caracteres multibyte).
 */
function str_take(string $value, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length, 'UTF-8');
    }
    preg_match('/^.{0,' . $length . '}/us', $value, $match);

    return $match[0] ?? '';
}

/**
 * Texto vacío → NULL (para guardar en la base de datos los campos opcionales).
 */
function nullable(string $value): ?string
{
    return $value === '' ? null : $value;
}

/**
 * Recorta un texto largo añadiendo "…".
 */
function truncate(string $value, int $length = 80): string
{
    return str_length($value) > $length ? rtrim(str_take($value, $length - 1)) . '…' : $value;
}

/**
 * Enumeración en español: ['a', 'b', 'c'] → "a, b y c".
 */
function human_list(array $items): string
{
    $items = array_values(array_filter($items, static fn ($item): bool => $item !== '' && $item !== null));
    $last  = array_pop($items);

    return $items ? implode(', ', $items) . ' y ' . $last : (string) $last;
}

/**
 * Primer nombre: "Fran García" → "Fran".
 */
function first_name(string $fullName): string
{
    $parts = preg_split('/\s+/u', trim($fullName)) ?: [];

    return $parts[0] ?? $fullName;
}

/**
 * Iniciales para el avatar: "Fran García" → "FG".
 */
function initials(string $fullName): string
{
    $parts = array_values(array_filter(preg_split('/\s+/u', trim($fullName)) ?: []));
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= str_take($part, 1);
    }

    return str_upper($letters !== '' ? $letters : '?');
}

/**
 * Cantidad con su palabra en singular o plural: plural(1, 'tarea', 'tareas') → "1 tarea".
 */
function plural(int $count, string $singular, string $plural): string
{
    return $count . ' ' . ($count === 1 ? $singular : $plural);
}

/**
 * Color hexadecimal seguro para usar en atributos style="".
 * Si el valor no es #RRGGBB se usa el color por defecto (evita inyectar CSS).
 */
function safe_color(?string $color, string $default = '#94A3B8'): string
{
    return is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color) === 1 ? $color : $default;
}

/**
 * Número con formato español: 7.5 → "7,50".
 */
function format_number(float $number, int $decimals = 2): string
{
    $formatted = number_format($number, $decimals, ',', '.');

    // 10,00 → 10 | 7,50 → 7,5
    return $decimals > 0 ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
}

/* =============================================================================
 * 6. Fechas
 * ========================================================================== */

const DAY_NAMES = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
const MONTH_NAMES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                     'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

function today(): string
{
    return date('Y-m-d');
}

/**
 * Convierte una fecha de la base de datos (Y-m-d) a formato español (d/m/Y).
 */
function format_date(?string $date, string $format = 'd/m/Y'): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $timestamp = strtotime($date);

    return $timestamp === false ? '' : date($format, $timestamp);
}

/**
 * Hora sin segundos: "09:30:00" → "09:30".
 */
function format_time(?string $time): string
{
    return $time ? substr($time, 0, 5) : '';
}

/**
 * Fecha larga en español: "jueves, 8 de octubre".
 */
function format_long_date(?string $date = null, bool $withYear = false): string
{
    $timestamp = $date ? strtotime($date) : time();
    if ($timestamp === false) {
        return '';
    }

    $text = DAY_NAMES[(int) date('N', $timestamp)] . ', ' . date('j', $timestamp)
          . ' de ' . MONTH_NAMES[(int) date('n', $timestamp)];

    return $withYear ? $text . ' de ' . date('Y', $timestamp) : $text;
}

/**
 * Días que faltan hasta una fecha (negativo si ya pasó).
 */
function days_until(string $date): int
{
    $today  = new DateTimeImmutable('today');
    $target = new DateTimeImmutable($date);

    return (int) $today->diff($target)->format('%r%a');
}

/**
 * Texto relativo: "Hoy", "Mañana", "En 3 días", "Hace 2 días"…
 */
function relative_day(string $date): string
{
    $days = days_until($date);

    return match (true) {
        $days === 0  => 'Hoy',
        $days === 1  => 'Mañana',
        $days === -1 => 'Ayer',
        $days > 1    => "En {$days} días",
        default      => 'Hace ' . abs($days) . ' días',
    };
}

/**
 * Lunes y domingo de la semana de una fecha: ['2026-10-05', '2026-10-11'].
 */
function week_bounds(?string $date = null): array
{
    $day    = new DateTimeImmutable($date ?? 'today');
    $monday = $day->modify('-' . ((int) $day->format('N') - 1) . ' days');

    return [$monday->format('Y-m-d'), $monday->modify('+6 days')->format('Y-m-d')];
}

/**
 * Rango de fechas corto: "5 – 11 oct" o "28 sep – 4 oct".
 */
function format_date_range(string $start, string $end): string
{
    $from = new DateTimeImmutable($start);
    $to   = new DateTimeImmutable($end);
    $month = static fn (DateTimeImmutable $d): string => str_take(MONTH_NAMES[(int) $d->format('n')], 3);

    return $from->format('n') === $to->format('n')
        ? $from->format('j') . ' – ' . $to->format('j') . ' ' . $month($to)
        : $from->format('j') . ' ' . $month($from) . ' – ' . $to->format('j') . ' ' . $month($to);
}

/**
 * Datos para una cuenta atrás hasta una fecha y hora (sin hora: las 00:00).
 * 'target' va en ISO 8601 con zona horaria para que JavaScript lo interprete bien.
 */
function countdown_parts(string $date, ?string $time = null): array
{
    $target  = new DateTimeImmutable($date . ' ' . ($time ?: '00:00:00'));
    $seconds = max(0, $target->getTimestamp() - time());

    return [
        'target'   => $target->format(DATE_ATOM),
        'days'     => intdiv($seconds, 86400),
        'hours'    => intdiv($seconds % 86400, 3600),
        'minutes'  => intdiv($seconds % 3600, 60),
        'seconds'  => $seconds % 60,
        'finished' => $seconds === 0,
    ];
}

/**
 * Saludo según la hora del día.
 */
function greeting(): string
{
    $hour = (int) date('G');

    return match (true) {
        $hour >= 6 && $hour < 14  => 'Buenos días',
        $hour >= 14 && $hour < 21 => 'Buenas tardes',
        default                   => 'Buenas noches',
    };
}

/* =============================================================================
 * 7. Vistas y plantillas
 * ========================================================================== */

/**
 * Pinta una vista dentro del layout (cabecera + navegación + pie).
 *
 *   render('tasks/index', ['pageTitle' => 'Tareas', 'tasks' => $tasks]);
 *
 * @param string $view   Ruta dentro de /views sin ".php"
 * @param array  $data   Variables disponibles en la vista
 * @param string $layout 'app' (con navegación) o 'auth' (login/registro)
 */
function render(string $view, array $data = [], string $layout = 'app'): void
{
    $viewFile = BASE_PATH . '/views/' . $view . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException("La vista «{$view}» no existe.");
    }

    $currentUser = $layout === 'app' ? current_user() : null;
    $flashes = get_flashes();
    extract($data, EXTR_SKIP);

    // Con el buffer, si algo falla a mitad no se envía una página a medias
    ob_start();
    try {
        require BASE_PATH . '/includes/header.php';
        require $viewFile;
        require BASE_PATH . '/includes/footer.php';
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    ob_end_flush();
}

/**
 * Pinta un fragmento reutilizable de /views/partials (estado vacío, cuenta atrás…).
 *
 *   partial('empty', ['icon' => 'ri-book-2-line', 'title' => 'Sin asignaturas']);
 */
function partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require BASE_PATH . '/views/partials/' . $name . '.php';
}

/**
 * Ruta a un archivo estático con versión para evitar la caché del navegador
 * al modificarlo: "css/style.css?v=1728370000". Devuelve el valor ya escapado.
 */
function asset(string $path): string
{
    $file = BASE_PATH . '/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : APP_VERSION;

    return e($path . '?v=' . $version);
}

/* =============================================================================
 * 8. Errores
 * ========================================================================== */

/**
 * Muestra una página de error independiente del layout.
 */
function render_error_page(int $code, string $title, string $message, ?Throwable $exception = null): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($code);
    }

    require BASE_PATH . '/views/errors/error.php';
}

/**
 * Corta la petición con un error HTTP (404 si un registro no existe o es de otro usuario).
 */
function abort(int $code = 404, ?string $message = null): void
{
    $titles = [
        403 => 'Acceso denegado',
        404 => 'Página no encontrada',
        405 => 'Método no permitido',
        500 => 'Error del servidor',
    ];

    render_error_page(
        $code,
        $titles[$code] ?? 'Error',
        $message ?? 'Lo que buscas no existe o no tienes permiso para verlo.'
    );
    exit;
}

/**
 * Manejador global de excepciones no capturadas.
 */
function handle_uncaught_exception(Throwable $exception): void
{
    error_log('[StudyTrack] ' . $exception);

    render_error_page(
        500,
        'Algo ha salido mal',
        'Se ha producido un error inesperado. Inténtalo de nuevo en unos minutos.',
        APP_DEBUG ? $exception : null
    );
}
