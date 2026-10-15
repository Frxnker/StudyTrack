<?php
/**
 * Notas: CRUD de calificaciones con medias por asignatura y media general.
 *
 *   GET  grades.php[?asignatura=N]                  → resumen + listado
 *   GET  grades.php?action=new[&asignatura=N&tipo=examen&fecha=AAAA-MM-DD]
 *   GET  grades.php?action=edit&id=N
 *   POST grades.php  action=create|update|delete
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/models/grade.php';
require_once BASE_PATH . '/models/subject.php';

require_login();

$userId = current_user_id();

function grade_subject_param(string $value): int
{
    return ctype_digit($value) ? (int) $value : 0;
}

function grade_list_url(int $subjectId = 0): string
{
    return 'grades.php' . ($subjectId > 0 ? '?asignatura=' . $subjectId : '');
}

function grade_form_data(): array
{
    return [
        'asignatura_id' => input('asignatura_id'),
        'tipo'          => input('tipo', 'examen'),
        // Se admite la coma decimal española: "7,5" → "7.5"
        'nota'          => str_replace(',', '.', input('nota')),
        'fecha'         => input('fecha'),
        'comentarios'   => input('comentarios'),
    ];
}

function grade_validate(int $userId, array $data): array
{
    $errors = validate(
        $data,
        [
            'asignatura_id' => 'required|integer',
            'tipo'          => 'required|in:' . implode(',', array_keys(GRADE_TYPES)),
            'nota'          => 'required|numeric|between:0,10',
            'fecha'         => 'required|date',
            'comentarios'   => 'max:1000',
        ],
        [
            'asignatura_id' => 'asignatura',
            'tipo'          => 'tipo',
            'nota'          => 'nota',
            'fecha'         => 'fecha',
            'comentarios'   => 'comentarios',
        ],
        ['asignatura_id.required' => 'Elige la asignatura de la nota.']
    );

    // Formato decimal sencillo (descarta "1e1", "+5"…) y como mucho dos decimales
    if (!isset($errors['nota']) && preg_match('/^\d{1,2}(\.\d{1,2})?$/', $data['nota']) !== 1) {
        $errors['nota'] = 'Escribe la nota con dos decimales como máximo (por ejemplo, 7,25).';
    }

    if (!isset($errors['asignatura_id']) && !subject_belongs_to($userId, (int) $data['asignatura_id'])) {
        $errors['asignatura_id'] = 'Elige una de tus asignaturas.';
    }

    return $errors;
}

function grade_render_form(int $userId, array $grade, int $subjectFilter, array $errors = [], bool $isEdit = false): void
{
    if ($errors) {
        http_response_code(422);
    }

    render('grades/form', [
        'pageTitle'     => $isEdit ? 'Editar nota' : 'Nueva nota',
        'grade'         => $grade,
        'subjects'      => subject_options($userId),
        'errors'        => $errors,
        'isEdit'        => $isEdit,
        'subjectFilter' => $subjectFilter,
    ]);
}

// --- Acciones POST (patrón POST → redirect → GET) ---------------------------
if (is_post()) {
    verify_csrf();

    $action       = input('action');
    $id           = request_id();
    $returnFilter = grade_subject_param(input('return'));

    if ($action === 'create' || $action === 'update') {
        $isEdit = $action === 'update';
        if ($isEdit && grade_find($userId, $id) === null) {
            abort(404);
        }

        $data   = grade_form_data();
        $errors = grade_validate($userId, $data);

        if (!$errors) {
            $mark = format_grade($data['nota']);
            if ($isEdit) {
                grade_update($userId, $id, $data);
                flash('success', "Nota actualizada: {$mark}.");
            } else {
                grade_create($userId, $data);
                flash('success', grade_passed($data['nota'])
                    ? "Nota registrada: {$mark}. ¡Enhorabuena!"
                    : "Nota registrada: {$mark}. ¡A por la siguiente!");
            }
            redirect(grade_list_url($returnFilter));
        }

        // Se vuelve a mostrar lo que escribió el usuario (con su coma decimal)
        $data['nota'] = input('nota');
        grade_render_form($userId, ['id' => $id] + $data, $returnFilter, $errors, $isEdit);
        exit;
    }

    if ($action === 'delete') {
        $grade = grade_find($userId, $id);
        if ($grade === null) {
            abort(404);
        }
        grade_delete($userId, $id);
        flash('success', 'Nota de ' . format_grade($grade['nota']) . ' eliminada.');
        redirect(grade_list_url($returnFilter));
    }

    abort(405);
}

// --- Vistas GET --------------------------------------------------------------
$action    = query('action');
$subjectId = grade_subject_param(query('asignatura'));

if ($subjectId > 0 && !subject_belongs_to($userId, $subjectId)) {
    $subjectId = 0;
}

if ($action === 'new') {
    // Valores iniciales opcionales (p. ej. desde "Añadir nota" de un examen realizado)
    $type = query('tipo');
    $date = query('fecha');
    grade_render_form($userId, [
        'id'            => 0,
        'asignatura_id' => $subjectId > 0 ? (string) $subjectId : '',
        'tipo'          => isset(GRADE_TYPES[$type]) ? $type : 'examen',
        'nota'          => '',
        'fecha'         => is_valid_date($date) ? $date : today(),
        'comentarios'   => '',
    ], $subjectId);
    exit;
}

if ($action === 'edit') {
    $grade = grade_find($userId, request_id());
    if ($grade === null) {
        abort(404);
    }
    // 7.50 → "7,5" en el campo
    $grade['nota'] = format_grade($grade['nota']);
    grade_render_form($userId, $grade, $subjectId, [], true);
    exit;
}

$averages = grade_subject_averages($userId);
$graded   = array_filter($averages, static fn (array $s): bool => (int) $s['num_notas'] > 0);
$passed   = array_filter($graded, static fn (array $s): bool => grade_passed($s['media']));

render('grades/index', [
    'pageTitle'      => 'Notas',
    'grades'         => grade_list($userId, $subjectId),
    'averages'       => $averages,
    'overallAverage' => grade_overall_average($userId),
    'summary'        => grade_summary($userId),
    'gradedCount'    => count($graded),
    'passedCount'    => count($passed),
    'subjectId'      => $subjectId,
    'fab'            => ['href' => 'grades.php?action=new' . ($subjectId > 0 ? '&asignatura=' . $subjectId : ''), 'label' => 'Nueva nota'],
]);
