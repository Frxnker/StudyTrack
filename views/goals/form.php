<?php
/**
 * Vista: formulario de alta / edición de objetivo.
 *
 * @var array $goal    id, titulo, descripcion, fecha_limite, valor_actual, valor_meta, unidad, estado
 * @var array $errors
 * @var bool  $isEdit
 */

$statusIcons = ['pendiente' => 'ri-time-line', 'en_progreso' => 'ri-loader-4-line', 'completado' => 'ri-trophy-line'];
?>
<header class="page-header">
  <div>
    <a href="goals.php" class="back-link"><i class="ri-arrow-left-line" aria-hidden="true"></i> Objetivos</a>
    <h2 class="page-header__title"><?= $isEdit ? 'Editar objetivo' : 'Nuevo objetivo' ?></h2>
  </div>
</header>

<section class="card card--form">
  <form method="post" action="goals.php" class="form" data-loading data-goal-form>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?= (int) $goal['id'] ?>">
    <?php endif; ?>

    <div class="<?= field_class($errors, 'titulo') ?>">
      <label for="titulo" class="field__label">Objetivo</label>
      <input type="text" id="titulo" name="titulo" class="input" value="<?= e($goal['titulo']) ?>"
             placeholder="Ej.: Completar 5 ejercicios de Java" maxlength="150" required
             <?= $isEdit ? '' : 'autofocus' ?> <?= field_aria($errors, 'titulo') ?>>
      <?= field_error($errors, 'titulo') ?>
    </div>

    <div class="form-row form-row--3">
      <div class="<?= field_class($errors, 'valor_actual') ?>">
        <label for="valor_actual" class="field__label">Llevo</label>
        <input type="number" id="valor_actual" name="valor_actual" class="input" value="<?= e($goal['valor_actual']) ?>"
               min="0" max="<?= GOAL_MAX_VALUE ?>" step="1" inputmode="numeric" required
               <?= field_aria($errors, 'valor_actual') ?>>
        <?= field_error($errors, 'valor_actual') ?>
      </div>

      <div class="<?= field_class($errors, 'valor_meta') ?>">
        <label for="valor_meta" class="field__label">Meta</label>
        <input type="number" id="valor_meta" name="valor_meta" class="input" value="<?= e($goal['valor_meta']) ?>"
               placeholder="Ej.: 5" min="1" max="<?= GOAL_MAX_VALUE ?>" step="1" inputmode="numeric" required
               <?= field_aria($errors, 'valor_meta') ?>>
        <?= field_error($errors, 'valor_meta') ?>
      </div>

      <div class="<?= field_class($errors, 'unidad') ?>">
        <label for="unidad" class="field__label">Unidad <span class="field__optional">Opcional</span></label>
        <input type="text" id="unidad" name="unidad" class="input" value="<?= e($goal['unidad']) ?>"
               placeholder="ejercicios, horas, %…" maxlength="30" list="goal-units"
               <?= field_aria($errors, 'unidad') ?>>
        <datalist id="goal-units">
          <option value="ejercicios"><option value="temas"><option value="horas">
          <option value="páginas"><option value="capítulos"><option value="%">
        </datalist>
        <?= field_error($errors, 'unidad') ?>
      </div>
    </div>

    <div class="<?= field_class($errors, 'fecha_limite') ?>">
      <label for="fecha_limite" class="field__label">Fecha límite <span class="field__optional">Opcional</span></label>
      <input type="date" id="fecha_limite" name="fecha_limite" class="input" value="<?= e($goal['fecha_limite']) ?>"
             <?= field_aria($errors, 'fecha_limite') ?>>
      <?= field_error($errors, 'fecha_limite') ?>
    </div>

    <fieldset class="<?= field_class($errors, 'estado') ?> fieldset">
      <legend class="field__label">Estado</legend>
      <div class="segmented">
        <?php foreach (GOAL_STATUSES as $key => $label): ?>
          <label class="segmented__option">
            <input type="radio" name="estado" value="<?= e($key) ?>"<?= $goal['estado'] === $key ? ' checked' : '' ?>>
            <span><i class="<?= $statusIcons[$key] ?>" aria-hidden="true"></i> <?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php if (isset($errors['estado'])): ?>
        <?= field_error($errors, 'estado') ?>
      <?php else: ?>
        <p class="field__hint">Al llegar a la meta se marca como completado automáticamente.</p>
      <?php endif; ?>
    </fieldset>

    <div class="<?= field_class($errors, 'descripcion') ?>">
      <label for="descripcion" class="field__label">Descripción <span class="field__optional">Opcional</span></label>
      <textarea id="descripcion" name="descripcion" class="textarea" maxlength="1000"
                placeholder="¿Por qué es importante? ¿Cómo lo vas a conseguir?"
                <?= field_aria($errors, 'descripcion') ?>><?= e($goal['descripcion']) ?></textarea>
      <?= field_error($errors, 'descripcion') ?>
    </div>

    <div class="form-actions">
      <a href="goals.php" class="btn btn--ghost">Cancelar</a>
      <button type="submit" class="btn btn--primary">
        <i class="ri-check-line" aria-hidden="true"></i> <?= $isEdit ? 'Guardar cambios' : 'Crear objetivo' ?>
      </button>
    </div>
  </form>
</section>

<?php if ($isEdit): ?>
  <section class="card card--form danger-zone">
    <div>
      <h3 class="card__title">Eliminar objetivo</h3>
      <p class="text-muted text-sm">Se perderá también su progreso.</p>
    </div>
    <?php partial('delete', [
        'action'   => 'goals.php',
        'id'       => (int) $goal['id'],
        'label'    => 'Eliminar',
        'question' => '¿Eliminar el objetivo «' . $goal['titulo'] . '»?',
        'full'     => true,
    ]); ?>
  </section>
<?php endif; ?>
