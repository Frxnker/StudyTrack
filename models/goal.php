<?php
/**
 * Modelo: objetivos de estudio.
 * Todas las consultas a la tabla `objetivos` están en este archivo.
 */

declare(strict_types=1);

/**
 * Resumen de objetivos: total, completados y progreso medio (0–100).
 */
function goal_summary(int $userId): array
{
    $row = db_one(
        "SELECT COUNT(*)                                       AS total,
                COALESCE(SUM(estado = 'completado'), 0)        AS completados,
                AVG(LEAST(valor_actual / valor_meta, 1)) * 100 AS progreso_medio
           FROM objetivos
          WHERE usuario_id = :uid",
        ['uid' => $userId]
    );

    return [
        'total'          => (int) ($row['total'] ?? 0),
        'completados'    => (int) ($row['completados'] ?? 0),
        'progreso_medio' => (int) round((float) ($row['progreso_medio'] ?? 0)),
    ];
}
