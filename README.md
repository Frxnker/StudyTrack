# StudyTrack

Aplicación web para que los estudiantes gestionen su curso en un solo lugar:
asignaturas, tareas, exámenes, notas, horario, objetivos y estadísticas.

Está hecha con **PHP 8 y MySQL, sin frameworks**, con un diseño *mobile-first*
que funciona igual de bien en el móvil que en el ordenador, con tema claro y oscuro.

![Dashboard de StudyTrack](assets/img/screenshots/dashboard.png)

## Funcionalidades

| Sección | Qué puedes hacer |
| --- | --- |
| **Inicio** | Resumen del día: tareas para hoy y atrasadas, exámenes de la semana, cuenta atrás del próximo examen, progreso académico (media general), resumen semanal y últimas notas. |
| **Asignaturas** | Crear, editar y borrar asignaturas con profesor, curso y color (paleta o color libre). Cada tarjeta muestra la media, las tareas pendientes y el próximo examen; el detalle reúne sus tareas, exámenes, notas y horario. |
| **Tareas** | Tareas con asignatura opcional, fecha límite, prioridad y estado. Pestañas (hoy, próximos 7 días, atrasadas), filtros, búsqueda y completar en un clic. Se resaltan las vencidas y las de hoy. |
| **Exámenes** | Próximos exámenes con cuenta atrás y exámenes pasados. Un examen realizado tiene el botón «Añadir nota», que abre el formulario de notas ya rellenado. |
| **Notas** | Calificaciones de 0 a 10 (con coma o punto y hasta dos decimales) por tipo, agrupadas por asignatura con su media, media general y aprobado/suspenso. |
| **Horario** | Clases semanales sin solapes: rejilla en escritorio y lista por día en el móvil, con el día de hoy destacado. |
| **Calendario** | Vista mensual con exámenes y fechas límite de tareas en el color de su asignatura. Al pulsar un día se ve lo que hay; en el móvil, agenda del mes en lista. |
| **Objetivos** | Metas medibles («5 ejercicios», «20 horas»…) con barra de progreso y botones +/−. Se completan solas al llegar a la meta. |
| **Estadísticas** | Gráficos SVG hechos a mano: media por asignatura, evolución de las notas, tareas completadas por semana y tareas por estado, con *tooltips*. |
| **Perfil** | Datos personales, tema claro/oscuro/sistema, cambio de contraseña y eliminación de la cuenta. |

### Seguridad

- Contraseñas cifradas con `password_hash()` y sesión con cookie `HttpOnly`, `SameSite=Lax`,
  `Secure` en HTTPS, regeneración del id al iniciar sesión y caducidad por inactividad.
- Todas las consultas son **preparadas** (PDO) y filtran por el usuario de la sesión:
  nadie puede ver ni modificar datos de otro usuario.
- Todos los formularios llevan **token CSRF** y toda la salida se escapa con `e()` (evita XSS).
- Patrón POST → redirección → GET con mensajes *flash*.

## Capturas

![Inicio de sesión](assets/img/screenshots/login.png)

| Login (móvil) | Inicio (móvil) | Tareas (móvil) | Exámenes (móvil) |
| --- | --- | --- | --- |
| ![Inicio de sesión en el móvil](assets/img/screenshots/login-movil.png) | ![Dashboard en el móvil](assets/img/screenshots/dashboard-movil.png) | ![Listado de tareas en el móvil](assets/img/screenshots/tareas-movil.png) | ![Exámenes con cuenta atrás en el móvil](assets/img/screenshots/examenes-movil.png) |

![Calendario mensual](assets/img/screenshots/calendario.png)

![Horario semanal](assets/img/screenshots/horario.png)

![Estadísticas con el tema oscuro](assets/img/screenshots/estadisticas-oscuro.png)

## Tecnologías

- **PHP 8** sin frameworks (`declare(strict_types=1)`): controladores en la raíz, modelos con las
  consultas y vistas con `render()`.
- **MySQL / MariaDB** con **PDO** y consultas preparadas. Claves foráneas compuestas para que la
  base de datos impida mezclar datos de usuarios distintos.
