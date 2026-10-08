<?php
/**
 * Modelo: usuarios.
 * Todas las consultas a la tabla `usuarios` están en este archivo.
 */

declare(strict_types=1);

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
