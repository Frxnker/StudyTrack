<?php
/**
 * Arranque común: todas las páginas lo cargan en su primera línea.
 *
 *   require __DIR__ . '/includes/bootstrap.php';
 *
 * 1. Carga la configuración y la conexión a la base de datos.
 * 2. Carga las funciones auxiliares y el sistema de autenticación.
 * 3. Configura errores, zona horaria y sesión segura.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/app.php';

// Errores: siempre se registran en el log; solo se muestran en modo depuración
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

date_default_timezone_set(APP_TIMEZONE);
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

set_exception_handler('handle_uncaught_exception');

start_secure_session();
