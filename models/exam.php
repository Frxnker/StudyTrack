<?php
/**
 * Modelo: exámenes.
 * Todas las consultas a la tabla `examenes` están en este archivo.
 * Todas filtran por usuario_id: un usuario nunca puede leer exámenes ajenos.
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
 * Exámenes próximos: desde hoy en adelante y aún no realizados (incluye aplazados
 * con fecha futura). Del más cercano al más lejano.
 */
function exam_upcoming(int $userId, int $subjectId = 0): array
{
    [$where, $params] = exam_subject_filter($userId, $subjectId);

    return db_all(
        "SELECT e.id, e.asignatura_id, e.nombre, e.fecha, e.hora, e.aula, e.descripcion, e.estado,
                a.nombre AS asignatura, a.color, c.origen AS calendario_origen
           FROM examenes e
           JOIN asignaturas a ON a.id = e.asignatura_id
           LEFT JOIN calendarios_externos c ON c.id = e.calendario_id AND c.usuario_id = e.usuario_id
          WHERE {$where}
            AND e.fecha >= :hoy
            AND e.estado <> 'realizado'
          ORDER BY e.fecha ASC, e.hora IS NULL, e.hora ASC",
        $params + ['hoy' => today()]
    );
}

/**
 * Exámenes pasados: con fecha anterior a hoy o ya realizados. Del más reciente al más antiguo.
 */
function exam_past(int $userId, int $subjectId = 0): array
{
    [$where, $params] = exam_subject_filter($userId, $subjectId);

    return db_all(
        "SELECT e.id, e.asignatura_id, e.nombre, e.fecha, e.hora, e.aula, e.descripcion, e.estado,
                a.nombre AS asignatura, a.color, c.origen AS calendario_origen
           FROM examenes e
           JOIN asignaturas a ON a.id = e.asignatura_id
           LEFT JOIN calendarios_externos c ON c.id = e.calendario_id AND c.usuario_id = e.usuario_id
          WHERE {$where}
            AND (e.fecha < :hoy OR e.estado = 'realizado')
          ORDER BY e.fecha DESC, e.hora DESC",
        $params + ['hoy' => today()]
    );
}

/**
 * Exámenes entre dos fechas (para el calendario), con el color de su asignatura.
 */
function exam_between(int $userId, string $start, string $end): array
{
    return db_all(
        'SELECT e.id, e.nombre, e.fecha, e.hora, e.aula, e.estado, a.nombre AS asignatura, a.color
           FROM examenes e
           JOIN asignaturas a ON a.id = e.asignatura_id
          WHERE e.usuario_id = :uid
            AND e.fecha BETWEEN :inicio AND :fin
          ORDER BY e.fecha, e.hora IS NULL, e.hora',
        ['uid' => $userId, 'inicio' => $start, 'fin' => $end]
    );
}

/**
 * WHERE común: exámenes del usuario y, opcionalmente, de una asignatura.
 */
function exam_subject_filter(int $userId, int $subjectId): array
{
    $where  = 'e.usuario_id = :uid';
    $params = ['uid' => $userId];

    if ($subjectId > 0) {
        $where .= ' AND e.asignatura_id = :asignatura';
        $params['asignatura'] = $subjectId;
    }

    return [$where, $params];
}

/**
 * Un examen del usuario o null si no existe (o es de otro usuario).
 * Si se importó de un calendario externo, incluye su nombre y su origen.
 */
function exam_find(int $userId, int $id): ?array
{
    return db_one(
        'SELECT e.id, e.asignatura_id, e.nombre, e.fecha, e.hora, e.aula, e.descripcion, e.estado,
                c.nombre AS calendario, c.origen AS calendario_origen
           FROM examenes e
           LEFT JOIN calendarios_externos c ON c.id = e.calendario_id AND c.usuario_id = e.usuario_id
          WHERE e.id = :id AND e.usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    );
}

/**
 * Crea un examen. $data: asignatura_id, nombre, fecha, hora, aula, descripcion, estado.
 * La clave foránea compuesta (asignatura_id, usuario_id) impide usar asignaturas ajenas.
 */
function exam_create(int $userId, array $data): int
{
    db_execute(
        'INSERT INTO examenes (usuario_id, asignatura_id, nombre, fecha, hora, aula, descripcion, estado)
         VALUES (:uid, :asignatura_id, :nombre, :fecha, :hora, :aula, :descripcion, :estado)',
        ['uid' => $userId] + exam_params($data)
    );

    return db_last_id();
}

function exam_update(int $userId, int $id, array $data): void
{
    db_execute(
        'UPDATE examenes
            SET asignatura_id = :asignatura_id, nombre = :nombre, fecha = :fecha, hora = :hora,
                aula = :aula, descripcion = :descripcion, estado = :estado
          WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId] + exam_params($data)
    );
}

/**
 * Cambia solo el estado (botón "Marcar como realizado").
 */
function exam_set_status(int $userId, int $id, string $status): void
{
    db_execute(
        'UPDATE examenes SET estado = :estado WHERE id = :id AND usuario_id = :uid',
        ['estado' => $status, 'id' => $id, 'uid' => $userId]
    );
}

function exam_delete(int $userId, int $id): bool
{
    return db_execute(
        'DELETE FROM examenes WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    ) > 0;
}

/**
 * Parámetros comunes de INSERT/UPDATE (los campos opcionales vacíos van como NULL).
 */
function exam_params(array $data): array
{
    return [
        'asignatura_id' => (int) $data['asignatura_id'],
        'nombre'        => $data['nombre'],
        'fecha'         => $data['fecha'],
        'hora'          => nullable($data['hora']),
        'aula'          => nullable($data['aula']),
        'descripcion'   => nullable($data['descripcion']),
        'estado'        => $data['estado'],
    ];
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
