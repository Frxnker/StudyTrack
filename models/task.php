<?php
/**
 * Modelo: tareas.
 * Todas las consultas a la tabla `tareas` están en este archivo.
 * Todas filtran por usuario_id: un usuario nunca puede leer tareas ajenas.
 */

declare(strict_types=1);

const TASK_PRIORITIES = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta'];
const TASK_STATUSES   = ['pendiente' => 'Pendiente', 'en_progreso' => 'En progreso', 'completada' => 'Completada'];

// Vistas rápidas del listado (pestañas)
const TASK_VIEWS = ['todas' => 'Todas', 'hoy' => 'Hoy', 'semana' => 'Próximos 7 días', 'atrasadas' => 'Atrasadas'];

/**
 * Listado de tareas con filtros. Todas las claves de $filters son opcionales:
 *   vista      todas | hoy | semana | atrasadas
 *   estado     pendiente | en_progreso | completada | abiertas (sin completar)
 *   asignatura id de asignatura | 'ninguna' (tareas sin asignatura)
 *   prioridad  baja | media | alta
 *   q          texto a buscar en el título y la descripción
 *
 * Orden: primero las que no están completadas, por fecha límite (sin fecha al final)
 * y, a igual fecha, por prioridad.
 */
function task_list(int $userId, array $filters = []): array
{
    [$where, $params] = task_filter_sql($userId, $filters);

    return db_all(
        "SELECT t.id, t.titulo, t.descripcion, t.fecha_limite, t.prioridad, t.estado,
                t.fecha_completada, t.asignatura_id, a.nombre AS asignatura, a.color
           FROM tareas t
           LEFT JOIN asignaturas a ON a.id = t.asignatura_id
          WHERE {$where}
          ORDER BY t.estado = 'completada',
                   t.fecha_limite IS NULL, t.fecha_limite,
                   FIELD(t.prioridad, 'alta', 'media', 'baja'),
                   t.id DESC",
        $params
    );
}

/**
 * Número de tareas de cada vista rápida (respetando el resto de filtros).
 */
function task_view_counts(int $userId, array $filters = []): array
{
    $counts = [];
    foreach (array_keys(TASK_VIEWS) as $view) {
        [$where, $params] = task_filter_sql($userId, ['vista' => $view] + $filters);
        $counts[$view] = (int) db_value("SELECT COUNT(*) FROM tareas t WHERE {$where}", $params);
    }

    return $counts;
}

/**
 * Construye el WHERE de los filtros. Los valores siempre van como parámetros.
 * Devuelve [sql, parámetros].
 */
function task_filter_sql(int $userId, array $filters): array
{
    $where  = ['t.usuario_id = :uid'];
    $params = ['uid' => $userId];
    $today  = today();

    switch ($filters['vista'] ?? 'todas') {
        case 'hoy':
            $where[] = "t.estado <> 'completada' AND t.fecha_limite = :hoy";
            $params['hoy'] = $today;
            break;
        case 'semana':
            $where[] = "t.estado <> 'completada' AND t.fecha_limite BETWEEN :desde AND :hasta";
            $params['desde'] = $today;
            $params['hasta'] = (new DateTimeImmutable($today))->modify('+7 days')->format('Y-m-d');
            break;
        case 'atrasadas':
            $where[] = "t.estado <> 'completada' AND t.fecha_limite < :hoy";
            $params['hoy'] = $today;
            break;
    }

    $status = $filters['estado'] ?? '';
    if ($status === 'abiertas') {
        $where[] = "t.estado <> 'completada'";
    } elseif (isset(TASK_STATUSES[$status])) {
        $where[] = 't.estado = :estado';
        $params['estado'] = $status;
    }

    $subject = $filters['asignatura'] ?? '';
    if ($subject === 'ninguna') {
        $where[] = 't.asignatura_id IS NULL';
    } elseif (is_int($subject) && $subject > 0) {
        $where[] = 't.asignatura_id = :asignatura';
        $params['asignatura'] = $subject;
    }

    $priority = $filters['prioridad'] ?? '';
    if (isset(TASK_PRIORITIES[$priority])) {
        $where[] = 't.prioridad = :prioridad';
        $params['prioridad'] = $priority;
    }

    $search = $filters['q'] ?? '';
    if ($search !== '') {
        // Se escapan los comodines de LIKE para buscar el texto tal cual
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = '(t.titulo LIKE :q1 OR t.descripcion LIKE :q2)';
        $params['q1'] = $like;
        $params['q2'] = $like;
    }

    return [implode(' AND ', $where), $params];
}

