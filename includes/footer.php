<?php
/**
 * Pie común: cierra el layout y carga los scripts.
 * Se incluye automáticamente desde render().
 *
 * Variables opcionales:
 * @var array $fab          Botón flotante "+": ['href' => 'tasks.php?action=new', 'label' => 'Nueva tarea']
 * @var array $pageScripts  Scripts propios de la página: ['js/dashboard.js']
 */
?>
<?php if ($layout === 'app'): ?>
    </main>
  </div><!-- /.app__main -->
</div><!-- /.app -->

<?php if (!empty($fab)): ?>
<a href="<?= e($fab['href']) ?>" class="fab" aria-label="<?= e($fab['label']) ?>" title="<?= e($fab['label']) ?>">
  <i class="ri-add-line" aria-hidden="true"></i>
</a>
<?php endif; ?>
<?php else: ?>
  </section><!-- /.auth__panel -->
</main>
<?php endif; ?>

<script src="<?= asset('js/app.js') ?>" defer></script>
<?php foreach ($pageScripts ?? [] as $script): ?>
<script src="<?= asset($script) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
