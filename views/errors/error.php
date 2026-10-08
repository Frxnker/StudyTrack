<?php
/**
 * Página de error independiente del layout (404, 500, sin conexión a la BD…).
 *
 * @var int            $code
 * @var string         $title
 * @var string         $message
 * @var Throwable|null $exception  Solo en modo depuración
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
  <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css">
  <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
</head>
<body>
  <main class="error-page">
    <section class="card error-page__card">
      <p class="error-page__code"><?= (int) $code ?></p>
      <h1 class="empty__title"><?= e($title) ?></h1>
      <p class="text-muted"><?= e($message) ?></p>
      <a href="index.php" class="btn btn--primary mt-3">
        <i class="ri-home-5-line" aria-hidden="true"></i> Ir al inicio
      </a>
      <?php if (!empty($exception)): ?>
        <pre class="error-page__debug"><?= e(get_class($exception) . ': ' . $exception->getMessage()
          . "\n" . $exception->getFile() . ':' . $exception->getLine()
          . "\n\n" . $exception->getTraceAsString()) ?></pre>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