/**
 * Tareas con fecha límite entre dos fechas (para el calendario), con el color de su asignatura.
 */
function task_between(int $userId, string $start, string $end): array
{
    return db_all(
        "SELECT t.id, t.titulo, t.fecha_limite, t.prioridad, t.estado, a.nombre AS asignatura, a.color
           FROM tareas t
           LEFT JOIN asignaturas a ON a.id = t.asignatura_id
          WHERE t.usuario_id = :uid
            AND t.fecha_limite BETWEEN :inicio AND :fin
          ORDER BY t.fecha_limite, t.estado = 'completada', FIELD(t.prioridad, 'alta', 'media', 'baja')",
        ['uid' => $userId, 'inicio' => $start, 'fin' => $end]
    );
}

/**
 * Una tarea del usuario o null si no existe (o es de otro usuario).
 */
function task_find(int $userId, int $id): ?array
{
    return db_one(
        'SELECT id, asignatura_id, titulo, descripcion, fecha_limite, prioridad, estado, fecha_completada
           FROM tareas
          WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    );
}

/**
 * Crea una tarea. $data: titulo, descripcion, asignatura_id, fecha_limite, prioridad, estado.
 */
function task_create(int $userId, array $data): int
{
    db_execute(
        "INSERT INTO tareas (usuario_id, asignatura_id, titulo, descripcion, fecha_limite, prioridad, estado, fecha_completada)
         VALUES (:uid, :asignatura_id, :titulo, :descripcion, :fecha_limite, :prioridad, :estado,
                 IF(:estado2 = 'completada', NOW(), NULL))",
        ['uid' => $userId, 'estado2' => $data['estado']] + task_params($data)
    );

    return db_last_id();
}

/**
 * Actualiza una tarea. La fecha de completado se rellena al pasar a "completada"
 * (si ya lo estaba se conserva) y se borra al volver a otro estado.
 */
function task_update(int $userId, int $id, array $data): void
{
    db_execute(
        "UPDATE tareas
            SET asignatura_id = :asignatura_id, titulo = :titulo, descripcion = :descripcion,
                fecha_limite = :fecha_limite, prioridad = :prioridad, estado = :estado,
                fecha_completada = IF(:estado2 = 'completada', COALESCE(fecha_completada, NOW()), NULL)
          WHERE id = :id AND usuario_id = :uid",
        ['id' => $id, 'uid' => $userId, 'estado2' => $data['estado']] + task_params($data)
    );
}

/**
 * Marca una tarea como completada o, si ya lo estaba, la devuelve a pendiente.
 * Devuelve el nuevo estado.
 */
function task_toggle(int $userId, array $task): string
{
    $newStatus = $task['estado'] === 'completada' ? 'pendiente' : 'completada';

    db_execute(
        "UPDATE tareas
            SET estado = :estado,
                fecha_completada = IF(:estado2 = 'completada', NOW(), NULL)
          WHERE id = :id AND usuario_id = :uid",
        ['estado' => $newStatus, 'estado2' => $newStatus, 'id' => (int) $task['id'], 'uid' => $userId]
    );

    return $newStatus;
}

