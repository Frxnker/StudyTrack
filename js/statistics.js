/**
 * StudyTrack · statistics.js
 * Gráficos en SVG con JavaScript vanilla (sin librerías).
 *
 * Los datos llegan desde PHP en <script type="application/json" id="stats-data">.
 * Cada gráfico se dibuja dentro de su contenedor [data-chart="…"]:
 *   subjects → barras horizontales con la media de cada asignatura
 *   timeline → cada nota en el tiempo + línea de media acumulada
 *   weeks    → columnas con las tareas completadas por semana
 *   statuses → anillo (donut) con las tareas por estado
 *
 * Los colores generales y la tipografía vienen del CSS (clases .chart__*), así que
 * los gráficos respetan el tema claro/oscuro. Al cambiar el ancho se redibujan.
 * Al pasar el ratón (o tocar en el móvil) cada barra o punto muestra un tooltip.
 */
'use strict';

(() => {
  const source = document.getElementById('stats-data');
  if (!source) return;

  const data = JSON.parse(source.textContent);
  const SVG_NS = 'http://www.w3.org/2000/svg';
  const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  const GRADE_TICKS = [0, 2.5, 5, 7.5, 10];

  /* ---------- Utilidades ---------- */

  /** Crea un elemento SVG con sus atributos y, si se indica, lo añade a un padre. */
  function el(tag, attrs = {}, parent = null) {
    const node = document.createElementNS(SVG_NS, tag);
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, String(value)));
    if (parent) parent.append(node);
    return node;
  }

  /** Texto SVG. textContent: los nombres de asignatura nunca se interpretan como HTML. */
  function text(parent, x, y, content, attrs = {}) {
    const node = el('text', { x, y, ...attrs }, parent);
    node.textContent = String(content);
    return node;
  }

  /** Texto del tooltip de un elemento del gráfico (lo muestra initTooltip). */
  function tooltip(node, content) {
    node.dataset.tip = content;
    node.classList.add('has-tip');
  }

  /** Número con formato español: 7.5 → "7,5". */
  const fmt = (n) => Number(n).toLocaleString('es-ES', { maximumFractionDigits: 2 });

  /** "2026-10-05" → "5 oct" */
  function shortDate(iso) {
    const [, month, day] = iso.split('-').map(Number);
    return `${day} ${MONTHS[month - 1]}`;
  }

  /** Recorta un texto para que quepa en un ancho aproximado (≈ 7 px por carácter). */
  function fit(content, width, charWidth = 7) {
    const max = Math.max(3, Math.floor(width / charWidth));
    return content.length > max ? `${content.slice(0, max - 1)}…` : content;
  }

  /** Lienzo SVG con el ancho real del contenedor (el texto no se deforma). */
  function canvas(container, height) {
    const width = Math.max(Math.floor(container.clientWidth), 260);
    const svg = el('svg', {
      viewBox: `0 0 ${width} ${height}`,
      width,
      height,
      role: 'img',
      'aria-label': container.dataset.label || '',
    }, container);
    return { svg, width, height };
  }

  /* ---------- 1. Media por asignatura: barras horizontales ---------- */
  function drawSubjects(container, rows) {
    const rowH = 36;
    const top = 4;
    const bottom = 24;
    const { svg, width, height } = canvas(container, top + rows.length * rowH + bottom);

    const labelW = Math.min(150, Math.round(width * 0.32));
    const x0 = labelW + 10;
    const x1 = width - 44; // hueco a la derecha para el valor
    const scale = (value) => x0 + (Math.min(value, 10) / 10) * (x1 - x0);

    const grid = el('g', { class: 'chart__grid' }, svg);
    GRADE_TICKS.forEach((value) => {
      el('line', { x1: scale(value), x2: scale(value), y1: top, y2: height - bottom }, grid);
      text(svg, scale(value), height - 6, fmt(value), { 'text-anchor': 'middle', class: 'chart__tick' });
    });

    rows.forEach((row, i) => {
      const cy = top + i * rowH + rowH / 2;
      const barH = 18;

      text(svg, labelW, cy + 4, fit(row.name, labelW), { 'text-anchor': 'end', class: 'chart__label' });

      const bar = el('rect', {
        x: x0,
        y: cy - barH / 2,
        width: Math.max(3, scale(row.average) - x0),
        height: barH,
        rx: 5,
        class: 'chart__bar',
      }, svg);
      bar.style.fill = row.color;
      bar.style.animationDelay = `${i * 60}ms`;
      tooltip(bar, `${row.name}: ${fmt(row.average)} de media (${row.count} ${row.count === 1 ? 'nota' : 'notas'})`);

      text(svg, scale(row.average) + 6, cy + 4, fmt(row.average), {
        class: `chart__value${row.average < data.passMark ? ' is-fail' : ''}`,
      });
    });

    // Línea del aprobado, por encima de las barras
    const passX = scale(data.passMark);
    el('line', { x1: passX, x2: passX, y1: top - 2, y2: height - bottom + 2, class: 'chart__pass' }, svg);
  }

  /* ---------- 2. Evolución de las notas: puntos + media acumulada ---------- */
  function drawTimeline(container, points) {
    const pad = { top: 12, right: 14, bottom: 26, left: 32 };
    const { svg, width, height } = canvas(container, 240);
    const plotW = width - pad.left - pad.right;
    const plotH = height - pad.top - pad.bottom;

    const times = points.map((p) => Date.parse(p.date)); // UTC: sin saltos por cambio de hora
    const min = Math.min(...times);
    const max = Math.max(...times);
    const x = (t) => (max === min ? pad.left + plotW / 2 : pad.left + ((t - min) / (max - min)) * plotW);
    const y = (value) => pad.top + (1 - value / 10) * plotH;

    // Eje Y: rejilla horizontal
    const grid = el('g', { class: 'chart__grid' }, svg);
    GRADE_TICKS.forEach((value) => {
      el('line', { x1: pad.left, x2: width - pad.right, y1: y(value), y2: y(value) }, grid);
      text(svg, pad.left - 8, y(value) + 4, fmt(value), { 'text-anchor': 'end', class: 'chart__tick' });
    });

    // Eje X: hasta 5 fechas repartidas (menos si no hay tantas fechas distintas o falta sitio)
    const distinctDates = new Set(points.map((p) => p.date)).size;
    const ticks = Math.max(1, Math.min(distinctDates, plotW < 320 ? 3 : 5));
    for (let i = 0; i < ticks; i++) {
      const t = ticks === 1 ? min : min + ((max - min) * i) / (ticks - 1);
      let anchor = 'middle';
      if (ticks > 1 && i === 0) anchor = 'start';
      if (ticks > 1 && i === ticks - 1) anchor = 'end';
      text(svg, x(t), height - 6, shortDate(new Date(t).toISOString().slice(0, 10)), {
        'text-anchor': anchor,
        class: 'chart__tick',
      });
    }

    el('line', { x1: pad.left, x2: width - pad.right, y1: y(data.passMark), y2: y(data.passMark), class: 'chart__pass' }, svg);

    // Media acumulada: área suave + línea
    const coords = points.map((p, i) => [x(times[i]).toFixed(1), y(p.average).toFixed(1)]);
    const line = coords.map(([cx, cy], i) => `${i === 0 ? 'M' : 'L'}${cx},${cy}`).join(' ');
    const [firstX] = coords[0];
    const [lastX] = coords[coords.length - 1];
    el('path', { d: `${line} L${lastX},${y(0)} L${firstX},${y(0)} Z`, class: 'chart__area' }, svg);
    el('path', { d: line, class: 'chart__line', pathLength: 1 }, svg);

    // Cada nota, con el color de su asignatura
    points.forEach((p, i) => {
      const dot = el('circle', { cx: x(times[i]), cy: y(p.grade), r: 5, class: 'chart__point' }, svg);
      dot.style.fill = p.color;
      dot.style.animationDelay = `${300 + i * 40}ms`;
      tooltip(dot, `${p.subject} (${p.type.toLowerCase()}), ${shortDate(p.date)}: ${fmt(p.grade)} · media ${fmt(p.average)}`);
    });
  }

  /* ---------- 3. Tareas completadas por semana: columnas ---------- */
  function drawWeeks(container, weeks) {
    const pad = { top: 20, right: 4, bottom: 26, left: 4 };
    const { svg, width, height } = canvas(container, 220);
    const plotH = height - pad.top - pad.bottom;
    const base = pad.top + plotH;
    const slot = (width - pad.left - pad.right) / weeks.length;
    const barW = Math.min(38, slot * 0.6);
    const maxCount = Math.max(1, ...weeks.map((w) => w.count));
    const showAllLabels = slot >= 48;

    el('line', { x1: pad.left, x2: width - pad.right, y1: base, y2: base, class: 'chart__axis' }, svg);

    weeks.forEach((week, i) => {
      const cx = pad.left + slot * i + slot / 2;
      const h = Math.max(3, (week.count / maxCount) * plotH);

      const bar = el('rect', {
        x: cx - barW / 2,
        y: base - h,
        width: barW,
        height: h,
        rx: 6,
        class: `chart__col${week.current ? ' is-current' : ''}${week.count === 0 ? ' is-empty' : ''}`,
      }, svg);
      bar.style.animationDelay = `${i * 50}ms`;
      tooltip(bar, `Semana del ${week.range}: ${week.count} ${week.count === 1 ? 'tarea completada' : 'tareas completadas'}`);

      if (week.count > 0) {
        text(svg, cx, base - h - 6, week.count, { 'text-anchor': 'middle', class: 'chart__value' });
      }

      // En pantallas estrechas, una etiqueta sí y otra no (siempre la semana actual)
      if (showAllLabels || (weeks.length - 1 - i) % 2 === 0) {
        text(svg, cx, height - 6, week.current ? 'Esta' : week.label, {
          'text-anchor': 'middle',
          class: `chart__tick${week.current ? ' is-current' : ''}`,
        });
      }
    });
  }

  /* ---------- 4. Tareas por estado: anillo ---------- */
  function drawStatuses(container, slices) {
    const size = 180;
    const center = size / 2;
    const radius = 68;
    const stroke = 24;
    const circumference = 2 * Math.PI * radius;
    const total = slices.reduce((sum, s) => sum + s.count, 0);
    const visible = slices.filter((s) => s.count > 0);
    const gap = visible.length > 1 ? 3 : 0; // pequeño hueco entre porciones

    const svg = el('svg', {
      viewBox: `0 0 ${size} ${size}`,
      width: size,
      height: size,
      role: 'img',
      'aria-label': container.dataset.label || '',
    }, container);

    el('circle', { cx: center, cy: center, r: radius, 'stroke-width': stroke, class: 'chart__track' }, svg);

    // Cada porción es un círculo con un trazo discontinuo; se empieza arriba (−90°)
    const ring = el('g', { transform: `rotate(-90 ${center} ${center})` }, svg);
    let offset = 0;
    visible.forEach((slice, i) => {
      const length = (slice.count / total) * circumference;
      const arc = el('circle', {
        cx: center,
        cy: center,
        r: radius,
        'stroke-width': stroke,
        'stroke-dasharray': `${Math.max(0, length - gap)} ${circumference - length + gap}`,
        'stroke-dashoffset': -offset,
        class: `chart__slice chart__slice--${slice.key}`,
      }, ring);
      arc.style.animationDelay = `${i * 120}ms`;
      tooltip(arc, `${slice.label}: ${slice.count} (${Math.round((slice.count / total) * 100)} %)`);
      offset += length;
    });

    text(svg, center, center + 4, total, { 'text-anchor': 'middle', class: 'chart__donut-value' });
    text(svg, center, center + 24, total === 1 ? 'tarea' : 'tareas', { 'text-anchor': 'middle', class: 'chart__donut-label' });
  }

  /* ---------- Tooltip ----------
   * Un único <div> que sigue al puntero sobre los elementos con data-tip.
   * Con ratón aparece al pasar por encima; en pantallas táctiles, al tocar
   * (y se oculta al tocar fuera). Los lectores de pantalla usan las tablas
   * "Ver datos", por eso el tooltip va con aria-hidden.
   */
  function initTooltip() {
    const tip = document.createElement('div');
    tip.className = 'chart-tooltip';
    tip.setAttribute('aria-hidden', 'true');
    document.body.append(tip);

    let current = null;

    const place = (x, y) => {
      const margin = 8;
      const { width, height } = tip.getBoundingClientRect();
      const left = Math.min(Math.max(x, width / 2 + margin), window.innerWidth - width / 2 - margin);
      const above = y - height - 14 > margin; // si no cabe encima, debajo del puntero
      tip.style.left = `${left}px`;
      tip.style.top = `${above ? y - 14 : y + 18}px`;
      tip.classList.toggle('is-below', !above);
    };

    const show = (target, x, y) => {
      if (current) current.classList.remove('is-active');
      current = target;
      current.classList.add('is-active');
      tip.textContent = target.dataset.tip; // textContent: nunca HTML
      tip.classList.add('is-visible');
      place(x, y);
    };

    const hide = () => {
      if (current) current.classList.remove('is-active');
      current = null;
      tip.classList.remove('is-visible');
    };

    document.addEventListener('pointerover', (event) => {
      const target = event.target.closest?.('.has-tip');
      if (target && event.pointerType !== 'touch') show(target, event.clientX, event.clientY);
    });
    document.addEventListener('pointermove', (event) => {
      if (current && event.pointerType !== 'touch') place(event.clientX, event.clientY);
    });
    document.addEventListener('pointerout', (event) => {
      if (current && event.pointerType !== 'touch' && !current.contains(event.relatedTarget)) hide();
    });
    document.addEventListener('pointerdown', (event) => {
      if (event.pointerType !== 'touch') return;
      const target = event.target.closest?.('.has-tip');
      if (target) show(target, event.clientX, event.clientY);
      else hide();
    });
    window.addEventListener('scroll', hide, { passive: true });
  }

  /* ---------- Dibujo y redibujo ---------- */
  const DRAW = {
    subjects: drawSubjects,
    timeline: drawTimeline,
    weeks: drawWeeks,
    statuses: drawStatuses,
  };

  const containers = [...document.querySelectorAll('[data-chart]')];

  function render(container, animate) {
    const key = container.dataset.chart;
    const rows = data[key];
    if (!DRAW[key] || !Array.isArray(rows) || rows.length === 0) return;

    container.replaceChildren();
    container.classList.toggle('is-static', !animate); // Al redibujar no se repite la animación
    DRAW[key](container, rows);
  }

  containers.forEach((container) => render(container, true));
  initTooltip();

  // Redibuja al cambiar el ancho (girar el móvil, redimensionar la ventana…)
  if ('ResizeObserver' in window) {
    const widths = new WeakMap();
    const observer = new ResizeObserver((entries) => {
      entries.forEach(({ target, contentRect }) => {
        const width = Math.round(contentRect.width);
        if (widths.has(target) && widths.get(target) !== width) render(target, false);
        widths.set(target, width);
      });
    });
    containers
      .filter((container) => container.dataset.chart !== 'statuses') // el anillo tiene tamaño fijo
      .forEach((container) => observer.observe(container));
  }
})();
