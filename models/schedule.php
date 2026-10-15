<?php
/**
 * Modelo: horario semanal de clases.
 * Todas las consultas a la tabla `horario` están en este archivo.
 * Todas filtran por usuario_id: un usuario nunca puede leer clases ajenas.
 *
 * dia_semana sigue la norma ISO-8601: 1 = lunes … 7 = domingo (igual que date('N')).
 */

declare(strict_types=1);

/**
 * Todas las clases del usuario con su asignatura, ordenadas por día y hora.
 */
function schedule_all(int $userId): array
{
    return db_all(
        'SELECT h.id, h.asignatura_id, h.dia_semana, h.hora_inicio, h.hora_fin, h.aula,
                a.nombre AS asignatura, a.color, a.profesor
           FROM horario h
           JOIN asignaturas a ON a.id = h.asignatura_id
          WHERE h.usuario_id = :uid
          ORDER BY h.dia_semana, h.hora_inicio',
        ['uid' => $userId]
    );
}

/**
 * Clases agrupadas por día: [1 => [...], 2 => [...], …, 7 => [...]].
 */
function schedule_by_day(int $userId): array
{
    $days = array_fill_keys(array_keys(DAY_NAMES), []);
    foreach (schedule_all($userId) as $class) {
        $days[(int) $class['dia_semana']][] = $class;
    }

    return $days;
}

/**
 * Una clase del usuario o null si no existe (o es de otro usuario).
 */
function schedule_find(int $userId, int $id): ?array
{
    return db_one(
        'SELECT id, asignatura_id, dia_semana, hora_inicio, hora_fin, aula
           FROM horario
          WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    );
}

/**
 * Primera clase del mismo día que se solapa con el tramo [inicio, fin).
 * Dos clases se solapan si una empieza antes de que termine la otra.
 * Las clases seguidas (una acaba a las 10:00 y otra empieza a las 10:00) no se solapan.
 */
function schedule_overlap(int $userId, int $day, string $start, string $end, int $exceptId = 0): ?array
{
    return db_one(
        'SELECT h.id, h.hora_inicio, h.hora_fin, a.nombre AS asignatura
           FROM horario h
           JOIN asignaturas a ON a.id = h.asignatura_id
          WHERE h.usuario_id = :uid
            AND h.dia_semana = :dia
            AND h.hora_inicio < :fin
            AND h.hora_fin > :inicio
            AND h.id <> :id
          ORDER BY h.hora_inicio
          LIMIT 1',
        ['uid' => $userId, 'dia' => $day, 'inicio' => $start, 'fin' => $end, 'id' => $exceptId]
    );
}

/**
 * Crea una clase. $data: asignatura_id, dia_semana, hora_inicio, hora_fin, aula.
 */
function schedule_create(int $userId, array $data): int
{
    db_execute(
        'INSERT INTO horario (usuario_id, asignatura_id, dia_semana, hora_inicio, hora_fin, aula)
         VALUES (:uid, :asignatura_id, :dia_semana, :hora_inicio, :hora_fin, :aula)',
        ['uid' => $userId] + schedule_params($data)
    );

    return db_last_id();
}

function schedule_update(int $userId, int $id, array $data): void
{
    db_execute(
        'UPDATE horario
            SET asignatura_id = :asignatura_id, dia_semana = :dia_semana,
                hora_inicio = :hora_inicio, hora_fin = :hora_fin, aula = :aula
          WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId] + schedule_params($data)
    );
}

function schedule_delete(int $userId, int $id): bool
{
    return db_execute(
        'DELETE FROM horario WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    ) > 0;
}

/**
 * Parámetros comunes de INSERT/UPDATE.
 */
function schedule_params(array $data): array
{
    return [
        'asignatura_id' => (int) $data['asignatura_id'],
        'dia_semana'    => (int) $data['dia_semana'],
        'hora_inicio'   => $data['hora_inicio'],
        'hora_fin'      => $data['hora_fin'],
        'aula'          => nullable($data['aula']),
    ];
}

/**
 * Minutos desde las 00:00: "09:30:00" → 570.
 */
function time_to_minutes(string $time): int
{
    [$hours, $minutes] = array_map('intval', explode(':', $time) + [0, 0]);

    return $hours * 60 + $minutes;
}
