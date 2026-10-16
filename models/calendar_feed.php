<?php
/**
 * Modelo: calendarios externos (Moodle, Google Classroom… por URL iCal).
 * Todas las consultas a calendarios_externos y correspondencias_externas están
 * en este archivo, y todas filtran por usuario_id.
 *
 *  - La URL se guarda cifrada (encrypt_secret con APP_KEY) y en pantalla solo se
 *    muestra enmascarada.
 *  - Solo se importan fechas y títulos: cada evento se convierte en una tarea o
 *    en un examen. Las notas de Moodle o Classroom NO se importan.
 *  - Sincronizar no borra nunca nada ni toca el estado, la prioridad ni la
 *    asignatura que el usuario haya puesto: solo actualiza título y fecha.
 */

declare(strict_types=1);

require_once BASE_PATH . '/includes/ical.php';

const CALENDAR_ORIGINS = ['moodle' => 'Moodle', 'classroom' => 'Google Classroom', 'otro' => 'Otro calendario'];
const CALENDAR_TYPES   = ['tarea' => 'Tarea', 'examen' => 'Examen'];

// Solo se importan los eventos desde hace estos días en adelante
const CALENDAR_PAST_DAYS = 30;

// Palabras que no cuentan al comparar nombres de asignaturas
const CALENDAR_STOPWORDS = ['de', 'del', 'la', 'las', 'el', 'los', 'en', 'y', 'e', 'a', 'al', 'para', 'con', 'por'];

// Abreviaturas equivalentes: EIE es el nombre anterior de IPE; ING, de inglés
const CALENDAR_ABBR_ALIASES = ['ipe' => ['eie'], 'eie' => ['ipe'], 'ip' => ['ing'], 'ing' => ['ip']];

/* =============================================================================
 * 1. Calendarios: consultas y CRUD
 * ========================================================================== */

/**
 * Calendarios del usuario con su asignatura y cuántas tareas y exámenes se han importado.
 */
function calendar_feed_all(int $userId): array
{
    return db_all(
        'SELECT c.id, c.nombre, c.origen, c.url, c.asignatura_id, c.tipo_por_defecto, c.activo,
                c.ultima_sincronizacion, c.ultimo_error, c.fecha_creacion,
                a.nombre AS asignatura, a.color,
                (SELECT COUNT(*) FROM tareas t   WHERE t.calendario_id = c.id AND t.usuario_id = c.usuario_id) AS num_tareas,
                (SELECT COUNT(*) FROM examenes e WHERE e.calendario_id = c.id AND e.usuario_id = c.usuario_id) AS num_examenes
           FROM calendarios_externos c
           LEFT JOIN asignaturas a ON a.id = c.asignatura_id AND a.usuario_id = c.usuario_id
          WHERE c.usuario_id = :uid
          ORDER BY c.nombre, c.id',
        ['uid' => $userId]
    );
}

/**
 * Un calendario del usuario o null si no existe (o es de otro usuario).
 */
function calendar_feed_find(int $userId, int $id): ?array
{
    return db_one(
        'SELECT c.id, c.usuario_id, c.nombre, c.origen, c.url, c.asignatura_id, c.tipo_por_defecto, c.activo,
                c.ultima_sincronizacion, c.ultimo_error,
                (SELECT COUNT(*) FROM tareas t   WHERE t.calendario_id = c.id AND t.usuario_id = c.usuario_id) AS num_tareas,
                (SELECT COUNT(*) FROM examenes e WHERE e.calendario_id = c.id AND e.usuario_id = c.usuario_id) AS num_examenes
           FROM calendarios_externos c
          WHERE c.id = :id AND c.usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    );
}

/**
 * Calendarios activos de TODOS los usuarios (solo para la sincronización automática).
 */
function calendar_feed_active_all(): array
{
    return db_all(
        'SELECT id, usuario_id, nombre, origen, url, asignatura_id, tipo_por_defecto
           FROM calendarios_externos
          WHERE activo = 1
          ORDER BY usuario_id, id'
    );
}

/**
 * Crea un calendario. $data: nombre, origen, url (en claro), asignatura_id, tipo_por_defecto, activo.
 */
