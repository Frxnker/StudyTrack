<?php
/**
 * Perfil y preferencias del usuario.
 * Pendiente: se implementa en la fase 15.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_login();

render('placeholder', [
    'pageTitle' => 'Perfil',
    'icon'      => 'ri-user-3-line',
    'phase'     => 15,
]);
