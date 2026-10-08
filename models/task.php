<?php
/**
 * Modelo: tareas.
 * Todas las consultas a la tabla `tareas` están en este archivo.
 * Todas filtran por usuario_id: un usuario nunca puede leer tareas ajenas.
 */

declare(strict_types=1);

const TASK_PRIORITIES = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta'];
const TASK_STATUSES   = ['pendiente' => 'Pendiente', 'en_progreso' => 'En progreso', 'completada' => 'Completada'];

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
