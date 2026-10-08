<?php
/**
 * Dashboard: resumen del estudiante.
 * Versión provisional: el resumen completo se implementa en la fase 6.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_login();

render('dashboard', [
    'pageTitle' => 'Inicio',
]);
