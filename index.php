<?php
/**
 * Punto de entrada: dashboard si hay sesión iniciada, login si no.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
