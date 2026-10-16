<?php
/**
 * Vista: formulario de alta / edición de asignatura.
 *
 * @var array  $subject        id, nombre, descripcion, profesor, curso, color
 * @var array  $errors
 * @var bool   $isEdit
 * @var string $deleteWarning  Qué se borra con la asignatura (solo al editar)
 */
?>
<header class="page-header">
  <div>
    <a href="subjects.php" class="back-link"><i class="ri-arrow-left-line" aria-hidden="true"></i> Asignaturas</a>
    <h2 class="page-header__title"><?= $isEdit ? 'Editar asignatura' : 'Nueva asignatura' ?></h2>
  </div>
</header>

<section class="card card--form">
  <form method="post" action="subjects.php" class="form" data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?= (int) $subject['id'] ?>">
    <?php endif; ?>

    <div class="<?= field_class($errors, 'nombre') ?>">
      <label for="nombre" class="field__label">Nombre</label>
      <input type="text" id="nombre" name="nombre" class="input" value="<?= e($subject['nombre']) ?>"
             placeholder="Ej.: Programación" maxlength="100" required autofocus
             <?= field_aria($errors, 'nombre') ?>>
      <?= field_error($errors, 'nombre') ?>
    </div>

    <div class="form-row">
      <div class="<?= field_class($errors, 'profesor') ?>">
        <label for="profesor" class="field__label">Profesor/a <span class="field__optional">Opcional</span></label>
        <input type="text" id="profesor" name="profesor" class="input" value="<?= e($subject['profesor']) ?>"
               placeholder="Ej.: Laura Méndez" maxlength="100" autocomplete="off"
               <?= field_aria($errors, 'profesor') ?>>
        <?= field_error($errors, 'profesor') ?>
      </div>

      <div class="<?= field_class($errors, 'curso') ?>">
        <label for="curso" class="field__label">Curso <span class="field__optional">Opcional</span></label>
        <input type="text" id="curso" name="curso" class="input" value="<?= e($subject['curso']) ?>"
               placeholder="Ej.: 1º DAW" maxlength="50"
               <?= field_aria($errors, 'curso') ?>>
        <?= field_error($errors, 'curso') ?>
      </div>
    </div>

    <div class="<?= field_class($errors, 'descripcion') ?>">
      <label for="descripcion" class="field__label">Descripción <span class="field__optional">Opcional</span></label>
      <textarea id="descripcion" name="descripcion" class="textarea" maxlength="1000"
                placeholder="Temario, criterios de evaluación, enlaces…"
                <?= field_aria($errors, 'descripcion') ?>><?= e($subject['descripcion']) ?></textarea>
      <?= field_error($errors, 'descripcion') ?>
    </div>

    <fieldset class="<?= field_class($errors, 'color') ?> fieldset">
      <legend class="field__label">Color</legend>
      <div class="swatches">
        <?php $current = strtoupper((string) $subject['color']); ?>
        <?php foreach (SUBJECT_COLORS as $i => $color): ?>
          <label class="swatch" style="--subject: <?= e($color) ?>">
            <input type="radio" name="color" value="<?= e($color) ?>" class="sr-only"
                   <?= $current === $color ? 'checked' : '' ?>>
            <span class="swatch__dot"><span class="sr-only">Color <?= $i + 1 ?> (<?= e($color) ?>)</span></span>
          </label>
        <?php endforeach; ?>
        <?php $isCustom = !in_array($current, SUBJECT_COLORS, true); ?>
        <label class="swatch swatch--custom" title="Elegir otro color">
          <input type="radio" name="color" value="<?= e(safe_color($current, '#4F46E5')) ?>" class="sr-only"
                 data-color-custom <?= $isCustom ? 'checked' : '' ?>>
          <input type="color" class="swatch__picker" value="<?= e(strtolower(safe_color($current, '#4F46E5'))) ?>"
                 aria-label="Elegir otro color" data-color-picker>
        </label>
      </div>
      <?= field_error($errors, 'color') ?>
    </fieldset>

    <div class="form-actions">
      <a href="subjects.php" class="btn btn--ghost">Cancelar</a>
      <button type="submit" class="btn btn--primary">
        <i class="ri-check-line" aria-hidden="true"></i> <?= $isEdit ? 'Guardar cambios' : 'Crear asignatura' ?>
      </button>
    </div>
  </form>
</section>

<?php if ($isEdit): ?>
  <section class="card card--form danger-zone">
    <div>
      <h3 class="card__title">Eliminar asignatura</h3>
      <p class="text-muted text-sm">Se borrarán también sus exámenes, notas y clases del horario. Las tareas se conservarán sin asignatura.</p>
    </div>
    <?php partial('delete', [
        'action'   => 'subjects.php',
        'id'       => (int) $subject['id'],
        'label'    => 'Eliminar',
        'question' => '¿Eliminar la asignatura «' . $subject['nombre'] . '»?',
        'text'     => $deleteWarning,
        'full'     => true,
    ]); ?>
  </section>
<?php endif; ?>
