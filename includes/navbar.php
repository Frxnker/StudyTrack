<?php
/**
 * Navegación principal.
 *  - Sidebar: menú lateral fijo en escritorio y menú deslizante en móvil.
 *  - Bottom nav: barra inferior fija (móvil) con las 4 secciones principales y una
 *    quinta pestaña que abre el menú con el resto. En una de esas otras secciones,
 *    la quinta pestaña muestra su icono y su nombre y queda marcada como activa.
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

$bottomNav = ['dashboard', 'tasks', 'calendar', 'statistics'];

$activePage = current_page();
$navItems   = array_merge(...array_values($navSections));

// Quinta pestaña: "Más" o, si estamos en una sección que no está en la barra, esa sección
$inMenuOnly = isset($navItems[$activePage]) && !in_array($activePage, $bottomNav, true);
[$moreLabel, $moreIcon] = $inMenuOnly ? $navItems[$activePage] : ['Más', 'ri-apps-2'];
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
    <?php $isActive = $page === $activePage; ?>
    <a href="<?= e($page) ?>.php"
       class="bottom-nav__item<?= $isActive ? ' is-active' : '' ?>"
       <?= $isActive ? 'aria-current="page"' : '' ?>>
      <i class="<?= e($icon . ($isActive ? '-fill' : '-line')) ?>" aria-hidden="true"></i>
      <span><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
  <button type="button" class="bottom-nav__item<?= $inMenuOnly ? ' is-active' : '' ?>"
          data-sidebar-open aria-controls="sidebar" aria-expanded="false"
          <?= $inMenuOnly ? 'aria-current="page" aria-label="' . e($moreLabel) . ': abrir el menú"' : 'aria-label="Más secciones: abrir el menú"' ?>>
    <i class="<?= e($moreIcon . ($inMenuOnly ? '-fill' : '-line')) ?>" aria-hidden="true"></i>
    <span><?= e($moreLabel) ?></span>
  </button>
</nav>