function calendar_feed_create(int $userId, array $data): int
{
    db_execute(
        'INSERT INTO calendarios_externos (usuario_id, asignatura_id, nombre, origen, url, tipo_por_defecto, activo)
         VALUES (:uid, :asignatura_id, :nombre, :origen, :url, :tipo, :activo)',
        ['uid' => $userId, 'url' => encrypt_secret($data['url'])] + calendar_feed_params($data)
    );

    return db_last_id();
}

/**
 * Actualiza un calendario. La URL solo cambia si $data['url'] trae una nueva.
 */
function calendar_feed_update(int $userId, int $id, array $data): void
{
    $params = ['id' => $id, 'uid' => $userId] + calendar_feed_params($data);
    $setUrl = '';
    if ($data['url'] !== '') {
        $setUrl = ', url = :url, ultimo_error = NULL';
        $params['url'] = encrypt_secret($data['url']);
    }

    db_execute(
        "UPDATE calendarios_externos
            SET asignatura_id = :asignatura_id, nombre = :nombre, origen = :origen,
                tipo_por_defecto = :tipo, activo = :activo{$setUrl}
          WHERE id = :id AND usuario_id = :uid",
        $params
    );
}

function calendar_feed_params(array $data): array
{
    return [
        'asignatura_id' => $data['asignatura_id'] !== '' ? (int) $data['asignatura_id'] : null,
        'nombre'        => $data['nombre'],
        'origen'        => $data['origen'],
        'tipo'          => $data['tipo_por_defecto'],
        'activo'        => $data['activo'] ? 1 : 0,
    ];
}

/**
 * Borra un calendario. Con $deleteImported también sus tareas y exámenes importados;
 * si no, se conservan (dejan de estar asociados al calendario).
 * Devuelve ['tareas' => n, 'examenes' => n] borrados.
 */
