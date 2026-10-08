<?php
/**
 * Vista: inicio de sesión.
 *
 * @var array $errors
 * @var array $old
 */
?>
<div class="auth__card">
  <header class="auth__card-header">
    <h2>Inicia sesión</h2>
    <p>Bienvenido/a de nuevo. Introduce tus datos para continuar.</p>
  </header>

  <form method="post" action="login.php" class="form" data-loading>
    <?= csrf_field() ?>

    <?php if (isset($errors['general'])): ?>
      <div class="alert alert--error" role="alert">
        <i class="ri-error-warning-line" aria-hidden="true"></i>
        <span><?= e($errors['general']) ?></span>
      </div>
    <?php endif; ?>

    <div class="<?= field_class($errors, 'email') ?>">
      <label for="email" class="field__label">Correo electrónico</label>
      <div class="input-icon">
        <i class="ri-mail-line" aria-hidden="true"></i>
        <input type="email" id="email" name="email" class="input"
               value="<?= e($old['email']) ?>" placeholder="tu@email.com"
               autocomplete="email" inputmode="email" maxlength="150" required
               <?= $old['email'] === '' ? 'autofocus' : '' ?>
               <?= field_aria($errors, 'email') ?>>
      </div>
      <?= field_error($errors, 'email') ?>
    </div>

    <div class="<?= field_class($errors, 'password') ?>">
      <label for="password" class="field__label">Contraseña</label>
      <div class="input-icon">
        <i class="ri-lock-2-line" aria-hidden="true"></i>
        <input type="password" id="password" name="password" class="input"
               placeholder="Tu contraseña" autocomplete="current-password" required
               <?= $old['email'] !== '' ? 'autofocus' : '' ?>
               <?= field_aria($errors, 'password') ?>>
        <button type="button" class="icon-btn icon-btn--sm input-icon__action"
                data-toggle-password="password" aria-label="Mostrar contraseña" aria-pressed="false">
          <i class="ri-eye-line" aria-hidden="true"></i>
        </button>
      </div>
      <?= field_error($errors, 'password') ?>
    </div>

    <button type="submit" class="btn btn--primary btn--lg btn--block">
      Entrar <i class="ri-arrow-right-line" aria-hidden="true"></i>
    </button>
  </form>

  <p class="auth__switch">¿Aún no tienes cuenta? <a href="register.php">Regístrate gratis</a></p>
</div>
