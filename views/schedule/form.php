<?php
/**
 * Vista: formulario de alta / edición de una clase del horario.
 *
 * @var array $class     id, asignatura_id, dia_semana, hora_inicio, hora_fin, aula
 * @var array $subjects  id, nombre, color
 * @var array $errors
 * @var bool  $isEdit
 */
?>
<header class="page-header">
  <div>
    <a href="schedule.php" class="back-link"><i class="ri-arrow-left-line" aria-hidden="true"></i> Horario</a>
    <h2 class="page-header__title"><?= $isEdit ? 'Editar clase' : 'Nueva clase' ?></h2>
  </div>
</header>

<?php if (!$subjects): ?>
  <section class="card card--form">
    <?php partial('empty', [
        'icon'        => 'ri-book-2-line',
        'title'       => 'Primero crea una asignatura',
        'text'        => 'Cada clase del horario pertenece a una asignatura.',
        'actionHref'  => 'subjects.php?action=new',
        'actionLabel' => 'Crear asignatura',
    ]); ?>
  </section>
<?php else: ?>
  <section class="card card--form">
    <form method="post" action="schedule.php" class="form" data-loading>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $class['id'] ?>">
      <?php endif; ?>

      <div class="<?= field_class($errors, 'asignatura_id') ?>">
        <label for="asignatura_id" class="field__label">Asignatura</label>
        <select id="asignatura_id" name="asignatura_id" class="select" required <?= field_aria($errors, 'asignatura_id') ?>>
          <option value="">Elige una asignatura</option>
          <?php foreach ($subjects as $subject): ?>
            <option value="<?= (int) $subject['id'] ?>"<?= (string) $class['asignatura_id'] === (string) $subject['id'] ? ' selected' : '' ?>>
              <?= e($subject['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'asignatura_id') ?>
      </div>

      <fieldset class="<?= field_class($errors, 'dia_semana') ?> fieldset">
        <legend class="field__label">Día de la semana</legend>
        <div class="segmented segmented--days">
          <?php foreach (DAY_NAMES as $day => $dayName): ?>
            <label class="segmented__option">
              <input type="radio" name="dia_semana" value="<?= $day ?>"<?= (string) $class['dia_semana'] === (string) $day ? ' checked' : '' ?>>
              <span><abbr title="<?= e(ucfirst($dayName)) ?>"><?= e(ucfirst(str_take($dayName, 3))) ?></abbr></span>
            </label>
          <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'dia_semana') ?>
      </fieldset>

      <div class="form-row">
        <div class="<?= field_class($errors, 'hora_inicio') ?>">
          <label for="hora_inicio" class="field__label">Empieza</label>
          <input type="time" id="hora_inicio" name="hora_inicio" class="input" value="<?= e($class['hora_inicio']) ?>"
                 required <?= field_aria($errors, 'hora_inicio') ?>>
          <?= field_error($errors, 'hora_inicio') ?>
        </div>

        <div class="<?= field_class($errors, 'hora_fin') ?>">
          <label for="hora_fin" class="field__label">Termina</label>
          <input type="time" id="hora_fin" name="hora_fin" class="input" value="<?= e($class['hora_fin']) ?>"
                 required <?= field_aria($errors, 'hora_fin') ?>>
          <?= field_error($errors, 'hora_fin') ?>
        </div>
      </div>

      <div class="<?= field_class($errors, 'aula') ?>">
        <label for="aula" class="field__label">Aula <span class="field__optional">Opcional</span></label>
        <input type="text" id="aula" name="aula" class="input" value="<?= e($class['aula']) ?>"
               placeholder="Ej.: Aula 12" maxlength="50" <?= field_aria($errors, 'aula') ?>>
        <?= field_error($errors, 'aula') ?>
      </div>

      <div class="form-actions">
        <a href="schedule.php" class="btn btn--ghost">Cancelar</a>
        <button type="submit" class="btn btn--primary">
          <i class="ri-check-line" aria-hidden="true"></i> <?= $isEdit ? 'Guardar cambios' : 'Añadir clase' ?>
        </button>
      </div>
    </form>
  </section>

  <?php if ($isEdit): ?>
    <section class="card card--form danger-zone">
      <div>
        <h3 class="card__title">Eliminar clase</h3>
        <p class="text-muted text-sm">Se quitará solo esta clase del horario.</p>
      </div>
      <?php partial('delete', [
          'action'   => 'schedule.php',
          'id'       => (int) $class['id'],
          'label'    => 'Eliminar',
          'question' => '¿Eliminar esta clase del horario?',
          'full'     => true,
      ]); ?>
    </section>
  <?php endif; ?>
<?php endif; ?>
