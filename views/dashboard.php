<?php
/**
 * Vista: dashboard (versión provisional de la fase 5).
 * Confirma que la sesión funciona. El dashboard completo llega en la fase 6.
 *
 * @var array $currentUser
 */
?>
<section class="hero">
  <p class="hero__eyebrow"><?= e(format_long_date()) ?></p>
  <h2 class="hero__title"><?= e(greeting()) ?>, <?= e(first_name($currentUser['nombre'])) ?> 👋</h2>
  <p class="hero__text">Tu sesión está activa. El resumen completo de tus estudios llegará en la fase 6.</p>
</section>
