<?php
/**
 * Vista: exámenes próximos (tarjetas con cuenta atrás) y pasados (lista).
 *
 * @var array  $upcoming   Próximos, cada uno con 'countdown' (countdown_parts())
 * @var array  $past
 * @var array  $subjects   id, nombre, color
 * @var int    $subjectId  Asignatura filtrada (0 = todas)
 * @var string $today
 */

$statusBadge = ['pendiente' => 'primary', 'realizado' => 'success', 'aplazado' => 'warning'];
$return      = $subjectId > 0 ? (string) $subjectId : '';
$editUrl     = static fn (array $exam): string => 'exams.php?action=edit&id=' . (int) $exam['id']
                                                 . ($subjectId > 0 ? '&asignatura=' . $subjectId : '');
?>
<header class="page-header">
  <div>
    <h2 class="page-header__title">Mis exámenes</h2>
    <p class="page-header__subtitle">
      <?php if ($upcoming): ?>
        <?= e(plural(count($upcoming), 'examen próximo', 'exámenes próximos')) ?> ·
        el siguiente <?= e(str_lower(relative_day($upcoming[0]['fecha']))) ?>
      <?php else: ?>
        No tienes exámenes a la vista.
      <?php endif; ?>
    </p>
  </div>
  <div class="page-header__actions">
    <?php if (count($subjects) > 1): ?>
      <form method="get" action="exams.php" class="inline-filter">
        <label for="f-asignatura" class="sr-only">Filtrar por asignatura</label>
        <select id="f-asignatura" name="asignatura" class="select select--sm" data-autosubmit>
          <option value="">Todas las asignaturas</option>
          <?php foreach ($subjects as $subject): ?>
            <option value="<?= (int) $subject['id'] ?>"<?= $subjectId === (int) $subject['id'] ? ' selected' : '' ?>>
              <?= e($subject['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn--secondary btn--sm">Filtrar</button></noscript>
      </form>
    <?php endif; ?>
    <?php if ($subjects): ?>
      <span class="hide-mobile">
        <a href="exams.php?action=new<?= $subjectId > 0 ? '&amp;asignatura=' . $subjectId : '' ?>" class="btn btn--primary">
          <i class="ri-add-line" aria-hidden="true"></i> Nuevo examen
        </a>
      </span>
    <?php endif; ?>
  </div>
</header>

<?php if (!$subjects): ?>
  <section class="card">
    <?php partial('empty', [
        'icon'        => 'ri-book-2-line',
        'title'       => 'Primero crea una asignatura',
        'text'        => 'Cada examen pertenece a una asignatura. Crea tus asignaturas y vuelve aquí.',
        'actionHref'  => 'subjects.php?action=new',
        'actionLabel' => 'Crear asignatura',
    ]); ?>
  </section>
<?php else: ?>

  <section class="section" aria-labelledby="upcoming-title">
    <h3 class="section__title" id="upcoming-title">
      <i class="ri-calendar-event-line" aria-hidden="true"></i> Próximos
      <span class="badge"><?= count($upcoming) ?></span>
    </h3>

    <?php if ($upcoming): ?>
      <div class="exam-grid">
        <?php foreach ($upcoming as $i => $exam): ?>
          <article class="card exam-card<?= $i === 0 ? ' exam-card--next' : '' ?>"
                   style="--subject: <?= e(safe_color($exam['color'])) ?>" aria-labelledby="exam-<?= (int) $exam['id'] ?>">
            <header class="exam-card__header">
              <span class="chip"><span class="dot" aria-hidden="true"></span><?= e($exam['asignatura']) ?></span>
              <div class="exam-card__actions">
                <a href="<?= e($editUrl($exam)) ?>" class="icon-btn icon-btn--sm"
                   aria-label="Editar <?= e($exam['nombre']) ?>" title="Editar">
                  <i class="ri-pencil-line" aria-hidden="true"></i>
                </a>
                <?php partial('delete', [
                    'action'   => 'exams.php',
                    'id'       => (int) $exam['id'],
                    'label'    => 'Eliminar ' . $exam['nombre'],
                    'question' => '¿Eliminar el examen «' . $exam['nombre'] . '»?',
                    'return'   => $return,
                ]); ?>
              </div>
            </header>

            <h4 class="exam-card__name" id="exam-<?= (int) $exam['id'] ?>"><?= e($exam['nombre']) ?></h4>
            <ul class="meta-list">
              <li><i class="ri-calendar-line" aria-hidden="true"></i> <?= e(ucfirst(format_long_date($exam['fecha']))) ?></li>
              <?php if ($exam['hora']): ?>
                <li><i class="ri-time-line" aria-hidden="true"></i> <?= e(format_time($exam['hora'])) ?></li>
              <?php endif; ?>
              <?php if ($exam['aula']): ?>
                <li><i class="ri-map-pin-line" aria-hidden="true"></i> <?= e($exam['aula']) ?></li>
              <?php endif; ?>
            </ul>
            <?php if ($exam['descripcion']): ?>
              <p class="exam-card__desc"><?= e(truncate($exam['descripcion'], 140)) ?></p>
            <?php endif; ?>

            <?php if ($exam['estado'] === 'aplazado'): ?>
              <p class="alert alert--warning mt-1">
                <i class="ri-time-line" aria-hidden="true"></i> Aplazado. Edítalo cuando sepas la nueva fecha.
              </p>
            <?php else: ?>
              <div class="exam-card__countdown">
                <?php partial('countdown', ['countdown' => $exam['countdown']]); ?>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card">
        <?php partial('empty', [
            'icon'        => 'ri-calendar-check-line',
            'title'       => 'Sin exámenes próximos',
            'text'        => 'Añade tus exámenes para ver cuánto falta para cada uno.',
            'actionHref'  => 'exams.php?action=new' . ($subjectId > 0 ? '&asignatura=' . $subjectId : ''),
            'actionLabel' => 'Añadir examen',
            'compact'     => true,
        ]); ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="section" aria-labelledby="past-title">
    <h3 class="section__title" id="past-title">
      <i class="ri-history-line" aria-hidden="true"></i> Pasados
      <span class="badge"><?= count($past) ?></span>
    </h3>

    <?php if ($past): ?>
      <ul class="card card--flush exam-list">
        <?php foreach ($past as $exam): ?>
          <?php $unmarked = $exam['estado'] === 'pendiente'; ?>
          <li class="exam-row" style="--subject: <?= e(safe_color($exam['color'])) ?>">
            <span class="date-badge" aria-hidden="true">
              <span class="date-badge__day"><?= e(format_date($exam['fecha'], 'j')) ?></span>
              <span class="date-badge__month"><?= e(str_take(MONTH_NAMES[(int) format_date($exam['fecha'], 'n')], 3)) ?></span>
            </span>

            <div class="exam-row__body">
              <a href="<?= e($editUrl($exam)) ?>" class="exam-row__name"><?= e($exam['nombre']) ?></a>
              <div class="task__meta">
                <span class="chip"><span class="dot" aria-hidden="true"></span><?= e($exam['asignatura']) ?></span>
                <span class="task__date"><i class="ri-calendar-line" aria-hidden="true"></i> <?= e(format_date($exam['fecha'])) ?></span>
                <?php if ($unmarked): ?>
                  <span class="badge badge--warning">Sin marcar</span>
                <?php else: ?>
                  <span class="badge badge--<?= $statusBadge[$exam['estado']] ?>"><?= e(EXAM_STATUSES[$exam['estado']]) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="exam-row__actions">
              <?php if ($unmarked): ?>
                <form method="post" action="exams.php" class="inline-form" data-loading>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="status">
                  <input type="hidden" name="estado" value="realizado">
                  <input type="hidden" name="id" value="<?= (int) $exam['id'] ?>">
                  <input type="hidden" name="return" value="<?= e($return) ?>">
                  <button type="submit" class="btn btn--secondary btn--sm">
                    <i class="ri-check-line" aria-hidden="true"></i> Marcar realizado
                  </button>
                </form>
              <?php elseif ($exam['estado'] === 'realizado'): ?>
                <a href="<?= e(exam_grade_url($exam)) ?>" class="btn btn--secondary btn--sm"
                   aria-label="Añadir nota de <?= e($exam['nombre']) ?>">
                  <i class="ri-award-line" aria-hidden="true"></i> Añadir nota
                </a>
              <?php endif; ?>
              <a href="<?= e($editUrl($exam)) ?>" class="icon-btn icon-btn--sm"
                 aria-label="Editar <?= e($exam['nombre']) ?>" title="Editar">
                <i class="ri-pencil-line" aria-hidden="true"></i>
              </a>
              <?php partial('delete', [
                  'action'   => 'exams.php',
                  'id'       => (int) $exam['id'],
                  'label'    => 'Eliminar ' . $exam['nombre'],
                  'question' => '¿Eliminar el examen «' . $exam['nombre'] . '»?',
                  'return'   => $return,
              ]); ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="card">
        <?php partial('empty', [
            'icon'    => 'ri-history-line',
            'title'   => 'Todavía no hay exámenes pasados',
            'text'    => 'Aquí verás los exámenes ya realizados.',
            'compact' => true,
        ]); ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
