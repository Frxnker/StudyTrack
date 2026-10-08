<?php
/**
 * Registro de nuevos usuarios.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_guest();

$errors = [];
$old = ['nombre' => '', 'email' => ''];

if (is_post()) {
    verify_csrf();

    $data = [
        'nombre'           => input('nombre'),
        'email'            => str_lower(input('email')),
        'password'         => (string) ($_POST['password'] ?? ''),
        'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
    ];
    $old = ['nombre' => $data['nombre'], 'email' => $data['email']];

    $errors = validate(
        $data,
        [
            'nombre'           => 'required|min:2|max:100',
            'email'            => 'required|email|max:150',
            'password'         => 'required|min:8|max:72|password',
            'password_confirm' => 'required|same:password',
        ],
        [
            'nombre'           => 'nombre',
            'email'            => 'correo electrónico',
            'password'         => 'contraseña',
            'password_confirm' => 'repetir contraseña',
        ],
        ['password_confirm.same' => 'Las contraseñas no coinciden.']
    );

    // bcrypt solo usa los primeros 72 bytes (una letra con tilde ocupa 2)
    if (!isset($errors['password']) && strlen($data['password']) > 72) {
        $errors['password'] = 'La contraseña es demasiado larga.';
    }

    if (!isset($errors['email']) && user_email_exists($data['email'])) {
        $errors['email'] = 'Ya existe una cuenta con ese correo electrónico.';
    }

    if (!$errors) {
        try {
            $userId = user_create($data['nombre'], $data['email'], $data['password']);
        } catch (PDOException $e) {
            // 23000: clave duplicada (dos registros simultáneos con el mismo email)
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $userId = 0;
            $errors['email'] = 'Ya existe una cuenta con ese correo electrónico.';
        }

        if ($userId > 0) {
            login_user(['id' => $userId]);
            flash('success', '¡Cuenta creada! Bienvenido/a a StudyTrack, ' . first_name($data['nombre']) . '.');
            redirect('dashboard.php');
        }
    }

    http_response_code(422);
}

render('auth/register', [
    'pageTitle' => 'Crear cuenta',
    'errors'    => $errors,
    'old'       => $old,
], 'auth');
