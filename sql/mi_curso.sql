-- =============================================================================
--  StudyTrack · Mi curso: 2º DAW · IES Celia Viñas (Almería) · Curso 2026/2027
--
--  Carga MIS asignaturas y MI horario en MI cuenta. Es independiente de
--  demo_data.sql (no lo necesita ni lo modifica).
--
--  Cómo usarlo:
--    1. Regístrate en StudyTrack desde la web.
--    2. Cambia TU_EMAIL_AQUI (justo debajo) por el email con el que te registraste.
--    3. phpMyAdmin → base de datos studytrack → Importar → este archivo → Importar.
--       Por consola: mysql -u root < sql/mi_curso.sql
--
--  Se puede ejecutar todas las veces que quieras sin duplicar nada:
--    - Las asignaturas se crean o se actualizan (clave única usuario + nombre).
--    - El horario se borra entero y se vuelve a crear.
--    - NO toca tus tareas, exámenes, notas ni objetivos.
--
--  Requiere sql/database.sql importado (y sql/migrations/ aplicadas si las hay).
-- =============================================================================

USE studytrack;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ⚠ CAMBIA ESTE EMAIL por el de tu cuenta de StudyTrack ⚠
SET @email = 'TU_EMAIL_AQUI';


-- -----------------------------------------------------------------------------
-- 1. Comprobación: si no hay ningún usuario con ese email, el script se detiene
--    aquí con un mensaje claro y no se cambia nada. (El procedimiento auxiliar
--    se borra al terminar o, si se detuvo, al volver a ejecutar el script.)
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS mi_curso_comprobar_email;

DELIMITER //
CREATE PROCEDURE mi_curso_comprobar_email()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM usuarios WHERE email = @email COLLATE utf8mb4_unicode_ci) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'mi_curso.sql: no existe ningún usuario con el email de @email. Regístrate en StudyTrack y cambia TU_EMAIL_AQUI por tu email al principio del archivo.';
  END IF;
END //
DELIMITER ;

CALL mi_curso_comprobar_email();
DROP PROCEDURE IF EXISTS mi_curso_comprobar_email;

SET @u = (SELECT id FROM usuarios WHERE email = @email COLLATE utf8mb4_unicode_ci);

START TRANSACTION;


-- -----------------------------------------------------------------------------
-- 2. Mis datos
-- -----------------------------------------------------------------------------
UPDATE usuarios
   SET curso = '2º DAW', centro = 'IES Celia Viñas (Almería)'
 WHERE id = @u;


-- -----------------------------------------------------------------------------
-- 3. Asignaturas
--    La descripción empieza por la abreviatura: la usa la importación de
--    calendarios (Moodle, Classroom) para reconocer cada asignatura.
-- -----------------------------------------------------------------------------
INSERT INTO asignaturas (usuario_id, nombre, descripcion, profesor, curso, color) VALUES
  (@u, 'Desarrollo Web en Entorno Servidor',          'DWES',                                         'Alfredo Jesús Moreno Vozmediano',  '2º DAW', '#2563EB'),
  (@u, 'Desarrollo Web en Entorno Cliente',           'DWEC · Tutor: Juan Pedro Martínez Cerezuela', 'Juan Pedro Martínez Cerezuela',    '2º DAW', '#E11D48'),
  (@u, 'Despliegue de Aplicaciones Web',              'DAW',                                          'José Juan Sánchez Hernández',      '2º DAW', '#16A34A'),
  (@u, 'Diseño de Interfaces Web',                    'DIW',                                          'Alejandro Ramallo Martínez',       '2º DAW', '#0891B2'),
  (@u, 'Itinerario Personal para la Empleabilidad II', 'IPE II',                                      'Antonia Márquez Galera',           '2º DAW', '#7C3AED'),
  (@u, 'Inglés Profesional',                          'IP',                                           'Raúl Sánchez Quesada',             '2º DAW', '#D97706'),
  (@u, 'Optativa',                                    'OPT',                                          'Francisco José Gutiérrez Martínez', '2º DAW', '#DB2777'),
  (@u, 'Proyecto Web',                                'PW',                                           'Alejandro Ramallo Martínez',       '2º DAW', '#4F46E5')
ON DUPLICATE KEY UPDATE
  nombre      = VALUES(nombre),        -- "desarrollo web…" pasa a "Desarrollo Web…"
  descripcion = VALUES(descripcion),
  profesor    = VALUES(profesor),
  curso       = VALUES(curso),
  color       = VALUES(color);

