<?php
/**
 * Vista: formulario de alta / edición de nota.
 *
 * @var array $grade          id, asignatura_id, tipo, nota, fecha, comentarios
 * @var array $subjects       id, nombre, color
 * @var array $errors
 * @var bool  $isEdit
 * @var int   $subjectFilter  Asignatura del listado al que se vuelve (0 = todas)
 */

$backUrl = grade_list_url($subjectFilter);
?>
<header class="page-header">
  <div>
    <a href="<?= e($backUrl) ?>" class="back-link"><i class="ri-arrow-left-line" aria-hidden="true"></i> Notas</a>
    <h2 class="page-header__title"><?= $isEdit ? 'Editar nota' : 'Nueva nota' ?></h2>
  </div>
</header>

<?php if (!$subjects): ?>
  <section class="card card--form">
    <?php partial('empty', [
        'icon'        => 'ri-book-2-line',
        'title'       => 'Primero crea una asignatura',
        'text'        => 'Cada nota pertenece a una asignatura.',
        'actionHref'  => 'subjects.php?action=new',
        'actionLabel' => 'Crear asignatura',
    ]); ?>
  </section>
<?php else: ?>
  <section class="card card--form">
    <form method="post" action="grades.php" class="form" data-loading>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
      <input type="hidden" name="return" value="<?= $subjectFilter > 0 ? $subjectFilter : '' ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $grade['id'] ?>">
      <?php endif; ?>

      <div class="form-row">
        <div class="<?= field_class($errors, 'asignatura_id') ?>">
          <label for="asignatura_id" class="field__label">Asignatura</label>
          <select id="asignatura_id" name="asignatura_id" class="select" required <?= field_aria($errors, 'asignatura_id') ?>>
            <option value="">Elige una asignatura</option>
            <?php foreach ($subjects as $subject): ?>
              <option value="<?= (int) $subject['id'] ?>"<?= (string) $grade['asignatura_id'] === (string) $subject['id'] ? ' selected' : '' ?>>
                <?= e($subject['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?= field_error($errors, 'asignatura_id') ?>
        </div>

        <div class="<?= field_class($errors, 'tipo') ?>">
          <label for="tipo" class="field__label">Tipo</label>
          <select id="tipo" name="tipo" class="select" required <?= field_aria($errors, 'tipo') ?>>
            <?php foreach (GRADE_TYPES as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= $grade['tipo'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error($errors, 'tipo') ?>
        </div>
      </div>

      <div class="form-row">
        <div class="<?= field_class($errors, 'nota') ?>">
          <label for="nota" class="field__label">Nota <span class="field__optional">De 0 a 10</span></label>
          <div class="input-icon">
            <i class="ri-award-line" aria-hidden="true"></i>
            <input type="text" id="nota" name="nota" class="input" value="<?= e($grade['nota']) ?>"
                   placeholder="Ej.: 7,5" inputmode="decimal" autocomplete="off" required
                   pattern="\d{1,2}([.,]\d{1,2})?" maxlength="5"
                   aria-describedby="nota-hint<?= isset($errors['nota']) ? ' nota-error' : '' ?>"
                   <?= isset($errors['nota']) ? 'aria-invalid="true"' : '' ?>
                   <?= $isEdit ? '' : 'autofocus' ?>>
          </div>
          <?php if (isset($errors['nota'])): ?>
            <?= field_error($errors, 'nota') ?>
          <?php endif; ?>
          <p class="field__hint" id="nota-hint">Aprobado a partir de <?= e(format_grade(PASS_MARK)) ?>. Puedes usar coma o punto.</p>
        </div>

        <div class="<?= field_class($errors, 'fecha') ?>">
          <label for="fecha" class="field__label">Fecha</label>
          <input type="date" id="fecha" name="fecha" class="input" value="<?= e($grade['fecha']) ?>" required
                 <?= field_aria($errors, 'fecha') ?>>
          <?= field_error($errors, 'fecha') ?>
        </div>
      </div>

      <div class="<?= field_class($errors, 'comentarios') ?>">
        <label for="comentarios" class="field__label">Comentarios <span class="field__optional">Opcional</span></label>
        <textarea id="comentarios" name="comentarios" class="textarea" maxlength="1000"
                  placeholder="Temas que entraban, qué mejorar…"
                  <?= field_aria($errors, 'comentarios') ?>><?= e($grade['comentarios']) ?></textarea>
        <?= field_error($errors, 'comentarios') ?>
      </div>

      <div class="form-actions">
        <a href="<?= e($backUrl) ?>" class="btn btn--ghost">Cancelar</a>
        <button type="submit" class="btn btn--primary">
          <i class="ri-check-line" aria-hidden="true"></i> <?= $isEdit ? 'Guardar cambios' : 'Guardar nota' ?>
        </button>
      </div>
    </form>
  </section>

  <?php if ($isEdit): ?>
    <section class="card card--form danger-zone">
      <div>
        <h3 class="card__title">Eliminar nota</h3>
        <p class="text-muted text-sm">La media de la asignatura se recalculará.</p>
      </div>
      <?php partial('delete', [
          'action'   => 'grades.php',
          'id'       => (int) $grade['id'],
          'label'    => 'Eliminar',
          'question' => '¿Eliminar esta nota?',
          'return'   => $subjectFilter > 0 ? (string) $subjectFilter : '',
          'full'     => true,
      ]); ?>
    </section>
  <?php endif; ?>
<?php endif; ?>
