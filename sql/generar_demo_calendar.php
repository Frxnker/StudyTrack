<?php
/**
 * Genera sql/demo_calendar.ics: un calendario de ejemplo (como el que exporta Moodle)
 * con eventos de 2º DAW y fechas relativas a HOY, para probar la importación.
 *
 *   php sql/generar_demo_calendar.php          (XAMPP: C:\xampp\php\php.exe sql\generar_demo_calendar.php)
 *
 * Vuelve a ejecutarlo cuando quieras refrescar las fechas. Incluye a propósito:
 * un evento de día completo, uno con TZID=Europe/Madrid, varios en UTC, uno con
 * líneas plegadas y caracteres escapados, exámenes ("Examen", "Prueba"), un curso
 * que no coincide con ninguna asignatura, uno antiguo y uno cancelado (se ignoran).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Europe/Madrid');

/** Fecha relativa a hoy: day(3) → '20261019' */
function day(int $offset): string
{
    return (new DateTimeImmutable('today'))->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Ymd');
}

/** Hora local de Madrid relativa a hoy, pasada a UTC: utc(4, '23:59') → '20261020T215900Z' */
function utc(int $offset, string $time): string
{
    return (new DateTimeImmutable(day($offset) . ' ' . $time, new DateTimeZone('Europe/Madrid')))
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Ymd\THis\Z');
}

/** Pliega una línea a 75 bytes (RFC 5545) sin partir caracteres UTF-8. */
function fold(string $line): string
{
    $out = '';
    while (strlen($line) > 75) {
        $chunk = mb_strcut($line, 0, 75, 'UTF-8');
        $out  .= $chunk . "\r\n ";
        $line  = substr($line, strlen($chunk));
    }

    return $out . $line . "\r\n";
}

/** Escapa un texto iCal: \ ; , y saltos de línea. */
function esc(string $text): string
{
    return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
}

