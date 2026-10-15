<?php
/**
 * Modelo: objetivos de estudio.
 * Todas las consultas a la tabla `objetivos` están en este archivo.
 * Todas filtran por usuario_id: un usuario nunca puede leer objetivos ajenos.
 *
 * El progreso (%) no se guarda: se calcula como valor_actual / valor_meta.
 */

declare(strict_types=1);

const GOAL_STATUSES = ['pendiente' => 'Pendiente', 'en_progreso' => 'En progreso', 'completado' => 'Completado'];

// Valor máximo de una meta (evita números absurdos en el formulario)
const GOAL_MAX_VALUE = 100000;

/**
 * Objetivos del usuario: primero los activos (por fecha límite, sin fecha al final),
 * después los completados (los más recientes primero).
 */
function goal_list(int $userId): array
{
    return db_all(
        "SELECT id, titulo, descripcion, fecha_limite, valor_actual, valor_meta, unidad, estado, fecha_creacion
           FROM objetivos
          WHERE usuario_id = :uid
          ORDER BY estado = 'completado',
                   fecha_limite IS NULL, fecha_limite,
                   id DESC",
        ['uid' => $userId]
    );
}

/**
 * Un objetivo del usuario o null si no existe (o es de otro usuario).
 */
function goal_find(int $userId, int $id): ?array
{
    return db_one(
        'SELECT id, titulo, descripcion, fecha_limite, valor_actual, valor_meta, unidad, estado
           FROM objetivos
          WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    );
}

/**
 * Estado coherente con el progreso:
 *  - Al llegar a la meta → completado (automático).
 *  - Marcarlo como completado a mano → el progreso pasa a la meta.
 *  - Con algo de progreso no puede estar "pendiente" → en progreso.
 * Devuelve [valor_actual, estado].
 */
function goal_resolve_status(int $current, int $target, string $status): array
{
    if ($current >= $target || $status === 'completado') {
        return [$target, 'completado'];
    }
    if ($status === 'pendiente' && $current > 0) {
        return [$current, 'en_progreso'];
    }

    return [$current, $status];
}

/**
 * Cuánto suman o restan los botones +/−: 1, o un 5 % de la meta si es grande (100 → 5).
 */
function goal_step(int $target): int
{
    return $target > 20 ? (int) ceil($target / 20) : 1;
}

/**
 * Porcentaje de progreso (0–100).
 */
function goal_percent(array $goal): int
{
    $target = max(1, (int) $goal['valor_meta']);

    return (int) min(100, floor((int) $goal['valor_actual'] / $target * 100));
}

/**
 * Crea un objetivo. $data: titulo, descripcion, fecha_limite, valor_actual, valor_meta, unidad, estado.
 */
function goal_create(int $userId, array $data): int
{
    db_execute(
        'INSERT INTO objetivos (usuario_id, titulo, descripcion, fecha_limite, valor_actual, valor_meta, unidad, estado)
         VALUES (:uid, :titulo, :descripcion, :fecha_limite, :valor_actual, :valor_meta, :unidad, :estado)',
        ['uid' => $userId] + goal_params($data)
    );

    return db_last_id();
}

function goal_update(int $userId, int $id, array $data): void
{
    db_execute(
        'UPDATE objetivos
            SET titulo = :titulo, descripcion = :descripcion, fecha_limite = :fecha_limite,
                valor_actual = :valor_actual, valor_meta = :valor_meta, unidad = :unidad, estado = :estado
          WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId] + goal_params($data)
    );
}

/**
 * Avanza o retrocede el progreso (botones +/−) y recalcula el estado.
 * Devuelve el objetivo actualizado.
 */
function goal_step_progress(int $userId, array $goal, int $direction): array
{
    $target  = (int) $goal['valor_meta'];
    $current = max(0, min($target, (int) $goal['valor_actual'] + $direction * goal_step($target)));
    $status  = match (true) {
        $current >= $target => 'completado',
        $current > 0        => 'en_progreso',
        default             => 'pendiente',
    };

    db_execute(
        'UPDATE objetivos SET valor_actual = :actual, estado = :estado WHERE id = :id AND usuario_id = :uid',
        ['actual' => $current, 'estado' => $status, 'id' => (int) $goal['id'], 'uid' => $userId]
    );

    return ['valor_actual' => $current, 'estado' => $status] + $goal;
}

function goal_delete(int $userId, int $id): bool
{
    return db_execute(
        'DELETE FROM objetivos WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    ) > 0;
}

/**
 * Parámetros comunes de INSERT/UPDATE (el estado se ajusta al progreso).
 */
function goal_params(array $data): array
{
    [$current, $status] = goal_resolve_status((int) $data['valor_actual'], (int) $data['valor_meta'], $data['estado']);

    return [
        'titulo'       => $data['titulo'],
        'descripcion'  => nullable($data['descripcion']),
        'fecha_limite' => nullable($data['fecha_limite']),
        'valor_actual' => $current,
        'valor_meta'   => (int) $data['valor_meta'],
        'unidad'       => nullable($data['unidad']),
        'estado'       => $status,
    ];
}

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
