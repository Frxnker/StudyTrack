<?php
/**
 * Cierre de sesión.
 * Solo acepta POST con token CSRF: así una web externa no puede cerrar
 * la sesión del usuario con un simple enlace o imagen.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (!is_post()) {
    redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
}

verify_csrf();

logout_user();

// Nueva sesión vacía solo para mostrar el mensaje de despedida
start_secure_session();
session_regenerate_id(true);
flash('success', 'Has cerrado sesión correctamente. ¡Hasta pronto!');

redirect('login.php');
