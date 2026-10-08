<?php
/**
 * Fragmento: cuenta atrás hasta un examen.
 * PHP pinta los valores iniciales (funciona sin JavaScript) y js/app.js
 * los actualiza cada segundo a partir de data-countdown.
 *
 * @var array $countdown  Resultado de countdown_parts()
 */
$units = [
    'days'    => 'días',
    'hours'   => 'horas',
    'minutes' => 'min',
    'seconds' => 'seg',
];
?>
<div class="countdown" role="timer" aria-label="Tiempo que falta para el examen"
     data-countdown="<?= e($countdown['target']) ?>"<?= $countdown['finished'] ? ' hidden' : '' ?>>
  <?php foreach ($units as $unit => $label): ?>
    <div class="countdown__unit">
      <span class="countdown__value" data-unit="<?= $unit ?>">
        <?= $unit === 'days' ? (int) $countdown[$unit] : sprintf('%02d', $countdown[$unit]) ?>
      </span>
      <span class="countdown__label"><?= $label ?></span>
    </div>
  <?php endforeach; ?>
</div>
<p class="countdown__done" data-countdown-done<?= $countdown['finished'] ? '' : ' hidden' ?>>
  <i class="ri-sparkling-line" aria-hidden="true"></i> ¡Es hoy! Mucha suerte en el examen.
</p>
