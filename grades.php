<?php
/**
 * Notas: calificaciones y medias por asignatura.
 * Pendiente: se implementa en la fase 10.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_login();

render('placeholder', [
    'pageTitle' => 'Notas',
    'icon'      => 'ri-award-line',
    'phase'     => 10,
]);