function task_delete(int $userId, int $id): bool
{
    return db_execute(
        'DELETE FROM tareas WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    ) > 0;
}

/**
 * Parámetros comunes de INSERT/UPDATE (los campos opcionales vacíos van como NULL).
 */
function task_params(array $data): array
{
    return [
        'asignatura_id' => $data['asignatura_id'] !== '' ? (int) $data['asignatura_id'] : null,
        'titulo'        => $data['titulo'],
        'descripcion'   => nullable($data['descripcion']),
        'fecha_limite'  => nullable($data['fecha_limite']),
        'prioridad'     => $data['prioridad'],
        'estado'        => $data['estado'],
    ];
}

/**
 * Contadores generales: total, completadas, pendientes, atrasadas y para hoy.
 */
function task_counts(int $userId): array
{
    $today = today();

    $row = db_one(
        "SELECT COUNT(*)                                                         AS total,
                COALESCE(SUM(estado = 'completada'), 0)                          AS completadas,
                COALESCE(SUM(estado <> 'completada'), 0)                         AS pendientes,
                COALESCE(SUM(estado <> 'completada' AND fecha_limite < :hoy1), 0) AS atrasadas,
                COALESCE(SUM(estado <> 'completada' AND fecha_limite = :hoy2), 0) AS hoy
           FROM tareas
          WHERE usuario_id = :uid",
        ['uid' => $userId, 'hoy1' => $today, 'hoy2' => $today]
    );

    return array_map('intval', $row ?? []);
}

/**
 * Tareas sin completar con fecha límite hasta $date (incluye las atrasadas).
 * Primero las más antiguas y, a igual fecha, las de prioridad más alta.
 */
function task_pending_until(int $userId, string $date, int $limit = 6): array
{
    // LIMIT no admite parámetros con nombre en todas las versiones: se fuerza a entero
    $limit = max(1, $limit);

    return db_all(
        "SELECT t.id, t.titulo, t.fecha_limite, t.prioridad, t.estado,
                a.nombre AS asignatura, a.color
           FROM tareas t
           LEFT JOIN asignaturas a ON a.id = t.asignatura_id
          WHERE t.usuario_id = :uid
            AND t.estado <> 'completada'
            AND t.fecha_limite <= :fecha
          ORDER BY t.fecha_limite ASC, FIELD(t.prioridad, 'alta', 'media', 'baja')
          LIMIT {$limit}",
        ['uid' => $userId, 'fecha' => $date]
    );
}

/**
 * Progreso de una semana: tareas que vencen en ella o que se completaron en ella.
 * Devuelve total, completadas y porcentaje (0–100).
 */
function task_week_progress(int $userId, string $start, string $end): array
{
    $nextDay = (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');

    $row = db_one(
        "SELECT COUNT(*)                                AS total,
                COALESCE(SUM(estado = 'completada'), 0) AS completadas
           FROM tareas
          WHERE usuario_id = :uid
            AND (fecha_limite BETWEEN :inicio AND :fin
                 OR (fecha_completada >= :inicio_c AND fecha_completada < :fin_c))",
        [
            'uid'      => $userId,
            'inicio'   => $start,
            'fin'      => $end,
            'inicio_c' => $start . ' 00:00:00',
            'fin_c'    => $nextDay . ' 00:00:00',
        ]
    );

    $total = (int) ($row['total'] ?? 0);
    $done  = (int) ($row['completadas'] ?? 0);

    return [
        'total'       => $total,
        'completadas' => $done,
        'porcentaje'  => $total > 0 ? (int) round($done / $total * 100) : 0,
    ];
}

/**
 * Número de tareas en cada estado: ['pendiente' => 5, 'en_progreso' => 2, 'completada' => 4].
 */
function task_status_counts(int $userId): array
{
    $rows = db_all(
        'SELECT estado, COUNT(*) AS total FROM tareas WHERE usuario_id = :uid GROUP BY estado',
        ['uid' => $userId]
    );
    $counts = array_column($rows, 'total', 'estado');

    return array_map(
        static fn (string $status): int => (int) ($counts[$status] ?? 0),
        array_combine(array_keys(TASK_STATUSES), array_keys(TASK_STATUSES))
    );
}

/**
 * Tareas completadas en cada una de las últimas $weeks semanas (de lunes a domingo),
 * de la más antigua a la actual: ['2026-08-24' => 3, …] (la clave es el lunes).
 */
function task_completed_by_week(int $userId, int $weeks = 8): array
{
    [$currentMonday, $currentSunday] = week_bounds();
    $firstMonday = (new DateTimeImmutable($currentMonday))->modify('-' . ($weeks - 1) . ' weeks')->format('Y-m-d');

    $result = [];
    foreach (task_completed_by_day($userId, $firstMonday, $currentSunday) as $day => $count) {
        $monday = week_bounds($day)[0];
        $result[$monday] = ($result[$monday] ?? 0) + $count;
    }

    return $result;
}

/**
 * Tareas completadas cada día entre dos fechas: ['2026-10-05' => 2, '2026-10-06' => 0, …]
 */
function task_completed_by_day(int $userId, string $start, string $end): array
{
    $nextDay = (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');

    $rows = db_all(
        'SELECT DATE(fecha_completada) AS dia, COUNT(*) AS total
           FROM tareas
          WHERE usuario_id = :uid
            AND fecha_completada >= :inicio
            AND fecha_completada < :fin
          GROUP BY DATE(fecha_completada)',
        ['uid' => $userId, 'inicio' => $start . ' 00:00:00', 'fin' => $nextDay . ' 00:00:00']
    );
    $counts = array_column($rows, 'total', 'dia');

    // Rellenamos con 0 los días sin actividad
    $days = [];
    $day  = new DateTimeImmutable($start);
    $last = new DateTimeImmutable($end);
    while ($day <= $last) {
        $key = $day->format('Y-m-d');
        $days[$key] = (int) ($counts[$key] ?? 0);
        $day = $day->modify('+1 day');
    }

    return $days;
}
