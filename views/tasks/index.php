<?php
/**
 * Vista: listado de tareas con pestañas, filtros y acciones rápidas.
 *
 * @var array  $tasks
 * @var array  $counts      total, completadas, pendientes, atrasadas, hoy
 * @var array  $viewCounts  [vista => nº de tareas]
 * @var array  $subjects    id, nombre, color
 * @var array  $filters     Filtros activos (vista, estado, asignatura, prioridad, q)
 * @var string $currentView  Vista rápida activa (todas, hoy, semana, atrasadas)
 * @var string $today
 */

$priorityBadge = ['alta' => 'danger', 'media' => 'warning', 'baja' => 'info'];
$listFilters   = array_diff_key($filters, ['vista' => true]);
$return        = http_build_query($filters);
$activeFilters = count($listFilters);
?>
<header class="page-header">
  <div>
    <h2 class="page-header__title">Mis tareas</h2>
    <p class="page-header__subtitle">
      <?php if ($counts['total'] > 0): ?>
        <?= e(plural($counts['pendientes'], 'tarea sin completar', 'tareas sin completar')) ?>
        <?php if ($counts['atrasadas'] > 0): ?>
          · <span class="text-danger fw-600"><?= e(plural($counts['atrasadas'], 'atrasada', 'atrasadas')) ?></span>
        <?php endif; ?>
      <?php else: ?>
        Apunta tus deberes y entregas para no olvidar nada.
      <?php endif; ?>
    </p>
  </div>
  <div class="page-header__actions hide-mobile">
    <a href="<?= e(task_list_url(['action' => 'new'] + $filters)) ?>" class="btn btn--primary">
      <i class="ri-add-line" aria-hidden="true"></i> Nueva tarea
    </a>
  </div>
</header>

