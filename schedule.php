<?php
/**
 * Horario semanal.
 * Pendiente: se implementa en la fase 11.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_login();

render('placeholder', [
    'pageTitle' => 'Horario',
    'icon'      => 'ri-time-line',
    'phase'     => 11,
]);
