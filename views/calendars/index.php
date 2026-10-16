<?php
/**
 * Vista: calendarios externos conectados, ayuda para conseguir la URL, tareas
 * importadas pendientes de asignar y correspondencias guardadas.
 *
 * @var array $calendars  calendar_feed_all() + url_enmascarada
 * @var array $pending    calendar_feed_pending(): [['texto', 'origen', 'calendario', 'tareas'], …]
 * @var array $mappings   calendar_feed_mappings()
 * @var array $subjects   id, nombre, color
 * @var bool  $hasKey     ¿Está configurada APP_KEY?
 */

$pendingCount = array_sum(array_map(static fn (array $g): int => count($g['tareas']), $pending));
?>
<header class="page-header">
  <div>
    <h2 class="page-header__title">Calendarios externos</h2>
    <p class="page-header__subtitle">Importa las entregas y exámenes de Moodle y Google Classroom. Solo fechas y títulos: las notas no.</p>
  </div>
  <?php if ($hasKey): ?>
    <div class="page-header__actions hide-mobile">
      <a href="calendars.php?action=new" class="btn btn--primary">
        <i class="ri-add-line" aria-hidden="true"></i> Conectar calendario
      </a>
    </div>
  <?php endif; ?>
</header>

<?php if (!$hasKey): ?>
  <div class="alert alert--warning mb-2" role="alert">
    <i class="ri-key-2-line" aria-hidden="true"></i>
    <div>
      <strong>Falta la clave de cifrado (APP_KEY).</strong>
      Las URLs de los calendarios se guardan cifradas. Copia <code>config/app.local.example.php</code> como
      <code>config/app.local.php</code> y pon una clave generada con
      <code>php -r "echo 'base64:' . base64_encode(random_bytes(32));"</code>, o define la variable de entorno APP_KEY.
    </div>
  </div>
<?php endif; ?>

<?php if ($calendars): ?>
  <div class="feed-grid">
    <?php foreach ($calendars as $calendar): ?>
      <?php
        $id     = (int) $calendar['id'];
        $origin = $calendar['origen'];
      ?>
      <article class="card feed-card" id="calendario-<?= $id ?>" aria-labelledby="feed-<?= $id ?>-title">
        <header class="feed-card__header">
          <?php partial('feed_icon', ['origin' => $origin]); ?>
          <div class="feed-card__heading">
            <h3 class="feed-card__title" id="feed-<?= $id ?>-title"><?= e($calendar['nombre']) ?></h3>
            <p class="feed-card__origin">
              <?= e(CALENDAR_ORIGINS[$origin]) ?>
              <?php if (!$calendar['activo']): ?>
                · <span class="text-soft">sincronización automática pausada</span>
              <?php endif; ?>
            </p>
          </div>
          <a href="calendars.php?action=edit&amp;id=<?= $id ?>" class="icon-btn icon-btn--sm"
             aria-label="Editar <?= e($calendar['nombre']) ?>" title="Editar">
            <i class="ri-pencil-line" aria-hidden="true"></i>
          </a>
        </header>

        <dl class="feed-card__facts">
          <div>
            <dt>Asignatura</dt>
            <dd>
              <?php if ($calendar['asignatura'] !== null): ?>
                <span class="chip" style="--subject: <?= e(safe_color($calendar['color'])) ?>"><span class="dot" aria-hidden="true"></span><?= e($calendar['asignatura']) ?></span>
              <?php else: ?>
                Según el curso de cada evento
              <?php endif; ?>
            </dd>
          </div>
          <div>
            <dt>Importados</dt>
            <dd><?= e(human_list([plural((int) $calendar['num_tareas'], 'tarea', 'tareas'), plural((int) $calendar['num_examenes'], 'examen', 'exámenes')])) ?></dd>
          </div>
          <div>
            <dt>Última sincronización</dt>
            <dd><?= $calendar['ultima_sincronizacion'] ? e(ucfirst(format_moment($calendar['ultima_sincronizacion']))) : '<span class="text-soft">Nunca</span>' ?></dd>
          </div>
          <div>
            <dt>URL</dt>
            <dd class="feed-card__url"><i class="ri-lock-2-line" aria-hidden="true"></i> <?= e($calendar['url_enmascarada']) ?></dd>
          </div>
        </dl>

        <?php if ($calendar['ultimo_error']): ?>
          <p class="alert alert--error">
            <i class="ri-error-warning-line" aria-hidden="true"></i>
            <span><strong>Último error:</strong> <?= e($calendar['ultimo_error']) ?></span>
          </p>
        <?php endif; ?>

        <footer class="feed-card__footer">
          <form method="post" action="calendars.php" class="inline-form" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="sync">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn btn--primary btn--sm">
              <i class="ri-refresh-line" aria-hidden="true"></i> Sincronizar ahora
            </button>
          </form>
          <details class="feed-card__more">
            <summary class="btn btn--ghost btn--sm">
              <i class="ri-delete-bin-6-line" aria-hidden="true"></i> Eliminar
            </summary>
            <?php partial('feed_delete', ['calendar' => $calendar]); ?>
          </details>
        </footer>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <section class="card">
    <?php partial('empty', [
        'icon'        => 'ri-links-line',
        'title'       => 'Aún no has conectado ningún calendario',
        'text'        => 'Conecta Moodle o tus clases de Classroom y sus entregas y exámenes aparecerán en Tareas, Exámenes y Calendario.',
        'actionHref'  => $hasKey ? 'calendars.php?action=new' : null,
        'actionLabel' => 'Conectar calendario',
    ]); ?>
  </section>
