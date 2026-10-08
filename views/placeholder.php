<?php
/**
 * Vista provisional para las secciones que aún no se han desarrollado.
 *
 * @var string $pageTitle
 * @var string $icon   Clase del icono de Remix Icon
 * @var int    $phase  Fase del desarrollo en la que se implementa
 */
?>
<section class="card">
  <div class="empty">
    <div class="empty__icon"><i class="<?= e($icon) ?>" aria-hidden="true"></i></div>
    <h2 class="empty__title"><?= e($pageTitle) ?></h2>
    <p class="empty__text">
      Esta sección está en construcción. Se implementa en la
      <strong>fase <?= (int) $phase ?></strong> del desarrollo.
    </p>
    <a href="dashboard.php" class="btn btn--secondary">
      <i class="ri-home-5-line" aria-hidden="true"></i> Volver al inicio
    </a>
  </div>
</section>
