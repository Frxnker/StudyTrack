/**
 * StudyTrack · goals.js
 * Formulario de objetivos: mantiene el estado coherente con el progreso mientras
 * se escribe, con las mismas reglas que aplica el servidor (goal_resolve_status):
 *  - Al llegar a la meta → "Completado".
 *  - Con algo de progreso no puede estar "Pendiente" → "En progreso".
 *  - Si estaba "Completado" y se baja el progreso → "En progreso" (o "Pendiente" en 0).
 *  - Al elegir "Completado" a mano → el progreso pasa a la meta.
 *
 * Sin JavaScript el servidor aplica las mismas reglas al guardar.
 */
'use strict';

(() => {
  const form = document.querySelector('[data-goal-form]');
  if (!form) return;

  const current = form.querySelector('#valor_actual');
  const target = form.querySelector('#valor_meta');
  const radios = [...form.querySelectorAll('input[name="estado"]')];
  if (!current || !target || radios.length === 0) return;

  const toInt = (input) => (/^\d+$/.test(input.value.trim()) ? parseInt(input.value, 10) : null);
  const checked = () => radios.find((radio) => radio.checked)?.value;
  const check = (value) => radios.forEach((radio) => { radio.checked = radio.value === value; });

  function syncStatus() {
    const now = toInt(current);
    const goal = toInt(target);
    if (now === null || goal === null || goal < 1) return;

    // El navegador avisa si el progreso supera la meta antes de enviar
    current.max = String(goal);

    if (now >= goal) {
      check('completado');
    } else if (checked() === 'completado') {
      check(now > 0 ? 'en_progreso' : 'pendiente');
    } else if (checked() === 'pendiente' && now > 0) {
      check('en_progreso');
    }
  }

  current.addEventListener('input', syncStatus);
  target.addEventListener('input', syncStatus);

  radios.forEach((radio) => {
    radio.addEventListener('change', () => {
      const goal = toInt(target);
      if (radio.checked && radio.value === 'completado' && goal !== null && goal >= 1) {
        current.value = String(goal);
      }
    });
  });

  syncStatus();
})();
