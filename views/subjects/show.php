<?php
/**
 * Vista: detalle de una asignatura con sus tareas, exámenes, notas y horario.
 *
 * @var array      $subject        id, nombre, descripcion, profesor, curso, color
 * @var array      $pendingTasks   Tareas sin completar (task_list)
 * @var int        $doneCount      Tareas completadas
 * @var array      $upcoming       Exámenes próximos (exam_upcoming)
 * @var array|null $countdown      Cuenta atrás del más cercano
 * @var array      $past           Exámenes pasados (exam_past)
 * @var array      $grades         Notas (grade_list)
 * @var float|null $average        Media de la asignatura
 * @var array      $classes        Clases del horario
 * @var int        $weeklyMinutes
 * @var string     $deleteWarning  Texto del diálogo de borrado
 * @var string     $today
 */

$id          = (int) $subject['id'];
$color       = safe_color($subject['color']);
$statusBadge = ['pendiente' => 'primary', 'realizado' => 'success', 'aplazado' => 'warning'];
$nextExam    = $upcoming[0] ?? null;
?>
<header class="page-header">
  <div>
    <a href="subjects.php" class="back-link"><i class="ri-arrow-left-line" aria-hidden="true"></i> Asignaturas</a>
    <h2 class="page-header__title subject-title" style="--subject: <?= e($color) ?>">
      <span class="subject-title__mark" aria-hidden="true"></span><?= e($subject['nombre']) ?>
    </h2>
    <?php if ($subject['profesor'] || $subject['curso']): ?>
      <ul class="meta-list mt-1">
        <?php if ($subject['profesor']): ?>
          <li><i class="ri-user-line" aria-hidden="true"></i> <?= e($subject['profesor']) ?></li>
        <?php endif; ?>
        <?php if ($subject['curso']): ?>
          <li><i class="ri-graduation-cap-line" aria-hidden="true"></i> <?= e($subject['curso']) ?></li>
        <?php endif; ?>
      </ul>
    <?php endif; ?>
  </div>
  <div class="page-header__actions">
    <a href="subjects.php?action=edit&amp;id=<?= $id ?>" class="btn btn--secondary btn--sm">
      <i class="ri-pencil-line" aria-hidden="true"></i> Editar
    </a>
    <?php partial('delete', [
        'action'   => 'subjects.php',
        'id'       => $id,
        'label'    => 'Eliminar ' . $subject['nombre'],
        'question' => '¿Eliminar la asignatura «' . $subject['nombre'] . '»?',
        'text'     => $deleteWarning,
    ]); ?>
  </div>
</header>

<?php if ($subject['descripcion']): ?>
  <p class="subject-desc"><?= nl2br(e($subject['descripcion'])) ?></p>
<?php endif; ?>

<section class="stats mb-2" aria-label="Resumen de la asignatura">
  <a href="grades.php?asignatura=<?= $id ?>"
     class="stat stat--<?= $average === null ? 'primary' : (grade_passed($average) ? 'success' : 'danger') ?>">
    <span class="stat__icon"><i class="ri-medal-line" aria-hidden="true"></i></span>
    <span class="stat__value"><?= e(format_grade($average)) ?></span>
    <span class="stat__label">Media</span>
  </a>
  <a href="tasks.php?asignatura=<?= $id ?>&amp;estado=abiertas" class="stat stat--warning">
    <span class="stat__icon"><i class="ri-list-check-3" aria-hidden="true"></i></span>
    <span class="stat__value"><?= count($pendingTasks) ?></span>
    <span class="stat__label">Tareas pendientes</span>
  </a>
  <a href="exams.php?asignatura=<?= $id ?>" class="stat stat--info">
    <span class="stat__icon"><i class="ri-calendar-event-line" aria-hidden="true"></i></span>
    <span class="stat__value"><?= count($upcoming) ?></span>
    <span class="stat__label">Exámenes próximos</span>
  </a>
  <a href="schedule.php" class="stat stat--primary">
    <span class="stat__icon"><i class="ri-time-line" aria-hidden="true"></i></span>
    <span class="stat__value"><?= e(format_number($weeklyMinutes / 60, 1)) ?> h</span>
    <span class="stat__label">De clase a la semana</span>
  </a>
</section>

<nav class="quick-actions" aria-label="Añadir a esta asignatura">
  <a href="tasks.php?action=new&amp;asignatura=<?= $id ?>" class="btn btn--secondary btn--sm">
    <i class="ri-add-line" aria-hidden="true"></i> Tarea
  </a>
  <a href="exams.php?action=new&amp;asignatura=<?= $id ?>" class="btn btn--secondary btn--sm">
    <i class="ri-add-line" aria-hidden="true"></i> Examen
  </a>
  <a href="grades.php?action=new&amp;asignatura=<?= $id ?>" class="btn btn--secondary btn--sm">
    <i class="ri-add-line" aria-hidden="true"></i> Nota
  </a>
  <a href="schedule.php?action=new&amp;asignatura=<?= $id ?>" class="btn btn--secondary btn--sm">
    <i class="ri-add-line" aria-hidden="true"></i> Clase
  </a>
