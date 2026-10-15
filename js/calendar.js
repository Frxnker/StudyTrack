/**
 * StudyTrack · calendar.js
 * Calendario: al pulsar un día se muestra lo que hay ese día sin recargar la página.
 *
 * Sin JavaScript todo funciona igual: cada día es un enlace a ?mes=…&dia=…, que
 * marca el día en el servidor. Aquí solo se evita la recarga:
 *  - se marca la celda elegida (.is-selected),
 *  - se muestra su bloque de la agenda (#dia-AAAA-MM-DD) o el aviso "no hay nada",
 *  - se actualizan el título del panel y los enlaces "+ Tarea" / "+ Examen",
 *  - y la URL (history.replaceState) para que al recargar se vea el mismo día.
 */
'use strict';

(() => {
  const calendar = document.querySelector('[data-calendar]');
  const agenda = document.querySelector('[data-agenda]');
  if (!calendar || !agenda) return;

  const title = agenda.querySelector('[data-day-title]');
  const actions = agenda.querySelector('[data-day-actions]');
  const emptyDay = agenda.querySelector('[data-empty-day]');
  const emptyText = agenda.querySelector('[data-empty-text]');

  function selectDay(cell) {
    const { date, label } = cell.dataset;

    calendar.querySelectorAll('.calendar__cell.is-selected').forEach((c) => {
      c.classList.remove('is-selected');
      c.querySelector('.calendar__day')?.removeAttribute('aria-current');
    });
    cell.classList.add('is-selected');
    cell.querySelector('.calendar__day')?.setAttribute('aria-current', 'true');

    agenda.querySelectorAll('.agenda__day.is-selected').forEach((s) => s.classList.remove('is-selected'));
    const section = document.getElementById(`dia-${date}`);
    if (section) {
      section.classList.add('is-selected');
    } else {
      emptyText.textContent = 'No tienes exámenes ni entregas este día.';
      emptyDay.classList.add('is-selected');
    }

    title.textContent = label; // aria-live: los lectores de pantalla lo anuncian
    actions.hidden = false;
    actions.querySelectorAll('[data-add]').forEach((link) => {
      link.href = `${link.dataset.add}.php?action=new&fecha=${date}`;
    });

    const url = new URL(window.location.href);
    url.searchParams.set('mes', date.slice(0, 7));
    url.searchParams.set('dia', date);
    url.hash = '';
    history.replaceState(null, '', url);
  }

  calendar.addEventListener('click', (event) => {
    // Los eventos llevan a su tarea o examen: no se interceptan
    if (event.target.closest('.calendar__event')) return;

    const cell = event.target.closest('td[data-date]');
    if (!cell) return;

    // Días de otro mes: se deja que el enlace cargue ese mes
    if (cell.classList.contains('is-other') && event.target.closest('a')) return;
    if (cell.classList.contains('is-other')) {
      window.location.href = cell.querySelector('.calendar__day').href;
      return;
    }

    event.preventDefault();
    selectDay(cell);

    // Si el panel no se ve (pantalla baja), se desplaza hasta él
    const rect = agenda.getBoundingClientRect();
    if (rect.top > window.innerHeight - 80) {
      agenda.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
})();
