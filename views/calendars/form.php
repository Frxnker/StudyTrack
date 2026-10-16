<?php
/**
 * Vista: formulario para conectar o editar un calendario externo.
 * Al conectar, el botón comprueba la URL y lleva a la vista previa; al editar se guarda.
 *
 * @var array $calendar  id, nombre, origen, url, asignatura_id, tipo_por_defecto, activo
 *                       (al editar también url_enmascarada, num_tareas, num_examenes)
 * @var array $subjects  id, nombre, color
 * @var array $errors
 * @var bool  $isEdit
 */

$origins     = ['moodle' => ['Moodle', 'ri-graduation-cap-line'], 'classroom' => ['Classroom', 'ri-google-line'], 'otro' => ['Otro', 'ri-calendar-line']];
$types       = ['tarea' => ['Tareas', 'ri-checkbox-circle-line'], 'examen' => ['Exámenes', 'ri-file-list-3-line']];
$selected    = (string) $calendar['asignatura_id'];
$selectedColor = '';
foreach ($subjects as $subject) {
    if ((string) $subject['id'] === $selected) {
        $selectedColor = safe_color($subject['color']);
    }
}
?>
<header class="page-header">
  <div>
    <a href="calendars.php" class="back-link"><i class="ri-arrow-left-line" aria-hidden="true"></i> Calendarios</a>
    <h2 class="page-header__title"><?= $isEdit ? 'Editar calendario' : 'Conectar un calendario' ?></h2>
    <?php if (!$isEdit): ?>
      <p class="page-header__subtitle">Pega la URL iCal de Moodle o de una clase de Classroom. Antes de importar verás una vista previa.</p>
    <?php endif; ?>
  </div>
</header>

