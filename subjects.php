<?php
/**
 * Asignaturas: CRUD completo.
 *
 *   GET  subjects.php                    → listado en tarjetas
 *   GET  subjects.php?action=show&id=N   → detalle: tareas, exámenes, notas y horario
 *   GET  subjects.php?action=new         → formulario de alta
 *   GET  subjects.php?action=edit&id=N   → formulario de edición
 *   POST subjects.php  action=create|update|delete
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/models/subject.php';
require_once BASE_PATH . '/models/grade.php';
require_once BASE_PATH . '/models/task.php';
require_once BASE_PATH . '/models/exam.php';
require_once BASE_PATH . '/models/schedule.php';

require_login();

$userId = current_user_id();

/**
 * Datos del formulario recogidos de POST.
 */
function subject_form_data(): array
{
    return [
        'nombre'      => input('nombre'),
        'descripcion' => input('descripcion'),
        'profesor'    => input('profesor'),
        'curso'       => input('curso'),
        'color'       => input('color', SUBJECT_COLORS[0]),
    ];
}

/**
 * Valida el formulario. Devuelve [campo => mensaje].
 */
function subject_validate(int $userId, array $data, int $exceptId = 0): array
{
    $errors = validate(
        $data,
        [
            'nombre'      => 'required|max:100',
            'descripcion' => 'max:1000',
            'profesor'    => 'max:100',
            'curso'       => 'max:50',
            'color'       => 'required|color',
        ],
        ['nombre' => 'nombre', 'descripcion' => 'descripción', 'profesor' => 'profesor', 'curso' => 'curso', 'color' => 'color']
    );

    if (!isset($errors['nombre']) && subject_name_exists($userId, $data['nombre'], $exceptId)) {
        $errors['nombre'] = 'Ya tienes una asignatura con ese nombre.';
    }

    return $errors;
}

/**
 * Texto del diálogo de borrado: qué se perderá al eliminar la asignatura.
 * "Se borrarán también 1 examen, 3 notas y 3 clases del horario. Sus tareas se conservarán…"
 */
function subject_delete_warning(int $userId, int $id): string
{
    $related = subject_related_counts($userId, $id);
    $lost = human_list([
        $related['examenes'] ? plural($related['examenes'], 'examen', 'exámenes') : '',
        $related['notas'] ? plural($related['notas'], 'nota', 'notas') : '',
        $related['clases'] ? plural($related['clases'], 'clase del horario', 'clases del horario') : '',
    ]);

    return trim(($lost !== '' ? 'Se borrarán también ' . $lost . '. ' : '')
        . ($related['tareas'] ? 'Sus tareas se conservarán sin asignatura. ' : '')
        . 'Esta acción no se puede deshacer.');
}

/**
 * Pinta el formulario de alta o edición.
 */
function subject_render_form(array $subject, array $errors = [], bool $isEdit = false): void
{
    if ($errors) {
        http_response_code(422);
    }

    render('subjects/form', [
        'pageTitle' => $isEdit ? 'Editar asignatura' : 'Nueva asignatura',
        'subject'   => $subject,
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
        if ($isEdit && subject_find($userId, $id) === null) {
            abort(404);
        }

        $data   = subject_form_data();
        $errors = subject_validate($userId, $data, $id);

        if (!$errors) {
            try {
                if ($isEdit) {
                    subject_update($userId, $id, $data);
                    flash('success', 'Asignatura «' . $data['nombre'] . '» actualizada.');
                } else {
                    subject_create($userId, $data);
                    flash('success', 'Asignatura «' . $data['nombre'] . '» creada.');
                }
                redirect('subjects.php');
            } catch (PDOException $e) {
                // 23000: nombre duplicado (dos envíos simultáneos)
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $errors['nombre'] = 'Ya tienes una asignatura con ese nombre.';
            }
        }

        subject_render_form(['id' => $id] + $data, $errors, $isEdit);
        exit;
    }

    if ($action === 'delete') {
        $subject = subject_find($userId, $id);
        if ($subject === null) {
            abort(404);
        }

        subject_delete($userId, $id);
        flash('success', 'Asignatura «' . $subject['nombre'] . '» eliminada.');
        redirect('subjects.php');
    }

    abort(405);
}

// --- Vistas GET --------------------------------------------------------------
$action = query('action');

if ($action === 'new') {
    subject_render_form([
        'id'          => 0,
        'nombre'      => '',
        'descripcion' => '',
        'profesor'    => '',
        'curso'       => current_user()['curso'] ?? '',
        'color'       => SUBJECT_COLORS[count(subject_options($userId)) % count(SUBJECT_COLORS)],
    ]);
    exit;
}

if ($action === 'edit') {
    $subject = subject_find($userId, request_id());
    if ($subject === null) {
        abort(404);
    }
    subject_render_form($subject, [], true);
    exit;
}

if ($action === 'show') {
    $subject = subject_find($userId, request_id());
    if ($subject === null) {
        abort(404);
    }
    $id = (int) $subject['id'];

    $tasks    = task_list($userId, ['asignatura' => $id]);
    $grades   = grade_list($userId, $id);
    $classes  = array_values(array_filter(
        schedule_all($userId),
        static fn (array $class): bool => (int) $class['asignatura_id'] === $id
    ));
    $upcoming = exam_upcoming($userId, $id);

    render('subjects/show', [
        'pageTitle'     => $subject['nombre'],
        'subject'       => $subject,
        'pendingTasks'  => array_values(array_filter($tasks, static fn (array $t): bool => $t['estado'] !== 'completada')),
        'doneCount'     => count(array_filter($tasks, static fn (array $t): bool => $t['estado'] === 'completada')),
        'upcoming'      => $upcoming,
        'countdown'     => $upcoming ? countdown_parts($upcoming[0]['fecha'], $upcoming[0]['hora']) : null,
        'past'          => exam_past($userId, $id),
        'grades'        => $grades,
        'average'       => $grades ? array_sum(array_column($grades, 'nota')) / count($grades) : null,
        'classes'       => $classes,
        'weeklyMinutes' => array_sum(array_map(
            static fn (array $c): int => time_to_minutes($c['hora_fin']) - time_to_minutes($c['hora_inicio']),
            $classes
        )),
        'deleteWarning' => subject_delete_warning($userId, $id),
        'today'         => today(),
        'fab'           => ['href' => 'tasks.php?action=new&asignatura=' . $id, 'label' => 'Nueva tarea de esta asignatura'],
    ]);
    exit;
}

$subjects = subject_all_with_stats($userId);

// Avisos para el diálogo de borrado: qué se perderá con cada asignatura
foreach ($subjects as &$subject) {
    $subject['aviso_borrado'] = subject_delete_warning($userId, (int) $subject['id']);
}
unset($subject);

render('subjects/index', [
    'pageTitle' => 'Asignaturas',
    'subjects'  => $subjects,
    'fab'       => ['href' => 'subjects.php?action=new', 'label' => 'Nueva asignatura'],
]);
