<?php
/**
 * Cabecera común (<head>, apertura del layout y mensajes flash).
 * Se incluye automáticamente desde render().
 *
 * Variables disponibles:
 * @var string      $layout       'app' o 'auth'
 * @var string|null $pageTitle    Título de la página
 * @var array|null  $currentUser  Usuario de la sesión (solo layout 'app')
 * @var array       $flashes      Mensajes flash pendientes
 */

$pageTitle = $pageTitle ?? APP_NAME;

// Tema elegido en el perfil (sistema | claro | oscuro); sin sesión, el del dispositivo
$theme = $currentUser['tema'] ?? 'sistema';
$toastIcons = [
    'success' => 'ri-checkbox-circle-fill',
    'error'   => 'ri-error-warning-fill',
    'warning' => 'ri-alert-fill',
    'info'    => 'ri-information-fill',
];
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= e($theme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="description" content="StudyTrack: organiza tus asignaturas, tareas, exámenes, notas y horario en un solo lugar.">
  <meta name="color-scheme" content="<?= $theme === 'claro' ? 'light' : ($theme === 'oscuro' ? 'dark' : 'light dark') ?>">
  <meta name="theme-color" content="#4f46e5">
  <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>

  <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">

  <!-- Tipografía Inter e iconos Remix Icon: copias locales, la app funciona sin conexión -->
  <link rel="stylesheet" href="<?= asset('assets/fonts/inter.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/vendor/remixicon/remixicon.css') ?>">

  <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
</head>
<body class="layout-<?= e($layout) ?>">
<a class="skip-link" href="#main">Saltar al contenido</a>

<div class="toasts" id="toasts" aria-live="polite">
  <?php foreach ($flashes as $flash): ?>
    <div class="toast toast--<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
      <i class="<?= e($toastIcons[$flash['type']] ?? $toastIcons['info']) ?>" aria-hidden="true"></i>
      <span class="toast__message"><?= e($flash['message']) ?></span>
      <button type="button" class="icon-btn icon-btn--sm toast__close" aria-label="Cerrar notificación">
        <i class="ri-close-line" aria-hidden="true"></i>
      </button>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($layout === 'app'): ?>
<div class="app">
  <?php require __DIR__ . '/navbar.php'; ?>

  <div class="app__main">
    <header class="topbar">
      <button type="button" class="icon-btn topbar__menu" data-sidebar-open
              aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menú">
        <i class="ri-menu-2-line" aria-hidden="true"></i>
      </button>
      <h1 class="topbar__title"><?= e($pageTitle) ?></h1>
      <div class="topbar__actions">
        <a href="profile.php" class="avatar avatar--sm" title="Mi perfil" aria-label="Mi perfil">
          <?= e(initials($currentUser['nombre'] ?? '')) ?>
        </a>
      </div>
    </header>

    <main class="content" id="main">
<?php else: ?>
<main class="auth" id="main">
  <section class="auth__hero">
    <a href="index.php" class="brand brand--light">
      <span class="brand__mark"><i class="ri-graduation-cap-fill" aria-hidden="true"></i></span>
      <span><?= e(APP_NAME) ?></span>
    </a>
    <div class="auth__hero-body">
      <h1 class="auth__title">Tu curso, bajo control.</h1>
      <p class="auth__subtitle">Tareas, exámenes, notas y horario en una sola app pensada para estudiantes.</p>
      <ul class="auth__features">
        <li><i class="ri-checkbox-circle-line" aria-hidden="true"></i> Organiza tus tareas por asignatura y prioridad</li>
        <li><i class="ri-calendar-check-line" aria-hidden="true"></i> No vuelvas a olvidarte de un examen</li>
        <li><i class="ri-line-chart-line" aria-hidden="true"></i> Sigue tu media y tu progreso semanal</li>
      </ul>
    </div>
  </section>

  <section class="auth__panel">
<?php endif; ?>
