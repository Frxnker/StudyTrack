<?php
/**
 * Vista: objetivos activos y completados.
 *
 * @var array  $active
 * @var array  $completed
 * @var array  $summary  total, completados, progreso_medio
 * @var string $today
 */
?>
<header class="page-header">
  <div>
    <h2 class="page-header__title">Mis objetivos</h2>
    <p class="page-header__subtitle">
      <?php if ($summary['total'] > 0): ?>
        <?= $summary['completados'] ?> de <?= e(plural($summary['total'], 'objetivo cumplido', 'objetivos cumplidos')) ?> ·
        progreso medio <?= $summary['progreso_medio'] ?>%
      <?php else: ?>
        Ponte metas medibles y sigue tu avance paso a paso.
      <?php endif; ?>
    </p>
  </div>
  <?php if ($summary['total'] > 0): ?>
    <div class="page-header__actions hide-mobile">
      <a href="goals.php?action=new" class="btn btn--primary">
        <i class="ri-add-line" aria-hidden="true"></i> Nuevo objetivo
      </a>
    </div>
  <?php endif; ?>
</header>

<?php if ($summary['total'] === 0): ?>
  <section class="card">
    <?php partial('empty', [
        'icon'        => 'ri-focus-3-line',
        'title'       => 'Aún no tienes objetivos',
        'text'        => 'Por ejemplo: «Completar 5 ejercicios de Java» o «Estudiar 20 horas esta semana».',
        'actionHref'  => 'goals.php?action=new',
        'actionLabel' => 'Crear objetivo',
    ]); ?>
  </section>
<?php else: ?>
  <section class="section" aria-labelledby="active-title">
    <h3 class="section__title" id="active-title">
      <i class="ri-focus-3-line" aria-hidden="true"></i> En marcha
      <span class="badge"><?= count($active) ?></span>
    </h3>
    <?php if ($active): ?>
      <div class="goal-grid">
        <?php foreach ($active as $goal): ?>
          <?php partial('goal_card', ['goal' => $goal, 'today' => $today]); ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card">
        <?php partial('empty', [
            'icon'        => 'ri-trophy-line',
            'title'       => '¡Has cumplido todos tus objetivos!',
            'text'        => 'Ponte un nuevo reto para seguir avanzando.',
            'actionHref'  => 'goals.php?action=new',
            'actionLabel' => 'Nuevo objetivo',
            'compact'     => true,
        ]); ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($completed): ?>
    <section class="section" aria-labelledby="completed-title">
      <h3 class="section__title" id="completed-title">
        <i class="ri-trophy-line" aria-hidden="true"></i> Cumplidos
        <span class="badge badge--success"><?= count($completed) ?></span>
      </h3>
      <div class="goal-grid">
        <?php foreach ($completed as $goal): ?>
          <?php partial('goal_card', ['goal' => $goal, 'today' => $today]); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
<?php endif; ?>
