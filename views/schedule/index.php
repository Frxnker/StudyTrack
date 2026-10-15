<?php
/**
 * Vista: horario semanal.
 *  - Escritorio (≥1024px): rejilla con una columna por día; cada clase se coloca
 *    según su hora (--top y --len en minutos, el CSS los convierte a píxeles).
 *  - Móvil: lista de clases agrupadas por día.
 * El CSS muestra solo una de las dos versiones.
 *
 * @var array $days           [1..7 => clases del día]
 * @var int[] $visibleDays    Días que aparecen en la rejilla
 * @var int   $firstHour      Primera hora de la rejilla
 * @var int   $lastHour       Última hora de la rejilla
 * @var int   $classCount
 * @var int   $weeklyMinutes
 * @var bool  $hasSubjects
 * @var int   $today          Día de la semana actual (1 = lunes)
 */

$hours = $lastHour - $firstHour;
$weeklyHours = format_number($weeklyMinutes / 60, 1);
?>
<header class="page-header">
  <div>
    <h2 class="page-header__title">Mi horario</h2>
    <p class="page-header__subtitle">
      <?php if ($classCount > 0): ?>
        <?= e(plural($classCount, 'clase', 'clases')) ?> · <?= e($weeklyHours) ?> h a la semana
      <?php else: ?>
        Añade tus clases para tener tu semana a la vista.
      <?php endif; ?>
    </p>
  </div>
  <?php if ($hasSubjects): ?>
    <div class="page-header__actions hide-mobile">
      <a href="schedule.php?action=new" class="btn btn--primary">
        <i class="ri-add-line" aria-hidden="true"></i> Nueva clase
      </a>
    </div>
  <?php endif; ?>
</header>

<?php if (!$hasSubjects): ?>
  <section class="card">
    <?php partial('empty', [
        'icon'        => 'ri-book-2-line',
        'title'       => 'Primero crea una asignatura',
        'text'        => 'Cada clase del horario pertenece a una asignatura.',
        'actionHref'  => 'subjects.php?action=new',
        'actionLabel' => 'Crear asignatura',
    ]); ?>
  </section>
<?php elseif ($classCount === 0): ?>
  <section class="card">
    <?php partial('empty', [
        'icon'        => 'ri-time-line',
        'title'       => 'Tu horario está vacío',
        'text'        => 'Añade tus clases con su día, hora y aula.',
        'actionHref'  => 'schedule.php?action=new',
        'actionLabel' => 'Añadir clase',
    ]); ?>
  </section>
