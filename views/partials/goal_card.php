<?php
/**
 * Fragmento: tarjeta de un objetivo con su barra de progreso y botones +/−.
 *
 * @var array  $goal   id, titulo, descripcion, fecha_limite, valor_actual, valor_meta, unidad, estado
 * @var string $today
 */

$statusBadge = ['pendiente' => 'warning', 'en_progreso' => 'info', 'completado' => 'success'];
$id          = (int) $goal['id'];
$current     = (int) $goal['valor_actual'];
$target      = (int) $goal['valor_meta'];
$percent     = goal_percent($goal);
$step        = goal_step($target);
$unit        = (string) $goal['unidad'];
$isDone      = $goal['estado'] === 'completado';
$due         = $goal['fecha_limite'];
$isOverdue   = !$isDone && $due !== null && $due < $today;
$stepText    = $step . ($unit !== '' ? " ({$unit})" : ''); // "Sumar 1 (ejercicios)"
?>
<article class="card goal-card<?= $isDone ? ' is-complete' : '' ?><?= $isOverdue ? ' is-overdue' : '' ?>"
         id="objetivo-<?= $id ?>" aria-labelledby="goal-<?= $id ?>-title">
  <header class="goal-card__header">
    <div class="goal-card__heading">
      <h4 class="goal-card__title" id="goal-<?= $id ?>-title"><?= e($goal['titulo']) ?></h4>
      <span class="badge badge--<?= $statusBadge[$goal['estado']] ?>">
        <?php if ($isDone): ?><i class="ri-trophy-line" aria-hidden="true"></i><?php endif; ?>
        <?= e(GOAL_STATUSES[$goal['estado']]) ?>
      </span>
    </div>
    <div class="goal-card__actions">
      <a href="goals.php?action=edit&amp;id=<?= $id ?>" class="icon-btn icon-btn--sm"
         aria-label="Editar <?= e($goal['titulo']) ?>" title="Editar">
        <i class="ri-pencil-line" aria-hidden="true"></i>
      </a>
      <?php partial('delete', [
          'action'   => 'goals.php',
          'id'       => $id,
          'label'    => 'Eliminar ' . $goal['titulo'],
          'question' => '¿Eliminar el objetivo «' . $goal['titulo'] . '»?',
      ]); ?>
    </div>
  </header>

  <?php if ($goal['descripcion']): ?>
    <p class="goal-card__desc"><?= e(truncate($goal['descripcion'], 140)) ?></p>
  <?php endif; ?>

  <div class="goal-card__progress">
    <div class="goal-card__numbers">
      <span><strong><?= $current ?></strong> / <?= $target ?><?= $unit !== '' ? ' ' . e($unit) : '' ?></span>
      <span class="goal-card__percent"><?= $percent ?>%</span>
    </div>
    <div class="progress" role="progressbar" aria-label="Progreso de <?= e($goal['titulo']) ?>"
         aria-valuemin="0" aria-valuemax="<?= $target ?>" aria-valuenow="<?= $current ?>"
         aria-valuetext="<?= $current ?> de <?= $target ?><?= $unit !== '' ? ' ' . e($unit) : '' ?> (<?= $percent ?> %)">
      <div class="progress__bar<?= $isDone ? ' progress__bar--success' : '' ?>" style="--value: <?= $percent ?>%"></div>
    </div>
  </div>

  <footer class="goal-card__footer">
    <?php if ($due !== null): ?>
      <span class="task__date<?= $isOverdue ? ' is-late' : '' ?>" title="<?= e(ucfirst(format_long_date($due, true))) ?>">
        <i class="<?= $isOverdue ? 'ri-alarm-warning-line' : 'ri-flag-line' ?>" aria-hidden="true"></i>
        <?php if ($isDone): ?>
          Límite: <?= e(format_date($due)) ?>
        <?php elseif ($isOverdue): ?>
          Venció <?= e(str_lower(relative_day($due))) ?>
        <?php else: ?>
          <?= e(relative_day($due)) ?> · <?= e(format_date($due, 'd/m')) ?>
        <?php endif; ?>
      </span>
    <?php else: ?>
      <span class="task__date text-soft"><i class="ri-flag-line" aria-hidden="true"></i> Sin fecha límite</span>
    <?php endif; ?>

    <div class="stepper" role="group" aria-label="Cambiar el progreso de <?= e($goal['titulo']) ?>">
      <?php foreach ([-1 => ['ri-subtract-line', 'Restar'], 1 => ['ri-add-line', 'Sumar']] as $direction => [$icon, $verb]): ?>
        <?php $disabled = $direction === -1 ? $current <= 0 : $current >= $target; ?>
        <form method="post" action="goals.php" class="inline-form" data-loading>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="step">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="direction" value="<?= $direction ?>">
          <button type="submit" class="stepper__btn<?= $direction === 1 ? ' stepper__btn--plus' : '' ?>"
                  aria-label="<?= e($verb . ' ' . $stepText) ?>" title="<?= e($verb . ' ' . $stepText) ?>"
                  <?= $disabled ? 'disabled' : '' ?>>
            <i class="<?= $icon ?>" aria-hidden="true"></i><?= $step > 1 ? '<span>' . $step . '</span>' : '' ?>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
  </footer>
</article>
