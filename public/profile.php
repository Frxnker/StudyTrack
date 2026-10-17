<?php
/**
 * Perfil del usuario.
 *
 *   POST profile.php  action=profile   → nombre, email (único), curso y centro
 *   POST profile.php  action=theme     → tema: sistema, claro u oscuro (se aplica en toda la app)
 *   POST profile.php  action=password  → cambiar contraseña (pide la actual)
 *   POST profile.php  action=delete    → eliminar la cuenta (pide la contraseña y confirmación)
 *
 * Cada formulario tiene sus propios errores: $errors['profile'], $errors['password']…
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$userId = current_user_id();
$user   = current_user();
$errors = ['profile' => [], 'password' => [], 'delete' => []];
$old    = [
    'nombre' => $user['nombre'],
    'email'  => $user['email'],
    'curso'  => (string) $user['curso'],
    'centro' => (string) $user['centro'],
];

if (is_post()) {
    verify_csrf();

    switch (input('action')) {
        // --- Datos personales -----------------------------------------------
        case 'profile':
            $old = [
                'nombre' => input('nombre'),
                'email'  => str_lower(input('email')),
                'curso'  => input('curso'),
                'centro' => input('centro'),
            ];
            $errors['profile'] = validate(
                $old,
                [
                    'nombre' => 'required|min:2|max:100',
                    'email'  => 'required|email|max:150',
                    'curso'  => 'max:100',
                    'centro' => 'max:150',
                ],
                ['nombre' => 'nombre', 'email' => 'correo electrónico', 'curso' => 'curso', 'centro' => 'centro']
            );

            if (!isset($errors['profile']['email']) && user_email_exists($old['email'], $userId)) {
                $errors['profile']['email'] = 'Ya existe otra cuenta con ese correo electrónico.';
            }

            if (!$errors['profile']) {
                try {
                    user_update_profile($userId, $old);
                    flash('success', 'Tus datos se han guardado.');
                    redirect('profile.php');
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') {
                        throw $e;
                    }
                    $errors['profile']['email'] = 'Ya existe otra cuenta con ese correo electrónico.';
                }
            }
            break;

        // --- Tema ---------------------------------------------------------------
        case 'theme':
            $theme = input('tema');
            if (isset(USER_THEMES[$theme])) {
                user_update_theme($userId, $theme);
                flash('success', 'Tema cambiado a «' . str_lower(USER_THEMES[$theme]) . '».');
            }
            redirect('profile.php#apariencia');

        // --- Contraseña ---------------------------------------------------------
        case 'password':
            $passwords = [
                'password_actual'  => (string) ($_POST['password_actual'] ?? ''), // Las contraseñas no se recortan
                'password'         => (string) ($_POST['password'] ?? ''),
                'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
            ];
            $errors['password'] = validate(
                $passwords,
                [
                    'password_actual'  => 'required',
                    'password'         => 'required|min:8|max:72|password',
                    'password_confirm' => 'required|same:password',
                ],
                [
                    'password_actual'  => 'contraseña actual',
                    'password'         => 'nueva contraseña',
                    'password_confirm' => 'repetir contraseña',
                ],
                ['password_confirm.same' => 'Las contraseñas no coinciden.']
            );

            // bcrypt solo usa los primeros 72 bytes (una letra con tilde ocupa 2)
            if (!isset($errors['password']['password']) && strlen($passwords['password']) > 72) {
                $errors['password']['password'] = 'La contraseña es demasiado larga.';
            }

            if (!isset($errors['password']['password_actual'])
                && !user_check_password($userId, $passwords['password_actual'])) {
                $errors['password']['password_actual'] = 'La contraseña actual no es correcta.';
            } elseif (!isset($errors['password']['password'])
                && hash_equals($passwords['password_actual'], $passwords['password'])) {
                $errors['password']['password'] = 'La nueva contraseña debe ser distinta de la actual.';
            }

            if (!$errors['password']) {
                user_update_password($userId, $passwords['password']);
                session_regenerate_id(true); // Nuevo id de sesión tras cambiar la contraseña
                flash('success', 'Contraseña actualizada.');
                redirect('profile.php');
            }
            break;

        // --- Eliminar la cuenta ---------------------------------------------------
        case 'delete':
            $password = (string) ($_POST['password_borrar'] ?? '');

            if ($password === '') {
                $errors['delete']['password_borrar'] = 'Escribe tu contraseña para confirmar.';
            } elseif (!user_check_password($userId, $password)) {
                $errors['delete']['password_borrar'] = 'La contraseña no es correcta.';
            } else {
                user_delete($userId);
                logout_user();

                // Nueva sesión vacía solo para mostrar el mensaje de despedida
                start_secure_session();
                session_regenerate_id(true);
                flash('success', 'Tu cuenta y todos tus datos se han eliminado. ¡Hasta pronto!');
                redirect('login.php');
            }
            break;

        default:
            abort(405);
    }

    http_response_code(422);
}

render('profile/index', [
    'pageTitle' => 'Mi perfil',
    'user'      => $user,
    'old'       => $old,
    'errors'    => $errors,
]);
