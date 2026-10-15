<?php
/**
 * Modelo: usuarios.
 * Todas las consultas a la tabla `usuarios` están en este archivo.
 */

declare(strict_types=1);

const USER_THEMES = ['sistema' => 'Sistema', 'claro' => 'Claro', 'oscuro' => 'Oscuro'];

/**
 * Busca un usuario por id (sin el hash de la contraseña).
 */
function user_find(int $id): ?array
{
    return db_one(
        'SELECT id, nombre, email, curso, centro, tema, fecha_registro, ultimo_acceso
           FROM usuarios
          WHERE id = :id',
        ['id' => $id]
    );
}

/**
 * Busca un usuario por email (incluye el hash: solo para el login).
 */
function user_find_by_email(string $email): ?array
{
    return db_one(
        'SELECT id, nombre, email, password_hash
           FROM usuarios
          WHERE email = :email',
        ['email' => $email]
    );
}

/**
 * ¿Existe ya una cuenta con este email? ($exceptId permite excluir al propio usuario)
 */
function user_email_exists(string $email, int $exceptId = 0): bool
{
    return (bool) db_value(
        'SELECT COUNT(*) FROM usuarios WHERE email = :email AND id <> :id',
        ['email' => $email, 'id' => $exceptId]
    );
}

/**
 * Crea un usuario. La contraseña se guarda SIEMPRE cifrada con password_hash().
 */
function user_create(string $name, string $email, string $password): int
{
    db_execute(
        'INSERT INTO usuarios (nombre, email, password_hash)
         VALUES (:nombre, :email, :hash)',
        [
            'nombre' => $name,
            'email'  => $email,
            'hash'   => password_hash($password, PASSWORD_DEFAULT),
        ]
    );

    return db_last_id();
}

function user_update_password(int $id, string $password): void
{
    db_execute(
        'UPDATE usuarios SET password_hash = :hash WHERE id = :id',
        ['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]
    );
}

function user_touch_last_access(int $id): void
{
    db_execute('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id', ['id' => $id]);
}

/**
 * ¿La contraseña es la del usuario? (para cambiarla o borrar la cuenta)
 */
function user_check_password(int $id, string $password): bool
{
    $hash = db_value('SELECT password_hash FROM usuarios WHERE id = :id', ['id' => $id]);

    return is_string($hash) && password_verify($password, $hash);
}

/**
 * Actualiza los datos personales. $data: nombre, email, curso, centro.
 */
function user_update_profile(int $id, array $data): void
{
    db_execute(
        'UPDATE usuarios SET nombre = :nombre, email = :email, curso = :curso, centro = :centro WHERE id = :id',
        [
            'nombre' => $data['nombre'],
            'email'  => $data['email'],
            'curso'  => nullable($data['curso']),
            'centro' => nullable($data['centro']),
            'id'     => $id,
        ]
    );
}

/**
 * Cambia el tema de la interfaz: sistema, claro u oscuro.
 */
function user_update_theme(int $id, string $theme): void
{
    db_execute('UPDATE usuarios SET tema = :tema WHERE id = :id', ['tema' => $theme, 'id' => $id]);
}

/**
 * Borra la cuenta. Las claves foráneas (ON DELETE CASCADE) borran también
 * sus asignaturas, tareas, exámenes, notas, horario y objetivos.
 */
function user_delete(int $id): void
{
    db_execute('DELETE FROM usuarios WHERE id = :id', ['id' => $id]);
}
