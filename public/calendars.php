<?php
/**
 * Calendarios externos: conectar los calendarios de Moodle y Google Classroom por su
 * URL iCal e importar sus entregas y exámenes. Solo fechas y títulos: nunca notas.
 *
 *   GET  calendars.php                    → calendarios, pendientes de asignar y correspondencias
 *   GET  calendars.php?action=new         → formulario de alta
 *   GET  calendars.php?action=preview     → vista previa de lo que se importará
 *   GET  calendars.php?action=edit&id=N   → formulario de edición
 *   POST calendars.php  action=preview|create|update|sync|delete|assign|unmap
 *
 * El alta tiene dos pasos: "preview" descarga y lee el calendario (si la URL no
 * devuelve un iCal válido, se avisa en el formulario) y guarda los datos en la
 * sesión con la URL cifrada; "create" lo guarda e importa los eventos.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_once BASE_PATH . '/models/calendar_feed.php';
require_once BASE_PATH . '/models/subject.php';

require_login();

$userId = current_user_id();

function calendar_form_data(): array
{
    return [
        'nombre'           => input('nombre'),
        'origen'           => input('origen', 'moodle'),
        'url'              => input('url'),
        'asignatura_id'    => input('asignatura_id'),
        'tipo_por_defecto' => input('tipo_por_defecto', 'tarea'),
        'activo'           => input('activo') === '1',
    ];
}

/**
 * Valida el formulario. Si trae URL, la normaliza (webcal:// → https://), la descarga
 * y comprueba que es un iCal. Devuelve [errores, calendario leído o null].
 * $exceptId: calendario que se edita (para no compararlo consigo mismo).
 */
function calendar_validate(int $userId, array &$data, bool $urlRequired, int $exceptId = 0): array
{
    $errors = validate(
        $data,
        [
            'nombre'           => 'required|max:100',
            'origen'           => 'required|in:' . implode(',', array_keys(CALENDAR_ORIGINS)),
            'url'              => ($urlRequired ? 'required|' : '') . 'max:2000',
            'asignatura_id'    => 'integer',
            'tipo_por_defecto' => 'required|in:' . implode(',', array_keys(CALENDAR_TYPES)),
        ],
        [
            'nombre'           => 'nombre',
            'origen'           => 'origen',
            'url'              => 'URL',
            'asignatura_id'    => 'asignatura',
            'tipo_por_defecto' => 'tipo',
        ],
        ['url.required' => 'Pega la URL iCal del calendario.']
    );

    if (!isset($errors['asignatura_id']) && $data['asignatura_id'] !== ''
        && !subject_belongs_to($userId, (int) $data['asignatura_id'])) {
        $errors['asignatura_id'] = 'Elige una de tus asignaturas.';
    }

    $parsed = null;
    if (!isset($errors['url']) && $data['url'] !== '') {
        try {
            $data['url'] = ical_normalize_url($data['url']);

            // El mismo calendario no se conecta dos veces
            foreach (calendar_feed_all($userId) as $existing) {
                try {
                    $same = (int) $existing['id'] !== $exceptId && calendar_feed_url($existing) === $data['url'];
                } catch (RuntimeException) {
                    $same = false;
                }
                if ($same) {
                    throw new RuntimeException('Ya tienes conectado este calendario («' . $existing['nombre'] . '»).');
                }
            }

            $parsed = ical_parse(ical_download($data['url']));
        } catch (RuntimeException $e) {
            $errors['url'] = $e->getMessage();
        }
    }

    return [$errors, $parsed];
}

function calendar_render_form(int $userId, array $calendar, array $errors = [], bool $isEdit = false): void
{
    if ($errors) {
        http_response_code(422);
    }

    render('calendars/form', [
        'pageTitle'   => $isEdit ? 'Editar calendario' : 'Conectar calendario',
        'calendar'    => $calendar,
        'errors'      => $errors,
        'isEdit'      => $isEdit,
        'subjects'    => subject_options($userId),
        'pageScripts' => ['js/calendars.js'],
    ]);
}

