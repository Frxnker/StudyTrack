<?php
/**
 * Vista: calendario mensual.
 *
 *  - Escritorio y tablet (≥768px): cuadrícula del mes; debajo, el panel con lo que hay
 *    el día seleccionado. Cada día enlaza a ?dia=AAAA-MM-DD y js/calendar.js cambia
 *    el panel sin recargar la página.
 *  - Móvil: sin cuadrícula; la agenda muestra en lista todos los días del mes con algo.
 *
 * El panel y la agenda son el mismo bloque (.agenda): en escritorio el CSS solo deja
 * visible el día seleccionado.
 *
 * @var string      $monthTitle
 * @var string      $monthKey       AAAA-MM
 * @var string      $prevMonth      AAAA-MM
 * @var string      $nextMonth      AAAA-MM
 * @var bool        $isCurrent
 * @var array       $weeks          [[date, day, inMonth, events, label], …] × semanas
 * @var array       $agenda         [fecha => eventos] del mes
 * @var string|null $selected       Día seleccionado (AAAA-MM-DD)
 * @var string      $selectedLabel  "Jueves, 15 de octubre"
 * @var int         $examCount
 * @var int         $taskCount
 * @var string      $today
 *
 * Cada evento: type (exam|task), title, subject, color, time, place, done, status, url.
 */

$maxEvents = 3; // Eventos visibles por día en la cuadrícula
$weekdays  = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$icons     = ['exam' => 'ri-file-list-3-fill', 'task' => 'ri-checkbox-circle-line'];
$dayUrl    = static fn (string $date): string => 'calendar.php?mes=' . substr($date, 0, 7) . '&dia=' . $date . '#agenda';
$selectedHasEvents = $selected !== null && isset($agenda[$selected]);
?>
<header class="page-header">
  <div>
    <h2 class="page-header__title"><?= e($monthTitle) ?></h2>
    <p class="page-header__subtitle">
      <?= e(plural($examCount, 'examen', 'exámenes')) ?> · <?= e(plural($taskCount, 'entrega', 'entregas')) ?> este mes
    </p>
  </div>
  <nav class="page-header__actions month-nav" aria-label="Cambiar de mes">
    <a href="calendar.php?mes=<?= e($prevMonth) ?>" class="icon-btn" aria-label="Mes anterior" title="Mes anterior">
      <i class="ri-arrow-left-s-line" aria-hidden="true"></i>
    </a>
    <a href="calendar.php" class="btn btn--secondary btn--sm"<?= $isCurrent ? ' aria-current="date"' : '' ?>>Hoy</a>
    <a href="calendar.php?mes=<?= e($nextMonth) ?>" class="icon-btn" aria-label="Mes siguiente" title="Mes siguiente">
      <i class="ri-arrow-right-s-line" aria-hidden="true"></i>
    </a>
  </nav>
</header>

