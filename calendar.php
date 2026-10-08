<?php
/**
 * Calendario mensual con tareas y exámenes.
 * Pendiente: se implementa en la fase 11.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_login();

render('placeholder', [
    'pageTitle' => 'Calendario',
    'icon'      => 'ri-calendar-2-line',
    'phase'     => 11,
]);
