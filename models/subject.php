<?php
/**
 * Modelo: asignaturas.
 * Todas las consultas a la tabla `asignaturas` están en este archivo.
 */

declare(strict_types=1);

/**
 * Asignaturas del usuario con sus estadísticas: tareas pendientes, media y nº de notas.
 */
function subject_all_with_stats(int $userId): array
{
    return db_all(
        "SELECT a.id, a.nombre, a.descripcion, a.profesor, a.curso, a.color, a.fecha_creacion,
                (SELECT COUNT(*)
                   FROM tareas t
                  WHERE t.asignatura_id = a.id
                    AND t.usuario_id = a.usuario_id
                    AND t.estado <> 'completada')   AS tareas_pendientes,
                (SELECT AVG(n.nota)
                   FROM notas n
                  WHERE n.asignatura_id = a.id)     AS media,
                (SELECT COUNT(*)
                   FROM notas n
                  WHERE n.asignatura_id = a.id)     AS num_notas
           FROM asignaturas a
          WHERE a.usuario_id = :uid
          ORDER BY a.nombre",
        ['uid' => $userId]
    );
}
