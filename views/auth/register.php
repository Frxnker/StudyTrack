<?php
/**
 * Vista: registro de usuario.
 *
 * @var array $errors
 * @var array $old
 */
?>
<div class="auth__card">
  <header class="auth__card-header">
    <h2>Crea tu cuenta</h2>
    <p>Empieza a organizar tus estudios en menos de un minuto.</p>
  </header>

  <form method="post" action="register.php" class="form" data-loading>
    <?= csrf_field() ?>

    <div class="<?= field_class($errors, 'nombre') ?>">
      <label for="nombre" class="field__label">Nombre</label>
      <div class="input-icon">
        <i class="ri-user-line" aria-hidden="true"></i>
        <input type="text" id="nombre" name="nombre" class="input"
               value="<?= e($old['nombre']) ?>" placeholder="¿Cómo te llamas?"
               autocomplete="name" minlength="2" maxlength="100" required autofocus
               <?= field_aria($errors, 'nombre') ?>>
      </div>
      <?= field_error($errors, 'nombre') ?>
    </div>

    <div class="<?= field_class($errors, 'email') ?>">
      <label for="email" class="field__label">Correo electrónico</label>
      <div class="input-icon">
        <i class="ri-mail-line" aria-hidden="true"></i>
        <input type="email" id="email" name="email" class="input"
               value="<?= e($old['email']) ?>" placeholder="tu@email.com"
               autocomplete="email" inputmode="email" maxlength="150" required
               <?= field_aria($errors, 'email') ?>>
      </div>
      <?= field_error($errors, 'email') ?>
    </div>

    <div class="<?= field_class($errors, 'password') ?>">
      <label for="password" class="field__label">Contraseña</label>
      <div class="input-icon">
        <i class="ri-lock-2-line" aria-hidden="true"></i>
        <input type="password" id="password" name="password" class="input"
               placeholder="Mínimo 8 caracteres" autocomplete="new-password"
               minlength="8" maxlength="72" required
               <?= field_aria($errors, 'password') ?>>
        <button type="button" class="icon-btn icon-btn--sm input-icon__action"
                data-toggle-password="password" aria-label="Mostrar contraseña" aria-pressed="false">
          <i class="ri-eye-line" aria-hidden="true"></i>
        </button>
      </div>
      <div class="strength" data-password-strength="password" aria-live="polite">
        <div class="strength__bars" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
        <span class="strength__label"></span>
      </div>
      <?php if (isset($errors['password'])): ?>
        <?= field_error($errors, 'password') ?>
      <?php else: ?>
        <p class="field__hint">Usa al menos 8 caracteres con letras y números.</p>
      <?php endif; ?>
    </div>

    <div class="<?= field_class($errors, 'password_confirm') ?>">
      <label for="password_confirm" class="field__label">Repite la contraseña</label>
      <div class="input-icon">
        <i class="ri-lock-2-line" aria-hidden="true"></i>
        <input type="password" id="password_confirm" name="password_confirm" class="input"
               placeholder="Vuelve a escribirla" autocomplete="new-password" maxlength="72" required
               <?= field_aria($errors, 'password_confirm') ?>>
      </div>
      <?= field_error($errors, 'password_confirm') ?>
    </div>

    <button type="submit" class="btn btn--primary btn--lg btn--block">
      Crear cuenta <i class="ri-arrow-right-line" aria-hidden="true"></i>
    </button>
  </form>

  <p class="auth__switch">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
</div>
