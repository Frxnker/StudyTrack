<?php
/**
 * Exámenes: CRUD con próximos (con cuenta atrás) y pasados.
 *
 *   GET  exams.php[?asignatura=N]               → listado (opcionalmente de una asignatura)
 *   GET  exams.php?action=new | action=edit&id=N → formularios
 *   POST exams.php  action=create|update|status|delete
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/models/exam.php';
require_once BASE_PATH . '/models/subject.php';

require_login();

$userId = current_user_id();

/**
 * Asignatura por la que se filtra (0 = todas). Se lee de GET o del campo "return".
 */
function exam_subject_param(string $value): int
{
    return ctype_digit($value) ? (int) $value : 0;
}

function exam_list_url(int $subjectId = 0): string
{
    return 'exams.php' . ($subjectId > 0 ? '?asignatura=' . $subjectId : '');
}

function exam_form_data(): array
{
    return [
        'asignatura_id' => input('asignatura_id'),
        'nombre'        => input('nombre'),
        'fecha'         => input('fecha'),
        'hora'          => input('hora'),
        'aula'          => input('aula'),
        'descripcion'   => input('descripcion'),
        'estado'        => input('estado', 'pendiente'),
    ];
}

function exam_validate(int $userId, array $data): array
{
    $errors = validate(
        $data,
        [
            'asignatura_id' => 'required|integer',
            'nombre'        => 'required|max:150',
            'fecha'         => 'required|date',
            'hora'          => 'time',
            'aula'          => 'max:50',
            'descripcion'   => 'max:2000',
            'estado'        => 'required|in:' . implode(',', array_keys(EXAM_STATUSES)),
        ],
        [
            'asignatura_id' => 'asignatura',
            'nombre'        => 'nombre',
            'fecha'         => 'fecha',
            'hora'          => 'hora',
            'aula'          => 'aula',
            'descripcion'   => 'descripción',
            'estado'        => 'estado',
        ],
        ['asignatura_id.required' => 'Elige la asignatura del examen.']
    );

    if (!isset($errors['asignatura_id']) && !subject_belongs_to($userId, (int) $data['asignatura_id'])) {
        $errors['asignatura_id'] = 'Elige una de tus asignaturas.';
    }

    return $errors;
}

function exam_render_form(int $userId, array $exam, int $subjectFilter, array $errors = [], bool $isEdit = false): void
{
    if ($errors) {
        http_response_code(422);
    }

    render('exams/form', [
        'pageTitle'     => $isEdit ? 'Editar examen' : 'Nuevo examen',
        'exam'          => $exam,
        'subjects'      => subject_options($userId),
        'errors'        => $errors,
        'isEdit'        => $isEdit,
        'subjectFilter' => $subjectFilter,
    ]);
}

// --- Acciones POST (patrón POST → redirect → GET) ---------------------------
if (is_post()) {
    verify_csrf();

    $action = input('action');
    $id     = request_id();
    $back   = exam_list_url(exam_subject_param(input('return')));

    if ($action === 'create' || $action === 'update') {
        $isEdit = $action === 'update';
        if ($isEdit && exam_find($userId, $id) === null) {
            abort(404);
        }

        $data   = exam_form_data();
        $errors = exam_validate($userId, $data);

        if (!$errors) {
            if ($isEdit) {
                exam_update($userId, $id, $data);
                flash('success', 'Examen «' . $data['nombre'] . '» actualizado.');
            } else {
                exam_create($userId, $data);
                flash('success', 'Examen «' . $data['nombre'] . '» añadido. ¡Mucho ánimo!');
            }
            redirect($back);
        }

        exam_render_form($userId, ['id' => $id] + $data, exam_subject_param(input('return')), $errors, $isEdit);
        exit;
    }

    $exam = exam_find($userId, $id);
    if ($exam === null) {
        abort(404);
    }

    if ($action === 'status') {
        $status = input('estado');
        if (!isset(EXAM_STATUSES[$status])) {
            abort(405);
        }
        exam_set_status($userId, $id, $status);
        flash('success', 'Examen «' . $exam['nombre'] . '» marcado como ' . str_lower(EXAM_STATUSES[$status]) . '.');
        redirect($back);
    }

    if ($action === 'delete') {
        exam_delete($userId, $id);
        flash('success', 'Examen «' . $exam['nombre'] . '» eliminado.');
        redirect($back);
    }

    abort(405);
}

// --- Vistas GET --------------------------------------------------------------
$action    = query('action');
$subjectId = exam_subject_param(query('asignatura'));

// Un filtro con una asignatura que no es del usuario se ignora
if ($subjectId > 0 && !subject_belongs_to($userId, $subjectId)) {
    $subjectId = 0;
}

if ($action === 'new') {
    exam_render_form($userId, [
        'id'            => 0,
        'asignatura_id' => $subjectId > 0 ? (string) $subjectId : '',
        'nombre'        => '',
        'fecha'         => '',
        'hora'          => '',
        'aula'          => '',
        'descripcion'   => '',
        'estado'        => 'pendiente',
    ], $subjectId);
    exit;
}

if ($action === 'edit') {
    $exam = exam_find($userId, request_id());
    if ($exam === null) {
        abort(404);
    }
    $exam['hora'] = format_time($exam['hora']);
    exam_render_form($userId, $exam, $subjectId, [], true);
    exit;
}

$upcoming = exam_upcoming($userId, $subjectId);
foreach ($upcoming as &$exam) {
    $exam['countdown'] = countdown_parts($exam['fecha'], $exam['hora']);
}
unset($exam);

render('exams/index', [
    'pageTitle' => 'Exámenes',
    'upcoming'  => $upcoming,
    'past'      => exam_past($userId, $subjectId),
    'subjects'  => subject_options($userId),
    'subjectId' => $subjectId,
    'today'     => today(),
    'fab'       => ['href' => 'exams.php?action=new' . ($subjectId > 0 ? '&asignatura=' . $subjectId : ''), 'label' => 'Nuevo examen'],
]);
