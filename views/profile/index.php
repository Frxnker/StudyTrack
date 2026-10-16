<?php
/**
 * Vista: perfil (datos personales, apariencia, contraseña y eliminar cuenta).
 *
 * @var array $user    Usuario actual (user_find)
 * @var array $old     Valores del formulario de datos personales
 * @var array $errors  ['profile' => [...], 'password' => [...], 'delete' => [...]]
 */

$themeIcons = ['sistema' => 'ri-computer-line', 'claro' => 'ri-sun-line', 'oscuro' => 'ri-moon-line'];
$pErrors    = $errors['profile'];
$pwErrors   = $errors['password'];
$delErrors  = $errors['delete'];
?>
<div class="profile">

  <section class="card profile-card">
    <span class="avatar avatar--lg" aria-hidden="true"><?= e(initials($user['nombre'])) ?></span>
    <div class="profile-card__info">
      <h2 class="profile-card__name"><?= e($user['nombre']) ?></h2>
      <p class="profile-card__email"><?= e($user['email']) ?></p>
      <ul class="meta-list mt-1">
        <?php if ($user['curso']): ?>
          <li><i class="ri-graduation-cap-line" aria-hidden="true"></i> <?= e($user['curso']) ?></li>
        <?php endif; ?>
        <?php if ($user['centro']): ?>
          <li><i class="ri-building-line" aria-hidden="true"></i> <?= e($user['centro']) ?></li>
        <?php endif; ?>
        <li><i class="ri-calendar-line" aria-hidden="true"></i> Desde <?= e(format_date($user['fecha_registro'])) ?></li>
      </ul>
    </div>
  </section>

  <!-- Datos personales -->
  <section class="card card--form" aria-labelledby="datos-title">
    <header class="card__header">
      <h3 class="card__title" id="datos-title"><i class="ri-user-3-line" aria-hidden="true"></i> Datos personales</h3>
    </header>
    <form method="post" action="profile.php" class="form" data-loading>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">

      <div class="form-row">
        <div class="<?= field_class($pErrors, 'nombre') ?>">
          <label for="nombre" class="field__label">Nombre</label>
          <input type="text" id="nombre" name="nombre" class="input" value="<?= e($old['nombre']) ?>"
                 autocomplete="name" minlength="2" maxlength="100" required <?= field_aria($pErrors, 'nombre') ?>>
          <?= field_error($pErrors, 'nombre') ?>
        </div>

        <div class="<?= field_class($pErrors, 'email') ?>">
          <label for="email" class="field__label">Correo electrónico</label>
          <input type="email" id="email" name="email" class="input" value="<?= e($old['email']) ?>"
                 autocomplete="email" inputmode="email" maxlength="150" required <?= field_aria($pErrors, 'email') ?>>
          <?= field_error($pErrors, 'email') ?>
        </div>
      </div>

      <div class="form-row">
        <div class="<?= field_class($pErrors, 'curso') ?>">
          <label for="curso" class="field__label">Curso <span class="field__optional">Opcional</span></label>
          <input type="text" id="curso" name="curso" class="input" value="<?= e($old['curso']) ?>"
                 placeholder="Ej.: 1º DAW" maxlength="100" <?= field_aria($pErrors, 'curso') ?>>
          <?= field_error($pErrors, 'curso') ?>
        </div>

        <div class="<?= field_class($pErrors, 'centro') ?>">
          <label for="centro" class="field__label">Centro <span class="field__optional">Opcional</span></label>
          <input type="text" id="centro" name="centro" class="input" value="<?= e($old['centro']) ?>"
                 placeholder="Ej.: IES Tecnológico" maxlength="150" autocomplete="organization"
                 <?= field_aria($pErrors, 'centro') ?>>
          <?= field_error($pErrors, 'centro') ?>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn--primary">
          <i class="ri-check-line" aria-hidden="true"></i> Guardar datos
        </button>
      </div>
    </form>
  </section>

  <!-- Apariencia -->
  <section class="card card--form" id="apariencia" aria-labelledby="tema-title">
    <header class="card__header">
      <h3 class="card__title" id="tema-title"><i class="ri-palette-line" aria-hidden="true"></i> Apariencia</h3>
    </header>
    <form method="post" action="profile.php" class="form" data-loading>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="theme">

      <fieldset class="field fieldset">
        <legend class="field__label">Tema</legend>
        <div class="segmented segmented--themes">
          <?php foreach (USER_THEMES as $key => $label): ?>
            <label class="segmented__option">
              <input type="radio" name="tema" value="<?= e($key) ?>" data-theme-preview
                     <?= $user['tema'] === $key ? 'checked' : '' ?>>
              <span><i class="<?= $themeIcons[$key] ?>" aria-hidden="true"></i> <?= e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="field__hint">«Sistema» usa el modo claro u oscuro de tu dispositivo.</p>
      </fieldset>

      <div class="form-actions">
        <button type="submit" class="btn btn--primary">
          <i class="ri-check-line" aria-hidden="true"></i> Guardar tema
        </button>
      </div>
    </form>
  </section>

  <!-- Calendarios externos -->
  <section class="card card--form profile-link" aria-labelledby="calendarios-title">
    <span class="profile-link__icon" aria-hidden="true"><i class="ri-links-line"></i></span>
    <div class="profile-link__body">
      <h3 class="card__title" id="calendarios-title">Calendarios de Moodle y Classroom</h3>
      <p class="text-muted text-sm">Importa tus entregas y exámenes con la URL iCal de cada calendario. Solo fechas y títulos, nunca las notas.</p>
    </div>
    <a href="calendars.php" class="btn btn--secondary btn--sm">
      Gestionar <i class="ri-arrow-right-s-line" aria-hidden="true"></i>
    </a>
  </section>

  <!-- Contraseña -->
  <section class="card card--form" aria-labelledby="password-title">
    <header class="card__header">
      <h3 class="card__title" id="password-title"><i class="ri-lock-2-line" aria-hidden="true"></i> Cambiar contraseña</h3>
    </header>
    <form method="post" action="profile.php" class="form" data-loading>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <!-- Ayuda a los gestores de contraseñas a saber de qué cuenta es -->
      <input type="email" name="username" value="<?= e($user['email']) ?>" autocomplete="username" hidden>

      <div class="<?= field_class($pwErrors, 'password_actual') ?>">
        <label for="password_actual" class="field__label">Contraseña actual</label>
        <div class="input-icon">
          <i class="ri-lock-line" aria-hidden="true"></i>
          <input type="password" id="password_actual" name="password_actual" class="input"
                 autocomplete="current-password" maxlength="72" required <?= field_aria($pwErrors, 'password_actual') ?>>
          <button type="button" class="icon-btn icon-btn--sm input-icon__action"
                  data-toggle-password="password_actual" aria-label="Mostrar contraseña" aria-pressed="false">
            <i class="ri-eye-line" aria-hidden="true"></i>
          </button>
        </div>
        <?= field_error($pwErrors, 'password_actual') ?>
      </div>

      <div class="form-row">
        <div class="<?= field_class($pwErrors, 'password') ?>">
          <label for="password" class="field__label">Nueva contraseña</label>
          <div class="input-icon">
            <i class="ri-lock-2-line" aria-hidden="true"></i>
            <input type="password" id="password" name="password" class="input" placeholder="Mínimo 8 caracteres"
                   autocomplete="new-password" minlength="8" maxlength="72" required <?= field_aria($pwErrors, 'password') ?>>
            <button type="button" class="icon-btn icon-btn--sm input-icon__action"
                    data-toggle-password="password" aria-label="Mostrar contraseña" aria-pressed="false">
              <i class="ri-eye-line" aria-hidden="true"></i>
            </button>
          </div>
          <div class="strength" data-password-strength="password" aria-live="polite">
            <div class="strength__bars" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            <span class="strength__label"></span>
          </div>
          <?php if (isset($pwErrors['password'])): ?>
            <?= field_error($pwErrors, 'password') ?>
          <?php else: ?>
            <p class="field__hint">Al menos 8 caracteres con letras y números.</p>
          <?php endif; ?>
        </div>

        <div class="<?= field_class($pwErrors, 'password_confirm') ?>">
          <label for="password_confirm" class="field__label">Repite la nueva contraseña</label>
          <div class="input-icon">
            <i class="ri-lock-2-line" aria-hidden="true"></i>
            <input type="password" id="password_confirm" name="password_confirm" class="input"
                   autocomplete="new-password" maxlength="72" required <?= field_aria($pwErrors, 'password_confirm') ?>>
          </div>
          <?= field_error($pwErrors, 'password_confirm') ?>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn--primary">
          <i class="ri-check-line" aria-hidden="true"></i> Cambiar contraseña
        </button>
      </div>
    </form>
  </section>

  <!-- Eliminar cuenta -->
  <section class="card card--form danger-zone danger-zone--stacked" aria-labelledby="delete-title">
    <div>
      <h3 class="card__title" id="delete-title"><i class="ri-error-warning-line" aria-hidden="true"></i> Eliminar cuenta</h3>
      <p class="text-muted text-sm">
        Se borrarán para siempre tu cuenta y todos tus datos: asignaturas, tareas, exámenes, notas, horario y objetivos.
      </p>
    </div>
    <form method="post" action="profile.php" class="form" data-loading
          data-confirm="¿Eliminar tu cuenta definitivamente?"
          data-confirm-text="Perderás todos tus datos y no podrás recuperarlos.">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">

      <div class="<?= field_class($delErrors, 'password_borrar') ?>">
        <label for="password_borrar" class="field__label">Escribe tu contraseña para confirmar</label>
        <input type="password" id="password_borrar" name="password_borrar" class="input"
               autocomplete="current-password" maxlength="72" required <?= field_aria($delErrors, 'password_borrar') ?>>
        <?= field_error($delErrors, 'password_borrar') ?>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn--danger">
          <i class="ri-delete-bin-6-line" aria-hidden="true"></i> Eliminar mi cuenta
        </button>
      </div>
    </form>
  </section>
</div>
