-- =============================================================================
--  StudyTrack · Esquema de la base de datos
--  Compatible con MySQL 8+ y MariaDB 10.4+ (XAMPP / Laragon)
--
--  Importación:
--    phpMyAdmin → pestaña "Importar" → seleccionar este archivo → Continuar
--    o por consola:  mysql -u root < sql/database.sql
--
--  ⚠ ATENCIÓN: este script BORRA y vuelve a crear las tablas. Si lo reimportas
--    perderás los datos guardados.
--
--  Decisiones de diseño
--  - Todas las tablas usan InnoDB (claves foráneas y transacciones) y utf8mb4
--    (acentos, ñ y emojis).
--  - Cada tabla de datos personales tiene usuario_id: así cada consulta filtra
--    por el usuario de la sesión y nadie puede ver datos ajenos.
--  - examenes, notas y horario usan una clave foránea COMPUESTA
--    (asignatura_id, usuario_id) → asignaturas(id, usuario_id). La propia base
--    de datos impide asociar un examen a una asignatura de otro usuario.
--  - El progreso de un objetivo NO se guarda: se calcula a partir de
--    valor_actual / valor_meta (evitamos datos derivados → 3FN).
--  - Calendarios externos (Moodle, Classroom… por iCal): sus eventos se
--    importan como tareas o exámenes con calendario_id y uid_externo, único por
--    usuario para no duplicarlos. Si ya tienes una base de datos en marcha,
--    aplica sql/migrations/002_calendarios_externos.sql en lugar de este archivo.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS studytrack
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE studytrack;

SET NAMES utf8mb4;

-- Se borran en orden inverso a sus dependencias
DROP TABLE IF EXISTS objetivos;
DROP TABLE IF EXISTS horario;
DROP TABLE IF EXISTS notas;
DROP TABLE IF EXISTS examenes;
DROP TABLE IF EXISTS tareas;
DROP TABLE IF EXISTS correspondencias_externas;
DROP TABLE IF EXISTS calendarios_externos;
DROP TABLE IF EXISTS asignaturas;
DROP TABLE IF EXISTS usuarios;


