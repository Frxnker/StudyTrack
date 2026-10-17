<?php
/**
 * Inicio de sesión.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

require_guest();

$errors = [];
$old = ['email' => ''];

if (is_post()) {
    verify_csrf();

    $email    = str_lower(input('email'));
    $password = (string) ($_POST['password'] ?? ''); // La contraseña no se recorta
    $old['email'] = $email;

    $errors = validate(
        ['email' => $email, 'password' => $password],
        [
            'email'    => 'required|email|max:150',
            'password' => 'required',
        ],
        ['email' => 'correo electrónico', 'password' => 'contraseña']
    );

    if (!$errors) {
        $user = attempt_login($email, $password);

        if ($user !== null) {
            login_user($user);
            flash('success', '¡Hola de nuevo, ' . first_name($user['nombre']) . '!');
            redirect(intended_url());
        }

        // Mensaje genérico: no revelamos si el email existe
        $errors['general'] = 'El correo o la contraseña no son correctos.';
    }

    http_response_code(422);
}

render('auth/login', [
    'pageTitle' => 'Iniciar sesión',
    'errors'    => $errors,
    'old'       => $old,
], 'auth');
