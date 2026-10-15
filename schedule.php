<?php
/**
 * Horario semanal de clases: CRUD.
 *
 *   GET  schedule.php                    → rejilla semanal (escritorio) / lista por día (móvil)
 *   GET  schedule.php?action=new[&dia=N] → formulario de alta
 *   GET  schedule.php?action=edit&id=N   → formulario de edición
 *   POST schedule.php  action=create|update|delete
 *
 * Reglas: hora de inicio anterior a la de fin y sin solaparse con otra clase del mismo día.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/models/schedule.php';
require_once BASE_PATH . '/models/subject.php';

require_login();

$userId = current_user_id();

function schedule_form_data(): array
{
    return [
        'asignatura_id' => input('asignatura_id'),
        'dia_semana'    => input('dia_semana'),
        'hora_inicio'   => input('hora_inicio'),
        'hora_fin'      => input('hora_fin'),
        'aula'          => input('aula'),
    ];
}

function schedule_validate(int $userId, array &$data, int $exceptId = 0): array
{
    $errors = validate(
        $data,
        [
            'asignatura_id' => 'required|integer',
            'dia_semana'    => 'required|in:1,2,3,4,5,6,7',
            'hora_inicio'   => 'required|time',
            'hora_fin'      => 'required|time',
            'aula'          => 'max:50',
        ],
        [
            'asignatura_id' => 'asignatura',
            'dia_semana'    => 'día',
            'hora_inicio'   => 'hora de inicio',
            'hora_fin'      => 'hora de fin',
            'aula'          => 'aula',
        ],
        ['asignatura_id.required' => 'Elige la asignatura de la clase.']
    );

    if (!isset($errors['asignatura_id']) && !subject_belongs_to($userId, (int) $data['asignatura_id'])) {
        $errors['asignatura_id'] = 'Elige una de tus asignaturas.';
    }

    if (isset($errors['hora_inicio']) || isset($errors['hora_fin'])) {
        return $errors;
    }

    // "09:30" y "09:30:00" se comparan igual: nos quedamos con HH:MM
    $data['hora_inicio'] = format_time($data['hora_inicio']);
    $data['hora_fin']    = format_time($data['hora_fin']);

    if ($data['hora_inicio'] >= $data['hora_fin']) {
        $errors['hora_fin'] = 'La clase debe terminar después de la hora de inicio.';
    } elseif (!isset($errors['dia_semana'])) {
        $overlap = schedule_overlap($userId, (int) $data['dia_semana'], $data['hora_inicio'], $data['hora_fin'], $exceptId);
        if ($overlap !== null) {
            $errors['hora_inicio'] = sprintf(
                'Se solapa con %s (%s – %s) del mismo día.',
                $overlap['asignatura'],
                format_time($overlap['hora_inicio']),
                format_time($overlap['hora_fin'])
            );
        }
    }

    return $errors;
}

function schedule_render_form(int $userId, array $class, array $errors = [], bool $isEdit = false): void
{
    if ($errors) {
        http_response_code(422);
    }

    render('schedule/form', [
        'pageTitle' => $isEdit ? 'Editar clase' : 'Nueva clase',
        'class'     => $class,
        'subjects'  => subject_options($userId),
        'errors'    => $errors,
        'isEdit'    => $isEdit,
    ]);
}

// --- Acciones POST (patrón POST → redirect → GET) ---------------------------
if (is_post()) {
    verify_csrf();

    $action = input('action');
    $id     = request_id();

    if ($action === 'create' || $action === 'update') {
        $isEdit = $action === 'update';
        if ($isEdit && schedule_find($userId, $id) === null) {
            abort(404);
        }

        $data   = schedule_form_data();
        $errors = schedule_validate($userId, $data, $isEdit ? $id : 0);

        if (!$errors) {
            $when = DAY_NAMES[(int) $data['dia_semana']] . ' de ' . $data['hora_inicio'] . ' a ' . $data['hora_fin'];
            if ($isEdit) {
                schedule_update($userId, $id, $data);
                flash('success', "Clase actualizada: {$when}.");
            } else {
                schedule_create($userId, $data);
                flash('success', "Clase añadida: {$when}.");
            }
            redirect('schedule.php');
        }

        schedule_render_form($userId, ['id' => $id] + $data, $errors, $isEdit);
        exit;
    }

    if ($action === 'delete') {
        $class = schedule_find($userId, $id);
        if ($class === null) {
            abort(404);
        }
        schedule_delete($userId, $id);
        flash('success', 'Clase del ' . DAY_NAMES[(int) $class['dia_semana']] . ' a las '
            . format_time($class['hora_inicio']) . ' eliminada.');
        redirect('schedule.php');
    }

    abort(405);
}

// --- Vistas GET --------------------------------------------------------------
$action = query('action');

if ($action === 'new') {
    $day = query('dia');
    schedule_render_form($userId, [
        'id'            => 0,
        'asignatura_id' => '',
        'dia_semana'    => isset(DAY_NAMES[(int) $day]) ? $day : (string) min((int) date('N'), 5),
        'hora_inicio'   => '',
        'hora_fin'      => '',
        'aula'          => '',
    ]);
    exit;
}

if ($action === 'edit') {
    $class = schedule_find($userId, request_id());
    if ($class === null) {
        abort(404);
    }
    $class['hora_inicio'] = format_time($class['hora_inicio']);
    $class['hora_fin']    = format_time($class['hora_fin']);
    schedule_render_form($userId, $class, [], true);
    exit;
}

$days    = schedule_by_day($userId);
$classes = array_merge(...array_values($days));

// Rango de horas de la rejilla: como mínimo de 8:00 a 14:00, ampliado si hace falta
$firstHour = 8;
$lastHour  = 14;
foreach ($classes as $class) {
    $firstHour = min($firstHour, intdiv(time_to_minutes($class['hora_inicio']), 60));
    $lastHour  = max($lastHour, (int) ceil(time_to_minutes($class['hora_fin']) / 60));
}

// De lunes a viernes; sábado y domingo solo si tienen clases
$visibleDays = array_filter(
    array_keys(DAY_NAMES),
    static fn (int $day): bool => $day <= 5 || $days[$day] !== []
);

// Horas lectivas a la semana
$weeklyMinutes = array_sum(array_map(
    static fn (array $c): int => time_to_minutes($c['hora_fin']) - time_to_minutes($c['hora_inicio']),
    $classes
));

render('schedule/index', [
    'pageTitle'     => 'Horario',
    'days'          => $days,
    'visibleDays'   => $visibleDays,
    'firstHour'     => $firstHour,
    'lastHour'      => $lastHour,
    'classCount'    => count($classes),
    'weeklyMinutes' => $weeklyMinutes,
    'hasSubjects'   => subject_options($userId) !== [],
    'today'         => (int) date('N'),
    'fab'           => ['href' => 'schedule.php?action=new', 'label' => 'Nueva clase'],
]);