-- -----------------------------------------------------------------------------
-- usuarios: cuentas de la aplicación
-- -----------------------------------------------------------------------------
CREATE TABLE usuarios (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(100)     NOT NULL,
  email           VARCHAR(150)     NOT NULL,
  password_hash   VARCHAR(255)     NOT NULL COMMENT 'Generado con password_hash()',
  curso           VARCHAR(100)     NULL     COMMENT 'Ej.: 1º DAW',
  centro          VARCHAR(150)     NULL     COMMENT 'Centro educativo',
  tema            ENUM('sistema','claro','oscuro') NOT NULL DEFAULT 'sistema',
  fecha_registro  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso   DATETIME         NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- asignaturas: materias de cada usuario
-- -----------------------------------------------------------------------------
CREATE TABLE asignaturas (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  usuario_id      INT UNSIGNED     NOT NULL,
  nombre          VARCHAR(100)     NOT NULL,
  descripcion     TEXT             NULL,
  profesor        VARCHAR(100)     NULL,
  curso           VARCHAR(50)      NULL,
  color           CHAR(7)          NOT NULL DEFAULT '#4F46E5' COMMENT 'Hexadecimal #RRGGBB',
  fecha_creacion  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- Un usuario no puede repetir el nombre de una asignatura
  UNIQUE KEY uk_asignaturas_usuario_nombre (usuario_id, nombre),
  -- Índice necesario para las claves foráneas compuestas de examenes, notas y horario
  UNIQUE KEY uk_asignaturas_id_usuario (id, usuario_id),
  CONSTRAINT fk_asignaturas_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- calendarios_externos: calendarios iCal conectados (Moodle, Classroom…)
--   La URL se guarda cifrada con APP_KEY. asignatura_id usa una clave foránea
--   simple: una compuesta con ON DELETE SET NULL pondría también usuario_id a
--   NULL, y MySQL/MariaDB no lo permiten (la pertenencia se comprueba en PHP).
-- -----------------------------------------------------------------------------
CREATE TABLE calendarios_externos (
  id                     INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  usuario_id             INT UNSIGNED     NOT NULL,
  asignatura_id          INT UNSIGNED     NULL     COMMENT 'Si se indica, todos sus eventos son de esa asignatura',
  nombre                 VARCHAR(100)     NOT NULL,
  origen                 ENUM('moodle','classroom','otro') NOT NULL DEFAULT 'otro',
  url                    TEXT             NOT NULL COMMENT 'Cifrada con APP_KEY (nunca en claro)',
  tipo_por_defecto       ENUM('tarea','examen') NOT NULL DEFAULT 'tarea',
  activo                 TINYINT(1)       NOT NULL DEFAULT 1 COMMENT 'La sincronización automática (cron) solo usa los activos',
  ultima_sincronizacion  DATETIME         NULL,
  ultimo_error           VARCHAR(500)     NULL,
  fecha_creacion         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_calendarios_usuario (usuario_id),
  KEY idx_calendarios_asignatura (asignatura_id),
  CONSTRAINT fk_calendarios_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_calendarios_asignatura
    FOREIGN KEY (asignatura_id) REFERENCES asignaturas (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- correspondencias_externas: "este curso de Moodle es esta asignatura mía"
--   Se crean desde calendars.php al asignar una tarea importada sin asignatura.
-- -----------------------------------------------------------------------------
CREATE TABLE correspondencias_externas (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  usuario_id      INT UNSIGNED     NOT NULL,
  texto_origen    VARCHAR(255)     NOT NULL COMMENT 'Nombre del curso (CATEGORIES) o título del evento',
  asignatura_id   INT UNSIGNED     NOT NULL,
  fecha_creacion  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_correspondencias_usuario_texto (usuario_id, texto_origen),
  KEY idx_correspondencias_asignatura_usuario (asignatura_id, usuario_id),
  CONSTRAINT fk_correspondencias_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_correspondencias_asignatura
    FOREIGN KEY (asignatura_id, usuario_id) REFERENCES asignaturas (id, usuario_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- tareas: deberes y entregas
--   La asignatura es opcional: si se borra la asignatura, la tarea se conserva
--   sin asignatura (ON DELETE SET NULL).
--   Las importadas de un calendario llevan calendario_id, uid_externo (único por
--   usuario: un evento no se importa dos veces) y texto_origen.
-- -----------------------------------------------------------------------------
CREATE TABLE tareas (
  id                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  usuario_id        INT UNSIGNED   NOT NULL,
  asignatura_id     INT UNSIGNED   NULL,
  calendario_id     INT UNSIGNED   NULL,
  uid_externo       VARCHAR(255)   NULL COMMENT 'UID del evento iCal importado',
  texto_origen      VARCHAR(255)   NULL COMMENT 'Curso o título de origen (para asignarle asignatura)',
  titulo            VARCHAR(150)   NOT NULL,
  descripcion       TEXT           NULL,
  fecha_limite      DATE           NULL,
  prioridad         ENUM('baja','media','alta')                  NOT NULL DEFAULT 'media',
  estado            ENUM('pendiente','en_progreso','completada') NOT NULL DEFAULT 'pendiente',
  fecha_creacion    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_completada  DATETIME       NULL COMMENT 'Se rellena al completar (estadísticas semanales)',
  PRIMARY KEY (id),
  UNIQUE KEY uk_tareas_usuario_uid (usuario_id, uid_externo),
  KEY idx_tareas_usuario_estado (usuario_id, estado),
  KEY idx_tareas_usuario_fecha (usuario_id, fecha_limite),
  KEY idx_tareas_asignatura (asignatura_id),
  KEY idx_tareas_calendario (calendario_id),
  CONSTRAINT fk_tareas_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_tareas_asignatura
    FOREIGN KEY (asignatura_id) REFERENCES asignaturas (id)
    ON DELETE SET NULL,
  CONSTRAINT fk_tareas_calendario
    FOREIGN KEY (calendario_id) REFERENCES calendarios_externos (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- examenes
--   Los importados de un calendario llevan calendario_id y uid_externo.
-- -----------------------------------------------------------------------------
CREATE TABLE examenes (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  usuario_id      INT UNSIGNED     NOT NULL,
  asignatura_id   INT UNSIGNED     NOT NULL,
  calendario_id   INT UNSIGNED     NULL,
  uid_externo     VARCHAR(255)     NULL COMMENT 'UID del evento iCal importado',
  nombre          VARCHAR(150)     NOT NULL,
  fecha           DATE             NOT NULL,
  hora            TIME             NULL,
  aula            VARCHAR(50)      NULL,
  descripcion     TEXT             NULL,
  estado          ENUM('pendiente','realizado','aplazado') NOT NULL DEFAULT 'pendiente',
  fecha_creacion  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_examenes_usuario_uid (usuario_id, uid_externo),
  KEY idx_examenes_usuario_fecha (usuario_id, fecha),
  KEY idx_examenes_asignatura_usuario (asignatura_id, usuario_id),
  KEY idx_examenes_calendario (calendario_id),
  CONSTRAINT fk_examenes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_examenes_asignatura
    FOREIGN KEY (asignatura_id, usuario_id) REFERENCES asignaturas (id, usuario_id)
    ON DELETE CASCADE,
  CONSTRAINT fk_examenes_calendario
    FOREIGN KEY (calendario_id) REFERENCES calendarios_externos (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- notas: calificaciones (escala 0–10)
-- -----------------------------------------------------------------------------
CREATE TABLE notas (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  usuario_id      INT UNSIGNED     NOT NULL,
  asignatura_id   INT UNSIGNED     NOT NULL,
  tipo            ENUM('examen','trabajo','practica','proyecto','participacion','otro') NOT NULL DEFAULT 'examen',
  nota            DECIMAL(4,2)     NOT NULL,
  fecha           DATE             NOT NULL,
  comentarios     TEXT             NULL,
  fecha_creacion  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notas_usuario_fecha (usuario_id, fecha),
  KEY idx_notas_asignatura_usuario (asignatura_id, usuario_id),
  CONSTRAINT chk_notas_rango CHECK (nota >= 0 AND nota <= 10),
  CONSTRAINT fk_notas_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_notas_asignatura
    FOREIGN KEY (asignatura_id, usuario_id) REFERENCES asignaturas (id, usuario_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- horario: clases semanales (dia_semana ISO-8601: 1 = lunes … 7 = domingo)
-- -----------------------------------------------------------------------------
CREATE TABLE horario (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  usuario_id      INT UNSIGNED     NOT NULL,
  asignatura_id   INT UNSIGNED     NOT NULL,
  dia_semana      TINYINT UNSIGNED NOT NULL,
  hora_inicio     TIME             NOT NULL,
  hora_fin        TIME             NOT NULL,
  aula            VARCHAR(50)      NULL,
  PRIMARY KEY (id),
  KEY idx_horario_usuario_dia (usuario_id, dia_semana, hora_inicio),
  KEY idx_horario_asignatura_usuario (asignatura_id, usuario_id),
  CONSTRAINT chk_horario_dia   CHECK (dia_semana BETWEEN 1 AND 7),
  CONSTRAINT chk_horario_horas CHECK (hora_fin > hora_inicio),
  CONSTRAINT fk_horario_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_horario_asignatura
    FOREIGN KEY (asignatura_id, usuario_id) REFERENCES asignaturas (id, usuario_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- objetivos: metas personales de estudio
--   Ej.: "Completar 5 ejercicios de Java" → valor_meta = 5, unidad = 'ejercicios'
--   Progreso (%) = valor_actual / valor_meta · 100 (se calcula, no se guarda)
-- -----------------------------------------------------------------------------
CREATE TABLE objetivos (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  usuario_id      INT UNSIGNED     NOT NULL,
  titulo          VARCHAR(150)     NOT NULL,
  descripcion     TEXT             NULL,
  fecha_limite    DATE             NULL,
  valor_actual    INT UNSIGNED     NOT NULL DEFAULT 0,
  valor_meta      INT UNSIGNED     NOT NULL DEFAULT 100,
  unidad          VARCHAR(30)      NULL COMMENT 'Ej.: ejercicios, temas, horas, %',
  estado          ENUM('pendiente','en_progreso','completado') NOT NULL DEFAULT 'pendiente',
  fecha_creacion  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_objetivos_usuario_estado (usuario_id, estado),
  CONSTRAINT chk_objetivos_meta     CHECK (valor_meta > 0),
  CONSTRAINT chk_objetivos_progreso CHECK (valor_actual <= valor_meta),
  CONSTRAINT fk_objetivos_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
