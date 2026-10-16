<?php
/**
 * Vista: horario semanal.
 *  - Escritorio (≥1024px): rejilla con una columna por día; cada bloque se coloca
 *    según su hora (--top y --len en minutos, el CSS los convierte a píxeles).
 *  - Móvil: lista de clases agrupadas por día.
 * El CSS muestra solo una de las dos versiones.
 *
 * Las horas seguidas de la misma asignatura se muestran unidas (un bloque); el
 * recreo (SCHOOL_BREAKS) aparece como una banda propia entre las clases. Hoy se
 * marcan la clase actual ("Ahora") y la siguiente ("Después").
 *
 * @var array $days           [1..7 => clases del día]
 * @var array $blocks         [1..7 => bloques del día (schedule_merge_blocks)]
 * @var int[] $visibleDays    Días que aparecen en la rejilla
 * @var int   $gridStart      Inicio de la rejilla en minutos (480 = 08:00)
 * @var int   $gridEnd        Fin de la rejilla en minutos (870 = 14:30)
 * @var array $breaks         Descansos dentro de la rejilla (schedule_breaks)
 * @var array $nowNext        ['ahora' => índice, 'despues' => índice, 'descanso' => …] de hoy
 * @var int   $classCount
 * @var int   $weeklyMinutes
 * @var bool  $hasSubjects
 * @var int   $today          Día de la semana actual (1 = lunes)
 */

$weeklyHours = format_number($weeklyMinutes / 60, 1);

// Bloque actual y siguiente de hoy, por id de su primera franja
$nowId  = $nowNext['ahora'] !== null ? (int) $blocks[$today][$nowNext['ahora']]['id'] : null;
$nextId = $nowNext['despues'] !== null ? (int) $blocks[$today][$nowNext['despues']]['id'] : null;

// Una hora suelta se edita directamente; un bloque de varias horas lleva a la
// asignatura, donde cada hora tiene su botón de editar.
$blockUrl = static fn (array $b): string => count($b['franjas']) > 1
    ? 'subjects.php?action=show&id=' . (int) $b['asignatura_id'] . '#horario'
    : 'schedule.php?action=edit&id=' . (int) $b['id'];

