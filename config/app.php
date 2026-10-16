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

// Sesión
define('SESSION_NAME', 'studytrack_session');
define('SESSION_TIMEOUT', 2 * 60 * 60); // segundos de inactividad antes de cerrar la sesión (2 h)

// Nota mínima para considerar aprobada una evaluación (escala 0–10)
define('PASS_MARK', 5.0);

// Descansos de la jornada: [inicio, fin, texto]. El horario los dibuja como una banda
// entre las clases (no son franjas de la base de datos).
define('SCHOOL_BREAKS', [['11:00', '11:30', 'Recreo']]);
