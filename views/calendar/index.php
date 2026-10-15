<?php
/**
 * Vista: calendario mensual + agenda del mes.
 *
 *  - Escritorio: cada día muestra hasta 3 eventos con su texto; la agenda va debajo en dos columnas.
 *  - Móvil: cada día muestra solo puntos de color; al pulsarlo se salta a ese día
 *    en la agenda, que va debajo del calendario.
 *
 * @var string $monthTitle
 * @var string $prevMonth   AAAA-MM
 * @var string $nextMonth   AAAA-MM
 * @var bool   $isCurrent
 * @var array  $weeks       [[date, day, inMonth, events], …] × semanas
 * @var array  $agenda      [fecha => eventos] del mes
 * @var int    $examCount
 * @var int    $taskCount
 * @var string $today
 *
 * Cada evento: type (exam|task), title, subject, color, time, place, done, status, url.
 */

$maxEvents = 3; // Eventos visibles por día en escritorio
$weekdays = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$icons    = ['exam' => 'ri-file-list-3-fill', 'task' => 'ri-checkbox-circle-line'];
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
  <section class="card calendar-card" aria-label="Calendario de <?= e($monthTitle) ?>">
    <table class="calendar">
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
                $classes = 'calendar__cell'
                    . ($cell['inMonth'] ? '' : ' is-other')
                    . ($cell['date'] === $today ? ' is-today' : '')
                    . ($cell['events'] ? ' has-events' : '');
                $label = ucfirst(format_long_date($cell['date']))
                    . ($cell['events'] ? ': ' . plural(count($cell['events']), 'evento', 'eventos') : '');
              ?>
              <td class="<?= $classes ?>">
                <?php if ($cell['events'] && $cell['inMonth']): ?>
                  <a href="#dia-<?= e($cell['date']) ?>" class="calendar__day" aria-label="<?= e($label) ?>"><?= $cell['day'] ?></a>
                <?php else: ?>
                  <span class="calendar__day"<?= $cell['date'] === $today ? ' aria-label="Hoy, ' . e($label) . '"' : '' ?>><?= $cell['day'] ?></span>
                <?php endif; ?>

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
                        <a href="#dia-<?= e($cell['date']) ?>">+<?= count($cell['events']) - $maxEvents ?> más</a>
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
      <li><span class="calendar-legend__mark calendar-legend__mark--exam" aria-hidden="true"></span><i class="<?= $icons['exam'] ?>" aria-hidden="true"></i> Examen</li>
      <li><span class="calendar-legend__mark" aria-hidden="true"></span><i class="<?= $icons['task'] ?>" aria-hidden="true"></i> Fecha límite de tarea</li>
    </ul>
  </section>

  <aside class="card agenda" aria-labelledby="agenda-title">
    <header class="card__header">
      <h3 class="card__title" id="agenda-title"><i class="ri-list-unordered" aria-hidden="true"></i> Agenda del mes</h3>
    </header>

    <?php if ($agenda): ?>
      <?php foreach ($agenda as $date => $dayEvents): ?>
        <section class="agenda__day<?= $date === $today ? ' is-today' : '' ?><?= $date < $today ? ' is-past' : '' ?>"
                 id="dia-<?= e($date) ?>" tabindex="-1" aria-labelledby="agenda-<?= e($date) ?>">
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
    <?php else: ?>
      <?php partial('empty', [
          'icon'    => 'ri-calendar-line',
          'title'   => 'Mes despejado',
          'text'    => 'No hay exámenes ni entregas este mes.',
          'compact' => true,
      ]); ?>
    <?php endif; ?>
  </aside>
</div>
