<?php
/**
 * Vista: resumen de notas, media por asignatura y listado de calificaciones.
 *
 * En móvil las medias van primero; en escritorio pasan a una columna lateral (.split).
 *
 * @var array      $grades
 * @var array      $averages        id, nombre, color, media, num_notas, mejor, peor
 * @var float|null $overallAverage
 * @var array      $summary         total, aprobadas
 * @var int        $gradedCount     Asignaturas con alguna nota
 * @var int        $passedCount     Asignaturas con media aprobada
 * @var int        $subjectId       Asignatura filtrada (0 = todas)
 */

$return       = $subjectId > 0 ? (string) $subjectId : '';
$passedGrades = $summary['total'] > 0 ? (int) round($summary['aprobadas'] / $summary['total'] * 100) : 0;
$filtered     = $subjectId > 0
    ? (array_values(array_filter($averages, static fn (array $s): bool => (int) $s['id'] === $subjectId))[0] ?? null)
    : null;
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

  <div class="split">
    <aside class="card split__side" aria-labelledby="averages-title">
      <header class="card__header">
        <h3 class="card__title" id="averages-title"><i class="ri-bar-chart-horizontal-line" aria-hidden="true"></i> Media por asignatura</h3>
        <?php if ($subjectId > 0): ?>
          <a href="grades.php" class="card__link">Ver todas</a>
        <?php endif; ?>
      </header>

      <ul class="average-list">
        <?php foreach ($averages as $subject): ?>
          <?php
            $hasGrades = (int) $subject['num_notas'] > 0;
            $isPass    = $hasGrades && grade_passed($subject['media']);
          ?>
          <li class="average<?= $subjectId === (int) $subject['id'] ? ' is-active' : '' ?>"
              style="--subject: <?= e(safe_color($subject['color'])) ?>">
            <div class="average__head">
              <a href="grades.php?asignatura=<?= (int) $subject['id'] ?>" class="average__name"
                 <?= $subjectId === (int) $subject['id'] ? 'aria-current="true"' : '' ?>>
                <span class="dot" aria-hidden="true"></span><?= e($subject['nombre']) ?>
              </a>
              <?php if ($hasGrades): ?>
                <span class="grade-pill grade-pill--sm grade-pill--<?= $isPass ? 'pass' : 'fail' ?>"
                      title="<?= $isPass ? 'Aprobada' : 'Suspensa' ?>">
                  <?= e(format_grade($subject['media'])) ?>
                </span>
              <?php else: ?>
                <span class="badge">Sin notas</span>
              <?php endif; ?>
            </div>
            <?php if ($hasGrades): ?>
              <div class="progress progress--sm progress--mark" style="--mark: <?= PASS_MARK * 10 ?>%"
                   role="progressbar" aria-label="Media de <?= e($subject['nombre']) ?>"
                   aria-valuemin="0" aria-valuemax="10" aria-valuenow="<?= round((float) $subject['media'], 2) ?>">
                <div class="progress__bar progress__bar--<?= $isPass ? 'success' : 'danger' ?>"
                     style="--value: <?= round(min((float) $subject['media'], 10) * 10, 1) ?>%"></div>
              </div>
              <span class="average__meta">
                <?= e(plural((int) $subject['num_notas'], 'nota', 'notas')) ?> ·
                mejor <?= e(format_grade($subject['mejor'])) ?> · peor <?= e(format_grade($subject['peor'])) ?>
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </aside>

    <section class="split__main" aria-labelledby="grades-title">
      <h3 class="section__title" id="grades-title">
        <i class="ri-award-line" aria-hidden="true"></i>
        <?= $filtered !== null ? 'Notas de ' . e($filtered['nombre']) : 'Todas las notas' ?>
        <span class="badge"><?= count($grades) ?></span>
      </h3>

      <?php if ($grades): ?>
        <ul class="card card--flush grade-list">
          <?php foreach ($grades as $grade): ?>
            <?php $isPass = grade_passed($grade['nota']); ?>
            <li class="grade-row">
              <span class="grade-pill grade-pill--<?= $isPass ? 'pass' : 'fail' ?>">
                <?= e(format_grade($grade['nota'])) ?>
                <span class="sr-only"><?= $isPass ? '(aprobado)' : '(suspenso)' ?></span>
              </span>
              <div class="grade-row__body">
                <span class="grade-mini__subject" style="--subject: <?= e(safe_color($grade['color'])) ?>">
                  <span class="dot" aria-hidden="true"></span><?= e($grade['asignatura']) ?>
                </span>
                <span class="grade-mini__meta">
                  <?= e(GRADE_TYPES[$grade['tipo']] ?? $grade['tipo']) ?> · <?= e(format_date($grade['fecha'])) ?>
                </span>
                <?php if ($grade['comentarios']): ?>
                  <p class="grade-row__comment"><?= e(truncate($grade['comentarios'], 140)) ?></p>
                <?php endif; ?>
              </div>
              <div class="grade-row__actions">
                <a href="<?= e($editUrl($grade)) ?>" class="icon-btn icon-btn--sm"
                   aria-label="Editar la nota de <?= e($grade['asignatura']) ?> del <?= e(format_date($grade['fecha'])) ?>" title="Editar">
                  <i class="ri-pencil-line" aria-hidden="true"></i>
                </a>
                <?php partial('delete', [
                    'action'   => 'grades.php',
                    'id'       => (int) $grade['id'],
                    'label'    => 'Eliminar la nota de ' . $grade['asignatura'] . ' del ' . format_date($grade['fecha']),
                    'question' => '¿Eliminar la nota de ' . format_grade($grade['nota']) . ' en ' . $grade['asignatura'] . '?',
                    'text'     => 'La media de la asignatura se recalculará. Esta acción no se puede deshacer.',
                    'return'   => $return,
                ]); ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="card">
          <?php partial('empty', [
              'icon'        => 'ri-award-line',
              'title'       => $subjectId > 0 ? 'Esta asignatura aún no tiene notas' : 'Aún no hay notas',
              'text'        => 'Registra tus calificaciones para calcular tus medias.',
              'actionHref'  => 'grades.php?action=new' . ($subjectId > 0 ? '&asignatura=' . $subjectId : ''),
              'actionLabel' => 'Añadir nota',
              'compact'     => true,
          ]); ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
<?php endif; ?>
