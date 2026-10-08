<?php
/**
 * Asignaturas: listado y CRUD.
 * Pendiente: se implementa en la fase 7.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_login();

render('placeholder', [
    'pageTitle' => 'Asignaturas',
    'icon'      => 'ri-book-2-line',
    'phase'     => 7,
]);