<nav class="tabs" aria-label="Vistas de tareas">
  <?php foreach (TASK_VIEWS as $key => $label): ?>
    <?php $isActive = $key === $currentView; ?>
    <a href="<?= e(task_list_url(($key !== 'todas' ? ['vista' => $key] : []) + $listFilters)) ?>"
       class="tabs__item<?= $isActive ? ' is-active' : '' ?><?= $key === 'atrasadas' && $viewCounts[$key] > 0 ? ' tabs__item--danger' : '' ?>"
       <?= $isActive ? 'aria-current="page"' : '' ?>>
      <?php if ($key === 'semana'): ?>
        <?php // En móvil, "7 días": así caben las cuatro pestañas y se ve la de atrasadas ?>
        <span><span class="hide-mobile">Próximos </span>7 días</span>
      <?php else: ?>
        <?= e($label) ?>
      <?php endif; ?>
      <span class="tabs__count"><?= $viewCounts[$key] ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<details class="card filters" data-filters<?= $activeFilters ? ' open' : '' ?>>
  <summary class="filters__summary">
    <i class="ri-filter-3-line" aria-hidden="true"></i> Filtros
    <?php if ($activeFilters): ?>
      <span class="badge badge--primary"><?= $activeFilters ?> <span class="sr-only">activos</span></span>
    <?php endif; ?>
    <i class="ri-arrow-down-s-line filters__chevron" aria-hidden="true"></i>
  </summary>

  <form method="get" action="tasks.php" class="filters__form" role="search">
    <?php if ($currentView !== 'todas'): ?>
      <input type="hidden" name="vista" value="<?= e($currentView) ?>">
    <?php endif; ?>

    <div class="field filters__search">
      <label for="f-q" class="field__label">Buscar</label>
      <div class="input-icon">
        <i class="ri-search-line" aria-hidden="true"></i>
        <input type="search" id="f-q" name="q" class="input" value="<?= e($filters['q'] ?? '') ?>"
               placeholder="Título o descripción" maxlength="100">
      </div>
    </div>

    <div class="field">
      <label for="f-estado" class="field__label">Estado</label>
      <select id="f-estado" name="estado" class="select" data-autosubmit>
        <option value="">Todos</option>
        <option value="abiertas"<?= ($filters['estado'] ?? '') === 'abiertas' ? ' selected' : '' ?>>Sin completar</option>
        <?php foreach (TASK_STATUSES as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= ($filters['estado'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="f-asignatura" class="field__label">Asignatura</label>
      <select id="f-asignatura" name="asignatura" class="select" data-autosubmit>
        <option value="">Todas</option>
        <?php foreach ($subjects as $subject): ?>
          <option value="<?= (int) $subject['id'] ?>"<?= ($filters['asignatura'] ?? '') === (int) $subject['id'] ? ' selected' : '' ?>>
            <?= e($subject['nombre']) ?>
          </option>
        <?php endforeach; ?>
        <option value="ninguna"<?= ($filters['asignatura'] ?? '') === 'ninguna' ? ' selected' : '' ?>>Sin asignatura</option>
      </select>
    </div>

    <div class="field">
      <label for="f-prioridad" class="field__label">Prioridad</label>
      <select id="f-prioridad" name="prioridad" class="select" data-autosubmit>
        <option value="">Todas</option>
        <?php foreach (TASK_PRIORITIES as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= ($filters['prioridad'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filters__actions">
      <button type="submit" class="btn btn--secondary">
        <i class="ri-search-line" aria-hidden="true"></i> Aplicar
      </button>
      <?php if ($activeFilters): ?>
        <a href="<?= e(task_list_url($currentView !== 'todas' ? ['vista' => $currentView] : [])) ?>" class="btn btn--ghost">Limpiar</a>
      <?php endif; ?>
    </div>
  </form>
</details>

<section class="card card--flush" aria-label="Listado de tareas">
  <?php if ($tasks): ?>
    <ul class="task-list">
      <?php foreach ($tasks as $task): ?>
        <?php
          $isDone    = $task['estado'] === 'completada';
          $due       = $task['fecha_limite'];
          $isOverdue = !$isDone && $due !== null && $due < $today;
          $isToday   = !$isDone && $due === $today;
          $editUrl   = task_list_url(['action' => 'edit', 'id' => (int) $task['id']] + $filters);
        ?>
        <li class="task<?= $isOverdue ? ' is-overdue' : '' ?><?= $isToday ? ' is-today' : '' ?><?= $isDone ? ' is-done' : '' ?>">
          <form method="post" action="tasks.php" class="inline-form" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
            <input type="hidden" name="return" value="<?= e($return) ?>">
            <button type="submit" class="task__check"
                    aria-label="<?= e(($isDone ? 'Marcar como pendiente: ' : 'Completar: ') . $task['titulo']) ?>"
                    title="<?= $isDone ? 'Marcar como pendiente' : 'Marcar como completada' ?>">
              <i class="ri-check-line" aria-hidden="true"></i>
            </button>
          </form>

          <div class="task__body">
            <a href="<?= e($editUrl) ?>" class="task__title"><?= e($task['titulo']) ?></a>
            <?php if ($task['descripcion']): ?>
              <p class="task__desc"><?= e(truncate($task['descripcion'], 120)) ?></p>
            <?php endif; ?>

            <div class="task__meta">
              <?php if ($task['asignatura'] !== null): ?>
                <span class="chip" style="--subject: <?= e(safe_color($task['color'])) ?>">
                  <span class="dot" aria-hidden="true"></span><?= e($task['asignatura']) ?>
                </span>
              <?php endif; ?>

              <?php if ($isDone && $task['fecha_completada']): ?>
                <span class="task__date">
                  <i class="ri-checkbox-circle-line" aria-hidden="true"></i>
                  Completada el <?= e(format_date($task['fecha_completada'])) ?>
                </span>
              <?php elseif ($due !== null): ?>
                <span class="task__date<?= $isOverdue ? ' is-late' : ($isToday ? ' is-today' : '') ?>"
                      title="<?= e(ucfirst(format_long_date($due, true))) ?>">
                  <i class="<?= $isOverdue ? 'ri-alarm-warning-line' : 'ri-calendar-line' ?>" aria-hidden="true"></i>
                  <?= $isOverdue ? 'Venció ' . e(str_lower(relative_day($due))) : e(relative_day($due)) ?>
                  <span class="text-soft">· <?= e(format_date($due, 'd/m')) ?></span>
                </span>
              <?php else: ?>
                <span class="task__date text-soft"><i class="ri-calendar-line" aria-hidden="true"></i> Sin fecha</span>
              <?php endif; ?>

              <?php if ($task['estado'] === 'en_progreso'): ?>
                <span class="badge badge--info"><i class="ri-loader-4-line" aria-hidden="true"></i> En progreso</span>
              <?php endif; ?>
              <?php if (!$isDone): ?>
                <span class="badge badge--<?= $priorityBadge[$task['prioridad']] ?>">
                  <span class="sr-only">Prioridad</span> <?= e(TASK_PRIORITIES[$task['prioridad']]) ?>
                </span>
              <?php endif; ?>
              <?php partial('source_badge', ['origin' => $task['calendario_origen']]); ?>
            </div>
          </div>

          <div class="task__actions">
            <a href="<?= e($editUrl) ?>" class="icon-btn icon-btn--sm" aria-label="Editar <?= e($task['titulo']) ?>" title="Editar">
              <i class="ri-pencil-line" aria-hidden="true"></i>
            </a>
            <?php partial('delete', [
                'action'   => 'tasks.php',
                'id'       => (int) $task['id'],
                'label'    => 'Eliminar ' . $task['titulo'],
                'question' => '¿Eliminar la tarea «' . $task['titulo'] . '»?',
                'return'   => $return,
            ]); ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php elseif ($counts['total'] === 0): ?>
    <?php partial('empty', [
        'icon'        => 'ri-checkbox-circle-line',
        'title'       => 'Aún no tienes tareas',
        'text'        => 'Añade tus deberes, prácticas y entregas con su fecha límite y prioridad.',
        'actionHref'  => 'tasks.php?action=new',
        'actionLabel' => 'Nueva tarea',
    ]); ?>
  <?php elseif ($currentView === 'atrasadas' && !$listFilters): ?>
    <?php partial('empty', [
        'icon'  => 'ri-emotion-happy-line',
        'title' => '¡No tienes tareas atrasadas!',
        'text'  => 'Vas al día con todas tus entregas.',
    ]); ?>
  <?php else: ?>
    <?php partial('empty', [
        'icon'  => 'ri-search-line',
        'title' => 'No hay tareas que coincidan',
        'text'  => 'Prueba con otra vista o cambia los filtros de búsqueda.',
    ]); ?>
  <?php endif; ?>
</section>
