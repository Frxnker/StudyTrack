<?php
/**
 * Estadísticas: gráficos de notas y tareas.
 *
 * PHP prepara los datos y los deja en la página como JSON
 * (<script type="application/json" id="stats-data">); js/statistics.js dibuja
 * los gráficos en SVG. Cada gráfico tiene además su tabla de datos
 * (accesible y visible sin JavaScript).
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/models/task.php';
require_once BASE_PATH . '/models/grade.php';
require_once BASE_PATH . '/models/goal.php';

require_login();

$userId = current_user_id();

// --- Media por asignatura (solo las que tienen notas) ------------------------
$subjects = array_values(array_map(
    static fn (array $s): array => [
        'name'    => $s['nombre'],
        'color'   => safe_color($s['color']),
        'average' => round((float) $s['media'], 2),
        'count'   => (int) $s['num_notas'],
    ],
    array_filter(grade_subject_averages($userId), static fn (array $s): bool => (int) $s['num_notas'] > 0)
));

// --- Evolución de las notas + media acumulada --------------------------------
$timeline = [];
$sum      = 0.0;
foreach (grade_timeline($userId) as $i => $grade) {
    $sum += (float) $grade['nota'];
    $timeline[] = [
        'date'    => $grade['fecha'],
        'grade'   => round((float) $grade['nota'], 2),
        'average' => round($sum / ($i + 1), 2),
        'subject' => $grade['asignatura'],
        'type'    => GRADE_TYPES[$grade['tipo']] ?? $grade['tipo'],
        'color'   => safe_color($grade['color']),
    ];
}

// --- Tareas completadas por semana (últimas 8) -------------------------------
$weeks = [];
foreach (task_completed_by_week($userId, 8) as $monday => $count) {
    $sunday  = (new DateTimeImmutable($monday))->modify('+6 days')->format('Y-m-d');
    $weeks[] = [
        'start'   => $monday,
        'label'   => format_date($monday, 'j') . ' ' . str_take(MONTH_NAMES[(int) format_date($monday, 'n')], 3),
        'range'   => format_date_range($monday, $sunday),
        'count'   => $count,
        'current' => $monday === week_bounds()[0],
    ];
}

// --- Distribución de tareas por estado ---------------------------------------
$statusCounts = task_status_counts($userId);
$statuses     = [];
foreach (TASK_STATUSES as $key => $label) {
    $statuses[] = ['key' => $key, 'label' => $label, 'count' => $statusCounts[$key]];
}

$taskCounts = task_counts($userId);

render('statistics/index', [
    'pageTitle'      => 'Estadísticas',
    'overallAverage' => grade_overall_average($userId),
    'gradeSummary'   => grade_summary($userId),
    'taskCounts'     => $taskCounts,
    'goals'          => goal_summary($userId),
    'chartData'      => [
        'subjects' => $subjects,
        'timeline' => $timeline,
        'weeks'    => $weeks,
        'statuses' => $statuses,
        'passMark' => PASS_MARK,
    ],
    'pageScripts'    => ['js/statistics.js'],
]);
