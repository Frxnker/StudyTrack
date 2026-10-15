<?php
/**
 * Modelo: notas (calificaciones).
 * Todas las consultas a la tabla `notas` están en este archivo.
 * Todas filtran por usuario_id: un usuario nunca puede leer notas ajenas.
 */

declare(strict_types=1);

const GRADE_TYPES = [
    'examen'        => 'Examen',
    'trabajo'       => 'Trabajo',
    'practica'      => 'Práctica',
    'proyecto'      => 'Proyecto',
    'participacion' => 'Participación',
    'otro'          => 'Otro',
];

/**
 * ¿La nota es un aprobado? (según PASS_MARK, por defecto 5)
 */
function grade_passed(float|string $grade): bool
{
    return (float) $grade >= PASS_MARK;
}

/**
 * Nota con formato español: 7.50 → "7,5", null → "—".
 */
function format_grade(float|string|null $grade): string
{
    return $grade === null ? '—' : format_number((float) $grade);
}

/**
 * Últimas notas registradas, con su asignatura.
 */
function grade_latest(int $userId, int $limit = 5): array
{
    $limit = max(1, $limit);

    return db_all(
        "SELECT n.id, n.tipo, n.nota, n.fecha, n.comentarios,
                a.nombre AS asignatura, a.color
           FROM notas n
           JOIN asignaturas a ON a.id = n.asignatura_id
          WHERE n.usuario_id = :uid
          ORDER BY n.fecha DESC, n.id DESC
          LIMIT {$limit}",
        ['uid' => $userId]
    );
}

/**
 * Media general: media de las medias de cada asignatura (todas pesan lo mismo,
 * igual que en un expediente académico). Devuelve null si no hay notas.
 */
function grade_overall_average(int $userId): ?float
{
    $average = db_value(
        'SELECT AVG(media)
           FROM (SELECT AVG(nota) AS media
                   FROM notas
                  WHERE usuario_id = :uid
                  GROUP BY asignatura_id) AS medias',
        ['uid' => $userId]
    );

    return $average === null ? null : (float) $average;
}

/**
 * Todas las notas del usuario (opcionalmente de una asignatura), de la más reciente a la más antigua.
 */
function grade_list(int $userId, int $subjectId = 0): array
{
    $where  = 'n.usuario_id = :uid';
    $params = ['uid' => $userId];

    if ($subjectId > 0) {
        $where .= ' AND n.asignatura_id = :asignatura';
        $params['asignatura'] = $subjectId;
    }

    return db_all(
        "SELECT n.id, n.asignatura_id, n.tipo, n.nota, n.fecha, n.comentarios,
                a.nombre AS asignatura, a.color
           FROM notas n
           JOIN asignaturas a ON a.id = n.asignatura_id
          WHERE {$where}
          ORDER BY n.fecha DESC, n.id DESC",
        $params
    );
}

/**
 * Media, nº de notas, mejor y peor nota de cada asignatura (también las que aún no tienen notas).
 */
function grade_subject_averages(int $userId): array
{
    return db_all(
        'SELECT a.id, a.nombre, a.color,
                AVG(n.nota)  AS media,
                COUNT(n.id)  AS num_notas,
                MAX(n.nota)  AS mejor,
                MIN(n.nota)  AS peor
           FROM asignaturas a
           LEFT JOIN notas n ON n.asignatura_id = a.id AND n.usuario_id = a.usuario_id
          WHERE a.usuario_id = :uid
          GROUP BY a.id, a.nombre, a.color
          ORDER BY COUNT(n.id) = 0, a.nombre',
        ['uid' => $userId]
    );
}

/**
 * Totales: nº de notas y cuántas son aprobados.
 */
function grade_summary(int $userId): array
{
    $row = db_one(
        'SELECT COUNT(*) AS total, COALESCE(SUM(nota >= :aprobado), 0) AS aprobadas
           FROM notas
          WHERE usuario_id = :uid',
        ['uid' => $userId, 'aprobado' => PASS_MARK]
    );

    return array_map('intval', $row ?? ['total' => 0, 'aprobadas' => 0]);
}

/**
 * Una nota del usuario o null si no existe (o es de otro usuario).
 */
function grade_find(int $userId, int $id): ?array
{
    return db_one(
        'SELECT id, asignatura_id, tipo, nota, fecha, comentarios
           FROM notas
          WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    );
}

/**
 * Crea una nota. $data: asignatura_id, tipo, nota (con punto decimal), fecha, comentarios.
 */
function grade_create(int $userId, array $data): int
{
    db_execute(
        'INSERT INTO notas (usuario_id, asignatura_id, tipo, nota, fecha, comentarios)
         VALUES (:uid, :asignatura_id, :tipo, :nota, :fecha, :comentarios)',
        ['uid' => $userId] + grade_params($data)
    );

    return db_last_id();
}

function grade_update(int $userId, int $id, array $data): void
{
    db_execute(
        'UPDATE notas
            SET asignatura_id = :asignatura_id, tipo = :tipo, nota = :nota,
                fecha = :fecha, comentarios = :comentarios
          WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId] + grade_params($data)
    );
}

function grade_delete(int $userId, int $id): bool
{
    return db_execute(
        'DELETE FROM notas WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    ) > 0;
}

/**
 * Parámetros comunes de INSERT/UPDATE.
 */
function grade_params(array $data): array
{
    return [
        'asignatura_id' => (int) $data['asignatura_id'],
        'tipo'          => $data['tipo'],
        'nota'          => round((float) $data['nota'], 2),
        'fecha'         => $data['fecha'],
        'comentarios'   => nullable($data['comentarios']),
    ];
}

/**
 * Número de notas registradas entre dos fechas (ambas incluidas).
 */
function grade_count_between(int $userId, string $start, string $end): int
{
    return (int) db_value(
        'SELECT COUNT(*) FROM notas WHERE usuario_id = :uid AND fecha BETWEEN :inicio AND :fin',
        ['uid' => $userId, 'inicio' => $start, 'fin' => $end]
    );
}
