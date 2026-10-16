<?php
/**
 * Fragmento: icono del origen de un calendario externo (Moodle, Classroom u otro).
 *
 * @var string    $origin  moodle | classroom | otro
 * @var bool|null $small   Versión pequeña (para textos)
 */
?>
<span class="feed-icon feed-icon--<?= e($origin) ?><?= !empty($small) ? ' feed-icon--sm' : '' ?>" aria-hidden="true">
  <?php if ($origin === 'moodle'): ?>
    M
  <?php elseif ($origin === 'classroom'): ?>
    <i class="ri-google-fill"></i>
  <?php else: ?>
    <i class="ri-calendar-2-line"></i>
  <?php endif; ?>
</span>
