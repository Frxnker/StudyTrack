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
 * Une las horas seguidas de la misma asignatura y aula en un solo bloque:
 * DWEC 09:00–10:00 + DWEC 10:00–11:00 → DWEC 09:00–11:00.
 * $classes son las clases de UN día, ordenadas por hora. Cada bloque conserva
 * los datos de su primera clase y la lista de sus horas en 'franjas'.
 */
function schedule_merge_blocks(array $classes): array
{
    $blocks = [];
    foreach ($classes as $class) {
        $last = array_key_last($blocks);
        if ($last !== null
            && (int) $blocks[$last]['asignatura_id'] === (int) $class['asignatura_id']
            && (string) $blocks[$last]['aula'] === (string) $class['aula']
            && $blocks[$last]['hora_fin'] === $class['hora_inicio']) {
            $blocks[$last]['hora_fin'] = $class['hora_fin'];
            $blocks[$last]['franjas'][] = $class;
            continue;
        }
        $blocks[] = $class + ['franjas' => [$class]];
    }

    return $blocks;
}

/**
 * Descansos de la jornada (SCHOOL_BREAKS) con sus horas en minutos.
 */
function schedule_breaks(): array
{
    return array_map(
        static fn (array $break): array => [
            'inicio' => $break[0],
            'fin'    => $break[1],
            'texto'  => $break[2] ?? 'Descanso',
            'desde'  => time_to_minutes($break[0]),
            'hasta'  => time_to_minutes($break[1]),
        ],
        SCHOOL_BREAKS
    );
}

/**
 * Bloques de un día con los descansos intercalados, en orden:
 * [['tipo' => 'clase', …bloque], ['tipo' => 'descanso', …], …].
 * Un descanso solo aparece si cae entre la primera y la última clase del día
 * y no se solapa con ninguna.
 */
function schedule_day_items(array $blocks): array
{
    $items = array_map(static fn (array $block): array => ['tipo' => 'clase'] + $block, $blocks);
    if ($blocks === []) {
        return $items;
    }

    $first = time_to_minutes($blocks[0]['hora_inicio']);
    $last  = time_to_minutes($blocks[array_key_last($blocks)]['hora_fin']);

    foreach (schedule_breaks() as $break) {
        $inside   = $break['desde'] >= $first && $break['hasta'] <= $last;
        $overlaps = array_filter(
            $blocks,
            static fn (array $b): bool => time_to_minutes($b['hora_inicio']) < $break['hasta']
                                       && time_to_minutes($b['hora_fin']) > $break['desde']
        );
        if ($inside && !$overlaps) {
            $items[] = ['tipo' => 'descanso', 'hora_inicio' => $break['inicio'], 'hora_fin' => $break['fin']] + $break;
        }
    }

    usort($items, static fn (array $a, array $b): int => time_to_minutes($a['hora_inicio']) <=> time_to_minutes($b['hora_inicio']));

    return $items;
}

/**
 * Qué toca ahora y qué viene después en un día, a una hora dada ("10:15").
 * Devuelve ['ahora' => índice del bloque o null, 'despues' => índice o null,
 *           'descanso' => descanso en curso o null].
 */
function schedule_now_next(array $blocks, string $time): array
{
    $now    = time_to_minutes($time);
    $result = ['ahora' => null, 'despues' => null, 'descanso' => null];

    foreach ($blocks as $i => $block) {
        $start = time_to_minutes($block['hora_inicio']);
        $end   = time_to_minutes($block['hora_fin']);

        if ($result['ahora'] === null && $start <= $now && $now < $end) {
            $result['ahora'] = $i;
        } elseif ($result['despues'] === null && $start > $now) {
            $result['despues'] = $i;
        }
    }

    // Recreo en curso (solo si hay clase antes y después)
    foreach (schedule_day_items($blocks) as $item) {
        if ($item['tipo'] === 'descanso' && $item['desde'] <= $now && $now < $item['hasta']) {
            $result['descanso'] = $item;
        }
    }

    return $result;
}

/**
 * Próximo día con clase después de $today (1 = lunes … 7 = domingo), contando
 * la semana siguiente. Devuelve [día, primer bloque] o null si el horario está vacío.
 */
function schedule_next_day(array $days, int $today): ?array
{
    for ($i = 1; $i <= 7; $i++) {
        $day = ($today - 1 + $i) % 7 + 1;
        if ($days[$day] !== []) {
            return [$day, schedule_merge_blocks($days[$day])[0]];
        }
    }

    return null;
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
