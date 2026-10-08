-- =============================================================================
--  StudyTrack · Datos de demostración (OPCIONAL)
--
--  Importar DESPUÉS de database.sql. Crea un usuario de prueba con
--  asignaturas, tareas, exámenes, notas, horario y objetivos.
--
--    Email:      demo@studytrack.local
--    Contraseña: Demo1234
--
--  Las fechas son relativas al día de la importación (CURDATE() ± N días),
--  así que siempre habrá tareas para hoy, exámenes próximos, etc.
--  Puedes reimportarlo cuando quieras: primero borra el usuario demo
--  (y, en cascada, todos sus datos).
-- =============================================================================

USE studytrack;
SET NAMES utf8mb4;

DELETE FROM usuarios WHERE email = 'demo@studytrack.local';

-- Usuario ---------------------------------------------------------------------
INSERT INTO usuarios (nombre, email, password_hash, curso, centro, ultimo_acceso) VALUES
  ('Fran', 'demo@studytrack.local',
   '$2y$12$ScPcAuxZWmTfjHW9kA/LfOv6bv4C0XErC9.c8zjKiPV48cKziF74a',
   '1º DAW', 'IES Tecnológico', NOW());
SET @u = LAST_INSERT_ID();

-- Asignaturas -----------------------------------------------------------------
INSERT INTO asignaturas (usuario_id, nombre, descripcion, profesor, curso, color, fecha_creacion) VALUES
  (@u, 'Programación', 'Fundamentos de programación con Java: estructuras de control, POO y colecciones.', 'Laura Méndez', '1º DAW', '#4F46E5', NOW() - INTERVAL 40 DAY);
SET @prog = LAST_INSERT_ID();

INSERT INTO asignaturas (usuario_id, nombre, descripcion, profesor, curso, color, fecha_creacion) VALUES
  (@u, 'Bases de Datos', 'Modelo entidad-relación, normalización y SQL.', 'Carlos Ruiz', '1º DAW', '#0EA5E9', NOW() - INTERVAL 40 DAY);
SET @bd = LAST_INSERT_ID();

INSERT INTO asignaturas (usuario_id, nombre, descripcion, profesor, curso, color, fecha_creacion) VALUES
  (@u, 'Entornos de Desarrollo', 'IDEs, control de versiones con Git, pruebas y UML.', 'Ana Torres', '1º DAW', '#10B981', NOW() - INTERVAL 40 DAY);
SET @ed = LAST_INSERT_ID();

INSERT INTO asignaturas (usuario_id, nombre, descripcion, profesor, curso, color, fecha_creacion) VALUES
  (@u, 'Lenguajes de Marcas', 'HTML, CSS, XML, DTD y XML Schema.', 'Javier Moreno', '1º DAW', '#F59E0B', NOW() - INTERVAL 40 DAY);
SET @lm = LAST_INSERT_ID();

INSERT INTO asignaturas (usuario_id, nombre, descripcion, profesor, curso, color, fecha_creacion) VALUES
  (@u, 'Sistemas Informáticos', 'Hardware, sistemas operativos, redes y virtualización.', 'Elena Navarro', '1º DAW', '#EF4444', NOW() - INTERVAL 40 DAY);
SET @si = LAST_INSERT_ID();