/**
 * Datos para el formulario de edición (la URL nunca se rellena: se muestra enmascarada).
 */
function calendar_edit_values(array $calendar): array
{
    return [
        'id'               => (int) $calendar['id'],
        'nombre'           => $calendar['nombre'],
        'origen'           => $calendar['origen'],
        'url'              => '',
        'url_enmascarada'  => calendar_feed_masked($calendar),
        'asignatura_id'    => (string) ($calendar['asignatura_id'] ?? ''),
        'tipo_por_defecto' => $calendar['tipo_por_defecto'],
        'activo'           => (bool) $calendar['activo'],
        'num_tareas'       => (int) $calendar['num_tareas'],
        'num_examenes'     => (int) $calendar['num_examenes'],
    ];
}

// --- Acciones POST (patrón POST → redirect → GET) ---------------------------
if (is_post()) {
    verify_csrf();

    $action = input('action');
    $id     = request_id();

    // Paso 1 del alta: comprobar la URL y preparar la vista previa
    if ($action === 'preview') {
        if (!has_app_key()) {
            flash('error', 'Falta configurar APP_KEY: sin ella no se pueden guardar las URLs cifradas.');
            redirect('calendars.php');
        }
        $data = calendar_form_data();
        [$errors, $parsed] = calendar_validate($userId, $data, true);

        if ($errors) {
            calendar_render_form($userId, ['id' => 0] + $data, $errors);
            exit;
        }

        $_SESSION['calendar_draft'] = [
            'data'    => ['url' => encrypt_secret($data['url'])] + $data,
            'preview' => calendar_feed_preview($userId, $data, $parsed),
        ];
        redirect('calendars.php?action=preview');
    }

    // Paso 2 del alta: guardar e importar
    if ($action === 'create') {
        $draft = $_SESSION['calendar_draft'] ?? null;
        unset($_SESSION['calendar_draft']);
        if (!is_array($draft)) {
            flash('warning', 'La vista previa ha caducado. Vuelve a comprobar el calendario.');
            redirect('calendars.php?action=new');
        }

        $data = $draft['data'];
        $data['url'] = decrypt_secret($data['url']);
        // Por si la asignatura se ha borrado mientras tanto
        if ($data['asignatura_id'] !== '' && !subject_belongs_to($userId, (int) $data['asignatura_id'])) {
            $data['asignatura_id'] = '';
        }

        $newId = calendar_feed_create($userId, $data);
        try {
            $summary = calendar_feed_sync($userId, calendar_feed_find($userId, $newId));
            flash('success', 'Calendario «' . $data['nombre'] . '» conectado: ' . calendar_feed_summary_text($summary) . '.');
        } catch (RuntimeException $e) {
            flash('warning', 'Calendario «' . $data['nombre'] . '» conectado, pero no se pudo importar: ' . $e->getMessage());
        }
        redirect('calendars.php#calendario-' . $newId);
    }

    if ($action === 'assign') {
        $text      = input('texto');
        $subjectId = request_id('asignatura_id');
        $subject   = subject_find($userId, $subjectId);
        if ($text === '' || $subject === null) {
            flash('error', 'Elige una de tus asignaturas.');
            redirect('calendars.php#pendientes');
        }
        $remember = input('recordar') === '1';
        $count    = calendar_feed_assign($userId, $text, $subjectId, $remember);
        flash('success', ucfirst(plural($count, 'tarea asignada', 'tareas asignadas')) . ' a «' . $subject['nombre'] . '».'
            . ($remember ? ' Las próximas de «' . $text . '» irán a la misma asignatura.' : ''));
        redirect('calendars.php#pendientes');
    }

    if ($action === 'unmap') {
        if (!calendar_feed_mapping_delete($userId, $id)) {
            abort(404);
        }
        flash('success', 'Correspondencia eliminada. Las tareas ya asignadas no cambian.');
        redirect('calendars.php#correspondencias');
    }

    // El resto de acciones son sobre un calendario del usuario
    $calendar = calendar_feed_find($userId, $id);
    if ($calendar === null) {
        abort(404);
    }

    if ($action === 'update') {
        $data = calendar_form_data();
        [$errors] = calendar_validate($userId, $data, false, $id);

        if ($errors) {
            calendar_render_form($userId, array_merge(calendar_edit_values($calendar), $data), $errors, true);
            exit;
        }

        calendar_feed_update($userId, $id, $data);
        flash('success', 'Calendario «' . $data['nombre'] . '» guardado.'
            . ($data['url'] !== '' ? ' Pulsa «Sincronizar ahora» para importar desde la nueva URL.' : ''));
        redirect('calendars.php#calendario-' . $id);
    }

    if ($action === 'sync') {
        try {
            $summary = calendar_feed_sync($userId, $calendar);
            flash('success', '«' . $calendar['nombre'] . '» sincronizado: ' . calendar_feed_summary_text($summary) . '.');
        } catch (RuntimeException $e) {
            flash('error', 'No se pudo sincronizar «' . $calendar['nombre'] . '»: ' . $e->getMessage());
        }
        redirect('calendars.php#calendario-' . $id);
    }

    if ($action === 'delete') {
        $withImported = input('importados') === 'borrar';
        $deleted      = calendar_feed_delete($userId, $id, $withImported);
        flash('success', 'Calendario «' . $calendar['nombre'] . '» eliminado' . ($withImported
            ? ' junto con ' . human_list([plural($deleted['tareas'], 'tarea', 'tareas'), plural($deleted['examenes'], 'examen', 'exámenes')]) . ' importados.'
            : '. Sus tareas y exámenes importados se conservan.'));
        redirect('calendars.php');
    }

    abort(405);
}

