<?php
/**
 * Vista: listado de asignaturas en tarjetas.
 *
 * @var array $subjects  Resultado de subject_all_with_stats() + 'aviso_borrado'
 */

$totalPending = array_sum(array_column($subjects, 'tareas_pendientes'));
?>
<header class="page-header">
  <div>
    <h2 class="page-header__title">Mis asignaturas</h2>
    <p class="page-header__subtitle">
      <?php if ($subjects): ?>
        <?= e(plural(count($subjects), 'asignatura', 'asignaturas')) ?> ·
        <?= e(plural($totalPending, 'tarea pendiente', 'tareas pendientes')) ?>
      <?php else: ?>
        Organiza tus tareas, exámenes y notas por asignatura.
      <?php endif; ?>
    </p>
  </div>
  <?php if ($subjects): ?>
    <div class="page-header__actions hide-mobile">
      <a href="subjects.php?action=new" class="btn btn--primary">
        <i class="ri-add-line" aria-hidden="true"></i> Nueva asignatura
      </a>
    </div>
  <?php endif; ?>
</header>

<?php if (!$subjects): ?>
  <section class="card">
    <?php partial('empty', [
        'icon'        => 'ri-book-2-line',
        'title'       => 'Aún no tienes asignaturas',
        'text'        => 'Crea tu primera asignatura para poder asociarle tareas, exámenes, notas y clases.',
        'actionHref'  => 'subjects.php?action=new',
        'actionLabel' => 'Crear asignatura',
    ]); ?>
  </section>
<?php else: ?>
  <div class="subject-grid subject-grid--cards">
    <?php foreach ($subjects as $subject): ?>
      <?php
        $pending = (int) $subject['tareas_pendientes'];
        $exams   = (int) $subject['examenes_proximos'];
      ?>
      <article class="card subject-card" style="--subject: <?= e(safe_color($subject['color'])) ?>"
               aria-labelledby="subject-<?= (int) $subject['id'] ?>">
        <header class="subject-card__header">
          <span class="subject-card__icon" aria-hidden="true"><?= e(initials($subject['nombre'])) ?></span>
          <div class="subject-card__heading">
            <h3 class="subject-card__name" id="subject-<?= (int) $subject['id'] ?>">
              <a href="subjects.php?action=show&amp;id=<?= (int) $subject['id'] ?>"><?= e($subject['nombre']) ?></a>
            </h3>
            <?php if ($subject['profesor'] || $subject['curso']): ?>
              <p class="subject-card__meta">
                <?php if ($subject['profesor']): ?>
                  <span><i class="ri-user-line" aria-hidden="true"></i> <?= e($subject['profesor']) ?></span>
                <?php endif; ?>
                <?php if ($subject['curso']): ?>
                  <span><i class="ri-graduation-cap-line" aria-hidden="true"></i> <?= e($subject['curso']) ?></span>
                <?php endif; ?>
              </p>
            <?php endif; ?>
          </div>
          <div class="subject-card__actions">
            <a href="subjects.php?action=edit&amp;id=<?= (int) $subject['id'] ?>" class="icon-btn icon-btn--sm"
               aria-label="Editar <?= e($subject['nombre']) ?>" title="Editar">
              <i class="ri-pencil-line" aria-hidden="true"></i>
            </a>
            <?php partial('delete', [
                'action'   => 'subjects.php',
                'id'       => (int) $subject['id'],
                'label'    => 'Eliminar ' . $subject['nombre'],
                'question' => '¿Eliminar la asignatura «' . $subject['nombre'] . '»?',
                'text'     => $subject['aviso_borrado'],
            ]); ?>
          </div>
        </header>

        <?php if ($subject['descripcion']): ?>
          <p class="subject-card__desc"><?= e(truncate($subject['descripcion'], 140)) ?></p>
        <?php endif; ?>

        <dl class="subject-card__stats">
          <div class="subject-card__stat">
            <dt>Media</dt>
            <dd>
              <?php if ($subject['media'] !== null): ?>
                <span class="<?= grade_passed($subject['media']) ? 'text-success' : 'text-danger' ?>">
                  <?= e(format_grade($subject['media'])) ?>
                </span>
              <?php else: ?>
                <span class="text-soft">—</span>
              <?php endif; ?>
            </dd>
          </div>
          <div class="subject-card__stat">
            <dt>Pendientes</dt>
            <dd><?= $pending ?></dd>
          </div>
          <div class="subject-card__stat">
            <dt>Exámenes</dt>
            <dd><?= $exams ?></dd>
          </div>
        </dl>

        <footer class="subject-card__footer">
          <?php if ($subject['proximo_examen'] !== null): ?>
            <span class="badge badge--<?= days_until($subject['proximo_examen']) <= 3 ? 'warning' : 'primary' ?>">
              <i class="ri-calendar-event-line" aria-hidden="true"></i>
              Próximo examen: <?= e(str_lower(relative_day($subject['proximo_examen']))) ?>
            </span>
          <?php else: ?>
            <span class="badge"><i class="ri-calendar-check-line" aria-hidden="true"></i> Sin exámenes próximos</span>
          <?php endif; ?>
          <a href="subjects.php?action=show&amp;id=<?= (int) $subject['id'] ?>" class="card__link"
             aria-label="Ver la asignatura <?= e($subject['nombre']) ?>">
            Ver asignatura <i class="ri-arrow-right-s-line" aria-hidden="true"></i>
          </a>
        </footer>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
