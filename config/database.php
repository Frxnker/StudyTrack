<?php
/**
 * Conexión a la base de datos mediante PDO + funciones de acceso a datos.
 *
 * Los valores por defecto funcionan con XAMPP y Laragon (usuario root sin
 * contraseña). Si tu instalación usa otras credenciales tienes dos opciones:
 *
 *  1. Crear config/database.local.php (ignorado por Git) que devuelva un array
 *     con las claves que quieras cambiar, por ejemplo:
 *
 *         <?php
 *         return ['user' => 'studytrack', 'password' => 'mi-contraseña'];
 *
 *  2. Definir variables de entorno: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS.
 */

declare(strict_types=1);

/**
 * Devuelve la configuración de la conexión.
 */
function db_config(): array
{
    $config = [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'name'     => getenv('DB_NAME') ?: 'studytrack',
        'user'     => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
    ];

    $localFile = __DIR__ . '/database.local.php';
    if (is_file($localFile)) {
        $config = array_merge($config, (array) require $localFile);
    }

    return $config;
}

/**
 * Devuelve la conexión PDO (se crea una sola vez por petición: patrón singleton).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Los errores lanzan excepciones
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Filas como arrays asociativos
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Consultas preparadas reales
    ];

    try {
        $pdo = new PDO($dsn, $config['user'], $config['password'], $options);
    } catch (PDOException $e) {
        // Nunca mostramos las credenciales: registramos el error y enseñamos un aviso
        error_log('[StudyTrack] Error de conexión a la base de datos: ' . $e->getMessage());

        $message = 'Comprueba que MySQL/MariaDB está iniciado en XAMPP o Laragon, '
                 . 'que has importado sql/database.sql y que las credenciales de '
                 . 'config/database.php son correctas.';

        if (function_exists('render_error_page')) {
            render_error_page(503, 'No se puede conectar con la base de datos', $message, APP_DEBUG ? $e : null);
        } else {
            http_response_code(503);
            echo 'No se puede conectar con la base de datos. ' . $message;
        }
        exit;
    }

    // Misma zona horaria en MySQL que en PHP, para que NOW() y CURDATE() coincidan
    $pdo->exec("SET time_zone = '" . date('P') . "'");

    return $pdo;
}

/* -----------------------------------------------------------------------------
 * Funciones auxiliares. TODAS usan consultas preparadas: los valores viajan
 * separados del SQL, por lo que no es posible la inyección SQL.
 *
 * Ejemplo:
 *   $tarea = db_one('SELECT * FROM tareas WHERE id = :id AND usuario_id = :uid',
 *                   ['id' => $id, 'uid' => $userId]);
 * -------------------------------------------------------------------------- */

/**
 * Prepara y ejecuta una consulta. Devuelve el PDOStatement.
 */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

/**
 * Devuelve la primera fila del resultado o null si no hay ninguna.
 */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();

    return $row === false ? null : $row;
}

/**
 * Devuelve todas las filas del resultado.
 */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/**
 * Devuelve el valor de la primera columna de la primera fila (COUNT, AVG…).
 */
function db_value(string $sql, array $params = []): mixed
{
    $value = db_run($sql, $params)->fetchColumn();

    return $value === false ? null : $value;
}

/**
 * Ejecuta INSERT/UPDATE/DELETE y devuelve el número de filas afectadas.
 */
function db_execute(string $sql, array $params = []): int
{
    return db_run($sql, $params)->rowCount();
}

/**
 * Devuelve el id generado por el último INSERT.
 */
function db_last_id(): int
{
    return (int) db()->lastInsertId();
}
