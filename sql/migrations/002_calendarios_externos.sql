-- =============================================================================
--  StudyTrack · Migración 002: calendarios externos (Moodle, Classroom… por iCal)
--
--  Para una base de datos que ya está en uso: añade tablas y columnas SIN BORRAR
--  NADA. Las instalaciones nuevas no la necesitan: sql/database.sql ya incluye
--  estos cambios (ese esquema inicial hace de migración 001).
--
--  Importación:
--    phpMyAdmin → base de datos studytrack → pestaña "Importar" → este archivo
--    o por consola:  mysql -u root < sql/migrations/002_calendarios_externos.sql
--
--  Ejecútala UNA sola vez. Si ya estaba aplicada, se detiene en el primer
--  ALTER TABLE con el error "Duplicate column name 'calendario_id'" sin cambiar nada.
--
--  Qué añade
--  - calendarios_externos: las URLs iCal conectadas (cifradas con APP_KEY).
--  - correspondencias_externas: "este curso de Moodle es esta asignatura mía".
--  - tareas y examenes: calendario_id y uid_externo (UID del evento iCal, único
--    por usuario para no importar dos veces el mismo evento); en tareas también
--    texto_origen (curso o título de origen, para asignarles asignatura después).
--
--  Nota: calendarios_externos.asignatura_id usa una clave foránea simple. Una
--  compuesta (asignatura_id, usuario_id) con ON DELETE SET NULL intentaría poner
--  también usuario_id a NULL, y MySQL/MariaDB no lo permiten; la pertenencia de
--  la asignatura al usuario se comprueba en PHP, igual que en tareas.
-- =============================================================================

USE studytrack;
SET NAMES utf8mb4;


-- -----------------------------------------------------------------------------
-- calendarios_externos: calendarios iCal de cada usuario
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS calendarios_externos (
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
-- correspondencias_externas: texto de origen (curso de Moodle, título…) → asignatura
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS correspondencias_externas (
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
-- tareas y examenes: de qué calendario vienen y con qué UID
-- -----------------------------------------------------------------------------
ALTER TABLE tareas
  ADD COLUMN calendario_id INT UNSIGNED NULL AFTER asignatura_id,
  ADD COLUMN uid_externo   VARCHAR(255) NULL COMMENT 'UID del evento iCal importado' AFTER calendario_id,
  ADD COLUMN texto_origen  VARCHAR(255) NULL COMMENT 'Curso o título de origen (para asignarle asignatura)' AFTER uid_externo,
  ADD UNIQUE KEY uk_tareas_usuario_uid (usuario_id, uid_externo),
  ADD KEY idx_tareas_calendario (calendario_id),
  ADD CONSTRAINT fk_tareas_calendario
    FOREIGN KEY (calendario_id) REFERENCES calendarios_externos (id)
    ON DELETE SET NULL;

ALTER TABLE examenes
  ADD COLUMN calendario_id INT UNSIGNED NULL AFTER asignatura_id,
  ADD COLUMN uid_externo   VARCHAR(255) NULL COMMENT 'UID del evento iCal importado' AFTER calendario_id,
  ADD UNIQUE KEY uk_examenes_usuario_uid (usuario_id, uid_externo),
  ADD KEY idx_examenes_calendario (calendario_id),
  ADD CONSTRAINT fk_examenes_calendario
    FOREIGN KEY (calendario_id) REFERENCES calendarios_externos (id)
    ON DELETE SET NULL;
