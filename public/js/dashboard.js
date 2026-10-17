/**
 * StudyTrack · dashboard.js
 * Animaciones del dashboard.
 *
 * Los números de las tarjetas de estadísticas "cuentan" desde 0 hasta su valor.
 * PHP ya pinta el valor final, así que sin JavaScript todo se ve igual (solo sin animación).
 *
 *   <span data-count-up="7.45" data-decimals="2">7,45</span>
 *
 * La cuenta atrás del próximo examen está en app.js (también se usa en Exámenes).
 */
'use strict';

(() => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduceMotion) return;

  const DURATION = 900; // ms

  document.querySelectorAll('[data-count-up]').forEach((el) => {
    const target = parseFloat(el.dataset.countUp);
    const decimals = parseInt(el.dataset.decimals || '0', 10);
    const finalText = el.textContent; // Texto exacto que generó PHP
    if (!Number.isFinite(target) || target <= 0) return;

    const format = (value) => value.toLocaleString('es-ES', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    });

    const start = performance.now();
    const step = (now) => {
      const progress = Math.min(1, (now - start) / DURATION);
      const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic

      if (progress < 1) {
        el.textContent = format(target * eased);
        requestAnimationFrame(step);
      } else {
        el.textContent = finalText;
      }
    };

    el.textContent = format(0);
    requestAnimationFrame(step);
  });
})();
