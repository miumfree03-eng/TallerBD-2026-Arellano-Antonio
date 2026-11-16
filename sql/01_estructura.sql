-- =====================================================================
-- Sistema de control de préstamos de equipo tecnológico
-- Script 01: estructura de la base de datos (DDL)
-- Motor: MariaDB 10.6+ · InnoDB · utf8mb4
-- Uso:   mariadb -u <usuario> -p < sql/01_estructura.sql
-- =====================================================================

DROP DATABASE IF EXISTS prestamos_equipo;
CREATE DATABASE prestamos_equipo
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE prestamos_equipo;

-- ---------------------------------------------------------------------
-- Catálogos
-- ---------------------------------------------------------------------

-- Departamento o área a la que pertenece cada solicitante.
CREATE TABLE departamento (
  id_departamento  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nombre           VARCHAR(100)  NOT NULL,
  CONSTRAINT pk_departamento PRIMARY KEY (id_departamento),
  CONSTRAINT uq_departamento_nombre UNIQUE (nombre)
) ENGINE = InnoDB;

-- Tipo de equipo: laptop, proyector, kit de electrónica, equipo de red, etc.
CREATE TABLE categoria (
  id_categoria  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(60)   NOT NULL,
  CONSTRAINT pk_categoria PRIMARY KEY (id_categoria),
  CONSTRAINT uq_categoria_nombre UNIQUE (nombre)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Personas
-- ---------------------------------------------------------------------

-- Personal del departamento que usa el sistema.
-- rol ADMIN: registra y modifica; rol CONSULTA: solo consulta (Situación 5).
CREATE TABLE usuario (
  id_usuario      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  usuario         VARCHAR(40)   NOT NULL,
  nombre_completo VARCHAR(120)  NOT NULL,
  password_hash   VARCHAR(255)  NOT NULL,              -- se guarda con password_hash() de PHP, nunca en claro
  rol             ENUM('ADMIN', 'CONSULTA') NOT NULL DEFAULT 'CONSULTA',
  activo          BOOLEAN       NOT NULL DEFAULT TRUE,
  CONSTRAINT pk_usuario PRIMARY KEY (id_usuario),
  CONSTRAINT uq_usuario_usuario UNIQUE (usuario)
) ENGINE = InnoDB;

-- Personas que piden equipo prestado. No se borran: se desactivan, para no perder su historial.
CREATE TABLE solicitante (
  id_solicitante     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  num_institucional  VARCHAR(20)   NOT NULL,           -- número de empleado o de control
  nombre             VARCHAR(60)   NOT NULL,
  apellidos          VARCHAR(80)   NOT NULL,
  id_departamento    INT UNSIGNED  NOT NULL,
  activo             BOOLEAN       NOT NULL DEFAULT TRUE,
  CONSTRAINT pk_solicitante PRIMARY KEY (id_solicitante),
  CONSTRAINT uq_solicitante_num UNIQUE (num_institucional),
  CONSTRAINT fk_solicitante_departamento FOREIGN KEY (id_departamento)
    REFERENCES departamento (id_departamento)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Equipos
-- ---------------------------------------------------------------------

-- Cada equipo físico es una fila: cinco laptops del mismo modelo son cinco filas
-- con distinto número de inventario.
-- estado: DISPONIBLE, PRESTADO, MANTENIMIENTO (fuera de servicio) o BAJA (ya no se usa).
-- Un equipo dado de baja no se borra, para conservar sus préstamos (Situación 4).
CREATE TABLE equipo (
  id_equipo       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  num_inventario  VARCHAR(30)   NOT NULL,
  descripcion     VARCHAR(150)  NOT NULL,
  id_categoria    INT UNSIGNED  NOT NULL,
  marca           VARCHAR(50)   NULL,
  modelo          VARCHAR(60)   NULL,
  condicion       ENUM('BUENA', 'REGULAR', 'DANADA') NOT NULL DEFAULT 'BUENA',
  estado          ENUM('DISPONIBLE', 'PRESTADO', 'MANTENIMIENTO', 'BAJA') NOT NULL DEFAULT 'DISPONIBLE',
  fecha_alta      DATE          NOT NULL DEFAULT (CURRENT_DATE),
  CONSTRAINT pk_equipo PRIMARY KEY (id_equipo),
  CONSTRAINT uq_equipo_inventario UNIQUE (num_inventario),
  CONSTRAINT fk_equipo_categoria FOREIGN KEY (id_categoria)
    REFERENCES categoria (id_categoria)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE INDEX ix_equipo_estado ON equipo (estado);

-- Envíos a mantenimiento: por qué se retiró un equipo y cuándo volvió (Situación 3).
CREATE TABLE mantenimiento (
  id_mantenimiento  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_equipo         INT UNSIGNED  NOT NULL,
  fecha_inicio      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_fin         DATETIME      NULL,                -- NULL = sigue en mantenimiento
  motivo            VARCHAR(255)  NOT NULL,
  id_usuario        INT UNSIGNED  NOT NULL,            -- quién lo envió a mantenimiento
  CONSTRAINT pk_mantenimiento PRIMARY KEY (id_mantenimiento),
  CONSTRAINT fk_mantenimiento_equipo FOREIGN KEY (id_equipo)
    REFERENCES equipo (id_equipo)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_mantenimiento_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT ck_mantenimiento_fechas CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Préstamos y devoluciones
-- ---------------------------------------------------------------------

-- Un préstamo es una fila. La devolución se registra en la misma fila
-- (fecha_devolucion_real, quién recibió y en qué condición), así el historial nunca se pierde.
CREATE TABLE prestamo (
  id_prestamo                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_equipo                  INT UNSIGNED  NOT NULL,
  id_solicitante             INT UNSIGNED  NOT NULL,
  id_usuario_entrega         INT UNSIGNED  NOT NULL,   -- quién registró la entrega
  fecha_prestamo             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_devolucion_prevista  DATE          NOT NULL,
  fecha_devolucion_real      DATETIME      NULL,       -- NULL = préstamo activo
  id_usuario_recibe          INT UNSIGNED  NULL,       -- quién registró la devolución
  condicion_devolucion       ENUM('BUENA', 'REGULAR', 'DANADA') NULL,
  observaciones              VARCHAR(255)  NULL,

  -- Columna calculada: vale id_equipo mientras el préstamo está activo y NULL al devolverse.
  -- Con el índice UNIQUE de abajo, la base de datos impide dos préstamos activos del mismo
  -- equipo aunque dos encargados registren al mismo tiempo (Situación 1).
  equipo_activo  INT UNSIGNED AS (IF(fecha_devolucion_real IS NULL, id_equipo, NULL)) PERSISTENT,

  CONSTRAINT pk_prestamo PRIMARY KEY (id_prestamo),
  CONSTRAINT uq_prestamo_equipo_activo UNIQUE (equipo_activo),
  -- Las llaves foráneas de prestamo usan ON UPDATE RESTRICT: MariaDB no permite que una columna
  -- calculada o un CHECK dependan de columnas que se actualizan en cascada. Los id nunca cambian.
  CONSTRAINT fk_prestamo_equipo FOREIGN KEY (id_equipo)
    REFERENCES equipo (id_equipo)
    ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_prestamo_solicitante FOREIGN KEY (id_solicitante)
    REFERENCES solicitante (id_solicitante)
    ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_prestamo_usuario_entrega FOREIGN KEY (id_usuario_entrega)
    REFERENCES usuario (id_usuario)
    ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_prestamo_usuario_recibe FOREIGN KEY (id_usuario_recibe)
    REFERENCES usuario (id_usuario)
    ON UPDATE RESTRICT ON DELETE RESTRICT,

  -- La devolución prevista no puede ser antes del préstamo.
  CONSTRAINT ck_prestamo_prevista CHECK (fecha_devolucion_prevista >= CAST(fecha_prestamo AS DATE)),
  -- Si hay devolución real, no puede ser antes del préstamo.
  CONSTRAINT ck_prestamo_real CHECK (fecha_devolucion_real IS NULL OR fecha_devolucion_real >= fecha_prestamo),
  -- Una devolución registrada debe decir quién la recibió y en qué condición; un préstamo activo no.
  CONSTRAINT ck_prestamo_devolucion_completa CHECK (
    (fecha_devolucion_real IS NULL AND id_usuario_recibe IS NULL AND condicion_devolucion IS NULL)
    OR (fecha_devolucion_real IS NOT NULL AND id_usuario_recibe IS NOT NULL AND condicion_devolucion IS NOT NULL)
  )
) ENGINE = InnoDB;

-- Consultas frecuentes: préstamos vencidos y historial por persona o por equipo.
CREATE INDEX ix_prestamo_prevista    ON prestamo (fecha_devolucion_prevista);
CREATE INDEX ix_prestamo_solicitante ON prestamo (id_solicitante, fecha_prestamo);
CREATE INDEX ix_prestamo_equipo      ON prestamo (id_equipo, fecha_prestamo);
