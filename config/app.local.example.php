<?php
/**
 * Configuración local de StudyTrack (ejemplo).
 *
 * Copia este archivo como config/app.local.php (Git lo ignora) y pon tu clave.
 * También puedes usar la variable de entorno APP_KEY (por ejemplo en Docker).
 *
 * APP_KEY cifra las URLs secretas de los calendarios de Moodle y Classroom.
 * Genera una clave aleatoria con:
 *
 *     php -r "echo 'base64:' . base64_encode(random_bytes(32)), PHP_EOL;"
 *
 * (en XAMPP: C:\xampp\php\php.exe -r "…")
 *
 * ⚠ No la cambies después de conectar calendarios: las URLs guardadas con la
 *   clave anterior no se podrán leer y tendrás que volver a conectarlos.
 */

declare(strict_types=1);

return [
    'APP_KEY' => 'base64:PEGA_AQUI_TU_CLAVE',
];
