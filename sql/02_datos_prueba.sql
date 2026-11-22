-- =====================================================================
-- Script 02: datos iniciales de prueba
-- Ejecutar después de 01_estructura.sql:
--   mariadb -u <usuario> -p prestamos_equipo < sql/02_datos_prueba.sql
--
-- Usuarios de PRUEBA del sistema (cámbialos al instalar en producción):
--   admin     / Admin#2026     -> rol ADMIN    (registra y modifica)
--   consulta  / Consulta#2026  -> rol CONSULTA (solo consulta)
-- Las contraseñas se guardan con password_hash() de PHP (bcrypt), nunca en texto plano.
-- =====================================================================
USE prestamos_equipo;

INSERT INTO departamento (nombre) VALUES
  ('Sistemas y Computación'),
  ('Ciencias Básicas'),
  ('Eléctrica y Electrónica'),
  ('Ciencias Económico-Administrativas'),
  ('Servicios Escolares');

INSERT INTO categoria (nombre) VALUES
  ('Laptop'), ('Proyector'), ('Kit de electrónica'), ('Equipo de red'),
  ('Herramienta de diagnóstico'), ('Adaptador o accesorio');

INSERT INTO usuario (usuario, nombre_completo, password_hash, rol) VALUES
  ('admin',    'Administrador de Soporte', '$2y$10$xfEVX69B45YSJUueN6LI3OWW1pyCPXBIUt90QTdHbLp4uxGBguMuK', 'ADMIN'),
  ('consulta', 'Personal de Consulta',     '$2y$10$7Gjj5tjXI3wE5IBcmLEkJ.YJPlzCAL1.4mMHnrb8.TBzOvKEzIzLO', 'CONSULTA');

INSERT INTO solicitante (num_institucional, nombre, apellidos, id_departamento) VALUES
  ('E1024', 'María',   'López Ruiz',     2),
  ('E2210', 'Jorge',   'Pérez Díaz',     1),
  ('E3105', 'Laura',   'Gómez Silva',    3),
  ('E4078', 'Ricardo', 'Torres Medina',  5),
  ('E5150', 'Ana',     'Ramírez Castro', 4);

INSERT INTO equipo (num_inventario, descripcion, id_categoria, marca, modelo, condicion, estado) VALUES
  ('INV-001', 'Laptop Dell Latitude 5440',  1, 'Dell',    'Latitude 5440', 'BUENA',   'DISPONIBLE'),
  ('INV-002', 'Proyector BenQ MS504',       2, 'BenQ',    'MS504',         'DANADA',  'BAJA'),
  ('INV-003', 'Laptop Dell Latitude 5440',  1, 'Dell',    'Latitude 5440', 'BUENA',   'PRESTADO'),
  ('INV-014', 'Proyector Epson PowerLite',  2, 'Epson',   'PowerLite E20', 'REGULAR', 'PRESTADO'),
  ('INV-017', 'Laptop HP ProBook 450',      1, 'HP',      'ProBook 450 G9','DANADA',  'MANTENIMIENTO'),
  ('INV-021', 'Kit Arduino Uno',            3, 'Arduino', 'Uno R3',        'BUENA',   'DISPONIBLE'),
  ('INV-030', 'Switch TP-Link 24 puertos',  4, 'TP-Link', 'TL-SG1024',     'BUENA',   'DISPONIBLE'),
  ('INV-041', 'Multímetro digital Fluke',   5, 'Fluke',   '115',           'BUENA',   'DISPONIBLE'),
  ('INV-055', 'Adaptador HDMI a VGA',       6, 'Steren',  'HDMI-VGA',      'BUENA',   'DISPONIBLE');

-- Historial: un préstamo ya devuelto y dos activos (uno vencido)
INSERT INTO prestamo (id_equipo, id_solicitante, id_usuario_entrega, fecha_prestamo, fecha_devolucion_prevista,
                      fecha_devolucion_real, id_usuario_recibe, condicion_devolucion)
VALUES
  (1, 2, 1, NOW() - INTERVAL 20 DAY, CURRENT_DATE - INTERVAL 13 DAY, NOW() - INTERVAL 13 DAY, 1, 'BUENA'),
  (5, 4, 1, NOW() - INTERVAL 15 DAY, CURRENT_DATE - INTERVAL 10 DAY, NOW() - INTERVAL 10 DAY, 1, 'DANADA');

INSERT INTO prestamo (id_equipo, id_solicitante, id_usuario_entrega, fecha_prestamo, fecha_devolucion_prevista)
VALUES
  (4, 1, 1, NOW() - INTERVAL 8 DAY, CURRENT_DATE - INTERVAL 1 DAY),   -- vencido
  (3, 2, 1, NOW() - INTERVAL 1 DAY, CURRENT_DATE + INTERVAL 3 DAY);   -- activo

INSERT INTO mantenimiento (id_equipo, fecha_inicio, motivo, id_usuario) VALUES
  (5, NOW() - INTERVAL 10 DAY, 'Devuelto dañado: no enciende la pantalla', 1);