- **HTML5 y CSS3**: variables CSS, Grid y Flexbox, diseño *mobile-first* y tema oscuro.
- **JavaScript vanilla**, sin librerías: menú, avisos, confirmaciones, cuentas atrás y gráficos en SVG.
- **[Inter](https://rsms.me/inter/)** y **[Remix Icon](https://remixicon.com/)** servidos desde
  `assets/`: la app se ve completa sin conexión a internet.
- **Docker**: imagen `php:8.3-apache` y MySQL 8.4 con Docker Compose.

## Requisitos

- **PHP 8.0 o superior** (probado con PHP 8.2) con las extensiones `pdo_mysql` y, recomendada, `mbstring`.
- **MySQL 8** o **MariaDB 10.4** o superior.
- Un servidor web: **XAMPP**, **Laragon** o **Docker** (ver más abajo).

## Instalación

### Con XAMPP

1. Copia la carpeta del proyecto en `C:\xampp\htdocs\StudyTrack`
   (o clónala: `git clone https://github.com/Frxnker/StudyTrack.git C:\xampp\htdocs\StudyTrack`).
2. Abre el panel de XAMPP (`xampp-control.exe`) y pulsa **Start** en **Apache** y en **MySQL**.
3. Abre phpMyAdmin en <http://localhost/phpmyadmin> e importa la base de datos:
   1. Pestaña **Importar** → **Seleccionar archivo** → `sql/database.sql` → **Importar**.
      Crea la base de datos `studytrack` con todas sus tablas.
   2. Repite con `sql/demo_data.sql` para cargar el [usuario de prueba](#usuario-de-prueba) (opcional).
4. Entra en <http://localhost/StudyTrack> e inicia sesión con el usuario de prueba o crea tu cuenta.

Si tu MySQL tiene contraseña o un usuario distinto de `root`, mira [Configuración](#configuración).

### Con Laragon

1. Copia o clona el proyecto en `C:\laragon\www\StudyTrack`.
2. Pulsa **Iniciar todo** en Laragon.
3. Importa la base de datos (ver [Base de datos](#base-de-datos)). Laragon incluye HeidiSQL
   y phpMyAdmin (menú *Base de datos*).
4. Entra en <http://studytrack.test> (si tienes activados los *virtual hosts* automáticos)
   o en <http://localhost/StudyTrack>.

### Con Docker

Solo necesitas [Docker Desktop](https://www.docker.com/products/docker-desktop/) (o Docker Engine con Compose).
`docker-compose.yml` arranca dos contenedores: la app (PHP 8.3 + Apache) y MySQL 8.4.

```bash
cp .env.example .env      # opcional: cambia las contraseñas y el puerto
docker compose up -d --build
```

Abre <http://localhost:8080> y entra con el [usuario de prueba](#usuario-de-prueba).

- La primera vez, MySQL importa automáticamente `sql/database.sql` y `sql/demo_data.sql`.
  Si no quieres los datos de ejemplo, comenta esa línea en `docker-compose.yml` antes de arrancar.
- Los datos se guardan en el volumen `db-data`. Para empezar de cero (y volver a importar el SQL):
  `docker compose down -v`.
- Errores de PHP: `docker compose logs -f app`.
- Si lo publicas detrás de un proxy inverso con HTTPS (Nginx, Traefik, Caddy…), pon
  `TRUST_PROXY=true` en `.env` para que la cookie de sesión se marque como `Secure`.

### Base de datos

El esquema está en `sql/database.sql` y crea la base de datos `studytrack` con todas las tablas.
Los datos de ejemplo (`sql/demo_data.sql`) son opcionales.

**Con phpMyAdmin** (<http://localhost/phpmyadmin>):

1. Pestaña **Importar** → selecciona `sql/database.sql` → **Continuar**.
2. Si quieres datos de ejemplo, repite con `sql/demo_data.sql`.

**Por consola:**

```bash
mysql -u root < sql/database.sql
mysql -u root < sql/demo_data.sql   # opcional
```

> ⚠️ `database.sql` **borra y vuelve a crear las tablas**: si lo reimportas perderás los datos.
> `demo_data.sql` se puede reimportar cuando quieras: solo borra y recrea el usuario de prueba.

### Configuración

Los valores por defecto funcionan con XAMPP y Laragon (usuario `root` sin contraseña).
Si tu MySQL usa otras credenciales, crea `config/database.local.php` (Git lo ignora):

```php
<?php
return ['user' => 'studytrack', 'password' => 'mi-contraseña'];
```

También se pueden usar variables de entorno:

| Variable | Por defecto | Para qué sirve |
| --- | --- | --- |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306` | Servidor de MySQL |
| `DB_NAME` | `studytrack` | Base de datos |
| `DB_USER`, `DB_PASS` | `root`, *(vacía)* | Credenciales |
| `APP_DEBUG` | `false` | `true` muestra los detalles de los errores. Solo para desarrollo. |
| `TRUST_PROXY` | `false` | `true` si hay un proxy inverso con HTTPS delante (usa `X-Forwarded-Proto`). |

Para activar la depuración en XAMPP o Laragon, añade `SetEnv APP_DEBUG true` al final de
`httpd.conf` (en XAMPP, *Config → Apache (httpd.conf)*) y reinicia Apache.

La cookie de sesión se marca como `Secure` automáticamente cuando la web se sirve por HTTPS.

El resto de ajustes (nombre, zona horaria, nota de aprobado…) están en `config/app.php`.

El archivo `.htaccess` impide el acceso directo a las carpetas internas
(`config/`, `includes/`, `models/`, `views/`, `sql/`…) y el listado de directorios.

## Usuario de prueba

Si has importado `sql/demo_data.sql`:

| Email | Contraseña |
| --- | --- |
| `demo@studytrack.local` | `Demo1234` |

Tiene cinco asignaturas de 1º DAW con tareas, exámenes, notas, horario y objetivos.
Las fechas son relativas al día de la importación, así que siempre hay tareas para hoy y exámenes próximos.

## Estructura de carpetas

```text
StudyTrack/
├── index.php             Entrada: lleva al dashboard o al login
├── login.php, register.php, logout.php
├── dashboard.php         ┐
├── subjects.php          │
├── tasks.php             │
├── exams.php             │ Controladores: reciben la petición, validan,
├── grades.php            │ llaman a los modelos y pintan una vista
├── schedule.php          │
├── calendar.php          │
├── goals.php             │
├── statistics.php        │
├── profile.php           ┘
├── config/
│   ├── app.php           Ajustes generales (zona horaria, nota de aprobado…)
│   └── database.php      Conexión PDO y funciones db_one(), db_all(), db_execute()…
├── includes/
│   ├── bootstrap.php     Arranque común de todas las páginas
│   ├── auth.php          Sesión segura, login y protección de páginas
│   ├── functions.php     Escape, CSRF, validación, fechas, render()…
│   └── header.php, navbar.php, footer.php   Layout
├── models/               Consultas SQL de cada tabla (subject_*, task_*, exam_*…)
├── views/                Plantillas HTML de cada sección
│   └── partials/         Fragmentos reutilizables (estado vacío, cuenta atrás, borrar…)
├── css/
│   ├── style.css         Estilos base mobile-first y tema oscuro
│   └── responsive.css    Ajustes para tablet y escritorio
├── js/
│   ├── app.js            Menú, avisos, confirmaciones, filtros, cuenta atrás…
│   ├── dashboard.js      Animaciones del dashboard
│   ├── calendar.js       Lista del día pulsado en el calendario
│   ├── goals.js          Estado del formulario de objetivos según el progreso
│   └── statistics.js     Gráficos SVG
├── sql/
│   ├── database.sql      Esquema de la base de datos
│   └── demo_data.sql     Datos de ejemplo
├── assets/
│   ├── fonts/            Tipografía Inter (woff2, pesos 400–800)
│   ├── vendor/remixicon/ Iconos Remix Icon (CSS y fuentes)
│   └── img/              Favicon y capturas de este README (screenshots/)
├── .htaccess             Bloquea el acceso a las carpetas internas
├── Dockerfile            Imagen PHP 8.3 + Apache
├── docker-compose.yml    App + MySQL con importación automática del SQL
└── .env.example          Variables para Docker (copiar como .env)
```
