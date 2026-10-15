<?php
/**
 * Vista: resumen de notas y notas agrupadas por asignatura, cada grupo con su media.
 *
 * @var array      $groups          [id => id, nombre, color, media, num_notas, mejor, peor, notas]
 * @var array      $ungraded        Asignaturas sin notas (id, nombre, color)
 * @var array      $averages        Todas las asignaturas (para el filtro)
 * @var float|null $overallAverage
 * @var array      $summary         total, aprobadas
 * @var int        $gradedCount     Asignaturas con alguna nota
 * @var int        $passedCount     Asignaturas con media aprobada
 * @var int        $subjectId       Asignatura filtrada (0 = todas)
 */

$return       = $subjectId > 0 ? (string) $subjectId : '';
$passedGrades = $summary['total'] > 0 ? (int) round($summary['aprobadas'] / $summary['total'] * 100) : 0;
$editUrl      = static fn (array $grade): string => 'grades.php?action=edit&id=' . (int) $grade['id']
                                                  . ($subjectId > 0 ? '&asignatura=' . $subjectId : '');
?>
<header class="page-header">
  <div>
    <h2 class="page-header__title">Mis notas</h2>
    <p class="page-header__subtitle">
      <?php if ($summary['total'] > 0): ?>
        <?= e(plural($summary['total'], 'nota registrada', 'notas registradas')) ?> ·
        aprobado a partir de <?= e(format_grade(PASS_MARK)) ?>
      <?php else: ?>
        Registra tus calificaciones para seguir tus medias.
      <?php endif; ?>
    </p>
  </div>
  <?php if ($averages): ?>
    <div class="page-header__actions">
      <form method="get" action="grades.php" class="inline-filter">
        <label for="f-asignatura" class="sr-only">Filtrar por asignatura</label>
        <select id="f-asignatura" name="asignatura" class="select select--sm" data-autosubmit>
          <option value="">Todas las asignaturas</option>
          <?php foreach ($averages as $subject): ?>
            <option value="<?= (int) $subject['id'] ?>"<?= $subjectId === (int) $subject['id'] ? ' selected' : '' ?>>
              <?= e($subject['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn--secondary btn--sm">Filtrar</button></noscript>
      </form>
      <span class="hide-mobile">
        <a href="grades.php?action=new<?= $subjectId > 0 ? '&amp;asignatura=' . $subjectId : '' ?>" class="btn btn--primary">
          <i class="ri-add-line" aria-hidden="true"></i> Nueva nota
        </a>
      </span>
    </div>
  <?php endif; ?>
</header>

<?php if (!$averages): ?>
  <section class="card">
    <?php partial('empty', [
        'icon'        => 'ri-book-2-line',
        'title'       => 'Primero crea una asignatura',
        'text'        => 'Cada nota pertenece a una asignatura. Crea tus asignaturas y vuelve aquí.',
        'actionHref'  => 'subjects.php?action=new',
        'actionLabel' => 'Crear asignatura',
    ]); ?>
  </section>
<?php else: ?>

  <section class="stats mb-2" aria-label="Resumen de notas">
    <div class="stat stat--<?= $overallAverage === null ? 'primary' : (grade_passed($overallAverage) ? 'success' : 'danger') ?>">
      <span class="stat__icon"><i class="ri-medal-line" aria-hidden="true"></i></span>
      <span class="stat__value"><?= e(format_grade($overallAverage)) ?></span>
      <span class="stat__label">Media general</span>
    </div>
    <div class="stat stat--success">
      <span class="stat__icon"><i class="ri-book-open-line" aria-hidden="true"></i></span>
      <span class="stat__value"><?= $passedCount ?>/<?= $gradedCount ?></span>
      <span class="stat__label">Asignaturas aprobadas</span>
    </div>
    <div class="stat stat--info">
      <span class="stat__icon"><i class="ri-file-list-3-line" aria-hidden="true"></i></span>
      <span class="stat__value"><?= $summary['total'] ?></span>
      <span class="stat__label">Notas registradas</span>
    </div>
    <div class="stat stat--warning">
      <span class="stat__icon"><i class="ri-percent-line" aria-hidden="true"></i></span>
      <span class="stat__value"><?= $passedGrades ?>%</span>
      <span class="stat__label">Notas aprobadas</span>
    </div>
  </section>

  <?php if ($subjectId > 0): ?>
    <p class="mb-2"><a href="grades.php" class="card__link"><i class="ri-arrow-left-s-line" aria-hidden="true"></i> Ver todas las asignaturas</a></p>
  <?php endif; ?>

  <?php if ($groups): ?>
    <div class="grade-groups">
      <?php foreach ($groups as $group): ?>
        <?php
          $id     = (int) $group['id'];
          $isPass = grade_passed($group['media']);
        ?>
        <section class="card card--flush grade-group" style="--subject: <?= e(safe_color($group['color'])) ?>"
                 aria-labelledby="group-<?= $id ?>">
          <header class="grade-group__header">
            <div class="grade-group__heading">
              <h3 class="grade-group__name subject-title" id="group-<?= $id ?>">
                <span class="subject-title__mark" aria-hidden="true"></span>
                <a href="subjects.php?action=show&amp;id=<?= $id ?>"><?= e($group['nombre']) ?></a>
              </h3>
              <p class="grade-group__meta">
                <?= e(plural((int) $group['num_notas'], 'nota', 'notas')) ?> ·
                mejor <?= e(format_grade($group['mejor'])) ?> · peor <?= e(format_grade($group['peor'])) ?>
              </p>
            </div>
            <div class="grade-group__average">
              <span class="grade-pill grade-pill--<?= $isPass ? 'pass' : 'fail' ?>"><?= e(format_grade($group['media'])) ?></span>
              <span class="badge badge--<?= $isPass ? 'success' : 'danger' ?>"><?= $isPass ? 'Aprobada' : 'Suspensa' ?></span>
            </div>
          </header>

          <div class="grade-group__bar">
            <div class="progress progress--sm progress--mark" style="--mark: <?= PASS_MARK * 10 ?>%"
                 role="progressbar" aria-label="Media de <?= e($group['nombre']) ?>"
                 aria-valuemin="0" aria-valuemax="10" aria-valuenow="<?= round((float) $group['media'], 2) ?>">
              <div class="progress__bar progress__bar--<?= $isPass ? 'success' : 'danger' ?>"
                   style="--value: <?= round(min((float) $group['media'], 10) * 10, 1) ?>%"></div>
            </div>
          </div>

          <ul class="grade-list">
            <?php foreach ($group['notas'] as $grade): ?>
              <?php
                $gradePass = grade_passed($grade['nota']);
                $type      = GRADE_TYPES[$grade['tipo']] ?? $grade['tipo'];
              ?>
              <li class="grade-row">
                <span class="grade-pill grade-pill--<?= $gradePass ? 'pass' : 'fail' ?>">
                  <?= e(format_grade($grade['nota'])) ?>
                  <span class="sr-only"><?= $gradePass ? '(aprobado)' : '(suspenso)' ?></span>
                </span>
                <div class="grade-row__body">
                  <span class="grade-row__type"><?= e($type) ?></span>
                  <span class="grade-mini__meta"><?= e(format_date($grade['fecha'])) ?></span>
                  <?php if ($grade['comentarios']): ?>
                    <p class="grade-row__comment"><?= e(truncate($grade['comentarios'], 140)) ?></p>
                  <?php endif; ?>
                </div>
                <div class="grade-row__actions">
                  <a href="<?= e($editUrl($grade)) ?>" class="icon-btn icon-btn--sm"
                     aria-label="Editar la nota de <?= e(str_lower($type)) ?> del <?= e(format_date($grade['fecha'])) ?>" title="Editar">
                    <i class="ri-pencil-line" aria-hidden="true"></i>
                  </a>
                  <?php partial('delete', [
                      'action'   => 'grades.php',
                      'id'       => (int) $grade['id'],
                      'label'    => 'Eliminar la nota de ' . str_lower($type) . ' del ' . format_date($grade['fecha']),
                      'question' => '¿Eliminar el ' . format_grade($grade['nota']) . ' de ' . $group['nombre'] . '?',
                      'text'     => 'La media de la asignatura se recalculará. Esta acción no se puede deshacer.',
                      'return'   => $return,
                  ]); ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>

          <footer class="grade-group__footer">
            <a href="grades.php?action=new&amp;asignatura=<?= $id ?>" class="card__link">
              <i class="ri-add-line" aria-hidden="true"></i> Añadir nota de <?= e($group['nombre']) ?>
            </a>
          </footer>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($ungraded): ?>
    <section class="card mt-2 ungraded" aria-labelledby="ungraded-title">
      <h3 class="card__title" id="ungraded-title"><i class="ri-hourglass-line" aria-hidden="true"></i> Sin notas todavía</h3>
      <ul class="ungraded__list">
        <?php foreach ($ungraded as $subject): ?>
          <li>
            <a href="grades.php?action=new&amp;asignatura=<?= (int) $subject['id'] ?>" class="chip"
               style="--subject: <?= e(safe_color($subject['color'])) ?>"
               aria-label="Añadir una nota de <?= e($subject['nombre']) ?>">
              <span class="dot" aria-hidden="true"></span><?= e($subject['nombre']) ?>
              <i class="ri-add-line" aria-hidden="true"></i>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php if (!$groups && !$ungraded): ?>
    <section class="card">
      <?php partial('empty', [
          'icon'        => 'ri-award-line',
          'title'       => 'Aún no hay notas',
          'text'        => 'Registra tus calificaciones para calcular tus medias.',
          'actionHref'  => 'grades.php?action=new',
          'actionLabel' => 'Añadir nota',
      ]); ?>
    </section>
  <?php endif; ?>
<?php endif; ?>
