<?php
/**
 * Vista: estadísticas.
 *
 * Los gráficos se dibujan con js/statistics.js dentro de cada [data-chart] a partir
 * del JSON de #stats-data. Debajo de cada gráfico hay una tabla plegable con los
 * mismos datos (lectores de pantalla y navegadores sin JavaScript).
 *
 * @var float|null $overallAverage
 * @var array      $gradeSummary  total, aprobadas
 * @var array      $taskCounts    total, completadas, pendientes, atrasadas, hoy
 * @var array      $goals         total, completados, progreso_medio
 * @var array      $chartData     subjects, timeline, weeks, statuses, passMark
 */

$completedPct = $taskCounts['total'] > 0 ? (int) round($taskCounts['completadas'] / $taskCounts['total'] * 100) : 0;
$totalTasks   = array_sum(array_column($chartData['statuses'], 'count'));
$weeksTotal   = array_sum(array_column($chartData['weeks'], 'count'));

// JSON seguro dentro de <script>: JSON_HEX_TAG evita que un texto cierre la etiqueta
$json = json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>
<header class="page-header">
  <div>
    <h2 class="page-header__title">Estadísticas</h2>
    <p class="page-header__subtitle">Cómo van tus notas y tu ritmo de trabajo.</p>
  </div>
  <div class="page-header__actions">
    <a href="grades.php" class="btn btn--secondary btn--sm"><i class="ri-award-line" aria-hidden="true"></i> Ver notas</a>
  </div>
</header>

<section class="stats mb-2" aria-label="Resumen">
  <div class="stat stat--<?= $overallAverage === null ? 'primary' : (grade_passed($overallAverage) ? 'success' : 'danger') ?>">
    <span class="stat__icon"><i class="ri-medal-line" aria-hidden="true"></i></span>
    <span class="stat__value"><?= e(format_grade($overallAverage)) ?></span>
    <span class="stat__label">Media general</span>
  </div>
  <div class="stat stat--primary">
    <span class="stat__icon"><i class="ri-checkbox-circle-line" aria-hidden="true"></i></span>
    <span class="stat__value"><?= $completedPct ?>%</span>
    <span class="stat__label">Tareas completadas (<?= $taskCounts['completadas'] ?>/<?= $taskCounts['total'] ?>)</span>
  </div>
  <div class="stat stat--info">
    <span class="stat__icon"><i class="ri-calendar-check-line" aria-hidden="true"></i></span>
    <span class="stat__value"><?= $weeksTotal ?></span>
    <span class="stat__label">Completadas en 8 semanas</span>
  </div>
  <div class="stat stat--warning">
    <span class="stat__icon"><i class="ri-focus-3-line" aria-hidden="true"></i></span>
    <span class="stat__value"><?= $goals['progreso_medio'] ?>%</span>
    <span class="stat__label">Progreso de objetivos</span>
  </div>
</section>

<script type="application/json" id="stats-data"><?= $json ?></script>

