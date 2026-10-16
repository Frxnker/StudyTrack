<?php
/**
 * Dashboard: resumen del estudiante.
 *
 * Reúne en una sola pantalla las tareas pendientes, el próximo examen,
 * las tareas para hoy, el progreso académico, las últimas notas,
 * las asignaturas y el resumen de la semana.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/models/task.php';
require_once BASE_PATH . '/models/exam.php';
require_once BASE_PATH . '/models/grade.php';
require_once BASE_PATH . '/models/subject.php';
require_once BASE_PATH . '/models/goal.php';
require_once BASE_PATH . '/models/schedule.php';

require_login();

$userId = current_user_id();
$today  = today();
[$weekStart, $weekEnd] = week_bounds();

// --- Datos ---------------------------------------------------------------
$taskCounts     = task_counts($userId);
$todayTasks     = task_pending_until($userId, $today, 6);
$nextExam       = exam_next($userId);
$latestGrades   = grade_latest($userId, 5);
$subjects       = subject_all_with_stats($userId);
$overallAverage = grade_overall_average($userId);
$goals          = goal_summary($userId);

$week = task_week_progress($userId, $weekStart, $weekEnd);
$week['por_dia']  = task_completed_by_day($userId, $weekStart, $weekEnd);
$week['examenes'] = exam_count_between($userId, $weekStart, $weekEnd);
$week['notas']    = grade_count_between($userId, $weekStart, $weekEnd);
$week['rango']    = format_date_range($weekStart, $weekEnd);

// --- Hoy en clase: lo que queda de hoy, con la clase actual y la siguiente ----
$scheduleDays = schedule_by_day($userId);
$weekday      = (int) date('N');
$nowTime      = date('H:i');
$classBlocks  = schedule_merge_blocks($scheduleDays[$weekday]);
$classes      = [
    'bloques'    => $classBlocks,
    'ahora'      => schedule_now_next($classBlocks, $nowTime),
    // Bloques y recreos que aún no han terminado
    'pendientes' => array_values(array_filter(
        schedule_day_items($classBlocks),
        static fn (array $item): bool => time_to_minutes($item['hora_fin']) > time_to_minutes($nowTime)
    )),
    'proximo'    => schedule_next_day($scheduleDays, $weekday),
    'vacio'      => array_merge(...array_values($scheduleDays)) === [],
];

// Asignaturas aprobadas (de las que ya tienen alguna nota)
$gradedSubjects = array_filter($subjects, static fn (array $s): bool => $s['media'] !== null);
$passedSubjects = array_filter($gradedSubjects, static fn (array $s): bool => grade_passed($s['media']));

// --- Frase de resumen bajo el saludo --------------------------------------
if (!$subjects) {
    $summary = 'Empieza creando tus asignaturas para organizar tareas, exámenes y notas.';
} else {
    $dueToday = $taskCounts['hoy'];
    $overdue  = $taskCounts['atrasadas'];

    $summary = $dueToday > 0
        ? 'Tienes ' . plural($dueToday, 'tarea', 'tareas') . ' para hoy'
        : 'No tienes tareas para hoy';

    if ($overdue > 0) {
        $summary .= ($dueToday > 0 ? ' y ' : ', pero hay ') . plural($overdue, 'atrasada', 'atrasadas') . '.';
    } else {
        $summary .= $dueToday > 0 ? '.' : '. ¡Buen trabajo!';
    }

    if ($nextExam !== null) {
        $days = days_until($nextExam['fecha']);
        $when = match ($days) {
            0       => 'es hoy',
            1       => 'es mañana',
            default => "es en {$days} días",
        };
        $summary .= " Tu próximo examen {$when}.";
    }
}

render('dashboard', [
    'pageTitle'      => 'Inicio',
    'summary'        => $summary,
    'today'          => $today,
    'taskCounts'     => $taskCounts,
    'todayTasks'     => $todayTasks,
    'nextExam'       => $nextExam,
    'countdown'      => $nextExam ? countdown_parts($nextExam['fecha'], $nextExam['hora']) : null,
    'latestGrades'   => $latestGrades,
    'subjects'       => $subjects,
    'overallAverage' => $overallAverage,
    'goals'          => $goals,
    'week'           => $week,
    'classes'        => $classes,
    'gradedCount'    => count($gradedSubjects),
    'passedCount'    => count($passedSubjects),
    'fab'            => ['href' => 'tasks.php?action=new', 'label' => 'Nueva tarea'],
    'pageScripts'    => ['js/dashboard.js'],
]);
