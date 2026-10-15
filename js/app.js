/**
 * StudyTrack · app.js
 * JavaScript global: se carga en todas las páginas.
 *
 * - Menú lateral deslizante (móvil)
 * - Notificaciones (toasts)
 * - Mostrar/ocultar contraseña y medidor de seguridad
 * - Evitar envíos dobles de formularios
 * - Confirmación antes de borrar, selector de color y filtros
 * - Vista previa del tema claro/oscuro
 * - Sombra de la barra superior al hacer scroll
 * - Cuentas atrás de exámenes
 */
'use strict';

const App = (() => {
  const $ = (selector, context = document) => context.querySelector(selector);
  const $$ = (selector, context = document) => [...context.querySelectorAll(selector)];

  /* ---------- Menú lateral (drawer en móvil) ---------- */
  function initSidebar() {
    const sidebar = $('#sidebar');
    const backdrop = $('.sidebar-backdrop');
    const openButton = $('[data-sidebar-open]');
    if (!sidebar || !openButton) return;

    const open = () => {
      sidebar.classList.add('is-open');
      backdrop?.classList.add('is-visible');
      openButton.setAttribute('aria-expanded', 'true');
      document.body.classList.add('no-scroll');
      // Movemos el foco dentro del menú para usuarios de teclado
      setTimeout(() => $('.sidebar__link', sidebar)?.focus(), 50);
    };

    const close = () => {
      if (!sidebar.classList.contains('is-open')) return;
      sidebar.classList.remove('is-open');
      backdrop?.classList.remove('is-visible');
      openButton.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('no-scroll');
      openButton.focus();
    };

    openButton.addEventListener('click', open);
    $$('[data-sidebar-close]').forEach((el) => el.addEventListener('click', close));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });
  }

  /* ---------- Notificaciones (toasts) ---------- */
  const TOAST_ICONS = {
    success: 'ri-checkbox-circle-fill',
    error: 'ri-error-warning-fill',
    warning: 'ri-alert-fill',
    info: 'ri-information-fill',
  };

  function dismissToast(toastEl) {
    if (!toastEl.isConnected || toastEl.classList.contains('is-leaving')) return;
    toastEl.classList.add('is-leaving');
    toastEl.addEventListener('animationend', () => toastEl.remove(), { once: true });
  }

  function setupToast(toastEl, index = 0) {
    $('.toast__close', toastEl)?.addEventListener('click', () => dismissToast(toastEl));
    // Los errores se quedan más tiempo en pantalla
    const delay = toastEl.classList.contains('toast--error') ? 7000 : 4500;
    setTimeout(() => dismissToast(toastEl), delay + index * 400);
  }

  /** Muestra un toast desde JavaScript: App.toast('Guardado', 'success') */
  function toast(message, type = 'info') {
    let container = $('#toasts');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toasts';
      container.className = 'toasts';
      container.setAttribute('aria-live', 'polite');
      document.body.append(container);
    }

    const toastEl = document.createElement('div');
    toastEl.className = `toast toast--${type}`;
    toastEl.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const icon = document.createElement('i');
    icon.className = TOAST_ICONS[type] || TOAST_ICONS.info;
    icon.setAttribute('aria-hidden', 'true');

    const text = document.createElement('span');
    text.className = 'toast__message';
    text.textContent = message; // textContent: nunca interpretamos HTML

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'icon-btn icon-btn--sm toast__close';
    closeButton.setAttribute('aria-label', 'Cerrar notificación');
    closeButton.innerHTML = '<i class="ri-close-line" aria-hidden="true"></i>';

    toastEl.append(icon, text, closeButton);
    container.append(toastEl);
    setupToast(toastEl);
  }

  function initToasts() {
    $$('.toast').forEach(setupToast);
  }

  /* ---------- Contraseñas ---------- */
  function initPasswordToggles() {
    $$('[data-toggle-password]').forEach((button) => {
      const input = document.getElementById(button.dataset.togglePassword);
      if (!input) return;

      button.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
        $('i', button).className = show ? 'ri-eye-off-line' : 'ri-eye-line';
      });
    });
  }

  function passwordScore(value) {
    if (!value) return 0;
    const checks = [
      value.length >= 8,
      value.length >= 12,
      /[a-záéíóúñ]/.test(value) && /[A-ZÁÉÍÓÚÑ]/.test(value),
      /\d/.test(value),
      /[^A-Za-z0-9]/.test(value),
    ];
    const passed = checks.filter(Boolean).length; // 0–5
    return Math.max(1, Math.min(4, Math.ceil((passed * 4) / 5)));
  }

  function initPasswordStrength() {
    const labels = ['', 'Débil', 'Mejorable', 'Buena', 'Muy segura'];

    $$('[data-password-strength]').forEach((meter) => {
      const input = document.getElementById(meter.dataset.passwordStrength);
      const label = $('.strength__label', meter);
      if (!input) return;

      const update = () => {
        const level = passwordScore(input.value);
        meter.dataset.level = String(level);
        if (label) label.textContent = labels[level];
      };
      input.addEventListener('input', update);
      update();
    });
  }

  /* ---------- Formularios: evita envíos dobles ---------- */
  function initFormLoading() {
    $$('form[data-loading]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (form.dataset.submitted === '1') {
          event.preventDefault();
          return;
        }
        form.dataset.submitted = '1';
        const button = event.submitter || $('[type="submit"]', form);
        button?.classList.add('is-loading');
      });
    });

    // Si el usuario vuelve atrás, el navegador puede restaurar la página
    // desde caché con el botón aún "cargando": lo reiniciamos.
    window.addEventListener('pageshow', (event) => {
      if (!event.persisted) return;
      $$('form[data-loading]').forEach((form) => {
        delete form.dataset.submitted;
        $$('.is-loading', form).forEach((el) => el.classList.remove('is-loading'));
      });
    });
  }

  /* ---------- Confirmación antes de borrar ----------
   * <form method="post" data-confirm="¿Eliminar «X»?" data-confirm-text="No se puede deshacer.">
   * Se escucha en fase de captura para actuar antes que initFormLoading.
   */
  function confirmDialog(title, text) {
    if (typeof HTMLDialogElement !== 'function') {
      return Promise.resolve(window.confirm(text ? `${title}\n\n${text}` : title));
    }

    return new Promise((resolve) => {
      const dialog = document.createElement('dialog');
      dialog.className = 'modal';
      dialog.setAttribute('aria-labelledby', 'modal-title');
      dialog.setAttribute('aria-describedby', 'modal-text');
      dialog.innerHTML = `
        <form method="dialog" class="modal__box">
          <div class="modal__icon"><i class="ri-delete-bin-6-line" aria-hidden="true"></i></div>
          <h2 class="modal__title" id="modal-title"></h2>
          <p class="modal__text" id="modal-text"></p>
          <div class="modal__actions">
            <button type="submit" value="cancel" class="btn btn--ghost">Cancelar</button>
            <button type="submit" value="ok" class="btn btn--danger">
              <i class="ri-delete-bin-6-line" aria-hidden="true"></i> Eliminar
            </button>
          </div>
        </form>`;
      // textContent: el título y el texto vienen de datos del usuario
      $('.modal__title', dialog).textContent = title;
      $('.modal__text', dialog).textContent = text;

      // Clic fuera del cuadro (en el fondo) = cancelar
      dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close('cancel');
      });
      dialog.addEventListener('close', () => {
        resolve(dialog.returnValue === 'ok');
        dialog.remove();
      }, { once: true });

      document.body.append(dialog);
      dialog.showModal();
      $('[value="cancel"]', dialog).focus(); // Opción segura por defecto
    });
  }

  function initConfirm() {
    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;

      // Segunda vuelta, ya confirmada: dejamos que el formulario se envíe
      if (form.dataset.confirmed === '1') {
        delete form.dataset.confirmed;
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      const opener = event.submitter || document.activeElement;

      confirmDialog(form.dataset.confirm, form.dataset.confirmText || '').then((ok) => {
        if (!ok) {
          opener?.focus?.(); // Devolvemos el foco al botón que abrió el diálogo
          return;
        }
        form.dataset.confirmed = '1';
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      });
    }, true);
  }

  /* ---------- Selector de color (asignaturas) ----------
   * El selector libre <input type="color" data-color-picker> actualiza el radio
   * "personalizado" (data-color-custom), que es el que se envía con el formulario.
   */
  function initColorPickers() {
    $$('[data-color-picker]').forEach((picker) => {
      const radio = $('[data-color-custom]', picker.parentElement);
      if (!radio) return;

      const select = () => {
        radio.value = picker.value.toUpperCase();
        radio.checked = true;
      };
      picker.addEventListener('input', select);
      picker.addEventListener('click', select);
    });
  }

  /* ---------- Filtros de los listados ----------
   * - Los <select data-autosubmit> aplican el filtro en cuanto cambian.
   * - El panel <details data-filters> se abre solo en escritorio (en móvil ocupa mucho).
   */
  function initFilters() {
    $$('select[data-autosubmit]').forEach((select) => {
      select.addEventListener('change', () => {
        const form = select.form;
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
      });
    });

    if (window.matchMedia('(min-width: 1024px)').matches) {
      $$('details[data-filters]').forEach((details) => { details.open = true; });
    }
  }

  /* ---------- Vista previa del tema (perfil) ----------
   * Al elegir un tema se aplica al momento; se guarda al enviar el formulario.
   */
  function initThemePreview() {
    $$('input[data-theme-preview]').forEach((radio) => {
      radio.addEventListener('change', () => {
        if (radio.checked) document.documentElement.dataset.theme = radio.value;
      });
    });
  }

  /* ---------- Barra superior ---------- */
  function initTopbar() {
    const topbar = $('.topbar');
    if (!topbar) return;
    const onScroll = () => topbar.classList.toggle('is-scrolled', window.scrollY > 4);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Cuenta atrás ----------
   * <div data-countdown="2026-10-11T10:00:00+02:00">
   *   <span data-unit="days"></span> <span data-unit="hours"></span> …
   * </div>
   * <p data-countdown-done hidden>¡Es hoy!</p>   ← se muestra al llegar a 0
   */
  function initCountdowns() {
    $$('[data-countdown]').forEach((el) => {
      const target = new Date(el.dataset.countdown).getTime();
      if (Number.isNaN(target)) return;

      const units = {
        days: $('[data-unit="days"]', el),
        hours: $('[data-unit="hours"]', el),
        minutes: $('[data-unit="minutes"]', el),
        seconds: $('[data-unit="seconds"]', el),
      };
      const doneMessage = el.parentElement && $('[data-countdown-done]', el.parentElement);
      const pad = (n) => String(n).padStart(2, '0');
      let timer = null;

      const tick = () => {
        const diff = Math.max(0, Math.floor((target - Date.now()) / 1000));

        if (diff === 0) {
          el.hidden = true;
          if (doneMessage) doneMessage.hidden = false;
          clearInterval(timer);
          return false;
        }

        if (units.days) units.days.textContent = Math.floor(diff / 86400);
        if (units.hours) units.hours.textContent = pad(Math.floor((diff % 86400) / 3600));
        if (units.minutes) units.minutes.textContent = pad(Math.floor((diff % 3600) / 60));
        if (units.seconds) units.seconds.textContent = pad(diff % 60);
        return true;
      };

      if (tick()) timer = setInterval(tick, 1000);
    });
  }

  /* ---------- Arranque ---------- */
  // El script se carga con "defer": el DOM ya está disponible.
  initSidebar();
  initToasts();
  initPasswordToggles();
  initPasswordStrength();
  initFormLoading();
  initConfirm();
  initColorPickers();
  initFilters();
  initThemePreview();
  initTopbar();
  initCountdowns();

  return { $, $$, toast };
})();
