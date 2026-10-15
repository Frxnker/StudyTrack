<?php
/**
 * Vista: dashboard.
 *
 * En móvil todas las tarjetas forman una sola columna (el orden lo marca el CSS).
 * En escritorio se reparten en dos columnas: principal (.dashboard__main) y lateral (.dashboard__side).
 *
 * @var array      $currentUser
 * @var string     $summary
 * @var string     $today
 * @var array      $taskCounts      total, completadas, pendientes, atrasadas, hoy
 * @var array      $todayTasks
 * @var array|null $nextExam
 * @var array|null $countdown
 * @var array      $latestGrades
 * @var array      $subjects
 * @var float|null $overallAverage
 * @var array      $goals           total, completados, progreso_medio
 * @var array      $week            total, completadas, porcentaje, por_dia, examenes, notas, rango
 * @var int        $gradedCount
 * @var int        $passedCount
 */

$priorityBadge = ['alta' => 'danger', 'media' => 'warning', 'baja' => 'info'];
$dayLetters    = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
$maxPerDay     = max([1, ...array_values($week['por_dia'])]);

// Anillo de la media general (SVG): circunferencia = 2·π·r
$ringRadius = 42;
$ringCirc   = 2 * M_PI * $ringRadius;
$ringValue  = $overallAverage === null ? 0 : min($overallAverage, 10) / 10;
$ringOffset = $ringCirc * (1 - $ringValue);