<div class="calendar-layout">
  <!-- Cuadrícula (oculta en móvil) -->
  <section class="card calendar-card" aria-label="Calendario de <?= e($monthTitle) ?>">
    <table class="calendar" data-calendar>
      <thead>
        <tr>
          <?php foreach ($weekdays as $i => $name): ?>
            <th scope="col"><abbr title="<?= e(ucfirst(DAY_NAMES[$i + 1])) ?>"><?= e($name) ?></abbr></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($weeks as $week): ?>
          <tr>
            <?php foreach ($week as $cell): ?>
              <?php
                $isSelected = $cell['date'] === $selected;
                $classes = 'calendar__cell'
                    . ($cell['inMonth'] ? '' : ' is-other')
                    . ($cell['date'] === $today ? ' is-today' : '')
                    . ($isSelected ? ' is-selected' : '')
                    . ($cell['events'] ? ' has-events' : '');
                $label = $cell['label']
                    . ($cell['events'] ? ': ' . plural(count($cell['events']), 'evento', 'eventos') : ': sin eventos');
              ?>
              <td class="<?= $classes ?>" data-date="<?= e($cell['date']) ?>" data-label="<?= e($cell['label']) ?>">
                <a href="<?= e($dayUrl($cell['date'])) ?>" class="calendar__day"
                   aria-label="<?= e(($cell['date'] === $today ? 'Hoy, ' : '') . $label) ?>"
                   <?= $isSelected ? 'aria-current="true"' : '' ?>><?= $cell['day'] ?></a>

                <?php if ($cell['events']): ?>
                  <ul class="calendar__events">
                    <?php foreach (array_slice($cell['events'], 0, $maxEvents) as $event): ?>
                      <li>
                        <a href="<?= e($event['url']) ?>"
                           class="calendar__event calendar__event--<?= $event['type'] ?><?= $event['done'] ? ' is-done' : '' ?>"
                           style="--subject: <?= e(safe_color($event['color'])) ?>"
                           title="<?= e(($event['type'] === 'exam' ? 'Examen: ' : 'Tarea: ') . $event['title']) ?>">
                          <i class="<?= $icons[$event['type']] ?>" aria-hidden="true"></i>
                          <span class="calendar__event-text"><?= e($event['title']) ?></span>
                        </a>
                      </li>
                    <?php endforeach; ?>
                    <?php if (count($cell['events']) > $maxEvents): ?>
                      <li class="calendar__more">
                        <a href="<?= e($dayUrl($cell['date'])) ?>" data-select-day>+<?= count($cell['events']) - $maxEvents ?> más</a>
                      </li>
                    <?php endif; ?>
                  </ul>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <ul class="calendar-legend" aria-label="Leyenda">
      <li><i class="<?= $icons['exam'] ?>" aria-hidden="true"></i> Examen</li>
      <li><i class="<?= $icons['task'] ?>" aria-hidden="true"></i> Fecha límite de tarea</li>
      <li class="calendar-legend__hint">Pulsa un día para ver lo que tienes</li>
    </ul>
  </section>

  <!-- Agenda del mes (móvil) / día seleccionado (escritorio) -->
  <section class="card agenda" id="agenda" data-agenda aria-labelledby="agenda-title">
    <header class="card__header agenda__header">
      <h3 class="card__title" id="agenda-title">
        <i class="ri-list-unordered" aria-hidden="true"></i>
        <span class="agenda__title-month">Agenda del mes</span>
        <span class="agenda__title-day" data-day-title aria-live="polite"><?= e($selectedLabel ?: 'Ningún día seleccionado') ?></span>
      </h3>
      <div class="agenda__actions"<?= $selected === null ? ' hidden' : '' ?> data-day-actions>
        <a href="tasks.php?action=new&amp;fecha=<?= e((string) $selected) ?>" class="btn btn--secondary btn--sm" data-add="tasks">
          <i class="ri-add-line" aria-hidden="true"></i> Tarea
        </a>
        <a href="exams.php?action=new&amp;fecha=<?= e((string) $selected) ?>" class="btn btn--secondary btn--sm" data-add="exams">
          <i class="ri-add-line" aria-hidden="true"></i> Examen
        </a>
      </div>
    </header>

    <?php foreach ($agenda as $date => $dayEvents): ?>
      <section class="agenda__day<?= $date === $today ? ' is-today' : '' ?><?= $date < $today ? ' is-past' : '' ?><?= $date === $selected ? ' is-selected' : '' ?>"
               id="dia-<?= e($date) ?>" aria-labelledby="agenda-<?= e($date) ?>">
        <h4 class="agenda__date" id="agenda-<?= e($date) ?>">
          <?= e(ucfirst(format_long_date($date))) ?>
          <?php if ($date === $today): ?><span class="badge badge--primary">Hoy</span><?php endif; ?>
        </h4>
        <ul class="mini-list">
          <?php foreach ($dayEvents as $event): ?>
            <li>
              <a href="<?= e($event['url']) ?>" class="task-mini<?= $event['done'] ? ' is-done' : '' ?>"
                 style="--subject: <?= e(safe_color($event['color'])) ?>">
                <span class="task-mini__bar" aria-hidden="true"></span>
                <span class="task-mini__body">
                  <span class="task-mini__title"><?= e($event['title']) ?></span>
                  <span class="task-mini__meta">
                    <span><i class="<?= $icons[$event['type']] ?>" aria-hidden="true"></i> <?= $event['type'] === 'exam' ? 'Examen' : 'Tarea' ?></span>
                    <?php if ($event['subject'] !== null): ?>
                      <span><span class="dot" aria-hidden="true"></span><?= e($event['subject']) ?></span>
                    <?php endif; ?>
                    <?php if ($event['time']): ?>
                      <span><i class="ri-time-line" aria-hidden="true"></i> <?= e($event['time']) ?></span>
                    <?php endif; ?>
                    <?php if ($event['place']): ?>
                      <span><i class="ri-map-pin-line" aria-hidden="true"></i> <?= e($event['place']) ?></span>
                    <?php endif; ?>
                    <?php if ($event['done']): ?>
                      <span><?= e($event['status']) ?></span>
                    <?php endif; ?>
                  </span>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>

    <!-- Escritorio: día seleccionado sin nada, o ningún día seleccionado -->
    <div class="agenda__day agenda__day--empty<?= !$selectedHasEvents ? ' is-selected' : '' ?>" data-empty-day>
      <p class="agenda__empty-text" data-empty-text>
        <?= $selected === null
            ? 'Pulsa un día del calendario para ver lo que tienes.'
            : 'No tienes exámenes ni entregas este día.' ?>
      </p>
    </div>

    <!-- Móvil: mes sin nada -->
    <?php if (!$agenda): ?>
      <div class="agenda__month-empty">
        <?php partial('empty', [
            'icon'    => 'ri-calendar-line',
            'title'   => 'Mes despejado',
            'text'    => 'No hay exámenes ni entregas este mes.',
            'compact' => true,
        ]); ?>
      </div>
    <?php endif; ?>
  </section>
</div>
