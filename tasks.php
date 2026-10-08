<?php
/**
 * Tareas: CRUD completo con filtros y búsqueda.
 * Pendiente: se implementa en la fase 8.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_login();

render('placeholder', [
    'pageTitle' => 'Tareas',
    'icon'      => 'ri-checkbox-circle-line',
    'phase'     => 8,
]);