-- Tareas ----------------------------------------------------------------------
INSERT INTO tareas (usuario_id, asignatura_id, titulo, descripcion, fecha_limite, prioridad, estado, fecha_creacion, fecha_completada) VALUES
  (@u, @prog, 'Ejercicios de bucles en Java',        'Boletín 4: ejercicios 1 al 10.',                      CURDATE(),                   'alta',  'pendiente',   NOW() - INTERVAL 6 DAY,  NULL),
  (@u, @prog, 'Práctica POO: clase CuentaBancaria',  'Constructores, getters/setters y método toString().', CURDATE() + INTERVAL 3 DAY,  'alta',  'en_progreso', NOW() - INTERVAL 5 DAY,  NULL),
  (@u, @bd,   'Diagrama E/R de la biblioteca',       'Entidades, relaciones y cardinalidades.',             CURDATE() + INTERVAL 5 DAY,  'media', 'pendiente',   NOW() - INTERVAL 4 DAY,  NULL),
  (@u, @bd,   'Consultas SQL con JOIN',              'Hoja de ejercicios del tema 5.',                      CURDATE() - INTERVAL 2 DAY,  'media', 'pendiente',   NOW() - INTERVAL 9 DAY,  NULL),
  (@u, @ed,   'Configurar repositorio en GitHub',    'Crear repo, README y primer commit.',                 CURDATE() - INTERVAL 3 DAY,  'baja',  'completada',  NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 2 DAY),
  (@u, @ed,   'Tests unitarios con JUnit',           'Cubrir la clase Calculadora con pruebas.',            CURDATE() + INTERVAL 9 DAY,  'media', 'pendiente',   NOW() - INTERVAL 2 DAY,  NULL),
  (@u, @lm,   'Maquetar formulario HTML accesible',  'Etiquetas, fieldset y validación nativa.',            CURDATE() - INTERVAL 1 DAY,  'media', 'completada',  NOW() - INTERVAL 7 DAY,  NOW() - INTERVAL 1 DAY),
  (@u, @lm,   'Validar XML con DTD',                 'Ejercicio del catálogo de películas.',                CURDATE(),                   'alta',  'pendiente',   NOW() - INTERVAL 3 DAY,  NULL),
  (@u, @si,   'Informe sobre sistemas de archivos',  'Comparar NTFS, ext4 y APFS.',                         CURDATE() - INTERVAL 1 DAY,  'baja',  'pendiente',   NOW() - INTERVAL 8 DAY,  NULL),
  (@u, @si,   'Instalar Ubuntu Server en VirtualBox', NULL,                                                 CURDATE() - INTERVAL 4 DAY,  'media', 'completada',  NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 4 DAY),
  (@u, @prog, 'Leer apuntes UT4: herencia',          NULL,                                                  CURDATE() - INTERVAL 5 DAY,  'baja',  'completada',  NOW() - INTERVAL 11 DAY, NOW() - INTERVAL 5 DAY),
  (@u, NULL,  'Revisar calendario de evaluaciones',  'Apuntar fechas de recuperación.',                     CURDATE() + INTERVAL 12 DAY, 'baja',  'pendiente',   NOW() - INTERVAL 1 DAY,  NULL);

-- Exámenes --------------------------------------------------------------------
INSERT INTO examenes (usuario_id, asignatura_id, nombre, fecha, hora, aula, descripcion, estado) VALUES
  (@u, @lm,   'Examen XML y DTD',                         CURDATE() + INTERVAL 3 DAY,  '10:00', 'Aula 14', 'Temas 3 y 4. Se permite consultar la especificación.', 'pendiente'),
  (@u, @prog, 'Examen UT3: Programación orientada a objetos', CURDATE() + INTERVAL 7 DAY,  '09:00', 'Aula 12', 'Clases, objetos, encapsulación y herencia.',          'pendiente'),
  (@u, @bd,   'Examen SQL: consultas avanzadas',          CURDATE() + INTERVAL 12 DAY, '11:30', 'Aula 8',  'JOIN, subconsultas y funciones de agregado.',         'pendiente'),
  (@u, @ed,   'Examen Git y control de versiones',        CURDATE() - INTERVAL 6 DAY,  '12:00', 'Aula 12', NULL,                                                  'realizado'),
  (@u, @si,   'Examen hardware y redes',                  CURDATE() - INTERVAL 14 DAY, '08:00', 'Aula 3',  NULL,                                                  'realizado');

