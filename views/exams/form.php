<?php
/**
 * Vista: formulario de alta / edición de examen.
 *
 * @var array $exam           id, asignatura_id, nombre, fecha, hora, aula, descripcion, estado
 * @var array $subjects       id, nombre, color
 * @var array $errors
 * @var bool  $isEdit
 * @var int   $subjectFilter  Asignatura del listado al que se vuelve (0 = todas)
 */

$statusIcons = ['pendiente' => 'ri-time-line', 'realizado' => 'ri-check-line', 'aplazado' => 'ri-calendar-close-line'];
$backUrl     = exam_list_url($subjectFilter);
?>
<header class="page-header">
  <div>
    <a href="<?= e($backUrl) ?>" class="back-link"><i class="ri-arrow-left-line" aria-hidden="true"></i> Exámenes</a>
    <h2 class="page-header__title"><?= $isEdit ? 'Editar examen' : 'Nuevo examen' ?></h2>
  </div>
</header>

<?php if (!$subjects): ?>
  <section class="card card--form">
    <?php partial('empty', [
        'icon'        => 'ri-book-2-line',
        'title'       => 'Primero crea una asignatura',
        'text'        => 'Cada examen pertenece a una asignatura.',
        'actionHref'  => 'subjects.php?action=new',
        'actionLabel' => 'Crear asignatura',
    ]); ?>
  </section>
<?php else: ?>
  <section class="card card--form">
    <form method="post" action="exams.php" class="form" data-loading>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
      <input type="hidden" name="return" value="<?= $subjectFilter > 0 ? $subjectFilter : '' ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $exam['id'] ?>">
      <?php endif; ?>

      <div class="<?= field_class($errors, 'nombre') ?>">
        <label for="nombre" class="field__label">Nombre del examen</label>
        <input type="text" id="nombre" name="nombre" class="input" value="<?= e($exam['nombre']) ?>"
               placeholder="Ej.: Examen UT3: Programación orientada a objetos" maxlength="150" required
               <?= $isEdit ? '' : 'autofocus' ?> <?= field_aria($errors, 'nombre') ?>>
        <?= field_error($errors, 'nombre') ?>
      </div>

      <div class="<?= field_class($errors, 'asignatura_id') ?>">
        <label for="asignatura_id" class="field__label">Asignatura</label>
        <select id="asignatura_id" name="asignatura_id" class="select" required <?= field_aria($errors, 'asignatura_id') ?>>
          <option value="">Elige una asignatura</option>
          <?php foreach ($subjects as $subject): ?>
            <option value="<?= (int) $subject['id'] ?>"<?= (string) $exam['asignatura_id'] === (string) $subject['id'] ? ' selected' : '' ?>>
              <?= e($subject['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'asignatura_id') ?>
      </div>

      <div class="form-row">
        <div class="<?= field_class($errors, 'fecha') ?>">
          <label for="fecha" class="field__label">Fecha</label>
          <input type="date" id="fecha" name="fecha" class="input" value="<?= e($exam['fecha']) ?>" required
                 <?= field_aria($errors, 'fecha') ?>>
          <?= field_error($errors, 'fecha') ?>
        </div>

        <div class="<?= field_class($errors, 'hora') ?>">
          <label for="hora" class="field__label">Hora <span class="field__optional">Opcional</span></label>
          <input type="time" id="hora" name="hora" class="input" value="<?= e($exam['hora']) ?>"
                 <?= field_aria($errors, 'hora') ?>>
          <?= field_error($errors, 'hora') ?>
        </div>
      </div>

      <div class="<?= field_class($errors, 'aula') ?>">
        <label for="aula" class="field__label">Aula <span class="field__optional">Opcional</span></label>
        <input type="text" id="aula" name="aula" class="input" value="<?= e($exam['aula']) ?>"
               placeholder="Ej.: Aula 12" maxlength="50" <?= field_aria($errors, 'aula') ?>>
        <?= field_error($errors, 'aula') ?>
      </div>

      <fieldset class="<?= field_class($errors, 'estado') ?> fieldset">
        <legend class="field__label">Estado</legend>
        <div class="segmented">
          <?php foreach (EXAM_STATUSES as $key => $label): ?>
            <label class="segmented__option">
              <input type="radio" name="estado" value="<?= e($key) ?>"<?= $exam['estado'] === $key ? ' checked' : '' ?>>
              <span><i class="<?= $statusIcons[$key] ?>" aria-hidden="true"></i> <?= e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'estado') ?>
      </fieldset>

      <div class="<?= field_class($errors, 'descripcion') ?>">
        <label for="descripcion" class="field__label">Descripción <span class="field__optional">Opcional</span></label>
        <textarea id="descripcion" name="descripcion" class="textarea" maxlength="2000"
                  placeholder="Temas que entran, material permitido…"
                  <?= field_aria($errors, 'descripcion') ?>><?= e($exam['descripcion']) ?></textarea>
        <?= field_error($errors, 'descripcion') ?>
      </div>

      <div class="form-actions">
        <a href="<?= e($backUrl) ?>" class="btn btn--ghost">Cancelar</a>
        <button type="submit" class="btn btn--primary">
          <i class="ri-check-line" aria-hidden="true"></i> <?= $isEdit ? 'Guardar cambios' : 'Añadir examen' ?>
        </button>
      </div>
    </form>
  </section>

  <?php if ($isEdit): ?>
    <section class="card card--form danger-zone">
      <div>
        <h3 class="card__title">Eliminar examen</h3>
        <p class="text-muted text-sm">Las notas que hayas registrado no se borran.</p>
      </div>
      <?php partial('delete', [
          'action'   => 'exams.php',
          'id'       => (int) $exam['id'],
          'label'    => 'Eliminar',
          'question' => '¿Eliminar el examen «' . $exam['nombre'] . '»?',
          'return'   => $subjectFilter > 0 ? (string) $subjectFilter : '',
          'full'     => true,
      ]); ?>
    </section>
  <?php endif; ?>
<?php endif; ?>
