<?php
/**
 * Sincronización automática de los calendarios externos (Moodle, Classroom…).
 * Sincroniza los calendarios ACTIVOS de todos los usuarios: es lo mismo que
 * pulsar «Sincronizar ahora» en cada uno.
 *
 * Solo se puede ejecutar por línea de comandos. Prográmalo cada hora (ver README):
 *
 *   php cron/sync_calendars.php
 *   C:\xampp\php\php.exe C:\xampp\htdocs\StudyTrack\cron\sync_calendars.php   (XAMPP)
 *
 * Termina con código 0 si todo ha ido bien y 1 si algún calendario ha fallado
 * (el error queda guardado en el calendario y se ve en calendars.php).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Arranque sin sesión ni HTML (bootstrap.php está pensado para las páginas web)
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/app.php';

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
date_default_timezone_set(APP_TIMEZONE);
mb_internal_encoding('UTF-8');

require BASE_PATH . '/config/database.php';
require BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/models/subject.php';
require_once BASE_PATH . '/models/calendar_feed.php';

$log = static function (string $message): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
};

if (!has_app_key()) {
    $log('ERROR: falta APP_KEY (config/app.local.php o variable de entorno): no se pueden leer las URLs.');
    exit(1);
}

// Una sola ejecución a la vez (si la anterior aún no ha terminado, esta se salta)
$lock = fopen(sys_get_temp_dir() . '/studytrack_sync_calendars.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    $log('Otra sincronización sigue en marcha: se omite esta.');
    exit(0);
}

$calendars = calendar_feed_active_all();
$log('Sincronizando ' . plural(count($calendars), 'calendario activo', 'calendarios activos') . '…');

$failed = 0;
foreach ($calendars as $calendar) {
    $label = '«' . $calendar['nombre'] . '» (usuario ' . $calendar['usuario_id'] . ')';
    try {
        $summary = calendar_feed_sync((int) $calendar['usuario_id'], $calendar);
        $log('  OK    ' . $label . ': ' . calendar_feed_summary_text($summary));
    } catch (RuntimeException $e) {
        $failed++;
        $log('  ERROR ' . $label . ': ' . $e->getMessage());
    }
}

$log('Terminado: ' . (count($calendars) - $failed) . ' correctos, ' . $failed . ' con error.');
flock($lock, LOCK_UN);

exit($failed > 0 ? 1 : 0);
