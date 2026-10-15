<?php
/**
 * Calendario mensual con los exámenes y las fechas límite de las tareas.
 *
 *   GET calendar.php?mes=2026-10                → mes indicado (sin parámetro: el mes actual)
 *   GET calendar.php?mes=2026-10&dia=2026-10-15 → además, lista de lo que hay ese día
 *
 * En móvil no se muestra la cuadrícula sino la agenda del mes en lista.
 * La rejilla empieza en lunes y termina en domingo, así que incluye algunos días
 * del mes anterior y del siguiente.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/models/task.php';
require_once BASE_PATH . '/models/exam.php';

require_login();

$userId = current_user_id();
$today  = today();

// --- Mes a mostrar (si el parámetro no es válido, el mes actual) ------------
// Con ?dia=AAAA-MM-DD y sin ?mes se muestra el mes de ese día
$dayParam   = query('dia');
$monthParam = query('mes');
if ($monthParam === '' && is_valid_date($dayParam)) {
    $monthParam = substr($dayParam, 0, 7);
}
if (preg_match('/^(\d{4})-(\d{2})$/', $monthParam, $m) === 1
    && (int) $m[1] >= 2000 && (int) $m[1] <= 2100 && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
    $firstDay = new DateTimeImmutable($monthParam . '-01');
} else {
    $firstDay = new DateTimeImmutable(date('Y-m-01'));
}

$monthKey  = $firstDay->format('Y-m');
$lastDay   = $firstDay->modify('last day of this month');

// Día seleccionado (su lista se ve en el panel de escritorio): el pedido si es de
// este mes; si no, hoy cuando se está viendo el mes actual; si no, ninguno
if (is_valid_date($dayParam) && substr($dayParam, 0, 7) === $monthKey) {
    $selected = $dayParam;
} else {
    $selected = substr($today, 0, 7) === $monthKey ? $today : null;
}
$gridStart = $firstDay->modify('-' . ((int) $firstDay->format('N') - 1) . ' days');
$gridEnd   = $lastDay->modify('+' . (7 - (int) $lastDay->format('N')) . ' days');

// --- Eventos agrupados por día ------------------------------------------------
$events = [];
foreach (exam_between($userId, $gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d')) as $exam) {
    $events[$exam['fecha']][] = [
        'type'    => 'exam',
        'title'   => $exam['nombre'],
        'subject' => $exam['asignatura'],
        'color'   => $exam['color'],
        'time'    => format_time($exam['hora']),
        'place'   => $exam['aula'],
        'done'    => $exam['estado'] !== 'pendiente',
        'status'  => EXAM_STATUSES[$exam['estado']],
        'url'     => 'exams.php?action=edit&id=' . (int) $exam['id'],
    ];
}
foreach (task_between($userId, $gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d')) as $task) {
    $events[$task['fecha_limite']][] = [
        'type'    => 'task',
        'title'   => $task['titulo'],
        'subject' => $task['asignatura'],
        'color'   => $task['color'],
        'time'    => '',
        'place'   => null,
        'done'    => $task['estado'] === 'completada',
        'status'  => TASK_STATUSES[$task['estado']],
        'url'     => 'tasks.php?action=edit&id=' . (int) $task['id'],
    ];
}

// --- Semanas de la rejilla ---------------------------------------------------
$weeks = [];
$day   = $gridStart;
while ($day <= $gridEnd) {
    $week = [];
    for ($i = 0; $i < 7; $i++) {
        $date   = $day->format('Y-m-d');
        $week[] = [
            'date'    => $date,
            'day'     => (int) $day->format('j'),
            'inMonth' => $day->format('Y-m') === $monthKey,
            'events'  => $events[$date] ?? [],
            'label'   => ucfirst(format_long_date($date)),
        ];
        $day = $day->modify('+1 day');
    }
    $weeks[] = $week;
}

// Agenda del mes: solo los días del mes con algún evento, en orden
$agenda = array_filter(
    $events,
    static fn (string $date): bool => substr($date, 0, 7) === $monthKey,
    ARRAY_FILTER_USE_KEY
);
ksort($agenda);

$monthEvents = array_merge([], ...array_values($agenda));
$examCount   = count(array_filter($monthEvents, static fn (array $e): bool => $e['type'] === 'exam'));
$taskCount   = count($monthEvents) - $examCount;

render('calendar/index', [
    'pageTitle'     => 'Calendario',
    'monthTitle'    => ucfirst(MONTH_NAMES[(int) $firstDay->format('n')]) . ' ' . $firstDay->format('Y'),
    'monthKey'      => $monthKey,
    'prevMonth'     => $firstDay->modify('-1 month')->format('Y-m'),
    'nextMonth'     => $firstDay->modify('+1 month')->format('Y-m'),
    'isCurrent'     => $monthKey === date('Y-m'),
    'weeks'         => $weeks,
    'agenda'        => $agenda,
    'selected'      => $selected,
    'selectedLabel' => $selected !== null ? ucfirst(format_long_date($selected)) : '',
    'examCount'     => $examCount,
    'taskCount'     => $taskCount,
    'today'         => $today,
    'fab'           => ['href' => 'tasks.php?action=new' . ($selected !== null ? '&fecha=' . $selected : ''), 'label' => 'Nueva tarea'],
    'pageScripts'   => ['js/calendar.js'],
]);
