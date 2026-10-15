<?php
/**
 * Fragmento: botón de borrar con confirmación.
 * js/app.js intercepta el envío (data-confirm) y pide confirmación en un diálogo.
 *
 *   partial('delete', ['action' => 'tasks.php', 'id' => 4, 'label' => 'Eliminar tarea',
 *                      'question' => '¿Eliminar «Leer apuntes»?']);
 *
 * @var string      $action    Página que recibe el POST
 * @var int         $id
 * @var string      $label     Texto accesible del botón ("Eliminar tarea")
 * @var string      $question  Título del diálogo
 * @var string|null $text      Explicación (qué se pierde al borrar)
 * @var bool|null   $full      true: botón con texto; false: solo icono
 * @var string|null $return    Filtros del listado al que volver (campo oculto "return")
 */
$text = $text ?? 'Esta acción no se puede deshacer.';
?>
<form method="post" action="<?= e($action) ?>" class="inline-form"
      data-confirm="<?= e($question) ?>" data-confirm-text="<?= e($text) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <?php if (!empty($return)): ?>
    <input type="hidden" name="return" value="<?= e($return) ?>">
  <?php endif; ?>
  <?php if (!empty($full)): ?>
    <button type="submit" class="btn btn--danger-soft">
      <i class="ri-delete-bin-6-line" aria-hidden="true"></i> <?= e($label) ?>
    </button>
  <?php else: ?>
    <button type="submit" class="icon-btn icon-btn--sm icon-btn--danger" aria-label="<?= e($label) ?>" title="<?= e($label) ?>">
      <i class="ri-delete-bin-6-line" aria-hidden="true"></i>
    </button>
  <?php endif; ?>
</form>