$blockMinutes = static fn (array $b): int => time_to_minutes($b['hora_fin']) - time_to_minutes($b['hora_inicio']);
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
    <?php foreach ($breaks as $break): ?>
      <p class="sr-only"><?= e($break['texto']) ?> de <?= e($break['inicio']) ?> a <?= e($break['fin']) ?> todos los días.</p>
    <?php endforeach; ?>

    <div class="timetable" style="--cols: <?= count($visibleDays) ?>; --hours: <?= ($gridEnd - $gridStart) / 60 ?>">
      <div class="timetable__corner" aria-hidden="true"></div>
      <?php foreach ($visibleDays as $day): ?>
        <div class="timetable__head<?= $day === $today ? ' is-today' : '' ?>">
          <?= e(ucfirst(DAY_NAMES[$day])) ?>
        </div>
      <?php endforeach; ?>

      <div class="timetable__times" aria-hidden="true">
        <?php for ($minute = $gridStart; $minute < $gridEnd; $minute += 60): ?>
          <span style="--row: <?= ($minute - $gridStart) / 60 ?>"><?= sprintf('%02d:00', intdiv($minute, 60)) ?></span>
        <?php endfor; ?>
      </div>

      <?php foreach ($visibleDays as $day): ?>
        <div class="timetable__col<?= $day === $today ? ' is-today' : '' ?>">
          <?php foreach ($breaks as $break): ?>
            <div class="timetable__break" aria-hidden="true"
                 style="--top: <?= $break['desde'] - $gridStart ?>; --len: <?= $break['hasta'] - $break['desde'] ?>">
              <i class="ri-cup-line"></i> <?= e($break['texto']) ?>
            </div>
          <?php endforeach; ?>

          <?php foreach ($blocks[$day] as $block): ?>
            <?php
              $start  = time_to_minutes($block['hora_inicio']);
              $isNow  = $day === $today && (int) $block['id'] === $nowId;
              $isNext = $day === $today && (int) $block['id'] === $nextId;
              $hoursN = count($block['franjas']);
              $when   = DAY_NAMES[$day] . ' de ' . format_time($block['hora_inicio']) . ' a ' . format_time($block['hora_fin']);
            ?>
            <a href="<?= e($blockUrl($block)) ?>"
               class="timetable__class<?= $isNow ? ' is-now' : '' ?><?= $isNext ? ' is-next' : '' ?>"
               style="--subject: <?= e(safe_color($block['color'])) ?>; --top: <?= $start - $gridStart ?>; --len: <?= $blockMinutes($block) ?>"
               title="<?= e($block['asignatura'] . ', ' . $when . ($hoursN > 1 ? " ({$hoursN} horas): ver sus horas" : ': editar')) ?>">
              <span class="timetable__name">
                <?php if ($isNow): ?>
                  <span class="timetable__badge timetable__badge--now">Ahora</span>
                <?php elseif ($isNext): ?>
                  <span class="timetable__badge">Después</span>
                <?php endif; ?>
                <?= e($block['asignatura']) ?>
              </span>
              <span class="timetable__meta">
                <?= e(format_time($block['hora_inicio'])) ?> – <?= e(format_time($block['hora_fin'])) ?>
                <?php if ($block['aula']): ?> · <?= e($block['aula']) ?><?php endif; ?>
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
        $dayName    = DAY_NAMES[$day];
        $dayBlocks  = $blocks[$day];
        $dayMinutes = array_sum(array_map($blockMinutes, $dayBlocks));
      ?>
      <details class="card card--flush day-list__day<?= $day === $today ? ' is-today' : '' ?>"<?= $day === $openDay ? ' open' : '' ?>>
        <summary class="day-list__summary">
          <span class="day-list__name"><?= e(ucfirst($dayName)) ?></span>
          <?php if ($day === $today): ?><span class="badge badge--primary">Hoy</span><?php endif; ?>
          <span class="day-list__count">
            <?php if ($dayBlocks): ?>
              <?= e(format_number($dayMinutes / 60, 1)) ?> h ·
              <?= e(format_time($dayBlocks[0]['hora_inicio'])) ?>–<?= e(format_time(end($dayBlocks)['hora_fin'])) ?>
            <?php else: ?>
              Sin clases
            <?php endif; ?>
          </span>
          <i class="ri-arrow-down-s-line day-list__chevron" aria-hidden="true"></i>
        </summary>

        <?php if ($dayBlocks): ?>
          <ul class="class-list">
            <?php foreach (schedule_day_items($dayBlocks) as $item): ?>
              <?php if ($item['tipo'] === 'descanso'): ?>
                <?php $isBreakNow = $day === $today && $nowNext['descanso'] !== null && $nowNext['descanso']['inicio'] === $item['inicio']; ?>
                <li class="class-break<?= $isBreakNow ? ' is-now' : '' ?>">
                  <span class="class-row__time">
                    <strong><?= e($item['inicio']) ?></strong>
                    <span><?= e($item['fin']) ?></span>
                  </span>
                  <span class="class-break__label"><i class="ri-cup-line" aria-hidden="true"></i> <?= e($item['texto']) ?></span>
                  <?php if ($isBreakNow): ?><span class="badge badge--primary">Ahora</span><?php endif; ?>
                </li>
                <?php continue; ?>
              <?php endif; ?>
              <?php
                $isNow  = $day === $today && (int) $item['id'] === $nowId;
                $isNext = $day === $today && (int) $item['id'] === $nextId;
                $hoursN = count($item['franjas']);
              ?>
              <li class="class-row<?= $isNow ? ' is-now' : '' ?>" style="--subject: <?= e(safe_color($item['color'])) ?>">
                <span class="class-row__time">
                  <strong><?= e(format_time($item['hora_inicio'])) ?></strong>
                  <span><?= e(format_time($item['hora_fin'])) ?></span>
                </span>
                <span class="class-row__bar" aria-hidden="true"></span>
                <a href="<?= e($blockUrl($item)) ?>" class="class-row__body">
                  <span class="class-row__name">
                    <?= e($item['asignatura']) ?>
                    <?php if ($isNow): ?>
                      <span class="badge badge--primary">Ahora</span>
                    <?php elseif ($isNext): ?>
                      <span class="badge">Después</span>
                    <?php endif; ?>
                  </span>
                  <span class="class-row__meta">
                    <?php if ($hoursN > 1): ?><span><i class="ri-time-line" aria-hidden="true"></i> <?= $hoursN ?> h</span><?php endif; ?>
                    <?php if ($item['aula']): ?><span><i class="ri-map-pin-line" aria-hidden="true"></i> <?= e($item['aula']) ?></span><?php endif; ?>
                    <?php if ($item['profesor']): ?><span><i class="ri-user-line" aria-hidden="true"></i> <?= e($item['profesor']) ?></span><?php endif; ?>
                  </span>
                </a>
                <?php if ($hoursN === 1): ?>
                  <?php partial('delete', [
                      'action'   => 'schedule.php',
                      'id'       => (int) $item['id'],
                      'label'    => 'Eliminar la clase de ' . $item['asignatura'] . ' del ' . $dayName,
                      'question' => '¿Eliminar la clase de ' . $item['asignatura'] . '?',
                      'text'     => ucfirst($dayName) . ' de ' . format_time($item['hora_inicio']) . ' a '
                                    . format_time($item['hora_fin']) . '. Esta acción no se puede deshacer.',
                  ]); ?>
                <?php endif; ?>
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