-- Notas -----------------------------------------------------------------------
INSERT INTO notas (usuario_id, asignatura_id, tipo, nota, fecha, comentarios) VALUES
  (@u, @prog, 'examen',        7.50, CURDATE() - INTERVAL 30 DAY, 'UT1 y UT2'),
  (@u, @prog, 'practica',      8.75, CURDATE() - INTERVAL 20 DAY, 'Calculadora por consola'),
  (@u, @prog, 'trabajo',       9.00, CURDATE() - INTERVAL 8 DAY,  NULL),
  (@u, @bd,   'examen',        6.25, CURDATE() - INTERVAL 28 DAY, 'Modelo E/R'),
  (@u, @bd,   'practica',      4.50, CURDATE() - INTERVAL 15 DAY, 'Repasar normalización'),
  (@u, @bd,   'proyecto',      7.00, CURDATE() - INTERVAL 5 DAY,  NULL),
  (@u, @ed,   'examen',        8.00, CURDATE() - INTERVAL 6 DAY,  'Git y control de versiones'),
  (@u, @ed,   'practica',      9.50, CURDATE() - INTERVAL 18 DAY, NULL),
  (@u, @lm,   'trabajo',       8.25, CURDATE() - INTERVAL 22 DAY, 'Web personal en HTML y CSS'),
  (@u, @lm,   'practica',      7.75, CURDATE() - INTERVAL 10 DAY, NULL),
  (@u, @lm,   'participacion', 10.00, CURDATE() - INTERVAL 3 DAY, NULL),
  (@u, @si,   'examen',        4.25, CURDATE() - INTERVAL 14 DAY, 'Recuperación en junio'),
  (@u, @si,   'practica',      6.00, CURDATE() - INTERVAL 25 DAY, NULL);

-- Horario (1 = lunes … 5 = viernes) -------------------------------------------
INSERT INTO horario (usuario_id, asignatura_id, dia_semana, hora_inicio, hora_fin, aula) VALUES
  (@u, @prog, 1, '08:00', '10:00', 'Aula 12'),
  (@u, @bd,   1, '10:00', '11:00', 'Aula 8'),
  (@u, @lm,   1, '11:30', '13:30', 'Aula 14'),
  (@u, @si,   2, '08:00', '10:00', 'Aula 3'),
  (@u, @ed,   2, '10:00', '11:00', 'Aula 12'),
  (@u, @prog, 2, '11:30', '13:30', 'Aula 12'),
  (@u, @bd,   3, '08:00', '10:00', 'Aula 8'),
  (@u, @lm,   3, '10:00', '11:00', 'Aula 14'),
  (@u, @si,   3, '11:30', '13:30', 'Aula 3'),
  (@u, @prog, 4, '08:00', '10:00', 'Aula 12'),
  (@u, @ed,   4, '10:00', '11:00', 'Aula 12'),
  (@u, @bd,   4, '11:30', '13:30', 'Aula 8'),
  (@u, @si,   5, '08:00', '09:00', 'Aula 3'),
  (@u, @lm,   5, '09:00', '10:00', 'Aula 14'),
  (@u, @prog, 5, '10:00', '11:00', 'Aula 12'),
  (@u, @ed,   5, '11:30', '13:30', 'Aula 12');

-- Objetivos -------------------------------------------------------------------
INSERT INTO objetivos (usuario_id, titulo, descripcion, fecha_limite, valor_actual, valor_meta, unidad, estado) VALUES
  (@u, 'Completar 5 ejercicios de Java',     'Del boletín de estructuras repetitivas.', CURDATE() + INTERVAL 4 DAY,  3,   5,   'ejercicios', 'en_progreso'),
  (@u, 'Repasar 4 temas de Bases de Datos',  'Antes del examen de SQL.',                CURDATE() + INTERVAL 10 DAY, 1,   4,   'temas',      'en_progreso'),
  (@u, 'Estudiar 20 horas esta semana',      NULL,                                      CURDATE() + INTERVAL 3 DAY,  12,  20,  'horas',      'en_progreso'),
  (@u, 'Terminar el curso de Git',           'Curso online de control de versiones.',   CURDATE() - INTERVAL 2 DAY,  100, 100, '%',          'completado'),
  (@u, 'Leer «Clean Code»',                  'Un capítulo por semana.',                 CURDATE() + INTERVAL 60 DAY, 0,   17,  'capítulos',  'pendiente');
