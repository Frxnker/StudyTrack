<?php
/**
 * Configuración general de StudyTrack.
 *
 * Valores globales de la aplicación. Las credenciales de la base de datos
 * están en config/database.php.
 *
 * Variables de entorno (opcionales):
 *   APP_DEBUG=true    Muestra los detalles de los errores (solo en desarrollo)
 *   TRUST_PROXY=true  La app está detrás de un proxy inverso que termina el HTTPS
 *                     (Nginx, Traefik…) y se puede confiar en X-Forwarded-Proto
 *   APP_KEY=…         Clave para cifrar las URLs de los calendarios conectados
 *                     (también se puede poner en config/app.local.php)
 */

declare(strict_types=1);

/**
 * Lee una variable de entorno como booleano ("true", "1", "on", "yes" → true).
 */
function env_bool(string $name, bool $default = false): bool
{
    $value = getenv($name);
    if ($value === false || trim($value) === '') {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

// Nombre y versión que se muestran en la interfaz
define('APP_NAME', 'StudyTrack');
define('APP_VERSION', '1.0.0');

// Zona horaria usada en fechas, cuentas atrás y saludos ("Buenos días")
define('APP_TIMEZONE', 'Europe/Madrid');

// Modo depuración: muestra los detalles de los errores. Desactivado salvo que APP_DEBUG=true.
define('APP_DEBUG', env_bool('APP_DEBUG'));

// ¿Confiar en la cabecera X-Forwarded-Proto para saber si la petición llegó por HTTPS?
define('TRUST_PROXY', env_bool('TRUST_PROXY'));

// Clave para cifrar datos sensibles: las URLs secretas de los calendarios conectados.
// Se lee de config/app.local.php (ignorado por Git, ver app.local.example.php) o de la
// variable de entorno APP_KEY. Genera una con:
//   php -r "echo 'base64:' . base64_encode(random_bytes(32)), PHP_EOL;"
$appLocal = is_file(__DIR__ . '/app.local.php') ? (array) require __DIR__ . '/app.local.php' : [];
define('APP_KEY', (string) ($appLocal['APP_KEY'] ?? (getenv('APP_KEY') ?: '')));
unset($appLocal);

// Sesión
define('SESSION_NAME', 'studytrack_session');
define('SESSION_TIMEOUT', 2 * 60 * 60); // segundos de inactividad antes de cerrar la sesión (2 h)

// Nota mínima para considerar aprobada una evaluación (escala 0–10)
define('PASS_MARK', 5.0);

// Descansos de la jornada: [inicio, fin, texto]. El horario los dibuja como una banda
// entre las clases (no son franjas de la base de datos).
define('SCHOOL_BREAKS', [['11:00', '11:30', 'Recreo']]);

// Aula de los exámenes importados de un calendario cuando el evento no indica lugar
define('EXAM_DEFAULT_ROOM', 'Aula 21 Informática');
