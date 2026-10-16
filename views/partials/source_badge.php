<?php
/**
 * Fragmento: insignia del calendario de origen de una tarea o un examen importado.
 *
 *   partial('source_badge', ['origin' => $task['calendario_origen']]);
 *
 * @var string|null $origin  moodle | classroom | otro (null: no se pinta nada)
 */

$sources = [
    'moodle'    => ['Moodle', 'ri-graduation-cap-line'],
    'classroom' => ['Classroom', 'ri-google-line'],
    'otro'      => ['iCal', 'ri-calendar-line'],
];
?>
<?php if (!empty($origin) && isset($sources[$origin])): ?>
  <span class="badge badge--source badge--<?= e($origin) ?>" title="Importada de <?= e($sources[$origin][0]) ?>">
    <i class="<?= $sources[$origin][1] ?>" aria-hidden="true"></i> <?= e($sources[$origin][0]) ?>
  </span>
<?php endif; ?>
