<?php
/**
 * Configuración general de StudyTrack.
 *
 * Valores globales de la aplicación. Las credenciales de la base de datos
 * están en config/database.php.
 */

declare(strict_types=1);

// Nombre y versión que se muestran en la interfaz
define('APP_NAME', 'StudyTrack');
define('APP_VERSION', '1.0.0');

// Zona horaria usada en fechas, cuentas atrás y saludos ("Buenos días")
define('APP_TIMEZONE', 'Europe/Madrid');

// Modo depuración: muestra los detalles de los errores. Pon false en producción.
define('APP_DEBUG', true);

// Sesión
define('SESSION_NAME', 'studytrack_session');
define('SESSION_TIMEOUT', 2 * 60 * 60); // segundos de inactividad antes de cerrar la sesión (2 h)

// Nota mínima para considerar aprobada una evaluación (escala 0–10)
define('PASS_MARK', 5.0);
