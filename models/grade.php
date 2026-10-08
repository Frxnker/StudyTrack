<?php
/**
 * Modelo: notas (calificaciones).
 * Todas las consultas a la tabla `notas` están en este archivo.
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
 * Número de notas registradas entre dos fechas (ambas incluidas).
 */
function grade_count_between(int $userId, string $start, string $end): int
{
    return (int) db_value(
        'SELECT COUNT(*) FROM notas WHERE usuario_id = :uid AND fecha BETWEEN :inicio AND :fin',
        ['uid' => $userId, 'inicio' => $start, 'fin' => $end]
    );
}