<?php else: ?>

  <!-- ============ Escritorio: rejilla semanal ============ -->
  <section class="card timetable-card" aria-label="Horario semanal">
    <div class="timetable" style="--cols: <?= count($visibleDays) ?>; --hours: <?= $hours ?>">
      <div class="timetable__corner" aria-hidden="true"></div>
      <?php foreach ($visibleDays as $day): ?>
        <div class="timetable__head<?= $day === $today ? ' is-today' : '' ?>">
          <?= e(ucfirst(DAY_NAMES[$day])) ?>
        </div>
      <?php endforeach; ?>

      <div class="timetable__times" aria-hidden="true">
        <?php for ($h = $firstHour; $h < $lastHour; $h++): ?>
          <span style="--row: <?= $h - $firstHour ?>"><?= sprintf('%02d:00', $h) ?></span>
        <?php endfor; ?>
      </div>

      <?php foreach ($visibleDays as $day): ?>
        <div class="timetable__col<?= $day === $today ? ' is-today' : '' ?>">
          <?php foreach ($days[$day] as $class): ?>
            <?php
              $start = time_to_minutes($class['hora_inicio']);
              $end   = time_to_minutes($class['hora_fin']);
            ?>
            <a href="schedule.php?action=edit&amp;id=<?= (int) $class['id'] ?>" class="timetable__class"
               style="--subject: <?= e(safe_color($class['color'])) ?>; --top: <?= $start - $firstHour * 60 ?>; --len: <?= $end - $start ?>"
               title="Editar: <?= e($class['asignatura']) ?>, <?= e(DAY_NAMES[$day]) ?> de <?= e(format_time($class['hora_inicio'])) ?> a <?= e(format_time($class['hora_fin'])) ?>">
              <span class="timetable__name"><?= e($class['asignatura']) ?></span>
              <span class="timetable__meta">
                <?= e(format_time($class['hora_inicio'])) ?> – <?= e(format_time($class['hora_fin'])) ?>
                <?php if ($class['aula']): ?> · <?= e($class['aula']) ?><?php endif; ?>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ============ Móvil: lista por día (plegable, con hoy abierto) ============ -->
  <?php
    // De lunes a viernes, y sábado/domingo solo si tienen clases
    $listDays = array_values(array_filter(array_keys(DAY_NAMES), static fn (int $d): bool => $d <= 5 || $days[$d] !== []));
    // Se abre hoy; si hoy no aparece (fin de semana sin clases), el siguiente día con clases
    $openDay = in_array($today, $listDays, true) ? $today : null;
    for ($i = 1; $openDay === null && $i <= 7; $i++) {
        $candidate = ($today - 1 + $i) % 7 + 1;
        if ($days[$candidate] !== []) {
            $openDay = $candidate;
        }
    }
  ?>
  <div class="day-list">
    <?php foreach ($listDays as $day): ?>
      <?php
        $dayName  = DAY_NAMES[$day];
        $dayItems = $days[$day];
      ?>
      <details class="card card--flush day-list__day<?= $day === $today ? ' is-today' : '' ?>"<?= $day === $openDay ? ' open' : '' ?>>
        <summary class="day-list__summary">
          <span class="day-list__name"><?= e(ucfirst($dayName)) ?></span>
          <?php if ($day === $today): ?><span class="badge badge--primary">Hoy</span><?php endif; ?>
          <span class="day-list__count">
            <?php if ($dayItems): ?>
              <?= e(plural(count($dayItems), 'clase', 'clases')) ?> ·
              <?= e(format_time($dayItems[0]['hora_inicio'])) ?>–<?= e(format_time(end($dayItems)['hora_fin'])) ?>
            <?php else: ?>
              Sin clases
            <?php endif; ?>
          </span>
          <i class="ri-arrow-down-s-line day-list__chevron" aria-hidden="true"></i>
        </summary>

        <?php if ($dayItems): ?>
          <ul class="class-list">
            <?php foreach ($dayItems as $class): ?>
              <li class="class-row" style="--subject: <?= e(safe_color($class['color'])) ?>">
                <span class="class-row__time">
                  <strong><?= e(format_time($class['hora_inicio'])) ?></strong>
                  <span><?= e(format_time($class['hora_fin'])) ?></span>
                </span>
                <span class="class-row__bar" aria-hidden="true"></span>
                <a href="schedule.php?action=edit&amp;id=<?= (int) $class['id'] ?>" class="class-row__body">
                  <span class="class-row__name"><?= e($class['asignatura']) ?></span>
                  <?php if ($class['aula'] || $class['profesor']): ?>
                    <span class="class-row__meta">
                      <?php if ($class['aula']): ?><span><i class="ri-map-pin-line" aria-hidden="true"></i> <?= e($class['aula']) ?></span><?php endif; ?>
                      <?php if ($class['profesor']): ?><span><i class="ri-user-line" aria-hidden="true"></i> <?= e($class['profesor']) ?></span><?php endif; ?>
                    </span>
                  <?php endif; ?>
                </a>
                <?php partial('delete', [
                    'action'   => 'schedule.php',
                    'id'       => (int) $class['id'],
                    'label'    => 'Eliminar la clase de ' . $class['asignatura'] . ' del ' . $dayName,
                    'question' => '¿Eliminar la clase de ' . $class['asignatura'] . '?',
                    'text'     => ucfirst($dayName) . ' de ' . format_time($class['hora_inicio']) . ' a '
                                  . format_time($class['hora_fin']) . '. Esta acción no se puede deshacer.',
                ]); ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="day-list__empty">
            No tienes clases este día. <a href="schedule.php?action=new&amp;dia=<?= $day ?>">Añadir una</a>
          </p>
        <?php endif; ?>
      </details>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