$completedPct = $taskCounts['total'] > 0 ? (int) round($taskCounts['completadas'] / $taskCounts['total'] * 100) : 0;
$passedPct    = $gradedCount > 0 ? (int) round($passedCount / $gradedCount * 100) : 0;
?>
<div class="dashboard">

  <!-- ===================== Columna principal ===================== -->
  <div class="dashboard__main">

    <section class="hero dash-hero">
      <p class="hero__eyebrow"><?= e(format_long_date()) ?></p>
      <h2 class="hero__title"><?= e(greeting()) ?>, <?= e(first_name($currentUser['nombre'])) ?> 👋</h2>
      <p class="hero__text"><?= e($summary) ?></p>
      <div class="hero__actions">
        <?php if ($subjects): ?>
          <a href="tasks.php?action=new" class="btn btn--glass btn--sm">
            <i class="ri-add-line" aria-hidden="true"></i> Nueva tarea
          </a>
          <a href="grades.php?action=new" class="btn btn--glass btn--sm">
            <i class="ri-award-line" aria-hidden="true"></i> Añadir nota
          </a>
        <?php else: ?>
          <a href="subjects.php?action=new" class="btn btn--glass btn--sm">
            <i class="ri-book-2-line" aria-hidden="true"></i> Crear mi primera asignatura
          </a>
        <?php endif; ?>
      </div>
    </section>

    <section class="stats dash-stats" aria-label="Resumen rápido">
      <a href="tasks.php?estado=abiertas" class="stat stat--primary">
        <span class="stat__icon"><i class="ri-list-check-3" aria-hidden="true"></i></span>
        <span class="stat__value" data-count-up="<?= $taskCounts['pendientes'] ?>"><?= $taskCounts['pendientes'] ?></span>
        <span class="stat__label">Tareas pendientes</span>
      </a>
      <a href="tasks.php?vista=hoy" class="stat stat--warning">
        <span class="stat__icon"><i class="ri-sun-line" aria-hidden="true"></i></span>
        <span class="stat__value" data-count-up="<?= $taskCounts['hoy'] ?>"><?= $taskCounts['hoy'] ?></span>
        <span class="stat__label">Para hoy</span>
      </a>
      <a href="tasks.php?vista=atrasadas" class="stat stat--danger">
        <span class="stat__icon"><i class="ri-alarm-warning-line" aria-hidden="true"></i></span>
        <span class="stat__value" data-count-up="<?= $taskCounts['atrasadas'] ?>"><?= $taskCounts['atrasadas'] ?></span>
        <span class="stat__label">Atrasadas</span>
      </a>
      <a href="grades.php" class="stat stat--success">
        <span class="stat__icon"><i class="ri-medal-line" aria-hidden="true"></i></span>
        <?php if ($overallAverage === null): ?>
          <span class="stat__value">—</span>
        <?php else: ?>
          <span class="stat__value" data-count-up="<?= round($overallAverage, 2) ?>" data-decimals="2"><?= e(format_grade($overallAverage)) ?></span>
        <?php endif; ?>
        <span class="stat__label">Media general</span>
      </a>
    </section>

    <section class="card dash-today">
      <header class="card__header">
        <h3 class="card__title"><i class="ri-sun-line" aria-hidden="true"></i> Para hoy</h3>
        <a href="tasks.php" class="card__link">Ver tareas <i class="ri-arrow-right-s-line" aria-hidden="true"></i></a>
      </header>

      <?php if ($todayTasks): ?>
        <ul class="mini-list">
          <?php foreach ($todayTasks as $task): ?>
            <?php $isOverdue = $task['fecha_limite'] < $today; ?>
            <li>
              <a href="tasks.php?action=edit&amp;id=<?= (int) $task['id'] ?>" class="task-mini<?= $isOverdue ? ' is-overdue' : '' ?>"
                 style="--subject: <?= e(safe_color($task['color'])) ?>">
                <span class="task-mini__bar" aria-hidden="true"></span>
                <span class="task-mini__body">
                  <span class="task-mini__title"><?= e($task['titulo']) ?></span>
                  <span class="task-mini__meta">
                    <?php if ($task['asignatura'] !== null): ?>
                      <span><span class="dot" aria-hidden="true"></span><?= e($task['asignatura']) ?></span>
                    <?php else: ?>
                      <span>Sin asignatura</span>
                    <?php endif; ?>
                    <?php if ($isOverdue): ?>
                      <span class="task-mini__late">
                        <i class="ri-alarm-warning-line" aria-hidden="true"></i>
                        Venció <?= e(str_lower(relative_day($task['fecha_limite']))) ?>
                      </span>
                    <?php elseif ($task['estado'] === 'en_progreso'): ?>
                      <span class="task-mini__status">
                        <i class="ri-loader-4-line" aria-hidden="true"></i> En progreso
                      </span>
                    <?php endif; ?>
                  </span>
                </span>
                <?php if ($isOverdue): ?>
                  <span class="badge badge--danger">Atrasada</span>
                <?php else: ?>
                  <span class="badge badge--<?= $priorityBadge[$task['prioridad']] ?>"><?= e(TASK_PRIORITIES[$task['prioridad']]) ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <?php partial('empty', [
            'icon'    => 'ri-cup-line',
            'title'   => '¡Nada pendiente para hoy!',
            'text'    => 'Aprovecha para descansar o adelantar trabajo.',
            'compact' => true,
        ]); ?>
      <?php endif; ?>
    </section>

    <section class="card dash-subjects">
      <header class="card__header">
        <h3 class="card__title"><i class="ri-book-2-line" aria-hidden="true"></i> Mis asignaturas</h3>
        <a href="subjects.php" class="card__link">Gestionar <i class="ri-arrow-right-s-line" aria-hidden="true"></i></a>
      </header>

      <?php if ($subjects): ?>
        <div class="subject-grid">
          <?php foreach ($subjects as $subject): ?>
            <a href="subjects.php?action=show&amp;id=<?= (int) $subject['id'] ?>" class="subject-tile" style="--subject: <?= e(safe_color($subject['color'])) ?>">
              <span class="subject-tile__name"><?= e($subject['nombre']) ?></span>
              <?php if ($subject['profesor']): ?>
                <span class="subject-tile__teacher">
                  <i class="ri-user-line" aria-hidden="true"></i> <?= e($subject['profesor']) ?>
                </span>
              <?php endif; ?>
              <span class="subject-tile__stats">
                <?php if ($subject['media'] !== null): ?>
                  <span class="badge badge--<?= grade_passed($subject['media']) ? 'success' : 'danger' ?>">
                    Media <?= e(format_grade($subject['media'])) ?>
                  </span>
                <?php else: ?>
                  <span class="badge">Sin notas</span>
                <?php endif; ?>
                <span class="badge">
                  <?= e(plural((int) $subject['tareas_pendientes'], 'tarea pendiente', 'tareas pendientes')) ?>
                </span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <?php partial('empty', [
            'icon'        => 'ri-book-2-line',
            'title'       => 'Aún no tienes asignaturas',
            'text'        => 'Crea tus asignaturas para poder asociarles tareas, exámenes y notas.',
            'actionHref'  => 'subjects.php?action=new',
            'actionLabel' => 'Crear asignatura',
            'compact'     => true,
        ]); ?>
      <?php endif; ?>
    </section>
  </div>

  <!-- ===================== Columna lateral ===================== -->
  <div class="dashboard__side">

    <section class="card dash-exam">
      <header class="card__header">
        <h3 class="card__title"><i class="ri-calendar-event-line" aria-hidden="true"></i> Próximo examen</h3>
        <a href="exams.php" class="card__link">Ver todos <i class="ri-arrow-right-s-line" aria-hidden="true"></i></a>
      </header>

      <?php if ($nextExam !== null): ?>
        <span class="chip" style="--subject: <?= e(safe_color($nextExam['color'])) ?>">
          <span class="dot" aria-hidden="true"></span><?= e($nextExam['asignatura']) ?>
        </span>
        <p class="exam-next__name"><?= e($nextExam['nombre']) ?></p>
        <ul class="meta-list">
          <li><i class="ri-calendar-line" aria-hidden="true"></i> <?= e(format_date($nextExam['fecha'])) ?></li>
          <?php if ($nextExam['hora']): ?>
            <li><i class="ri-time-line" aria-hidden="true"></i> <?= e(format_time($nextExam['hora'])) ?></li>
          <?php endif; ?>
          <?php if ($nextExam['aula']): ?>
            <li><i class="ri-map-pin-line" aria-hidden="true"></i> <?= e($nextExam['aula']) ?></li>
          <?php endif; ?>
        </ul>
        <?php partial('countdown', ['countdown' => $countdown]); ?>
      <?php else: ?>
        <?php partial('empty', [
            'icon'        => 'ri-calendar-check-line',
            'title'       => 'Sin exámenes próximos',
            'text'        => 'Cuando añadas un examen verás aquí la cuenta atrás.',
            'actionHref'  => 'exams.php?action=new',
            'actionLabel' => 'Añadir examen',
            'compact'     => true,
        ]); ?>
      <?php endif; ?>
    </section>

    <section class="card dash-week">
      <header class="card__header">
        <h3 class="card__title"><i class="ri-bar-chart-2-line" aria-hidden="true"></i> Resumen semanal</h3>
        <span class="text-soft text-xs fw-600"><?= e($week['rango']) ?></span>
      </header>

      <div class="week-progress">
        <div class="week-progress__head">
          <span class="week-progress__value"><?= $week['porcentaje'] ?>%</span>
          <span class="text-muted text-sm">
            <?= $week['completadas'] ?> de <?= e(plural($week['total'], 'tarea completada', 'tareas completadas')) ?>
          </span>
        </div>
        <div class="progress" role="progressbar" aria-label="Progreso semanal"
             aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $week['porcentaje'] ?>">
          <div class="progress__bar" style="--value: <?= $week['porcentaje'] ?>%"></div>
        </div>
      </div>

      <div class="week-chart" role="img" aria-label="Tareas completadas cada día de esta semana">
        <?php $i = 0; foreach ($week['por_dia'] as $date => $count): ?>
          <div class="week-chart__day<?= $date === $today ? ' is-today' : '' ?>"
               title="<?= e(ucfirst(format_long_date($date))) ?>: <?= e(plural($count, 'tarea completada', 'tareas completadas')) ?>">
            <span class="week-chart__track">
              <span class="week-chart__count"><?= $count > 0 ? $count : '' ?></span>
              <span class="week-chart__bar<?= $count > 0 ? ' has-value' : '' ?>"
                    style="--h: <?= (int) round($count / $maxPerDay * 80) ?>px; --i: <?= $i ?>"></span>
            </span>
            <span class="week-chart__label"><?= $dayLetters[$i] ?></span>
          </div>
        <?php $i++; endforeach; ?>
      </div>

      <ul class="week-summary">
        <li><i class="ri-checkbox-circle-line" aria-hidden="true"></i> <strong><?= array_sum($week['por_dia']) ?></strong> completadas</li>
        <li><i class="ri-file-list-3-line" aria-hidden="true"></i> <strong><?= $week['examenes'] ?></strong> <?= $week['examenes'] === 1 ? 'examen' : 'exámenes' ?></li>
        <li><i class="ri-award-line" aria-hidden="true"></i> <strong><?= $week['notas'] ?></strong> <?= $week['notas'] === 1 ? 'nota' : 'notas' ?></li>
      </ul>
    </section>

    <section class="card dash-progress">
      <header class="card__header">
        <h3 class="card__title"><i class="ri-line-chart-line" aria-hidden="true"></i> Progreso académico</h3>
        <a href="statistics.php" class="card__link">Detalles <i class="ri-arrow-right-s-line" aria-hidden="true"></i></a>
      </header>

      <div class="academic">
        <div class="ring<?= $overallAverage !== null && !grade_passed($overallAverage) ? ' ring--fail' : '' ?>"
             style="--circ: <?= round($ringCirc, 2) ?>; --offset: <?= round($ringOffset, 2) ?>">
          <svg viewBox="0 0 100 100" aria-hidden="true">
            <defs>
              <linearGradient id="ring-gradient" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#4f46e5"/>
                <stop offset="1" stop-color="#7c3aed"/>
              </linearGradient>
            </defs>
            <circle class="ring__track" cx="50" cy="50" r="<?= $ringRadius ?>"/>
            <circle class="ring__value" cx="50" cy="50" r="<?= $ringRadius ?>"/>
          </svg>
          <div class="ring__label">
            <span class="ring__number"><?= e(format_grade($overallAverage)) ?></span>
            <span class="ring__caption"><?= $overallAverage === null ? 'Sin notas' : 'Media' ?></span>
          </div>
        </div>

        <div class="academic__rows">
          <div class="progress-row">
            <div class="progress-row__head">
              <span>Asignaturas aprobadas</span>
              <span class="text-muted"><?= $passedCount ?>/<?= $gradedCount ?></span>
            </div>
            <div class="progress progress--sm"><div class="progress__bar progress__bar--success" style="--value: <?= $passedPct ?>%"></div></div>
          </div>
          <div class="progress-row">
            <div class="progress-row__head">
              <span>Tareas completadas</span>
              <span class="text-muted"><?= $taskCounts['completadas'] ?>/<?= $taskCounts['total'] ?></span>
            </div>
            <div class="progress progress--sm"><div class="progress__bar" style="--value: <?= $completedPct ?>%"></div></div>
          </div>
          <div class="progress-row">
            <div class="progress-row__head">
              <span>Objetivos</span>
              <span class="text-muted"><?= $goals['progreso_medio'] ?>%</span>
            </div>
            <div class="progress progress--sm"><div class="progress__bar progress__bar--warning" style="--value: <?= $goals['progreso_medio'] ?>%"></div></div>
          </div>
        </div>
      </div>
    </section>

    <section class="card dash-grades">
      <header class="card__header">
        <h3 class="card__title"><i class="ri-award-line" aria-hidden="true"></i> Últimas notas</h3>
        <a href="grades.php" class="card__link">Ver todas <i class="ri-arrow-right-s-line" aria-hidden="true"></i></a>
      </header>

      <?php if ($latestGrades): ?>
        <ul class="mini-list">
          <?php foreach ($latestGrades as $grade): ?>
            <li class="grade-mini">
              <span class="grade-pill grade-pill--<?= grade_passed($grade['nota']) ? 'pass' : 'fail' ?>">
                <?= e(format_grade($grade['nota'])) ?>
              </span>
              <span class="grade-mini__body">
                <span class="grade-mini__subject" style="--subject: <?= e(safe_color($grade['color'])) ?>">
                  <span class="dot" aria-hidden="true"></span><?= e($grade['asignatura']) ?>
                </span>
                <span class="grade-mini__meta">
                  <?= e(GRADE_TYPES[$grade['tipo']] ?? $grade['tipo']) ?> · <?= e(format_date($grade['fecha'])) ?>
                </span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <?php partial('empty', [
            'icon'    => 'ri-award-line',
            'title'   => 'Aún no hay notas',
            'text'    => 'Registra tus calificaciones para calcular tus medias.',
            'compact' => true,
        ]); ?>
      <?php endif; ?>
    </section>
  </div>
</div>
