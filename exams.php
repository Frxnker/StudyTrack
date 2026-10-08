<?php
/**
 * Exámenes: próximos exámenes y cuenta atrás.
 * Pendiente: se implementa en la fase 9.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_login();

render('placeholder', [
    'pageTitle' => 'Exámenes',
    'icon'      => 'ri-file-list-3-line',
    'phase'     => 9,
]);