function calendar_feed_delete(int $userId, int $id, bool $deleteImported): array
{
    $deleted = ['tareas' => 0, 'examenes' => 0];

    db()->beginTransaction();
    try {
        if ($deleteImported) {
            $params = ['id' => $id, 'uid' => $userId];
            $deleted['tareas']   = db_execute('DELETE FROM tareas WHERE calendario_id = :id AND usuario_id = :uid', $params);
            $deleted['examenes'] = db_execute('DELETE FROM examenes WHERE calendario_id = :id AND usuario_id = :uid', $params);
        }
        db_execute('DELETE FROM calendarios_externos WHERE id = :id AND usuario_id = :uid', ['id' => $id, 'uid' => $userId]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    return $deleted;
}

/**
 * Guarda el resultado de una sincronización: la fecha si ha ido bien o el error si no
 * (en ese caso se conserva la fecha de la última sincronización correcta).
 */
function calendar_feed_save_result(int $userId, int $id, ?string $error): void
{
    db_execute(
        'UPDATE calendarios_externos
            SET ultima_sincronizacion = IF(:ok = 1, NOW(), ultima_sincronizacion),
                ultimo_error = :error
          WHERE id = :id AND usuario_id = :uid',
        ['ok' => $error === null ? 1 : 0, 'error' => $error !== null ? str_take($error, 500) : null, 'id' => $id, 'uid' => $userId]
    );
}

/**
 * URL en claro de un calendario (descifrada con APP_KEY).
 */
function calendar_feed_url(array $calendar): string
{
    return decrypt_secret((string) $calendar['url']);
}

/**
 * URL enmascarada para mostrar: solo el servidor ("https://moodle.iescelia.org/••••••").
 */
function calendar_feed_mask_url(string $url): string
{
    $parts = parse_url($url);
    if ($parts === false || !isset($parts['host'])) {
        return '••••••••';
    }

    return strtolower($parts['scheme'] ?? 'https') . '://' . $parts['host'] . '/••••••••';
}

/**
 * URL enmascarada de un calendario guardado (sin descifrar si falta APP_KEY).
 */
function calendar_feed_masked(array $calendar): string
{
    try {
        return calendar_feed_mask_url(calendar_feed_url($calendar));
    } catch (RuntimeException) {
        return '•••••••• (no se puede leer: revisa APP_KEY)';
    }
}

/* =============================================================================
 * 2. Asignar cada evento a una de mis asignaturas
 * ========================================================================== */

/**
 * Normaliza un texto para comparar: minúsculas, sin tildes, sin el curso
 * ("2º DAW"), sin años ("26/27", "2026-2027") y sin signos.
 *   "2º DAW - Desarrollo web en entorno servidor (26/27)" → "desarrollo web en entorno servidor"
 */
function calendar_feed_normalize(string $text): string
{
    $text = strtr(str_lower($text), [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c', 'º' => 'o', 'ª' => 'a', '°' => 'o',
    ]);
    $text = preg_replace('/\b[1-4]\s*o?\s*(?:daw|dam|asir|smr)\b/', ' ', $text) ?? $text;   // 2º DAW, 2 DAW
    $text = preg_replace('/\bcfg[sm]\b/', ' ', $text) ?? $text;                             // CFGS
    $text = preg_replace('/\b(?:19|20)\d{2}\b|\b\d{2}\s*[\/-]\s*\d{2}\b/', ' ', $text) ?? $text; // 2026, 26/27
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;

    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
}

/**
 * Datos de cada asignatura para compararla: [id => nombre normalizado, palabras y siglas].
 */
function calendar_feed_subject_keys(array $subjects): array
{
    $keys = [];
    foreach ($subjects as $subject) {
        $name   = calendar_feed_normalize($subject['nombre']);
        $abbrs  = [];
        $abbr   = subject_abbreviation($subject['descripcion'] ?? null);
        if ($abbr !== null) {
            $full    = calendar_feed_normalize($abbr);  // "ipe ii"
            $first   = explode(' ', $full)[0];          // "ipe"
            $abbrs   = [$full, $first, ...(CALENDAR_ABBR_ALIASES[$first] ?? [])];
        }
        $keys[(int) $subject['id']] = [
            'nombre'   => $name,
            'palabras' => array_values(array_diff(explode(' ', $name), CALENDAR_STOPWORDS, [''])),
            'siglas'   => array_values(array_unique($abbrs)),
        ];
    }

    return $keys;
}

/**
 * Asignatura que corresponde a un texto (curso de Moodle o título del evento).
 *  - Mejor: el nombre de la asignatura aparece en el texto (o todas sus palabras).
 *    Entre varias, gana la de nombre más largo ("Diseño de interfaces web" frente a "Web").
 *  - Si no: aparece su abreviatura como palabra suelta (DWES, DWEC, DAW…). En los
 *    títulos solo cuentan las de 3 letras o más ("IP" o "PW" salen en cualquier frase).
 * Si dos asignaturas empatan, no se elige ninguna. Devuelve el id o null.
 */
function calendar_feed_match_text(string $text, array $subjectKeys, bool $isTitle = false): ?int
{
    $norm = calendar_feed_normalize($text);
    if ($norm === '') {
        return null;
    }
    $padded = ' ' . $norm . ' ';
    $words  = explode(' ', $norm);

    $scores = [];
    foreach ($subjectKeys as $id => $key) {
        $byName = $key['nombre'] !== ''
            && (str_contains($padded, ' ' . $key['nombre'] . ' ') || ($key['palabras'] && !array_diff($key['palabras'], $words)));
        if ($byName) {
            $scores[$id] = 1000 + count($key['palabras']);
            continue;
        }
        foreach ($key['siglas'] as $abbr) {
            if ($isTitle && strlen(str_replace(' ', '', $abbr)) < 3) {
                continue;
            }
            if (str_contains($padded, ' ' . $abbr . ' ')) {
                $scores[$id] = 100 + strlen($abbr);
                break;
            }
        }
    }

    if ($scores === []) {
        return null;
    }
    arsort($scores);
    $best = array_slice($scores, 0, 2, true);
    $top  = array_values($best);

    return count($top) > 1 && $top[0] === $top[1] ? null : (int) array_key_first($best);
}

/**
 * Texto de origen de un evento: su primer CATEGORIES (en Moodle, el nombre del curso)
 * o, si no tiene, su título. Es lo que se guarda en tareas.texto_origen.
 */
function calendar_feed_origin_text(array $event): string
{
    return str_take($event['categorias'][0] ?? $event['titulo'], 255);
}

/**
 * Asignatura de un evento, en este orden:
 *  1. La del calendario, si tiene (lo normal en Classroom: un calendario por clase).
 *  2. Una correspondencia guardada por el usuario (curso o título → asignatura).
 *  3. CATEGORIES comparado con mis asignaturas; si el evento no tiene, el título.
 * Null si no hay coincidencia (la tarea queda "pendiente de asignar").
 */
function calendar_feed_match_event(array $event, ?int $calendarSubject, array $subjectKeys, array $mappings): ?int
{
    if ($calendarSubject !== null) {
        return $calendarSubject;
    }

    foreach ([...$event['categorias'], $event['titulo']] as $text) {
        $mapped = $mappings[calendar_feed_normalize($text)] ?? null;
        if ($mapped !== null) {
            return $mapped;
        }
    }

    if ($event['categorias'] !== []) {
        foreach ($event['categorias'] as $category) {
            $match = calendar_feed_match_text($category, $subjectKeys);
            if ($match !== null) {
                return $match;
            }
        }
        return null;
    }

    return calendar_feed_match_text($event['titulo'], $subjectKeys, true);
}

/**
 * ¿El evento es un examen? Si el calendario es de exámenes o el título lo dice
 * ("examen", "prueba", "control" o "test" como palabra; "control de versiones" no cuenta).
 */
function calendar_feed_is_exam(array $event, string $defaultType): bool
{
    if ($defaultType === 'examen') {
        return true;
    }
    $title = str_replace('control de versiones', '', calendar_feed_normalize($event['titulo']));

    return preg_match('/\b(?:examen|prueba|control|test)\b/', $title) === 1;
}

/* =============================================================================
 * 3. Correspondencias (texto de origen → asignatura) y "pendientes de asignar"
 * ========================================================================== */

/**
 * Correspondencias del usuario indexadas por texto normalizado: [texto => asignatura_id].
 */
function calendar_feed_mapping_index(int $userId): array
{
    $index = [];
    foreach (calendar_feed_mappings($userId) as $mapping) {
        $index[calendar_feed_normalize($mapping['texto_origen'])] = (int) $mapping['asignatura_id'];
    }

    return $index;
}

function calendar_feed_mappings(int $userId): array
{
    return db_all(
        'SELECT m.id, m.texto_origen, m.asignatura_id, a.nombre AS asignatura, a.color
           FROM correspondencias_externas m
           JOIN asignaturas a ON a.id = m.asignatura_id
          WHERE m.usuario_id = :uid
          ORDER BY m.texto_origen',
        ['uid' => $userId]
    );
}

function calendar_feed_mapping_delete(int $userId, int $id): bool
{
    return db_execute(
        'DELETE FROM correspondencias_externas WHERE id = :id AND usuario_id = :uid',
        ['id' => $id, 'uid' => $userId]
    ) > 0;
}

/**
 * Tareas importadas sin asignatura, agrupadas por su texto de origen:
 * [['texto' => '…', 'tareas' => [...]], …]
 */
function calendar_feed_pending(int $userId): array
{
    $rows = db_all(
        "SELECT t.id, t.titulo, t.fecha_limite, t.estado, COALESCE(t.texto_origen, t.titulo) AS texto,
                c.nombre AS calendario, c.origen
           FROM tareas t
           JOIN calendarios_externos c ON c.id = t.calendario_id AND c.usuario_id = t.usuario_id
          WHERE t.usuario_id = :uid
            AND t.asignatura_id IS NULL
          ORDER BY texto, t.fecha_limite IS NULL, t.fecha_limite",
        ['uid' => $userId]
    );

    $groups = [];
    foreach ($rows as $row) {
        $groups[$row['texto']] ??= ['texto' => $row['texto'], 'origen' => $row['origen'], 'calendario' => $row['calendario'], 'tareas' => []];
        $groups[$row['texto']]['tareas'][] = $row;
    }

    return array_values($groups);
}

/**
 * Asigna una asignatura a las tareas importadas sin asignatura de un texto de origen
 * y, si $remember, guarda la correspondencia para las próximas sincronizaciones.
 * Devuelve cuántas tareas se han actualizado.
 */
function calendar_feed_assign(int $userId, string $text, int $subjectId, bool $remember): int
{
    db()->beginTransaction();
    try {
        $updated = db_execute(
            'UPDATE tareas
                SET asignatura_id = :asignatura
              WHERE usuario_id = :uid
                AND asignatura_id IS NULL
                AND calendario_id IS NOT NULL
                AND COALESCE(texto_origen, titulo) = :texto',
            ['asignatura' => $subjectId, 'uid' => $userId, 'texto' => $text]
        );
        if ($remember) {
            db_execute(
                'INSERT INTO correspondencias_externas (usuario_id, texto_origen, asignatura_id)
                 VALUES (:uid, :texto, :asignatura)
                 ON DUPLICATE KEY UPDATE asignatura_id = VALUES(asignatura_id)',
                ['uid' => $userId, 'texto' => str_take($text, 255), 'asignatura' => $subjectId]
            );
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    return $updated;
}

/* =============================================================================
 * 4. Vista previa y sincronización
 * ========================================================================== */

/**
 * Fecha desde la que se importan eventos (hoy menos CALENDAR_PAST_DAYS).
 */
function calendar_feed_since(): string
{
    return (new DateTimeImmutable('today'))->modify('-' . CALENDAR_PAST_DAYS . ' days')->format('Y-m-d');
}

/**
 * ¿Se importa el evento? Fuera los cancelados y los de hace más de 30 días.
 */
function calendar_feed_importable(array $event, string $since): bool
{
    return $event['estado'] !== 'CANCELLED' && $event['inicio']['fecha'] >= $since;
}

/**
 * Vista previa antes de importar: cuántos eventos hay y los próximos, cada uno con
 * el tipo (tarea/examen) y la asignatura que se le asignaría.
 * $settings: asignatura_id ('' o id) y tipo_por_defecto.
 */
function calendar_feed_preview(int $userId, array $settings, array $parsed, int $limit = 12): array
{
    $since    = calendar_feed_since();
    $today    = today();
    $subjects = subject_match_list($userId);
    $byId     = array_column($subjects, null, 'id');
    $keys     = calendar_feed_subject_keys($subjects);
    $mappings = calendar_feed_mapping_index($userId);
    $calendarSubject = $settings['asignatura_id'] !== '' ? (int) $settings['asignatura_id'] : null;

    $preview = ['total' => count($parsed['eventos']), 'importables' => 0, 'ignorados' => 0, 'sin_asignatura' => 0, 'proximos' => []];
    $events  = [];
    foreach ($parsed['eventos'] as $event) {
        if (!calendar_feed_importable($event, $since)) {
            $preview['ignorados']++;
            continue;
        }
        $preview['importables']++;
        $subjectId = calendar_feed_match_event($event, $calendarSubject, $keys, $mappings);
        if ($subjectId === null) {
            $preview['sin_asignatura']++;
        }
        if ($event['inicio']['fecha'] >= $today) {
            $events[] = [
                'titulo'     => $event['titulo'],
                'fecha'      => $event['inicio']['fecha'],
                'hora'       => $event['inicio']['hora'],
                'origen'     => calendar_feed_origin_text($event),
                // Un examen sin asignatura se importa como tarea
                'tipo'       => $subjectId !== null && calendar_feed_is_exam($event, $settings['tipo_por_defecto']) ? 'examen' : 'tarea',
                'asignatura' => $subjectId !== null ? $byId[$subjectId] ?? null : null,
            ];
        }
    }

    usort($events, static fn (array $a, array $b): int => [$a['fecha'], (string) $a['hora']] <=> [$b['fecha'], (string) $b['hora']]);
    $preview['proximos'] = array_slice($events, 0, $limit);
    $preview['mas']      = max(0, count($events) - $limit);

    return $preview;
}

/**
 * Sincroniza un calendario: descarga su iCal (o usa $parsed si ya se tiene) y
 * crea o actualiza las tareas y exámenes, todo en una transacción.
 *  - Evento nuevo → tarea (o examen si el calendario es de exámenes o el título lo
 *    dice y tiene asignatura; examenes.asignatura_id es obligatorio).
 *  - Evento ya importado (mismo UID) → solo se actualizan título y fecha.
 *  - Nunca se borra nada aunque el evento desaparezca del calendario.
 * Guarda ultima_sincronizacion o ultimo_error y devuelve el resumen:
 *   ['creadas', 'actualizadas', 'ignoradas', 'sin_asignatura', 'sin_cambios'].
 * Lanza RuntimeException con un mensaje para el usuario si falla.
 */
function calendar_feed_sync(int $userId, array $calendar, ?array $parsed = null): array
{
    $summary = ['creadas' => 0, 'actualizadas' => 0, 'ignoradas' => 0, 'sin_asignatura' => 0, 'sin_cambios' => 0];
    $calendarId = (int) $calendar['id'];

    try {
        $parsed ??= ical_parse(ical_download(calendar_feed_url($calendar)));

        $since    = calendar_feed_since();
        $keys     = calendar_feed_subject_keys(subject_match_list($userId));
        $mappings = calendar_feed_mapping_index($userId);
        $calendarSubject = $calendar['asignatura_id'] !== null && subject_belongs_to($userId, (int) $calendar['asignatura_id'])
            ? (int) $calendar['asignatura_id'] : null;

        db()->beginTransaction();
        $seen = [];
        foreach ($parsed['eventos'] as $event) {
            // UID largo (no debería pasar): se resume para que quepa en la columna
            $uid = strlen($event['uid']) > 255 ? 'sha1:' . sha1($event['uid']) : $event['uid'];

            if (isset($seen[$uid]) || !calendar_feed_importable($event, $since)) {
                $summary['ignoradas']++;
                continue;
            }
            $seen[$uid] = true;

            $subjectId = calendar_feed_match_event($event, $calendarSubject, $keys, $mappings);
            $result    = calendar_feed_upsert_event($userId, $calendarId, $calendar['tipo_por_defecto'], $uid, $event, $subjectId);

            $summary[$result['resultado']]++;
            if (!$result['con_asignatura']) {
                $summary['sin_asignatura']++;
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        // Los mensajes de ical_*() y decrypt_secret() son para el usuario; los errores
        // de base de datos u otros inesperados no se enseñan tal cual (podrían mostrar SQL)
        $forUser = $e instanceof RuntimeException && !($e instanceof PDOException);
        $message = $forUser ? $e->getMessage() : 'Error inesperado al guardar los eventos. Inténtalo de nuevo.';
        if (!$forUser) {
            error_log('[StudyTrack] Sincronización del calendario ' . $calendarId . ': ' . $e);
        }
        calendar_feed_save_result($userId, $calendarId, $message);
        throw new RuntimeException($message, 0, $e);
    }

    calendar_feed_save_result($userId, $calendarId, null);

    return $summary;
}

/**
 * Crea o actualiza la tarea o el examen de un evento.
 * Devuelve ['resultado' => creadas|actualizadas|sin_cambios, 'con_asignatura' => bool].
 */
function calendar_feed_upsert_event(int $userId, int $calendarId, string $defaultType, string $uid, array $event, ?int $subjectId): array
{
    $title  = str_take($event['titulo'], 150);
    $date   = $event['inicio']['fecha'];
    $time   = $event['inicio']['hora'];
    $origin = calendar_feed_origin_text($event);
    $params = ['uid' => $userId, 'uid_externo' => $uid];

    // ¿Ya se importó como examen? Solo se actualizan el nombre, la fecha y la hora.
    // Si quedó sin calendario (se borró conservando lo importado), se vuelve a enlazar.
    $exam = db_one('SELECT id, nombre, fecha, hora, calendario_id FROM examenes WHERE usuario_id = :uid AND uid_externo = :uid_externo', $params);
    if ($exam !== null) {
        $newTime = $time !== null ? $time . ':00' : $exam['hora'];
        if ($exam['nombre'] === $title && $exam['fecha'] === $date && $exam['hora'] === $newTime && $exam['calendario_id'] !== null) {
            return ['resultado' => 'sin_cambios', 'con_asignatura' => true];
        }
        db_execute(
            'UPDATE examenes SET nombre = :nombre, fecha = :fecha, hora = :hora,
                    calendario_id = COALESCE(calendario_id, :cal)
              WHERE id = :id AND usuario_id = :uid',
            ['nombre' => $title, 'fecha' => $date, 'hora' => $newTime, 'cal' => $calendarId, 'id' => $exam['id'], 'uid' => $userId]
        );
        return ['resultado' => 'actualizadas', 'con_asignatura' => true];
    }

    // ¿Ya se importó como tarea? Título y fecha; la asignatura solo si no tenía
    $task = db_one('SELECT id, titulo, fecha_limite, asignatura_id, calendario_id FROM tareas WHERE usuario_id = :uid AND uid_externo = :uid_externo', $params);
    if ($task !== null) {
        $current    = $task['asignatura_id'] !== null ? (int) $task['asignatura_id'] : null;
        $newSubject = $current ?? $subjectId;
        if ($task['titulo'] === $title && $task['fecha_limite'] === $date && $current === $newSubject
            && $task['calendario_id'] !== null) {
            return ['resultado' => 'sin_cambios', 'con_asignatura' => $newSubject !== null];
        }
        db_execute(
            'UPDATE tareas SET titulo = :titulo, fecha_limite = :fecha, asignatura_id = :asignatura,
                    texto_origen = :texto, calendario_id = COALESCE(calendario_id, :cal)
              WHERE id = :id AND usuario_id = :uid',
            ['titulo' => $title, 'fecha' => $date, 'asignatura' => $newSubject, 'texto' => $origin,
             'cal' => $calendarId, 'id' => $task['id'], 'uid' => $userId]
        );
        return ['resultado' => 'actualizadas', 'con_asignatura' => $newSubject !== null];
    }

    // Evento nuevo: examen (solo con asignatura) o tarea
    $description = nullable(str_take($event['descripcion'], 2000));
    if ($subjectId !== null && calendar_feed_is_exam($event, $defaultType)) {
        db_execute(
            "INSERT INTO examenes (usuario_id, asignatura_id, calendario_id, uid_externo, nombre, fecha, hora, aula, descripcion, estado)
             VALUES (:uid, :asignatura, :cal, :uid_externo, :nombre, :fecha, :hora, :aula, :descripcion, 'pendiente')",
            $params + [
                'asignatura'  => $subjectId,
                'cal'         => $calendarId,
                'nombre'      => $title,
                'fecha'       => $date,
                'hora'        => $time,
                'aula'        => str_take($event['lugar'] !== '' ? $event['lugar'] : EXAM_DEFAULT_ROOM, 50),
                'descripcion' => $description,
            ]
        );
        return ['resultado' => 'creadas', 'con_asignatura' => true];
    }

    db_execute(
        "INSERT INTO tareas (usuario_id, asignatura_id, calendario_id, uid_externo, texto_origen, titulo, descripcion,
                             fecha_limite, prioridad, estado)
         VALUES (:uid, :asignatura, :cal, :uid_externo, :texto, :titulo, :descripcion, :fecha, 'media', 'pendiente')",
        $params + [
            'asignatura'  => $subjectId,
            'cal'         => $calendarId,
            'texto'       => $origin,
            'titulo'      => $title,
            'descripcion' => $description,
            'fecha'       => $date,
        ]
    );

    return ['resultado' => 'creadas', 'con_asignatura' => $subjectId !== null];
}

/**
 * Resumen de una sincronización en una frase: "3 creadas, 1 actualizada, 2 ignoradas…".
 */
function calendar_feed_summary_text(array $summary): string
{
    $parts = [
        plural($summary['creadas'], 'creada', 'creadas'),
        plural($summary['actualizadas'], 'actualizada', 'actualizadas'),
        plural($summary['ignoradas'], 'ignorada', 'ignoradas'),
        $summary['sin_asignatura'] . ' sin asignatura',
    ];
    $text = implode(', ', $parts);

    return $summary['sin_cambios'] > 0 ? $text . ' (' . $summary['sin_cambios'] . ' sin cambios)' : $text;
}