<section class="card card--form">
  <form method="post" action="calendars.php" class="form feed-form" data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'preview' ?>">
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?= (int) $calendar['id'] ?>">
    <?php endif; ?>

    <div class="<?= field_class($errors, 'nombre') ?>">
      <label for="nombre" class="field__label">Nombre</label>
      <input type="text" id="nombre" name="nombre" class="input" value="<?= e($calendar['nombre']) ?>"
             placeholder="Ej.: Moodle del instituto · Classroom DIW" maxlength="100" required
             <?= $isEdit ? '' : 'autofocus' ?> <?= field_aria($errors, 'nombre') ?>>
      <?= field_error($errors, 'nombre') ?>
    </div>

    <fieldset class="<?= field_class($errors, 'origen') ?> fieldset">
      <legend class="field__label">Origen</legend>
      <div class="segmented">
        <?php foreach ($origins as $key => [$label, $icon]): ?>
          <label class="segmented__option">
            <input type="radio" name="origen" value="<?= e($key) ?>"<?= $calendar['origen'] === $key ? ' checked' : '' ?>>
            <span><i class="<?= $icon ?>" aria-hidden="true"></i> <?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?= field_error($errors, 'origen') ?>
    </fieldset>

    <div class="<?= field_class($errors, 'url') ?>">
      <label for="url" class="field__label">
        URL iCal
        <?php if ($isEdit): ?><span class="field__optional">Déjala vacía para no cambiarla</span><?php endif; ?>
      </label>
      <div class="input-icon">
        <i class="ri-link" aria-hidden="true"></i>
        <input type="url" id="url" name="url" class="input" value="<?= e($calendar['url']) ?>"
               placeholder="<?= $isEdit ? e($calendar['url_enmascarada'] ?? '') : 'https://… o webcal://…' ?>"
               maxlength="2000" autocomplete="off" spellcheck="false" inputmode="url"
               <?= $isEdit ? '' : 'required' ?>
               aria-describedby="url-hint<?= isset($errors['url']) ? ' url-error' : '' ?>"
               <?= isset($errors['url']) ? 'aria-invalid="true"' : '' ?>>
      </div>
      <?php if (isset($errors['url'])): ?>
        <?= field_error($errors, 'url') ?>
      <?php endif; ?>
      <p class="field__hint" id="url-hint">
        <i class="ri-lock-2-line" aria-hidden="true"></i>
        <?php if ($isEdit): ?>
          Guardada cifrada: <?= e($calendar['url_enmascarada'] ?? '') ?>.
        <?php else: ?>
          Se guarda cifrada y nunca se muestra completa. Trátala como una contraseña.
        <?php endif; ?>
        <a href="calendars.php#ayuda">¿Dónde la encuentro?</a>
      </p>
    </div>

    <div class="<?= field_class($errors, 'asignatura_id') ?>">
      <label for="asignatura_id" class="field__label">Asignatura <span class="field__optional">Opcional</span></label>
      <div class="select-swatch" data-subject-select>
        <span class="select-swatch__dot" style="--subject: <?= e($selectedColor ?: 'transparent') ?>" aria-hidden="true" data-swatch></span>
        <select id="asignatura_id" name="asignatura_id" class="select" <?= field_aria($errors, 'asignatura_id') ?>
                aria-describedby="asignatura-hint">
          <option value="">Detectar según el curso de cada evento</option>
          <?php foreach ($subjects as $subject): ?>
            <option value="<?= (int) $subject['id'] ?>" data-color="<?= e(safe_color($subject['color'])) ?>"
                    <?= (string) $subject['id'] === $selected ? 'selected' : '' ?>>
              <?= e($subject['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?= field_error($errors, 'asignatura_id') ?>
      <p class="field__hint" id="asignatura-hint">
        <span class="feed-hint feed-hint--classroom">En Classroom cada clase tiene su propio calendario: elige aquí su asignatura.</span>
        <span class="feed-hint feed-hint--moodle">Moodle mezcla todos tus cursos: déjalo en «Detectar» y cada evento irá a la asignatura de su curso.</span>
      </p>
    </div>

    <fieldset class="<?= field_class($errors, 'tipo_por_defecto') ?> fieldset">
      <legend class="field__label">Importar los eventos como</legend>
      <div class="segmented">
        <?php foreach ($types as $key => [$label, $icon]): ?>
          <label class="segmented__option">
            <input type="radio" name="tipo_por_defecto" value="<?= e($key) ?>"<?= $calendar['tipo_por_defecto'] === $key ? ' checked' : '' ?>>
            <span><i class="<?= $icon ?>" aria-hidden="true"></i> <?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?= field_error($errors, 'tipo_por_defecto') ?>
      <p class="field__hint">Con «Tareas», los eventos con «examen», «prueba», «control» o «test» en el título se importan como exámenes si tienen asignatura.</p>
    </fieldset>

    <label class="check">
      <input type="checkbox" name="activo" value="1"<?= $calendar['activo'] ? ' checked' : '' ?>>
      <span>
        Sincronizar automáticamente
        <span class="check__hint">Cada hora, si está programado <code>cron/sync_calendars.php</code> (ver README).</span>
      </span>
    </label>

    <div class="form-actions">
      <a href="calendars.php" class="btn btn--ghost">Cancelar</a>
      <button type="submit" class="btn btn--primary">
        <?php if ($isEdit): ?>
          <i class="ri-check-line" aria-hidden="true"></i> Guardar cambios
        <?php else: ?>
          <i class="ri-eye-line" aria-hidden="true"></i> Comprobar y ver vista previa
        <?php endif; ?>
      </button>
    </div>
  </form>
</section>

<?php if ($isEdit): ?>
  <section class="card card--form danger-zone danger-zone--stacked" aria-labelledby="delete-title">
    <div>
      <h3 class="card__title" id="delete-title">Eliminar calendario</h3>
      <p class="text-muted text-sm">
        Se importaron <?= e(human_list([plural($calendar['num_tareas'], 'tarea', 'tareas'), plural($calendar['num_examenes'], 'examen', 'exámenes')])) ?>.
        Puedes conservarlos o borrarlos junto con el calendario.
      </p>
    </div>
    <?php partial('feed_delete', ['calendar' => $calendar]); ?>
  </section>
<?php endif; ?>