$host   = 'aulavirtual.iescelia.example';
$events = [
    // Curso de Moodle con el nombre completo (y "2º DAW" delante) · UTC
    ['uid' => "1001@{$host}", 'summary' => 'Práctica 3: API REST con PHP y PDO', 'cat' => '2º DAW - Desarrollo web en entorno servidor',
     'start' => 'DTSTART:' . utc(4, '23:59')],
    // Abreviatura y curso académico · TZID Europe/Madrid · examen por el título
    ['uid' => "1002@{$host}", 'summary' => 'Examen UT2: JavaScript asíncrono y fetch', 'cat' => 'DWEC 26/27',
     'start' => 'DTSTART;TZID=Europe/Madrid:' . day(10) . 'T090000', 'end' => 'DTEND;TZID=Europe/Madrid:' . day(10) . 'T110000'],
    // Día completo
    ['uid' => "1003@{$host}", 'summary' => 'Entrega: despliegue de la aplicación con Docker', 'cat' => 'Despliegue de aplicaciones web',
     'start' => 'DTSTART;VALUE=DATE:' . day(6), 'end' => 'DTEND;VALUE=DATE:' . day(7)],
    // Línea plegada y caracteres escapados (\, \; \\ y saltos de línea)
    ['uid' => "1004@{$host}", 'summary' => 'Actividad 5: maquetación responsive, accesible y con Flexbox; Grid opcional (ver C:\\proyectos\\diw)',
     'cat' => 'Diseño de Interfaces Web (2º DAW)', 'start' => 'DTSTART:' . utc(8, '23:59'),
     'desc' => "Requisitos:\n- Contraste AA, navegación con teclado y textos alternativos.\n- Entregar el enlace al repositorio; no se admiten archivos .zip."],
    // Prueba oral de inglés: "ING 2DAW" → Inglés Profesional (ING = IP)
    ['uid' => "1005@{$host}", 'summary' => 'Prueba oral: job interview roleplay', 'cat' => 'ING 2DAW', 'start' => 'DTSTART:' . utc(12, '10:00')],
    // EIE es el nombre antiguo de IPE → Itinerario Personal para la Empleabilidad II
    ['uid' => "1006@{$host}", 'summary' => 'Simulacro de entrevista y CV Europass', 'cat' => 'EIE - Empresa e Iniciativa Emprendedora',
     'start' => 'DTSTART:' . utc(5, '23:59')],
    // Curso que no coincide con ninguna asignatura → "Pendientes de asignar"
    ['uid' => "1007@{$host}", 'summary' => 'Charla de orientación académica y laboral', 'cat' => 'Tutoría 2º curso',
     'start' => 'DTSTART:' . utc(3, '12:00')],
    // "Tests" no es un examen: es una tarea de DWES
    ['uid' => "1008@{$host}", 'summary' => 'Tests unitarios con PHPUnit', 'cat' => '2º DAW - Desarrollo web en entorno servidor',
     'start' => 'DTSTART:' . utc(15, '23:59')],
    // Sin CATEGORIES (como Classroom): se compara el título → Proyecto Web
    ['uid' => "1009@{$host}", 'summary' => 'Proyecto Web: entrega del anteproyecto', 'start' => 'DTSTART:' . utc(20, '23:59')],
    // Sin CATEGORIES y sin pistas en el título → sin asignatura
    ['uid' => "1010@{$host}", 'summary' => 'Reunión del grupo de prácticas', 'start' => 'DTSTART:' . day(2) . 'T170000'],
    // Hace 45 días → se ignora (solo se importan los de los últimos 30)
    ['uid' => "1011@{$host}", 'summary' => 'Práctica 1: entorno de desarrollo', 'cat' => '2º DAW - Desarrollo web en entorno servidor',
     'start' => 'DTSTART:' . utc(-45, '23:59')],
    // Cancelado → se ignora
    ['uid' => "1012@{$host}", 'summary' => 'Examen de recuperación (cancelado)', 'cat' => 'DWEC 26/27',
     'start' => 'DTSTART:' . utc(9, '09:00'), 'status' => 'CANCELLED'],
];

$ics  = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//StudyTrack//Calendario de ejemplo//ES\r\nCALSCALE:GREGORIAN\r\n";
$ics .= "X-WR-CALNAME:Moodle · IES Celia Viñas (ejemplo)\r\nX-WR-TIMEZONE:Europe/Madrid\r\n";
$ics .= "BEGIN:VTIMEZONE\r\nTZID:Europe/Madrid\r\nBEGIN:DAYLIGHT\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\nTZNAME:CEST\r\n"
      . "DTSTART:19700329T020000\r\nRRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\nEND:DAYLIGHT\r\nBEGIN:STANDARD\r\nTZOFFSETFROM:+0200\r\n"
      . "TZOFFSETTO:+0100\r\nTZNAME:CET\r\nDTSTART:19701025T030000\r\nRRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n";

foreach ($events as $event) {
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= fold('UID:' . $event['uid']);
    $ics .= fold('SUMMARY:' . esc($event['summary']));
    if (isset($event['desc'])) {
        $ics .= fold('DESCRIPTION:' . esc($event['desc']));
    }
    if (isset($event['cat'])) {
        $ics .= fold('CATEGORIES:' . esc($event['cat']));
    }
    $ics .= $event['start'] . "\r\n";
    if (isset($event['end'])) {
        $ics .= $event['end'] . "\r\n";
    }
    if (isset($event['status'])) {
        $ics .= 'STATUS:' . $event['status'] . "\r\n";
    }
    $ics .= 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\nEND:VEVENT\r\n";
}
$ics .= "END:VCALENDAR\r\n";

file_put_contents(__DIR__ . '/demo_calendar.ics', $ics);
echo 'sql/demo_calendar.ics generado con ' . count($events) . ' eventos (hoy: ' . date('d/m/Y') . ').' . PHP_EOL;
