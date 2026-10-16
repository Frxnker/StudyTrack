<?php
/**
 * Tareas: CRUD completo con filtros y búsqueda.
 *
 *   GET  tasks.php?vista=hoy&estado=…&asignatura=…&prioridad=…&q=…  → listado filtrado
 *   GET  tasks.php?action=new | action=edit&id=N                      → formularios
 *   POST tasks.php  action=create|update|toggle|delete
 *
 * Los filtros activos viajan en el campo oculto "return" de cada formulario
 * para volver al mismo listado después de guardar, completar o borrar.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/models/task.php';
require_once BASE_PATH . '/models/subject.php';

require_login();

$userId = current_user_id();

/**
 * Filtros válidos a partir de un array de parámetros (GET o el campo "return").
 * Lo que no es válido se descarta: así nunca se construyen URLs con datos arbitrarios.
 */
function task_read_filters(array $source): array
{
    $get = static fn (string $key): string => is_string($source[$key] ?? null) ? trim($source[$key]) : '';

    $subject = $get('asignatura');
    $filters = [
        'vista'      => isset(TASK_VIEWS[$get('vista')]) && $get('vista') !== 'todas' ? $get('vista') : '',
        'estado'     => isset(TASK_STATUSES[$get('estado')]) || $get('estado') === 'abiertas' ? $get('estado') : '',
        'asignatura' => $subject === 'ninguna' ? 'ninguna' : (ctype_digit($subject) && (int) $subject > 0 ? (int) $subject : ''),
        'prioridad'  => isset(TASK_PRIORITIES[$get('prioridad')]) ? $get('prioridad') : '',
        'q'          => str_take($get('q'), 100),
    ];

    return array_filter($filters, static fn ($value): bool => $value !== '');
}

/**
 * URL del listado con los filtros indicados.
 */
function task_list_url(array $filters = []): string
{
    return 'tasks.php' . ($filters ? '?' . http_build_query($filters) : '');
}

/**
 * Filtros guardados en el campo oculto "return" de un formulario POST.
 */
function task_return_filters(): array
{
    parse_str(input('return'), $params);

    return task_read_filters($params);
}

function task_form_data(): array
{
    return [
        'titulo'        => input('titulo'),
        'descripcion'   => input('descripcion'),
        'asignatura_id' => input('asignatura_id'),
        'fecha_limite'  => input('fecha_limite'),
        'prioridad'     => input('prioridad', 'media'),
        'estado'        => input('estado', 'pendiente'),
    ];
}

function task_validate(int $userId, array $data): array
{
    $errors = validate(
        $data,
        [
            'titulo'        => 'required|max:150',
            'descripcion'   => 'max:2000',
            'asignatura_id' => 'integer',
            'fecha_limite'  => 'date',
            'prioridad'     => 'required|in:' . implode(',', array_keys(TASK_PRIORITIES)),
            'estado'        => 'required|in:' . implode(',', array_keys(TASK_STATUSES)),
        ],
        [
            'titulo'        => 'título',
            'descripcion'   => 'descripción',
            'asignatura_id' => 'asignatura',
            'fecha_limite'  => 'fecha límite',
            'prioridad'     => 'prioridad',
            'estado'        => 'estado',
        ]
    );

    // La clave foránea de tareas no incluye el usuario: lo comprobamos aquí
    if (!isset($errors['asignatura_id']) && $data['asignatura_id'] !== ''
        && !subject_belongs_to($userId, (int) $data['asignatura_id'])) {
        $errors['asignatura_id'] = 'Elige una de tus asignaturas.';
    }

    return $errors;
}

function task_render_form(int $userId, array $task, array $filters, array $errors = [], bool $isEdit = false): void
{
    if ($errors) {
        http_response_code(422);
    }

    render('tasks/form', [
        'pageTitle' => $isEdit ? 'Editar tarea' : 'Nueva tarea',
        'task'      => $task,
        'subjects'  => subject_options($userId),
        'errors'    => $errors,
        'isEdit'    => $isEdit,
        'filters'   => $filters,
    ]);
}

// --- Acciones POST (patrón POST → redirect → GET) ---------------------------
if (is_post()) {
    verify_csrf();

    $action  = input('action');
    $id      = request_id();
    $filters = task_return_filters();

    if ($action === 'create' || $action === 'update') {
        $isEdit = $action === 'update';
        if ($isEdit && task_find($userId, $id) === null) {
            abort(404);
        }

        $data   = task_form_data();
        $errors = task_validate($userId, $data);

        if (!$errors) {
            if ($isEdit) {
                task_update($userId, $id, $data);
                flash('success', 'Tarea «' . $data['titulo'] . '» actualizada.');
            } else {
                task_create($userId, $data);
                flash('success', 'Tarea «' . $data['titulo'] . '» creada.');
            }
            redirect(task_list_url($filters));
        }

        task_render_form($userId, ['id' => $id] + $data, $filters, $errors, $isEdit);
        exit;
    }

    $task = task_find($userId, $id);
    if ($task === null) {
        abort(404);
    }

    if ($action === 'toggle') {
        $newStatus = task_toggle($userId, $task);
        flash('success', $newStatus === 'completada'
            ? 'Tarea «' . $task['titulo'] . '» completada. ¡Bien hecho!'
            : 'Tarea «' . $task['titulo'] . '» marcada como pendiente.');
        redirect(task_list_url($filters));
    }

    if ($action === 'delete') {
        task_delete($userId, $id);
        flash('success', 'Tarea «' . $task['titulo'] . '» eliminada.');
        redirect(task_list_url($filters));
    }

    abort(405);
}

// --- Vistas GET --------------------------------------------------------------
$action  = query('action');
$filters = task_read_filters($_GET);

if ($action === 'new') {
    // Valores iniciales opcionales: ?asignatura=3 (desde una asignatura) y ?fecha=AAAA-MM-DD (desde el calendario)
    $subjectId = is_int($filters['asignatura'] ?? null) ? (string) $filters['asignatura'] : '';
    task_render_form($userId, [
        'id'            => 0,
        'titulo'        => '',
        'descripcion'   => '',
        'asignatura_id' => $subjectId,
        'fecha_limite'  => is_valid_date(query('fecha')) ? query('fecha') : '',
        'prioridad'     => 'media',
        'estado'        => 'pendiente',
    ], $filters);
    exit;
}

if ($action === 'edit') {
    $task = task_find($userId, request_id());
    if ($task === null) {
        abort(404);
    }
    task_render_form($userId, $task, $filters, [], true);
    exit;
}

$view        = $filters['vista'] ?? 'todas';
$listFilters = array_diff_key($filters, ['vista' => true]);

// Ojo: no se puede pasar como 'view' (render() ya usa $view para el nombre de la plantilla)
render('tasks/index', [
    'pageTitle'   => 'Tareas',
    'tasks'       => task_list($userId, $filters),
    'counts'      => task_counts($userId),
    'viewCounts'  => task_view_counts($userId, $listFilters),
    'subjects'    => subject_options($userId),
    'filters'     => $filters,
    'currentView' => $view,
    'today'       => today(),
    'fab'         => ['href' => task_list_url(['action' => 'new'] + $filters), 'label' => 'Nueva tarea'],
]);
