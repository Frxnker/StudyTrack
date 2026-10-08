<?php
/**
 * Objetivos de estudio con barra de progreso.
 * Pendiente: se implementa en la fase 12.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_login();

render('placeholder', [
    'pageTitle' => 'Objetivos',
    'icon'      => 'ri-focus-3-line',
    'phase'     => 12,
]);
