<?php
/**
 * Navegación principal.
 *  - Sidebar: menú lateral fijo en escritorio y menú deslizante en móvil.
 *  - Bottom nav: barra inferior fija con las 5 secciones principales (móvil).
 *
 * @var array|null $currentUser
 */

// página => [texto, icono base de Remix Icon (se añade -line / -fill)]
$navSections = [
    'General' => [
        'dashboard'  => ['Inicio', 'ri-home-5'],
        'tasks'      => ['Tareas', 'ri-checkbox-circle'],
        'calendar'   => ['Calendario', 'ri-calendar-2'],
        'statistics' => ['Estadísticas', 'ri-bar-chart-box'],
    ],
    'Mis estudios' => [
        'subjects' => ['Asignaturas', 'ri-book-2'],
        'exams'    => ['Exámenes', 'ri-file-list-3'],
        'grades'   => ['Notas', 'ri-award'],
        'schedule' => ['Horario', 'ri-time'],
        'goals'    => ['Objetivos', 'ri-focus-3'],
    ],
    'Cuenta' => [
        'profile' => ['Perfil', 'ri-user-3'],
    ],
];

$bottomNav = ['dashboard', 'tasks', 'calendar', 'statistics', 'profile'];

// Secciones sin pestaña propia en la barra inferior: se marca la pestaña "padre"
// (todas las secciones dejan así alguna pestaña activa)
$bottomNavParent = [
    'subjects' => 'dashboard',   // el inicio resume las asignaturas
    'exams'    => 'calendar',
    'schedule' => 'calendar',
    'grades'   => 'statistics',
    'goals'    => 'statistics',  // el progreso de los objetivos está en estadísticas
];

$activePage = current_page();
$activeTab  = $bottomNavParent[$activePage] ?? $activePage;

$navItems = array_merge(...array_values($navSections));
?>
<aside class="sidebar" id="sidebar" aria-label="Menú principal">
  <div class="sidebar__header">
    <a href="dashboard.php" class="brand">
      <span class="brand__mark"><i class="ri-graduation-cap-fill" aria-hidden="true"></i></span>
      <span><?= e(APP_NAME) ?></span>
    </a>
    <button type="button" class="icon-btn sidebar__close" data-sidebar-close aria-label="Cerrar menú">
      <i class="ri-close-line" aria-hidden="true"></i>
    </button>
  </div>

  <nav class="sidebar__nav">
    <?php foreach ($navSections as $sectionName => $items): ?>
      <p class="sidebar__section"><?= e($sectionName) ?></p>
      <?php foreach ($items as $page => [$label, $icon]): ?>
        <?php $isActive = $page === $activePage; ?>
        <a href="<?= e($page) ?>.php"
           class="sidebar__link<?= $isActive ? ' is-active' : '' ?>"
           <?= $isActive ? 'aria-current="page"' : '' ?>>
          <i class="<?= e($icon . ($isActive ? '-fill' : '-line')) ?>" aria-hidden="true"></i>
          <span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar__footer">
    <a href="profile.php" class="sidebar__user">
      <span class="avatar avatar--sm"><?= e(initials($currentUser['nombre'] ?? '')) ?></span>
      <span class="sidebar__user-info">
        <span class="sidebar__user-name"><?= e($currentUser['nombre'] ?? '') ?></span>
        <span class="sidebar__user-email"><?= e($currentUser['email'] ?? '') ?></span>
      </span>
    </a>
    <form method="post" action="logout.php">
      <?= csrf_field() ?>
      <button type="submit" class="sidebar__link sidebar__logout">
        <i class="ri-logout-box-r-line" aria-hidden="true"></i>
        <span>Cerrar sesión</span>
      </button>
    </form>
  </div>
</aside>

<div class="sidebar-backdrop" data-sidebar-close></div>

<nav class="bottom-nav" aria-label="Navegación inferior">
  <?php foreach ($bottomNav as $page): ?>
    <?php [$label, $icon] = $navItems[$page]; ?>
    <?php $isActive = $page === $activeTab; ?>
    <a href="<?= e($page) ?>.php"
       class="bottom-nav__item<?= $isActive ? ' is-active' : '' ?>"
       <?= $page === $activePage ? 'aria-current="page"' : ($isActive ? 'aria-current="true"' : '') ?>>
      <i class="<?= e($icon . ($isActive ? '-fill' : '-line')) ?>" aria-hidden="true"></i>
      <span><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
</nav>
