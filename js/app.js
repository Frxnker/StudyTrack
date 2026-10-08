/**
 * StudyTrack · app.js
 * JavaScript global: se carga en todas las páginas.
 *
 * - Menú lateral deslizante (móvil)
 * - Notificaciones (toasts)
 * - Mostrar/ocultar contraseña y medidor de seguridad
 * - Evitar envíos dobles de formularios
 * - Sombra de la barra superior al hacer scroll
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

  /* ---------- Barra superior ---------- */
  function initTopbar() {
    const topbar = $('.topbar');
    if (!topbar) return;
    const onScroll = () => topbar.classList.toggle('is-scrolled', window.scrollY > 4);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Arranque ---------- */
  // El script se carga con "defer": el DOM ya está disponible.
  initSidebar();
  initToasts();
  initPasswordToggles();
  initPasswordStrength();
  initFormLoading();
  initTopbar();

  return { $, $$, toast };
})();
