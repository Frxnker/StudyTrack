<?php
/**
 * Calendario mensual con los exámenes y las fechas límite de las tareas.
 *
 *   GET calendar.php?mes=2026-10   → mes indicado (sin parámetro: el mes actual)
 *
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
$monthParam = query('mes');
if (preg_match('/^(\d{4})-(\d{2})$/', $monthParam, $m) === 1
    && (int) $m[1] >= 2000 && (int) $m[1] <= 2100 && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
    $firstDay = new DateTimeImmutable($monthParam . '-01');
} else {
    $firstDay = new DateTimeImmutable(date('Y-m-01'));
}

$lastDay   = $firstDay->modify('last day of this month');
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
            'inMonth' => $day->format('m') === $firstDay->format('m'),
            'events'  => $events[$date] ?? [],
        ];
        $day = $day->modify('+1 day');
    }
    $weeks[] = $week;
}

// Agenda del mes: solo los días del mes con algún evento, en orden
$agenda = array_filter(
    $events,
    static fn (string $date): bool => substr($date, 0, 7) === $firstDay->format('Y-m'),
    ARRAY_FILTER_USE_KEY
);
ksort($agenda);

$monthEvents = array_merge([], ...array_values($agenda));
$examCount   = count(array_filter($monthEvents, static fn (array $e): bool => $e['type'] === 'exam'));
$taskCount   = count($monthEvents) - $examCount;

render('calendar/index', [
    'pageTitle'  => 'Calendario',
    'monthTitle' => ucfirst(MONTH_NAMES[(int) $firstDay->format('n')]) . ' ' . $firstDay->format('Y'),
    'prevMonth'  => $firstDay->modify('-1 month')->format('Y-m'),
    'nextMonth'  => $firstDay->modify('+1 month')->format('Y-m'),
    'isCurrent'  => $firstDay->format('Y-m') === date('Y-m'),
    'weeks'      => $weeks,
    'agenda'     => $agenda,
    'examCount'  => $examCount,
    'taskCount'  => $taskCount,
    'today'      => $today,
    'fab'        => ['href' => 'tasks.php?action=new', 'label' => 'Nueva tarea'],
]);
