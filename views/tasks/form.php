<?php
/**
 * Vista: formulario de alta / edición de tarea.
 *
 * @var array $task      id, titulo, descripcion, asignatura_id, fecha_limite, prioridad, estado
 * @var array $subjects  id, nombre, color
 * @var array $errors
 * @var bool  $isEdit
 * @var array $filters   Filtros del listado al que se vuelve
 */

$priorityIcons = ['baja' => 'ri-arrow-down-line', 'media' => 'ri-subtract-line', 'alta' => 'ri-arrow-up-line'];
$statusIcons   = ['pendiente' => 'ri-time-line', 'en_progreso' => 'ri-loader-4-line', 'completada' => 'ri-check-line'];
$backUrl       = task_list_url($filters);
?>
<header class="page-header">
  <div>
    <a href="<?= e($backUrl) ?>" class="back-link"><i class="ri-arrow-left-line" aria-hidden="true"></i> Tareas</a>
    <h2 class="page-header__title"><?= $isEdit ? 'Editar tarea' : 'Nueva tarea' ?></h2>
  </div>
</header>

<?php if (!empty($task['calendario'])): ?>
  <p class="alert alert--info card--form mb-2">
    <i class="ri-links-line" aria-hidden="true"></i>
    <span>
      Importada del calendario «<?= e($task['calendario']) ?>». Al sincronizar se actualizan su título y su fecha;
      el estado, la prioridad y la asignatura que elijas se respetan.
    </span>
  </p>
<?php endif; ?>

<section class="card card--form">
  <form method="post" action="tasks.php" class="form" data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <input type="hidden" name="return" value="<?= e(http_build_query($filters)) ?>">
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
    <?php endif; ?>

    <div class="<?= field_class($errors, 'titulo') ?>">
      <label for="titulo" class="field__label">Título</label>
      <input type="text" id="titulo" name="titulo" class="input" value="<?= e($task['titulo']) ?>"
             placeholder="Ej.: Ejercicios de bucles en Java" maxlength="150" required
             <?= $isEdit ? '' : 'autofocus' ?> <?= field_aria($errors, 'titulo') ?>>
      <?= field_error($errors, 'titulo') ?>
    </div>

    <div class="form-row">
      <div class="<?= field_class($errors, 'asignatura_id') ?>">
        <label for="asignatura_id" class="field__label">Asignatura <span class="field__optional">Opcional</span></label>
        <select id="asignatura_id" name="asignatura_id" class="select" <?= field_aria($errors, 'asignatura_id') ?>>
          <option value="">Sin asignatura</option>
          <?php foreach ($subjects as $subject): ?>
            <option value="<?= (int) $subject['id'] ?>"<?= (string) $task['asignatura_id'] === (string) $subject['id'] ? ' selected' : '' ?>>
              <?= e($subject['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'asignatura_id') ?>
      </div>

      <div class="<?= field_class($errors, 'fecha_limite') ?>">
        <label for="fecha_limite" class="field__label">Fecha límite <span class="field__optional">Opcional</span></label>
        <input type="date" id="fecha_limite" name="fecha_limite" class="input" value="<?= e($task['fecha_limite']) ?>"
               <?= field_aria($errors, 'fecha_limite') ?>>
        <?= field_error($errors, 'fecha_limite') ?>
      </div>
    </div>

    <fieldset class="<?= field_class($errors, 'prioridad') ?> fieldset">
      <legend class="field__label">Prioridad</legend>
      <div class="segmented">
        <?php foreach (TASK_PRIORITIES as $key => $label): ?>
          <label class="segmented__option segmented__option--<?= e($key) ?>">
            <input type="radio" name="prioridad" value="<?= e($key) ?>"<?= $task['prioridad'] === $key ? ' checked' : '' ?>>
            <span><i class="<?= $priorityIcons[$key] ?>" aria-hidden="true"></i> <?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?= field_error($errors, 'prioridad') ?>
    </fieldset>

    <fieldset class="<?= field_class($errors, 'estado') ?> fieldset">
      <legend class="field__label">Estado</legend>
      <div class="segmented">
        <?php foreach (TASK_STATUSES as $key => $label): ?>
          <label class="segmented__option">
            <input type="radio" name="estado" value="<?= e($key) ?>"<?= $task['estado'] === $key ? ' checked' : '' ?>>
            <span><i class="<?= $statusIcons[$key] ?>" aria-hidden="true"></i> <?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?= field_error($errors, 'estado') ?>
    </fieldset>

    <div class="<?= field_class($errors, 'descripcion') ?>">
      <label for="descripcion" class="field__label">Descripción <span class="field__optional">Opcional</span></label>
      <textarea id="descripcion" name="descripcion" class="textarea" maxlength="2000"
                placeholder="Enunciado, páginas del libro, enlaces…"
                <?= field_aria($errors, 'descripcion') ?>><?= e($task['descripcion']) ?></textarea>
      <?= field_error($errors, 'descripcion') ?>
    </div>

    <div class="form-actions">
      <a href="<?= e($backUrl) ?>" class="btn btn--ghost">Cancelar</a>
      <button type="submit" class="btn btn--primary">
        <i class="ri-check-line" aria-hidden="true"></i> <?= $isEdit ? 'Guardar cambios' : 'Crear tarea' ?>
      </button>
    </div>
  </form>
</section>

<?php if ($isEdit): ?>
  <section class="card card--form danger-zone">
    <div>
      <h3 class="card__title">Eliminar tarea</h3>
      <p class="text-muted text-sm">La tarea desaparecerá también de tus estadísticas.</p>
    </div>
    <?php partial('delete', [
        'action'   => 'tasks.php',
        'id'       => (int) $task['id'],
        'label'    => 'Eliminar',
        'question' => '¿Eliminar la tarea «' . $task['titulo'] . '»?',
        'return'   => http_build_query($filters),
        'full'     => true,
    ]); ?>
  </section>
<?php endif; ?>