<div class="chart-grid">

  <!-- 1. Media por asignatura -->
  <section class="card chart-card" aria-labelledby="chart-subjects-title">
    <header class="card__header">
      <h3 class="card__title" id="chart-subjects-title"><i class="ri-bar-chart-horizontal-line" aria-hidden="true"></i> Media por asignatura</h3>
    </header>
    <?php if ($chartData['subjects']): ?>
      <div class="chart" data-chart="subjects"
           data-label="Gráfico de barras con la media de cada asignatura sobre 10"></div>
      <ul class="chart-legend">
        <li><span class="chart-legend__line chart-legend__line--pass" aria-hidden="true"></span> Aprobado (<?= e(format_grade(PASS_MARK)) ?>)</li>
      </ul>
      <details class="chart__data">
        <summary>Ver datos</summary>
        <table class="data-table">
          <thead><tr><th scope="col">Asignatura</th><th scope="col">Media</th><th scope="col">Notas</th></tr></thead>
          <tbody>
            <?php foreach ($chartData['subjects'] as $row): ?>
              <tr>
                <th scope="row"><?= e($row['name']) ?></th>
                <td><?= e(format_grade($row['average'])) ?></td>
                <td><?= $row['count'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </details>
    <?php else: ?>
      <?php partial('empty', [
          'icon'        => 'ri-award-line',
          'title'       => 'Aún no hay notas',
          'text'        => 'Registra notas para ver la media de cada asignatura.',
          'actionHref'  => 'grades.php?action=new',
          'actionLabel' => 'Añadir nota',
          'compact'     => true,
      ]); ?>
    <?php endif; ?>
  </section>

  <!-- 2. Evolución de las notas -->
  <section class="card chart-card" aria-labelledby="chart-timeline-title">
    <header class="card__header">
      <h3 class="card__title" id="chart-timeline-title"><i class="ri-line-chart-line" aria-hidden="true"></i> Evolución de las notas</h3>
    </header>
    <?php if ($chartData['timeline']): ?>
      <div class="chart" data-chart="timeline"
           data-label="Gráfico de líneas con cada nota a lo largo del tiempo y la media acumulada"></div>
      <ul class="chart-legend">
        <li><span class="chart-legend__dot" aria-hidden="true"></span> Nota (color de la asignatura)</li>
        <li><span class="chart-legend__line" aria-hidden="true"></span> Media acumulada</li>
        <li><span class="chart-legend__line chart-legend__line--pass" aria-hidden="true"></span> Aprobado</li>
      </ul>
      <details class="chart__data">
        <summary>Ver datos</summary>
        <table class="data-table">
          <thead><tr><th scope="col">Fecha</th><th scope="col">Asignatura</th><th scope="col">Nota</th><th scope="col">Media acumulada</th></tr></thead>
          <tbody>
            <?php foreach ($chartData['timeline'] as $row): ?>
              <tr>
                <td><?= e(format_date($row['date'])) ?></td>
                <th scope="row"><?= e($row['subject']) ?> <span class="text-soft">(<?= e(str_lower($row['type'])) ?>)</span></th>
                <td><?= e(format_grade($row['grade'])) ?></td>
                <td><?= e(format_grade($row['average'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </details>
    <?php else: ?>
      <?php partial('empty', [
          'icon'    => 'ri-line-chart-line',
          'title'   => 'Sin datos todavía',
          'text'    => 'Cuando tengas notas verás aquí su evolución.',
          'compact' => true,
      ]); ?>
    <?php endif; ?>
  </section>

  <!-- 3. Tareas completadas por semana -->
  <section class="card chart-card" aria-labelledby="chart-weeks-title">
    <header class="card__header">
      <h3 class="card__title" id="chart-weeks-title"><i class="ri-bar-chart-2-line" aria-hidden="true"></i> Tareas completadas por semana</h3>
      <span class="text-soft text-xs fw-600">Últimas 8 semanas</span>
    </header>
    <div class="chart" data-chart="weeks"
         data-label="Gráfico de columnas con las tareas completadas en cada una de las últimas 8 semanas"></div>
    <details class="chart__data">
      <summary>Ver datos</summary>
      <table class="data-table">
        <thead><tr><th scope="col">Semana</th><th scope="col">Completadas</th></tr></thead>
        <tbody>
          <?php foreach ($chartData['weeks'] as $row): ?>
            <tr>
              <th scope="row"><?= e($row['range']) ?><?= $row['current'] ? ' (esta semana)' : '' ?></th>
              <td><?= $row['count'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>
  </section>

  <!-- 4. Distribución de tareas por estado -->
  <section class="card chart-card" aria-labelledby="chart-status-title">
    <header class="card__header">
      <h3 class="card__title" id="chart-status-title"><i class="ri-pie-chart-2-line" aria-hidden="true"></i> Tareas por estado</h3>
      <?php if ($taskCounts['atrasadas'] > 0): ?>
        <a href="tasks.php?vista=atrasadas" class="badge badge--danger"><?= e(plural($taskCounts['atrasadas'], 'atrasada', 'atrasadas')) ?></a>
      <?php endif; ?>
    </header>
    <?php if ($totalTasks > 0): ?>
      <div class="donut-layout">
        <div class="chart chart--donut" data-chart="statuses"
             data-label="Gráfico circular con el reparto de las tareas por estado"></div>
        <ul class="status-legend">
          <?php foreach ($chartData['statuses'] as $row): ?>
            <li>
              <a href="tasks.php?estado=<?= e($row['key']) ?>" class="status-legend__item">
                <span class="status-legend__swatch status-legend__swatch--<?= e($row['key']) ?>" aria-hidden="true"></span>
                <span class="status-legend__label"><?= e($row['label']) ?></span>
                <span class="status-legend__value"><?= $row['count'] ?></span>
                <span class="status-legend__pct"><?= (int) round($row['count'] / $totalTasks * 100) ?>%</span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php else: ?>
      <?php partial('empty', [
          'icon'        => 'ri-checkbox-circle-line',
          'title'       => 'Aún no hay tareas',
          'text'        => 'Añade tareas para ver cómo se reparten.',
          'actionHref'  => 'tasks.php?action=new',
          'actionLabel' => 'Nueva tarea',
          'compact'     => true,
      ]); ?>
    <?php endif; ?>
  </section>
</div>
