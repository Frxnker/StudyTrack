<?php
/**
 * Modelo: asignaturas.
 * Todas las consultas a la tabla `asignaturas` están en este archivo.
 * Todas filtran por usuario_id: un usuario nunca puede leer asignaturas ajenas.
 */

declare(strict_types=1);

// Colores sugeridos en el selector del formulario
const SUBJECT_COLORS = [
    '#4F46E5', '#7C3AED', '#DB2777', '#EF4444', '#F97316',
    '#F59E0B', '#10B981', '#14B8A6', '#0EA5E9', '#64748B',
];

/**
 * Asignaturas del usuario con sus estadísticas: tareas pendientes, media,
 * nº de notas y exámenes pendientes (cuántos y la fecha del más cercano).
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
                  WHERE n.asignatura_id = a.id
                    AND n.usuario_id = a.usuario_id) AS media,
                (SELECT COUNT(*)
                   FROM notas n
                  WHERE n.asignatura_id = a.id
                    AND n.usuario_id = a.usuario_id) AS num_notas,
                (SELECT COUNT(*)
                   FROM examenes e
                  WHERE e.asignatura_id = a.id
                    AND e.usuario_id = a.usuario_id
                    AND e.estado = 'pendiente'
                    AND e.fecha >= :hoy1)           AS examenes_proximos,
                (SELECT MIN(e.fecha)
                   FROM examenes e
                  WHERE e.asignatura_id = a.id
                    AND e.usuario_id = a.usuario_id
                    AND e.estado = 'pendiente'
                    AND e.fecha >= :hoy2)           AS proximo_examen
           FROM asignaturas a
          WHERE a.usuario_id = :uid
          ORDER BY a.nombre",
        ['uid' => $userId, 'hoy1' => today(), 'hoy2' => today()]
    );
}

/**
 * Lista sencilla (id, nombre, color) para los desplegables de los formularios.
 */
function subject_options(int $userId): array
{
    return db_all(
        'SELECT id, nombre, color FROM asignaturas WHERE usuario_id = :uid ORDER BY nombre',
        ['uid' => $userId]
    );
}

/**
 * Asignaturas con su descripción (para reconocerlas en los calendarios importados).
 */
function subject_match_list(int $userId): array
{
    return db_all(
        'SELECT id, nombre, descripcion, color FROM asignaturas WHERE usuario_id = :uid ORDER BY nombre',
        ['uid' => $userId]
    );
}

/**
 * Abreviatura de una asignatura: el principio de su descripción si es corto y en
 * mayúsculas. "DWEC · Tutor: Juan Pedro…" → "DWEC", "IPE II" → "IPE II".
 * Null si la descripción no empieza por una abreviatura.
 */
function subject_abbreviation(?string $description): ?string
{
    $first = preg_split('/\s*(?:·|\||:|\(| - | – |\n)\s*/u', trim((string) $description), 2)[0] ?? '';

    return preg_match('/^[A-ZÁÉÍÓÚÑ]{2,6}(?: [A-Z0-9]{1,3})?$/u', $first) === 1 ? $first : null;
}

/**
 * Una asignatura del usuario o null si no existe (o es de otro usuario).
 */
function subject_find(int $userId, int $id): ?array
{
    return db_one(
        'SELECT id, nombre, descripcion, profesor, curso, color, fecha_creacion
           FROM asignaturas
          WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    );
}

/**
 * ¿La asignatura pertenece al usuario? (para validar los desplegables)
 */
function subject_belongs_to(int $userId, int $id): bool
{
    return (bool) db_value(
        'SELECT COUNT(*) FROM asignaturas WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    );
}

/**
 * ¿El usuario ya tiene una asignatura con ese nombre? ($exceptId excluye la que se edita)
 * La comparación usa la intercalación de la tabla: no distingue mayúsculas ni tildes,
 * igual que el índice único uk_asignaturas_usuario_nombre.
 */
function subject_name_exists(int $userId, string $name, int $exceptId = 0): bool
{
    return (bool) db_value(
        'SELECT COUNT(*) FROM asignaturas WHERE usuario_id = :uid AND nombre = :nombre AND id <> :id',
        ['uid' => $userId, 'nombre' => $name, 'id' => $exceptId]
    );
}

/**
 * Número de elementos asociados a una asignatura (para avisar antes de borrarla).
 */
function subject_related_counts(int $userId, int $id): array
{
    $row = db_one(
        'SELECT (SELECT COUNT(*) FROM tareas   WHERE asignatura_id = :id1 AND usuario_id = :uid1) AS tareas,
                (SELECT COUNT(*) FROM examenes WHERE asignatura_id = :id2 AND usuario_id = :uid2) AS examenes,
                (SELECT COUNT(*) FROM notas    WHERE asignatura_id = :id3 AND usuario_id = :uid3) AS notas,
                (SELECT COUNT(*) FROM horario  WHERE asignatura_id = :id4 AND usuario_id = :uid4) AS clases',
        [
            'id1' => $id, 'uid1' => $userId, 'id2' => $id, 'uid2' => $userId,
            'id3' => $id, 'uid3' => $userId, 'id4' => $id, 'uid4' => $userId,
        ]
    );

    return array_map('intval', $row ?? []);
}

/**
 * Crea una asignatura. $data: nombre, descripcion, profesor, curso, color.
 */
function subject_create(int $userId, array $data): int
{
    db_execute(
        'INSERT INTO asignaturas (usuario_id, nombre, descripcion, profesor, curso, color)
         VALUES (:uid, :nombre, :descripcion, :profesor, :curso, :color)',
        ['uid' => $userId] + subject_params($data)
    );

    return db_last_id();
}

/**
 * Actualiza una asignatura del usuario. Devuelve false si no existe.
 */
function subject_update(int $userId, int $id, array $data): bool
{
    db_execute(
        'UPDATE asignaturas
            SET nombre = :nombre, descripcion = :descripcion, profesor = :profesor,
                curso = :curso, color = :color
          WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId] + subject_params($data)
    );

    // rowCount() es 0 también si no cambió nada: comprobamos que exista
    return subject_belongs_to($userId, $id);
}

/**
 * Borra una asignatura. Sus exámenes, notas y clases se borran en cascada;
 * sus tareas se conservan sin asignatura (ON DELETE SET NULL).
 */
function subject_delete(int $userId, int $id): bool
{
    return db_execute(
        'DELETE FROM asignaturas WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    ) > 0;
}

/**
 * Parámetros comunes de INSERT/UPDATE (los textos vacíos se guardan como NULL).
 */
function subject_params(array $data): array
{
    return [
        'nombre'      => $data['nombre'],
        'descripcion' => nullable($data['descripcion']),
        'profesor'    => nullable($data['profesor']),
        'curso'       => nullable($data['curso']),
        'color'       => strtoupper($data['color']),
    ];
}