SET @dwes = (SELECT id FROM asignaturas WHERE usuario_id = @u AND nombre = 'Desarrollo Web en Entorno Servidor');
SET @dwec = (SELECT id FROM asignaturas WHERE usuario_id = @u AND nombre = 'Desarrollo Web en Entorno Cliente');
SET @daw  = (SELECT id FROM asignaturas WHERE usuario_id = @u AND nombre = 'Despliegue de Aplicaciones Web');
SET @diw  = (SELECT id FROM asignaturas WHERE usuario_id = @u AND nombre = 'Diseño de Interfaces Web');
SET @ipe  = (SELECT id FROM asignaturas WHERE usuario_id = @u AND nombre = 'Itinerario Personal para la Empleabilidad II');
SET @ing  = (SELECT id FROM asignaturas WHERE usuario_id = @u AND nombre = 'Inglés Profesional');
SET @opt  = (SELECT id FROM asignaturas WHERE usuario_id = @u AND nombre = 'Optativa');
SET @pw   = (SELECT id FROM asignaturas WHERE usuario_id = @u AND nombre = 'Proyecto Web');


-- -----------------------------------------------------------------------------
-- 4. Horario: se borran mis franjas y se vuelven a crear (30 horas a la semana).
--    Cada hora es una franja propia aunque se repita la asignatura; la vista
--    del horario las muestra unidas. El recreo (11:00–11:30) está en
--    config/app.php (SCHOOL_BREAKS), no en la base de datos.
-- -----------------------------------------------------------------------------
DELETE FROM horario WHERE usuario_id = @u;

SET @aula = 'Aula 21 Informática';

INSERT INTO horario (usuario_id, asignatura_id, dia_semana, hora_inicio, hora_fin, aula) VALUES
  -- Lunes: DAW · DWEC · DWEC · [recreo] · DWES · DWES · DIW
  (@u, @daw,  1, '08:00', '09:00', @aula),
  (@u, @dwec, 1, '09:00', '10:00', @aula),
  (@u, @dwec, 1, '10:00', '11:00', @aula),
  (@u, @dwes, 1, '11:30', '12:30', @aula),
  (@u, @dwes, 1, '12:30', '13:30', @aula),
  (@u, @diw,  1, '13:30', '14:30', @aula),
  -- Martes: DWES · Inglés Profesional · Optativa · [recreo] · DWEC · DWEC · DIW
  (@u, @dwes, 2, '08:00', '09:00', @aula),
  (@u, @ing,  2, '09:00', '10:00', @aula),
  (@u, @opt,  2, '10:00', '11:00', @aula),
  (@u, @dwec, 2, '11:30', '12:30', @aula),
  (@u, @dwec, 2, '12:30', '13:30', @aula),
  (@u, @diw,  2, '13:30', '14:30', @aula),
  -- Miércoles: IPE II · DAW · Proyecto Web · [recreo] · DWES · IPE II · Optativa
  (@u, @ipe,  3, '08:00', '09:00', @aula),
  (@u, @daw,  3, '09:00', '10:00', @aula),
  (@u, @pw,   3, '10:00', '11:00', @aula),
  (@u, @dwes, 3, '11:30', '12:30', @aula),
  (@u, @ipe,  3, '12:30', '13:30', @aula),
  (@u, @opt,  3, '13:30', '14:30', @aula),
  -- Jueves: DWES · Proyecto Web · Inglés Profesional · [recreo] · DIW · DIW · Optativa
  (@u, @dwes, 4, '08:00', '09:00', @aula),
  (@u, @pw,   4, '09:00', '10:00', @aula),
  (@u, @ing,  4, '10:00', '11:00', @aula),
  (@u, @diw,  4, '11:30', '12:30', @aula),
  (@u, @diw,  4, '12:30', '13:30', @aula),
  (@u, @opt,  4, '13:30', '14:30', @aula),
  -- Viernes: DWEC · DWEC · DIW · [recreo] · DWES · DWES · IPE II
  (@u, @dwec, 5, '08:00', '09:00', @aula),
  (@u, @dwec, 5, '09:00', '10:00', @aula),
  (@u, @diw,  5, '10:00', '11:00', @aula),
  (@u, @dwes, 5, '11:30', '12:30', @aula),
  (@u, @dwes, 5, '12:30', '13:30', @aula),
  (@u, @ipe,  5, '13:30', '14:30', @aula);

COMMIT;


-- -----------------------------------------------------------------------------
-- 5. Comprobación: horas a la semana de cada asignatura. Deben salir:
--    DWES 7 · DWEC 6 · DIW 5 · IPE II 3 · Optativa 3 · DAW 2 · Inglés 2 · Proyecto Web 2
--    (30 en total)
-- -----------------------------------------------------------------------------
SELECT a.descripcion AS abreviatura, a.nombre AS asignatura, COUNT(*) AS horas_semana
  FROM horario h
  JOIN asignaturas a ON a.id = h.asignatura_id
 WHERE h.usuario_id = @u
 GROUP BY a.id, a.descripcion, a.nombre
 ORDER BY horas_semana DESC, a.nombre;
