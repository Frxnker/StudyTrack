<?php
/**
 * Fragmento: eliminar un calendario externo preguntando qué hacer con lo importado.
 * Dos botones en el mismo formulario; cada uno lleva su texto de confirmación
 * (js/app.js lo muestra en el diálogo y envía el botón pulsado).
 *
 * @var array $calendar  id, nombre, num_tareas, num_examenes
 */

$imported = human_list([
    plural((int) $calendar['num_tareas'], 'tarea', 'tareas'),
    plural((int) $calendar['num_examenes'], 'examen', 'exámenes'),
]);
?>
<form method="post" action="calendars.php" class="feed-delete"
      data-confirm="¿Eliminar el calendario «<?= e($calendar['nombre']) ?>»?">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" value="<?= (int) $calendar['id'] ?>">
  <button type="submit" name="importados" value="conservar" class="btn btn--secondary btn--sm"
          data-confirm-text="Se conservarán <?= e($imported) ?> importados; solo dejará de sincronizarse.">
    <i class="ri-inbox-archive-line" aria-hidden="true"></i> Eliminar y conservar lo importado
  </button>
  <button type="submit" name="importados" value="borrar" class="btn btn--danger-soft btn--sm"
          data-confirm-text="Se borrarán también <?= e($imported) ?> importados. Esta acción no se puede deshacer.">
    <i class="ri-delete-bin-6-line" aria-hidden="true"></i> Eliminar también lo importado
  </button>
</form>