<?php endif; ?>

<!-- Ayuda: de dónde sacar la URL -->
<details class="card feed-help mt-2" id="ayuda"<?= $calendars ? '' : ' open' ?>>
  <summary class="feed-help__summary">
    <i class="ri-question-line" aria-hidden="true"></i> ¿Cómo consigo la URL de mi calendario?
    <i class="ri-arrow-down-s-line feed-help__chevron" aria-hidden="true"></i>
  </summary>
  <div class="feed-help__body">
    <section>
      <h3 class="feed-help__title"><?php partial('feed_icon', ['origin' => 'moodle', 'small' => true]); ?> Moodle</h3>
      <ol class="feed-help__steps">
        <li>Entra en Moodle y abre el <strong>Calendario</strong>.</li>
        <li>Pulsa <strong>Importar o exportar calendarios</strong> → <strong>Exportar calendario</strong>.</li>
        <li>En «Eventos a exportar» marca <strong>Todos los eventos</strong> y en «Período», <strong>Recientes y próximos</strong>.</li>
        <li>Pulsa <strong>Obtener URL del calendario</strong>, cópiala y pégala aquí.</li>
      </ol>
      <p class="text-muted text-sm">Moodle mezcla todos tus cursos en un calendario: StudyTrack reconoce la asignatura por el nombre del curso.</p>
    </section>
    <section>
      <h3 class="feed-help__title"><?php partial('feed_icon', ['origin' => 'classroom', 'small' => true]); ?> Google Classroom</h3>
      <ol class="feed-help__steps">
        <li>Abre <strong>Google Calendar</strong> con tu cuenta del instituto: cada clase de Classroom tiene su propio calendario.</li>
        <li>En «Mis calendarios», pulsa <strong>⋮</strong> junto a la clase → <strong>Configuración y uso compartido</strong>.</li>
        <li>Copia la <strong>Dirección secreta en formato iCal</strong>.</li>
        <li>Conecta un calendario por clase y <strong>elige su asignatura</strong> al conectarlo.</li>
      </ol>
    </section>
    <p class="alert alert--warning">
      <i class="ri-lock-2-line" aria-hidden="true"></i>
      <span>Esa URL da acceso a tu calendario sin contraseña: <strong>trátala como una contraseña</strong> y no la compartas.
        StudyTrack la guarda cifrada y nunca la muestra completa.</span>
    </p>
  </div>
