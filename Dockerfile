# StudyTrack · imagen de producción (PHP 8.3 + Apache)
#
# Se usa desde docker-compose.yml, que añade la base de datos MySQL:
#   docker compose up -d --build   → http://localhost:8080

FROM php:8.3-apache

# Extensión PDO para MySQL (mbstring ya viene incluida en la imagen oficial)
RUN docker-php-ext-install pdo_mysql

# PHP: configuración de producción; los errores van al log del contenedor (docker compose logs)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf '%s\n' \
      'expose_php = Off' \
      'display_errors = Off' \
      'log_errors = On' \
      'error_log = /dev/stderr' \
      > "$PHP_INI_DIR/conf.d/studytrack.ini"

# Apache: activa el .htaccess del proyecto (bloquea las carpetas internas)
# y no anuncia la versión del servidor
RUN printf '%s\n' \
      'ServerTokens Prod' \
      'ServerSignature Off' \
      '<Directory /var/www/html>' \
      '    Options -Indexes +FollowSymLinks' \
      '    AllowOverride All' \
      '</Directory>' \
      > /etc/apache2/conf-available/studytrack.conf \
 && a2enconf studytrack

# Código de la aplicación (lo que no hace falta está en .dockerignore)
COPY . /var/www/html/
