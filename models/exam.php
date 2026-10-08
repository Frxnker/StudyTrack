<?php
/**
 * Modelo: exámenes.
 * Todas las consultas a la tabla `examenes` están en este archivo.
 */

declare(strict_types=1);

const EXAM_STATUSES = ['pendiente' => 'Pendiente', 'realizado' => 'Realizado', 'aplazado' => 'Aplazado'];

/**
 * Próximo examen pendiente (hoy o en el futuro), con su asignatura.
 */
function exam_next(int $userId): ?array
{
    return db_one(
        "SELECT e.id, e.nombre, e.fecha, e.hora, e.aula, e.descripcion,
                a.nombre AS asignatura, a.color
           FROM examenes e
           JOIN asignaturas a ON a.id = e.asignatura_id
          WHERE e.usuario_id = :uid
            AND e.estado = 'pendiente'
            AND e.fecha >= :hoy
          ORDER BY e.fecha ASC, e.hora IS NULL, e.hora ASC
          LIMIT 1",
        ['uid' => $userId, 'hoy' => today()]
    );
}

/**
 * Número de exámenes pendientes entre dos fechas (ambas incluidas).
 */
function exam_count_between(int $userId, string $start, string $end): int
{
    return (int) db_value(
        "SELECT COUNT(*)
           FROM examenes
          WHERE usuario_id = :uid
            AND estado <> 'aplazado'
            AND fecha BETWEEN :inicio AND :fin",
        ['uid' => $userId, 'inicio' => $start, 'fin' => $end]
    );
}
