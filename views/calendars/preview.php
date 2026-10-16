<?php
/**
 * Vista: vista previa de un calendario antes de importarlo.
 * Muestra cuántos eventos se importarán y los próximos, cada uno con su tipo
 * (tarea o examen) y la asignatura que se le asignaría.
 *
 * @var array      $settings nombre, origen, asignatura_id, tipo_por_defecto, activo
 * @var array      $preview  total, importables, ignorados, sin_asignatura, proximos, mas
 * @var array|null $subject  Asignatura elegida para todo el calendario
 * @var string     $masked   URL enmascarada
 */
?>
<header class="page-header">
  <div>
    <a href="calendars.php?action=new&amp;volver=1" class="back-link"><i class="ri-arrow-left-line" aria-hidden="true"></i> Volver a editar</a>
    <h2 class="page-header__title">Vista previa</h2>
    <p class="page-header__subtitle">Así se importará «<?= e($settings['nombre']) ?>». Todavía no se ha guardado nada.</p>
  </div>
</header>

<section class="card card--form feed-summary" aria-label="Calendario">
  <?php partial('feed_icon', ['origin' => $settings['origen']]); ?>
  <div class="feed-summary__body">
    <p class="feed-card__title"><?= e($settings['nombre']) ?></p>
    <p class="feed-card__origin">
      <?= e(CALENDAR_ORIGINS[$settings['origen']]) ?> · <?= e($masked) ?>
    </p>
    <p class="feed-summary__settings">
      <?php if ($subject !== null): ?>
        Todo a <span class="chip" style="--subject: <?= e(safe_color($subject['color'])) ?>"><span class="dot" aria-hidden="true"></span><?= e($subject['nombre']) ?></span>
      <?php else: ?>
        Asignatura según el curso de cada evento
      <?php endif; ?>
      · como <?= $settings['tipo_por_defecto'] === 'examen' ? 'exámenes' : 'tareas' ?>
    </p>
  </div>
</section>

<section class="stats feed-stats card--form" aria-label="Resumen">
  <div class="stat stat--primary">
    <span class="stat__icon"><i class="ri-calendar-2-line" aria-hidden="true"></i></span>
    <span class="stat__value"><?= $preview['total'] ?></span>
    <span class="stat__label">Eventos en el calendario</span>
  </div>
  <div class="stat stat--success">
    <span class="stat__icon"><i class="ri-download-2-line" aria-hidden="true"></i></span>
    <span class="stat__value"><?= $preview['importables'] ?></span>
    <span class="stat__label">Se importarán</span>
  </div>
  <div class="stat stat--info">
    <span class="stat__icon"><i class="ri-history-line" aria-hidden="true"></i></span>
    <span class="stat__value"><?= $preview['ignorados'] ?></span>
    <span class="stat__label">Ignorados (antiguos o cancelados)</span>
  </div>
  <div class="stat stat--warning">
    <span class="stat__icon"><i class="ri-question-line" aria-hidden="true"></i></span>
    <span class="stat__value"><?= $preview['sin_asignatura'] ?></span>
    <span class="stat__label">Sin asignatura</span>
  </div>
</section>

<section class="card card--form mt-2" aria-labelledby="next-title">
  <header class="card__header">
    <h3 class="card__title" id="next-title"><i class="ri-calendar-event-line" aria-hidden="true"></i> Próximos eventos</h3>
  </header>

  <?php if ($preview['proximos']): ?>
    <ul class="preview-list">
      <?php foreach ($preview['proximos'] as $event): ?>
        <li class="preview-item" style="--subject: <?= e(safe_color($event['asignatura']['color'] ?? null)) ?>">
          <span class="date-badge" aria-hidden="true">
            <span class="date-badge__day"><?= e(format_date($event['fecha'], 'j')) ?></span>
            <span class="date-badge__month"><?= e(str_take(MONTH_NAMES[(int) format_date($event['fecha'], 'n')], 3)) ?></span>
          </span>
          <div class="preview-item__body">
            <p class="preview-item__title"><?= e($event['titulo']) ?></p>
            <div class="task__meta">
              <span class="task__date">
                <i class="ri-calendar-line" aria-hidden="true"></i>
                <?= e(ucfirst(format_long_date($event['fecha']))) ?><?= $event['hora'] !== null ? ', ' . e($event['hora']) : '' ?>
              </span>
              <span class="badge badge--<?= $event['tipo'] === 'examen' ? 'primary' : 'info' ?>">
                <i class="<?= $event['tipo'] === 'examen' ? 'ri-file-list-3-line' : 'ri-checkbox-circle-line' ?>" aria-hidden="true"></i>
                <?= $event['tipo'] === 'examen' ? 'Examen' : 'Tarea' ?>
              </span>
              <?php if ($event['asignatura'] !== null): ?>
                <span class="chip"><span class="dot" aria-hidden="true"></span><?= e($event['asignatura']['nombre']) ?></span>
              <?php else: ?>
                <span class="badge badge--warning" title="Curso: <?= e($event['origen']) ?>">
                  <i class="ri-question-line" aria-hidden="true"></i> Sin asignatura
                </span>
              <?php endif; ?>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($preview['mas'] > 0): ?>
      <p class="text-muted text-sm mt-1">Y <?= e(plural($preview['mas'], 'evento próximo más', 'eventos próximos más')) ?>.</p>
    <?php endif; ?>
  <?php else: ?>
    <?php partial('empty', [
        'icon'    => 'ri-calendar-check-line',
        'title'   => 'No hay eventos próximos',
        'text'    => $preview['importables'] > 0
            ? 'Se importarán los de los últimos ' . CALENDAR_PAST_DAYS . ' días; los nuevos llegarán al sincronizar.'
            : 'El calendario está vacío o solo tiene eventos antiguos. Se importarán los nuevos al sincronizar.',
        'compact' => true,
    ]); ?>
  <?php endif; ?>

  <?php if ($preview['sin_asignatura'] > 0): ?>
    <p class="alert alert--info mt-2">
      <i class="ri-information-line" aria-hidden="true"></i>
      <span>Los eventos sin asignatura se importan como tareas y aparecerán en «Pendientes de asignar», donde podrás elegir la suya.</span>
    </p>
  <?php endif; ?>
</section>

<div class="form-actions card--form mt-2">
  <a href="calendars.php?action=new&amp;volver=1" class="btn btn--ghost">Volver a editar</a>
  <form method="post" action="calendars.php" class="inline-form" data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <button type="submit" class="btn btn--primary">
      <i class="ri-download-2-line" aria-hidden="true"></i> Conectar e importar
    </button>
  </form>
</div>