</nav>

<div class="detail-grid" style="--subject: <?= e($color) ?>">

  <!-- Tareas pendientes -->
  <section class="card" aria-labelledby="tasks-title">
    <header class="card__header">
      <h3 class="card__title" id="tasks-title"><i class="ri-checkbox-circle-line" aria-hidden="true"></i> Tareas pendientes</h3>
      <?php if ($doneCount > 0): ?>
        <span class="badge badge--success"><?= e(plural($doneCount, 'completada', 'completadas')) ?></span>
      <?php endif; ?>
    </header>

    <?php if ($pendingTasks): ?>
      <ul class="mini-list">
        <?php foreach ($pendingTasks as $task): ?>
          <?php
            $due       = $task['fecha_limite'];
            $isOverdue = $due !== null && $due < $today;
          ?>
          <li>
            <a href="tasks.php?action=edit&amp;id=<?= (int) $task['id'] ?>&amp;asignatura=<?= $id ?>"
               class="task-mini<?= $isOverdue ? ' is-overdue' : '' ?>">
              <span class="task-mini__bar" aria-hidden="true"></span>
              <span class="task-mini__body">
                <span class="task-mini__title"><?= e($task['titulo']) ?></span>
                <span class="task-mini__meta">
                  <?php if ($due === null): ?>
                    <span>Sin fecha</span>
                  <?php elseif ($isOverdue): ?>
                    <span class="task-mini__late"><i class="ri-alarm-warning-line" aria-hidden="true"></i> Venció <?= e(str_lower(relative_day($due))) ?></span>
                  <?php else: ?>
                    <span><i class="ri-calendar-line" aria-hidden="true"></i> <?= e(relative_day($due)) ?></span>
                  <?php endif; ?>
                  <?php if ($task['estado'] === 'en_progreso'): ?>
                    <span class="task-mini__status"><i class="ri-loader-4-line" aria-hidden="true"></i> En progreso</span>
                  <?php endif; ?>
                </span>
              </span>
              <span class="badge badge--<?= ['alta' => 'danger', 'media' => 'warning', 'baja' => 'info'][$task['prioridad']] ?>">
                <?= e(TASK_PRIORITIES[$task['prioridad']]) ?>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?php partial('empty', [
          'icon'        => 'ri-cup-line',
          'title'       => 'Nada pendiente',
          'text'        => 'No tienes tareas sin completar en esta asignatura.',
          'actionHref'  => 'tasks.php?action=new&asignatura=' . $id,
          'actionLabel' => 'Nueva tarea',
          'compact'     => true,
      ]); ?>
    <?php endif; ?>
    <div class="card__footer-link">
      <a href="tasks.php?asignatura=<?= $id ?>" class="card__link">Todas las tareas <i class="ri-arrow-right-s-line" aria-hidden="true"></i></a>
    </div>
  </section>

  <!-- Exámenes -->
  <section class="card" aria-labelledby="exams-title">
    <header class="card__header">
      <h3 class="card__title" id="exams-title"><i class="ri-file-list-3-line" aria-hidden="true"></i> Exámenes</h3>
    </header>

    <?php if ($nextExam !== null): ?>
      <p class="exam-next__name"><?= e($nextExam['nombre']) ?></p>
      <ul class="meta-list">
        <li><i class="ri-calendar-line" aria-hidden="true"></i> <?= e(ucfirst(format_long_date($nextExam['fecha']))) ?></li>
        <?php if ($nextExam['hora']): ?>
          <li><i class="ri-time-line" aria-hidden="true"></i> <?= e(format_time($nextExam['hora'])) ?></li>
        <?php endif; ?>
        <?php if ($nextExam['aula']): ?>
          <li><i class="ri-map-pin-line" aria-hidden="true"></i> <?= e($nextExam['aula']) ?></li>
        <?php endif; ?>
      </ul>
      <?php if ($nextExam['estado'] === 'aplazado'): ?>
        <p class="alert alert--warning mt-1"><i class="ri-time-line" aria-hidden="true"></i> Aplazado</p>
      <?php else: ?>
        <div><?php partial('countdown', ['countdown' => $countdown]); ?></div>
      <?php endif; ?>
    <?php endif; ?>

    <?php $otherExams = array_merge(array_slice($upcoming, 1), $past); ?>
    <?php if ($otherExams): ?>
      <ul class="mini-list<?= $nextExam !== null ? ' mt-2' : '' ?>">
        <?php foreach ($otherExams as $exam): ?>
          <li>
            <a href="exams.php?action=edit&amp;id=<?= (int) $exam['id'] ?>&amp;asignatura=<?= $id ?>" class="task-mini">
              <span class="task-mini__bar" aria-hidden="true"></span>
              <span class="task-mini__body">
                <span class="task-mini__title"><?= e($exam['nombre']) ?></span>
                <span class="task-mini__meta">
                  <span><i class="ri-calendar-line" aria-hidden="true"></i> <?= e(format_date($exam['fecha'])) ?></span>
                </span>
              </span>
              <?php if ($exam['estado'] === 'pendiente' && $exam['fecha'] < $today): ?>
                <span class="badge badge--warning">Sin marcar</span>
              <?php else: ?>
                <span class="badge badge--<?= $statusBadge[$exam['estado']] ?>"><?= e(EXAM_STATUSES[$exam['estado']]) ?></span>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php elseif ($nextExam === null): ?>
      <?php partial('empty', [
          'icon'        => 'ri-calendar-check-line',
          'title'       => 'Sin exámenes',
          'text'        => 'Añade los exámenes de esta asignatura para ver la cuenta atrás.',
          'actionHref'  => 'exams.php?action=new&asignatura=' . $id,
          'actionLabel' => 'Añadir examen',
          'compact'     => true,
      ]); ?>
    <?php endif; ?>
    <div class="card__footer-link">
      <a href="exams.php?asignatura=<?= $id ?>" class="card__link">Ver exámenes <i class="ri-arrow-right-s-line" aria-hidden="true"></i></a>
    </div>
  </section>

  <!-- Notas -->
  <section class="card" aria-labelledby="grades-title">
    <header class="card__header">
      <h3 class="card__title" id="grades-title"><i class="ri-award-line" aria-hidden="true"></i> Notas</h3>
      <?php if ($average !== null): ?>
        <span class="grade-pill grade-pill--sm grade-pill--<?= grade_passed($average) ? 'pass' : 'fail' ?>"
              title="Media de la asignatura">
          <?= e(format_grade($average)) ?>
        </span>
      <?php endif; ?>
    </header>

    <?php if ($grades): ?>
      <ul class="mini-list">
        <?php foreach ($grades as $grade): ?>
          <li class="grade-mini">
            <span class="grade-pill grade-pill--<?= grade_passed($grade['nota']) ? 'pass' : 'fail' ?>">
              <?= e(format_grade($grade['nota'])) ?>
            </span>
            <span class="grade-mini__body">
              <a href="grades.php?action=edit&amp;id=<?= (int) $grade['id'] ?>&amp;asignatura=<?= $id ?>" class="grade-mini__subject">
                <?= e(GRADE_TYPES[$grade['tipo']] ?? $grade['tipo']) ?>
              </a>
              <span class="grade-mini__meta">
                <?= e(format_date($grade['fecha'])) ?><?= $grade['comentarios'] ? ' · ' . e(truncate($grade['comentarios'], 60)) : '' ?>
              </span>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?php partial('empty', [
          'icon'        => 'ri-award-line',
          'title'       => 'Aún no hay notas',
          'text'        => 'Registra tus calificaciones para calcular la media.',
          'actionHref'  => 'grades.php?action=new&asignatura=' . $id,
          'actionLabel' => 'Añadir nota',
          'compact'     => true,
      ]); ?>
    <?php endif; ?>
    <div class="card__footer-link">
      <a href="grades.php?asignatura=<?= $id ?>" class="card__link">Ver notas <i class="ri-arrow-right-s-line" aria-hidden="true"></i></a>
    </div>
  </section>

  <!-- Horario -->
  <section class="card" aria-labelledby="classes-title">
    <header class="card__header">
      <h3 class="card__title" id="classes-title"><i class="ri-time-line" aria-hidden="true"></i> Horario</h3>
    </header>

    <?php if ($classes): ?>
      <ul class="mini-list">
        <?php foreach ($classes as $class): ?>
          <li class="class-mini">
            <span class="class-mini__day"><?= e(ucfirst(DAY_NAMES[(int) $class['dia_semana']])) ?></span>
            <span class="class-mini__time">
              <?= e(format_time($class['hora_inicio'])) ?> – <?= e(format_time($class['hora_fin'])) ?>
              <?php if ($class['aula']): ?> · <?= e($class['aula']) ?><?php endif; ?>
            </span>
            <a href="schedule.php?action=edit&amp;id=<?= (int) $class['id'] ?>" class="icon-btn icon-btn--sm"
               aria-label="Editar la clase del <?= e(DAY_NAMES[(int) $class['dia_semana']]) ?>" title="Editar">
              <i class="ri-pencil-line" aria-hidden="true"></i>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?php partial('empty', [
          'icon'        => 'ri-time-line',
          'title'       => 'Sin clases en el horario',
          'text'        => 'Añade cuándo tienes clase de esta asignatura.',
          'actionHref'  => 'schedule.php?action=new&asignatura=' . $id,
          'actionLabel' => 'Añadir clase',
          'compact'     => true,
      ]); ?>
    <?php endif; ?>
    <div class="card__footer-link">
      <a href="schedule.php" class="card__link">Ver horario <i class="ri-arrow-right-s-line" aria-hidden="true"></i></a>
    </div>
  </section>
</div>
