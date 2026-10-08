<?php
/**
 * Fragmento: estado vacío ("todavía no hay nada aquí").
 *
 * @var string      $icon         Icono de Remix Icon
 * @var string      $title
 * @var string|null $text
 * @var string|null $actionHref   Botón opcional
 * @var string|null $actionLabel
 * @var bool|null   $compact      Versión reducida para tarjetas pequeñas
 */
?>
<div class="empty<?= !empty($compact) ? ' empty--compact' : '' ?>">
  <div class="empty__icon"><i class="<?= e($icon) ?>" aria-hidden="true"></i></div>
  <p class="empty__title"><?= e($title) ?></p>
  <?php if (!empty($text)): ?>
    <p class="empty__text"><?= e($text) ?></p>
  <?php endif; ?>
  <?php if (!empty($actionHref) && !empty($actionLabel)): ?>
    <a href="<?= e($actionHref) ?>" class="btn btn--secondary btn--sm">
      <i class="ri-add-line" aria-hidden="true"></i> <?= e($actionLabel) ?>
    </a>
  <?php endif; ?>
</div>
