<?php
/**
 * Estadísticas y gráficos.
 * Pendiente: se implementa en la fase 13.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_login();

render('placeholder', [
    'pageTitle' => 'Estadísticas',
    'icon'      => 'ri-bar-chart-box-line',
    'phase'     => 13,
]);