</details>

<?php if ($pending): ?>
  <section class="card mt-2" id="pendientes" aria-labelledby="pending-title">
    <header class="card__header">
      <h3 class="card__title" id="pending-title"><i class="ri-inbox-line" aria-hidden="true"></i> Pendientes de asignar</h3>
      <span class="badge badge--warning"><?= e(plural($pendingCount, 'tarea', 'tareas')) ?></span>
    </header>
    <p class="text-muted text-sm mb-2">
      Estas tareas importadas no se han podido asociar a ninguna asignatura. Elige la suya y, si la recuerdas,
      las próximas del mismo curso irán directamente a ella.
    </p>

    <ul class="pending-list">
      <?php foreach ($pending as $i => $group): ?>
        <li class="pending">
          <div class="pending__head">
            <span class="pending__text">«<?= e($group['texto']) ?>»</span>
            <?php partial('source_badge', ['origin' => $group['origen']]); ?>
          </div>
          <ul class="pending__tasks">
            <?php foreach (array_slice($group['tareas'], 0, 3) as $task): ?>
              <li>
                <a href="tasks.php?action=edit&amp;id=<?= (int) $task['id'] ?>"><?= e($task['titulo']) ?></a>
                <?php if ($task['fecha_limite']): ?><span class="text-soft">· <?= e(format_date($task['fecha_limite'])) ?></span><?php endif; ?>
              </li>
            <?php endforeach; ?>
            <?php if (count($group['tareas']) > 3): ?>
              <li class="text-soft">y <?= count($group['tareas']) - 3 ?> más</li>
            <?php endif; ?>
          </ul>

          <form method="post" action="calendars.php" class="pending__form" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="texto" value="<?= e($group['texto']) ?>">
            <label for="assign-<?= $i ?>" class="sr-only">Asignatura para «<?= e($group['texto']) ?>»</label>
            <select id="assign-<?= $i ?>" name="asignatura_id" class="select select--sm" required>
              <option value="">Elige la asignatura…</option>
              <?php foreach ($subjects as $subject): ?>
                <option value="<?= (int) $subject['id'] ?>"><?= e($subject['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="check check--sm">
              <input type="checkbox" name="recordar" value="1" checked>
              <span>Recordar para las próximas</span>
            </label>
            <button type="submit" class="btn btn--primary btn--sm">
              <i class="ri-check-line" aria-hidden="true"></i> Asignar
            </button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($mappings): ?>
  <section class="card mt-2" id="correspondencias" aria-labelledby="mappings-title">
    <header class="card__header">
      <h3 class="card__title" id="mappings-title"><i class="ri-git-merge-line" aria-hidden="true"></i> Correspondencias guardadas</h3>
    </header>
    <ul class="mapping-list">
      <?php foreach ($mappings as $mapping): ?>
        <li class="mapping">
          <span class="mapping__text">«<?= e($mapping['texto_origen']) ?>»</span>
          <i class="ri-arrow-right-line text-soft" aria-hidden="true"></i>
          <span class="chip" style="--subject: <?= e(safe_color($mapping['color'])) ?>"><span class="dot" aria-hidden="true"></span><?= e($mapping['asignatura']) ?></span>
          <form method="post" action="calendars.php" class="inline-form mapping__delete"
                data-confirm="¿Olvidar esta correspondencia?"
                data-confirm-text="Las próximas tareas de «<?= e($mapping['texto_origen']) ?>» se asignarán de nuevo automáticamente (o quedarán pendientes). Las ya asignadas no cambian.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="unmap">
            <input type="hidden" name="id" value="<?= (int) $mapping['id'] ?>">
            <button type="submit" class="icon-btn icon-btn--sm icon-btn--danger" aria-label="Olvidar la correspondencia de «<?= e($mapping['texto_origen']) ?>»" title="Olvidar">
              <i class="ri-close-line" aria-hidden="true"></i>
            </button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
