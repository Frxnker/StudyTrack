/**
 * StudyTrack · calendars.js
 * Formulario de calendarios externos: el punto de color junto al desplegable de
 * asignatura muestra el color de la asignatura elegida (un <select> no puede
 * mostrarlo por sí solo). Sin JavaScript el formulario funciona igual.
 */
'use strict';

(() => {
  document.querySelectorAll('[data-subject-select]').forEach((wrapper) => {
    const select = wrapper.querySelector('select');
    const swatch = wrapper.querySelector('[data-swatch]');
    if (!select || !swatch) return;

    const update = () => {
      const color = select.selectedOptions[0]?.dataset.color || 'transparent';
      swatch.style.setProperty('--subject', color);
    };

    select.addEventListener('change', update);
    update();
  });
})();