// --- Vistas GET --------------------------------------------------------------
$action = query('action');

if ($action === 'new') {
    if (!has_app_key()) {
        flash('error', 'Falta configurar APP_KEY: sin ella no se pueden guardar las URLs cifradas.');
        redirect('calendars.php');
    }
    $values = [
        'id'               => 0,
        'nombre'           => '',
        'origen'           => 'moodle',
        'url'              => '',
        'asignatura_id'    => '',
        'tipo_por_defecto' => 'tarea',
        'activo'           => true,
    ];
    // "Volver a editar" desde la vista previa: se recuperan los datos escritos
    $draft = $_SESSION['calendar_draft']['data'] ?? null;
    if (query('volver') === '1' && is_array($draft)) {
        $values = ['id' => 0, 'url' => decrypt_secret($draft['url'])] + $draft;
    }
    calendar_render_form($userId, $values);
    exit;
}

if ($action === 'preview') {
    $draft = $_SESSION['calendar_draft'] ?? null;
    if (!is_array($draft)) {
        redirect('calendars.php?action=new');
    }
    $data = $draft['data'];
    render('calendars/preview', [
        'pageTitle' => 'Vista previa',
        'settings'  => $data,
        'preview'   => $draft['preview'],
        'subject'   => $data['asignatura_id'] !== '' ? subject_find($userId, (int) $data['asignatura_id']) : null,
        'masked'    => calendar_feed_mask_url(decrypt_secret($data['url'])),
    ]);
    exit;
}

if ($action === 'edit') {
    $calendar = calendar_feed_find($userId, request_id());
    if ($calendar === null) {
        abort(404);
    }
    calendar_render_form($userId, calendar_edit_values($calendar), [], true);
    exit;
}

$calendars = calendar_feed_all($userId);
foreach ($calendars as &$calendar) {
    $calendar['url_enmascarada'] = calendar_feed_masked($calendar);
}
unset($calendar);

render('calendars/index', [
    'pageTitle' => 'Calendarios',
    'calendars' => $calendars,
    'pending'   => calendar_feed_pending($userId),
    'mappings'  => calendar_feed_mappings($userId),
    'subjects'  => subject_options($userId),
    'hasKey'    => has_app_key(),
    'fab'       => has_app_key() ? ['href' => 'calendars.php?action=new', 'label' => 'Conectar calendario'] : null,
]);
