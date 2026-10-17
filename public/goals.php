<?php
/**
 * Objetivos de estudio: CRUD con barra de progreso y botones +/−.
 *
 *   GET  goals.php                       → objetivos activos y completados
 *   GET  goals.php?action=new | action=edit&id=N
 *   POST goals.php  action=create|update|step|delete   (step: direction = 1 | -1)
 *
 * Al llegar a la meta el objetivo pasa a "completado" automáticamente.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_once BASE_PATH . '/models/goal.php';

require_login();

$userId = current_user_id();

function goal_form_data(): array
{
    return [
        'titulo'       => input('titulo'),
        'descripcion'  => input('descripcion'),
        'fecha_limite' => input('fecha_limite'),
        'valor_actual' => input('valor_actual', '0'),
        'valor_meta'   => input('valor_meta'),
        'unidad'       => input('unidad'),
        'estado'       => input('estado', 'pendiente'),
    ];
}

function goal_validate(array $data): array
{
    $errors = validate(
        $data,
        [
            'titulo'       => 'required|max:150',
            'descripcion'  => 'max:1000',
            'fecha_limite' => 'date',
            'valor_actual' => 'required|integer|between:0,' . GOAL_MAX_VALUE,
            'valor_meta'   => 'required|integer|between:1,' . GOAL_MAX_VALUE,
            'unidad'       => 'max:30',
            'estado'       => 'required|in:' . implode(',', array_keys(GOAL_STATUSES)),
        ],
        [
            'titulo'       => 'título',
            'descripcion'  => 'descripción',
            'fecha_limite' => 'fecha límite',
            'valor_actual' => 'progreso actual',
            'valor_meta'   => 'meta',
            'unidad'       => 'unidad',
            'estado'       => 'estado',
        ]
    );

    if (!isset($errors['valor_actual']) && !isset($errors['valor_meta'])
        && (int) $data['valor_actual'] > (int) $data['valor_meta']) {
        $errors['valor_actual'] = 'El progreso actual no puede superar la meta.';
    }

    return $errors;
}

function goal_render_form(array $goal, array $errors = [], bool $isEdit = false): void
{
    if ($errors) {
        http_response_code(422);
    }

    render('goals/form', [
        'pageTitle'   => $isEdit ? 'Editar objetivo' : 'Nuevo objetivo',
        'goal'        => $goal,
        'errors'      => $errors,
        'isEdit'      => $isEdit,
        'pageScripts' => ['js/goals.js'],
    ]);
}

// --- Acciones POST (patrón POST → redirect → GET) ---------------------------
if (is_post()) {
    verify_csrf();

    $action = input('action');
    $id     = request_id();

    if ($action === 'create' || $action === 'update') {
        $isEdit = $action === 'update';
        if ($isEdit && goal_find($userId, $id) === null) {
            abort(404);
        }

        $data   = goal_form_data();
        $errors = goal_validate($data);

        if (!$errors) {
            if ($isEdit) {
                goal_update($userId, $id, $data);
            } else {
                $id = goal_create($userId, $data);
            }

            $saved = goal_find($userId, $id);
            flash('success', $saved !== null && $saved['estado'] === 'completado'
                ? '¡Objetivo «' . $data['titulo'] . '» cumplido! 🎉'
                : 'Objetivo «' . $data['titulo'] . '» ' . ($isEdit ? 'actualizado.' : 'creado.'));
            redirect('goals.php#objetivo-' . $id);
        }

        goal_render_form(['id' => $id] + $data, $errors, $isEdit);
        exit;
    }

    $goal = goal_find($userId, $id);
    if ($goal === null) {
        abort(404);
    }

    if ($action === 'step') {
        $direction = input('direction') === '-1' ? -1 : 1;
        $updated   = goal_step_progress($userId, $goal, $direction);

        if ($updated['estado'] === 'completado' && $goal['estado'] !== 'completado') {
            flash('success', '¡Objetivo «' . $goal['titulo'] . '» cumplido! 🎉');
        }
        redirect('goals.php#objetivo-' . $id);
    }

    if ($action === 'delete') {
        goal_delete($userId, $id);
        flash('success', 'Objetivo «' . $goal['titulo'] . '» eliminado.');
        redirect('goals.php');
    }

    abort(405);
}

// --- Vistas GET --------------------------------------------------------------
$action = query('action');

if ($action === 'new') {
    goal_render_form([
        'id'           => 0,
        'titulo'       => '',
        'descripcion'  => '',
        'fecha_limite' => '',
        'valor_actual' => '0',
        'valor_meta'   => '',
        'unidad'       => '',
        'estado'       => 'pendiente',
    ]);
    exit;
}

if ($action === 'edit') {
    $goal = goal_find($userId, request_id());
    if ($goal === null) {
        abort(404);
    }
    goal_render_form($goal, [], true);
    exit;
}

$goals     = goal_list($userId);
$active    = array_values(array_filter($goals, static fn (array $g): bool => $g['estado'] !== 'completado'));
$completed = array_values(array_filter($goals, static fn (array $g): bool => $g['estado'] === 'completado'));

render('goals/index', [
    'pageTitle' => 'Objetivos',
    'active'    => $active,
    'completed' => $completed,
    'summary'   => goal_summary($userId),
    'today'     => today(),
    'fab'       => ['href' => 'goals.php?action=new', 'label' => 'Nuevo objetivo'],
]);
