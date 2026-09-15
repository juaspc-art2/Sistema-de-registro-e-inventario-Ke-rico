SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

DROP DATABASE IF EXISTS kerico;
CREATE DATABASE kerico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kerico;

CREATE TABLE roles (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(40)  NOT NULL,
  descripcion   VARCHAR(180) NOT NULL DEFAULT '',
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_roles_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo        VARCHAR(60)  NOT NULL,
  modulo        VARCHAR(40)  NOT NULL,
  descripcion   VARCHAR(180) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uk_permisos_codigo (codigo),
  KEY ix_permisos_modulo (modulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol_permisos (
  rol_id        INT UNSIGNED NOT NULL,
  permiso_id    INT UNSIGNED NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  CONSTRAINT fk_rp_rol     FOREIGN KEY (rol_id)     REFERENCES roles(id)    ON DELETE CASCADE,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario            VARCHAR(40)  NOT NULL,
  password_hash      VARCHAR(255) NOT NULL,
  nombre             VARCHAR(80)  NOT NULL,
  apellido           VARCHAR(80)  NOT NULL,
  tipo_documento     ENUM('CC','TI','CE','PA','NIT') NOT NULL DEFAULT 'CC',
  documento          VARCHAR(30)  NOT NULL,
  fecha_nac          DATE         DEFAULT NULL,
  correo             VARCHAR(120) NOT NULL,
  telefono           VARCHAR(30)  NOT NULL DEFAULT '',
  cargo              VARCHAR(80)  NOT NULL DEFAULT '',
  rol_id             INT UNSIGNED NOT NULL,
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  intentos_fallidos  INT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta    DATETIME     DEFAULT NULL,
  ultimo_acceso      DATETIME     DEFAULT NULL,
  token_recuperacion VARCHAR(64)  DEFAULT NULL,
  token_expira       DATETIME     DEFAULT NULL,
  creado_en          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_usuario (usuario),
  UNIQUE KEY uk_usuarios_correo (correo),
  UNIQUE KEY uk_usuarios_documento (documento),
  KEY ix_usuarios_rol (rol_id),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id),
  KEY ix_usuarios_borrado (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sesiones (
  id                CHAR(64)     NOT NULL,
  usuario_id        INT UNSIGNED NOT NULL,
  ip                VARCHAR(45)  NOT NULL DEFAULT '',
  user_agent        VARCHAR(255) NOT NULL DEFAULT '',
  creada_en         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultima_actividad  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_en         DATETIME     NOT NULL,
  cerrada_en        DATETIME     DEFAULT NULL,
  motivo_cierre     ENUM('Manual','Inactividad','Expiracion','Forzado') DEFAULT NULL,
  csrf_token        CHAR(64)     NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY ix_sesiones_usuario (usuario_id),
  KEY ix_sesiones_actividad (ultima_actividad),
  CONSTRAINT fk_sesiones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuracion (
  clave        VARCHAR(60)  NOT NULL,
  valor        TEXT         NOT NULL,
  tipo         ENUM('texto','entero','decimal','booleano','json') NOT NULL DEFAULT 'texto',
  descripcion  VARCHAR(200) NOT NULL DEFAULT '',
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorias (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(60)  NOT NULL,
  descripcion VARCHAR(180) NOT NULL DEFAULT '',
  activo      TINYINT(1)   NOT NULL DEFAULT 1,
  deleted_at  DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_categorias_nombre (nombre),
  KEY ix_categorias_borrado (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proveedores (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_empresa   VARCHAR(120) NOT NULL,
  nit              VARCHAR(30)  NOT NULL,
  telefono         VARCHAR(30)  NOT NULL DEFAULT '',
  correo           VARCHAR(120) NOT NULL DEFAULT '',
  direccion        VARCHAR(180) NOT NULL DEFAULT '',
  contacto_nombre  VARCHAR(120) NOT NULL DEFAULT '',
  tipo_productos   VARCHAR(180) NOT NULL DEFAULT '',
  estado           ENUM('Activo','Inactivo','En revision') NOT NULL DEFAULT 'Activo',
  calificacion     DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  notas            VARCHAR(255) NOT NULL DEFAULT '',
  creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at       DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_proveedores_nit (nit),
  KEY ix_proveedores_estado (estado),
  CONSTRAINT ck_proveedores_calif CHECK (calificacion >= 0 AND calificacion <= 5),
  KEY ix_proveedores_borrado (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE productos (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  sku             VARCHAR(30)   NOT NULL,
  nombre          VARCHAR(120)  NOT NULL,
  descripcion     VARCHAR(255)  NOT NULL DEFAULT '',
  categoria_id    INT UNSIGNED  NOT NULL,
  proveedor_id    INT UNSIGNED  DEFAULT NULL,
  unidad_medida   VARCHAR(20)   NOT NULL DEFAULT 'unidad',
  precio_costo    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  precio_venta    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  iva_porcentaje  DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  stock_actual    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  punto_reorden   DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  stock_maximo    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  es_insumo       TINYINT(1)    NOT NULL DEFAULT 0,
  imagen          VARCHAR(180)  NOT NULL DEFAULT '',
  activo          TINYINT(1)    NOT NULL DEFAULT 1,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME      DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_productos_sku (sku),
  KEY ix_productos_categoria (categoria_id),
  KEY ix_productos_proveedor (proveedor_id),
  KEY ix_productos_activo (activo),
  KEY ix_productos_stock (stock_actual, punto_reorden),
  CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  CONSTRAINT fk_productos_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL,
  CONSTRAINT ck_productos_stock  CHECK (stock_actual >= 0),
  CONSTRAINT ck_productos_precio CHECK (precio_venta >= 0 AND precio_costo >= 0),
  CONSTRAINT ck_productos_iva    CHECK (iva_porcentaje >= 0 AND iva_porcentaje <= 100),
  CONSTRAINT ck_productos_reorden CHECK (punto_reorden >= 0),
  KEY ix_productos_borrado (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clientes (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo_documento  ENUM('CC','TI','CE','PA','NIT','NA') NOT NULL DEFAULT 'CC',
  documento       VARCHAR(30)  NOT NULL DEFAULT '',
  nombre          VARCHAR(120) NOT NULL,
  telefono        VARCHAR(30)  NOT NULL DEFAULT '',
  correo          VARCHAR(120) NOT NULL DEFAULT '',
  direccion       VARCHAR(180) NOT NULL DEFAULT '',
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at      DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ix_clientes_documento (documento),
  KEY ix_clientes_nombre (nombre),
  KEY ix_clientes_borrado (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE promociones (
  id                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  codigo                 VARCHAR(30)   NOT NULL,
  descripcion            VARCHAR(180)  NOT NULL DEFAULT '',
  tipo                   ENUM('porcentaje','fijo') NOT NULL DEFAULT 'porcentaje',
  valor                  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  alcance                ENUM('venta','producto','categoria') NOT NULL DEFAULT 'venta',
  producto_id            INT UNSIGNED  DEFAULT NULL,
  categoria_id           INT UNSIGNED  DEFAULT NULL,
  monto_minimo           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  fecha_inicio           DATE          DEFAULT NULL,
  fecha_fin              DATE          DEFAULT NULL,
  usos_maximos           INT UNSIGNED  NOT NULL DEFAULT 0,
  usos_actuales          INT UNSIGNED  NOT NULL DEFAULT 0,
  requiere_autorizacion  TINYINT(1)    NOT NULL DEFAULT 0,
  activa                 TINYINT(1)    NOT NULL DEFAULT 1,
  creado_por             INT UNSIGNED  DEFAULT NULL,
  creado_en              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at             DATETIME      DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_promociones_codigo (codigo),
  KEY ix_promociones_activa (activa),
  CONSTRAINT fk_promo_producto  FOREIGN KEY (producto_id)  REFERENCES productos(id)  ON DELETE CASCADE,
  CONSTRAINT fk_promo_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE,
  CONSTRAINT fk_promo_usuario   FOREIGN KEY (creado_por)   REFERENCES usuarios(id)   ON DELETE SET NULL,
  CONSTRAINT ck_promo_valor CHECK (valor >= 0),
  KEY ix_promociones_borrado (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ventas (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  folio              VARCHAR(20)   NOT NULL,
  usuario_id         INT UNSIGNED  NOT NULL,
  cliente_id         INT UNSIGNED  DEFAULT NULL,
  fecha              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  subtotal           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  descuento_total    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  base_gravable      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  impuesto_total     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  costo_total        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  metodo_pago        ENUM('Efectivo','Tarjeta','Transferencia','Mixto') NOT NULL DEFAULT 'Efectivo',
  monto_recibido     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  cambio             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  estado             ENUM('Completada','Anulada') NOT NULL DEFAULT 'Completada',
  anulada_por        INT UNSIGNED  DEFAULT NULL,
  anulada_en         DATETIME      DEFAULT NULL,
  motivo_anulacion   VARCHAR(255)  NOT NULL DEFAULT '',
  creado_en          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_ventas_folio (folio),
  KEY ix_ventas_fecha (fecha),
  KEY ix_ventas_usuario (usuario_id),
  KEY ix_ventas_estado (estado),
  CONSTRAINT fk_ventas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_ventas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
  CONSTRAINT fk_ventas_anulada FOREIGN KEY (anulada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT ck_ventas_total CHECK (total >= 0 AND subtotal >= 0 AND descuento_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE venta_detalle (
  id                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  venta_id              INT UNSIGNED  NOT NULL,
  producto_id           INT UNSIGNED  NOT NULL,
  sku                   VARCHAR(30)   NOT NULL,
  nombre_producto       VARCHAR(120)  NOT NULL,
  cantidad              DECIMAL(12,3) NOT NULL,
  precio_unitario       DECIMAL(12,2) NOT NULL,
  costo_unitario        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  descuento_unitario    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  iva_porcentaje        DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  subtotal              DECIMAL(12,2) NOT NULL,
  impuesto              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total                 DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id),
  KEY ix_detalle_venta (venta_id),
  KEY ix_detalle_producto (producto_id),
  CONSTRAINT fk_detalle_venta    FOREIGN KEY (venta_id)    REFERENCES ventas(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  CONSTRAINT ck_detalle_cantidad CHECK (cantidad > 0),
  CONSTRAINT ck_detalle_precio   CHECK (precio_unitario >= 0 AND total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE descuentos_aplicados (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  venta_id        INT UNSIGNED  NOT NULL,
  promocion_id    INT UNSIGNED  DEFAULT NULL,
  codigo          VARCHAR(30)   NOT NULL DEFAULT '',
  tipo            ENUM('porcentaje','fijo','manual') NOT NULL DEFAULT 'porcentaje',
  valor           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  motivo          VARCHAR(255)  NOT NULL DEFAULT '',
  valor_original  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  valor_descuento DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  valor_final     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  autorizado_por  INT UNSIGNED  DEFAULT NULL,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_desc_venta (venta_id),
  KEY ix_desc_promocion (promocion_id),
  CONSTRAINT fk_desc_venta      FOREIGN KEY (venta_id)       REFERENCES ventas(id) ON DELETE CASCADE,
  CONSTRAINT fk_desc_promocion  FOREIGN KEY (promocion_id)   REFERENCES promociones(id) ON DELETE SET NULL,
  CONSTRAINT fk_desc_autorizado FOREIGN KEY (autorizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comprobantes (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  venta_id          INT UNSIGNED  NOT NULL,
  numero            VARCHAR(30)   NOT NULL,
  prefijo           VARCHAR(10)   NOT NULL DEFAULT 'KR',
  consecutivo       INT UNSIGNED  NOT NULL,
  tipo              ENUM('Tirilla POS','Factura de venta','Nota credito','Nota debito') NOT NULL DEFAULT 'Tirilla POS',
  resolucion_dian   VARCHAR(120)  NOT NULL DEFAULT '',
  fecha_emision     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  emisor            LONGTEXT      NOT NULL,
  cliente           LONGTEXT      NOT NULL,
  detalle           LONGTEXT      NOT NULL,
  subtotal          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  descuento         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  base_gravable     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  impuesto          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  estado            ENUM('Emitido','Anulado') NOT NULL DEFAULT 'Emitido',
  creado_en         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_comprobantes_numero (numero),
  KEY ix_comprobantes_venta (venta_id),
  KEY ix_comprobantes_fecha (fecha_emision),
  CONSTRAINT fk_comprobantes_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE compras (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  numero        VARCHAR(20)   NOT NULL,
  proveedor_id  INT UNSIGNED  NOT NULL,
  usuario_id    INT UNSIGNED  NOT NULL,
  fecha         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  subtotal      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  impuesto      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  estado        ENUM('Recibida','Pendiente','Anulada') NOT NULL DEFAULT 'Recibida',
  observaciones VARCHAR(255)  NOT NULL DEFAULT '',
  creado_en     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_compras_numero (numero),
  KEY ix_compras_proveedor (proveedor_id),
  KEY ix_compras_fecha (fecha),
  CONSTRAINT fk_compras_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
  CONSTRAINT fk_compras_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE compra_detalle (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  compra_id       INT UNSIGNED  NOT NULL,
  producto_id     INT UNSIGNED  NOT NULL,
  cantidad        DECIMAL(12,3) NOT NULL,
  costo_unitario  DECIMAL(12,2) NOT NULL,
  subtotal        DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id),
  KEY ix_cdet_compra (compra_id),
  KEY ix_cdet_producto (producto_id),
  CONSTRAINT fk_cdet_compra   FOREIGN KEY (compra_id)   REFERENCES compras(id) ON DELETE CASCADE,
  CONSTRAINT fk_cdet_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  CONSTRAINT ck_cdet_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE movimientos_inventario (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  producto_id      INT UNSIGNED  NOT NULL,
  tipo             ENUM('Entrada','Salida','Ajuste','Merma','Devolucion') NOT NULL,
  motivo           VARCHAR(120)  NOT NULL DEFAULT '',
  cantidad         DECIMAL(12,3) NOT NULL,
  stock_anterior   DECIMAL(12,3) NOT NULL,
  stock_nuevo      DECIMAL(12,3) NOT NULL,
  costo_unitario   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  referencia_tipo  ENUM('Venta','Compra','Ajuste','Anulacion','Inicial') NOT NULL DEFAULT 'Ajuste',
  referencia_id    INT UNSIGNED  DEFAULT NULL,
  usuario_id       INT UNSIGNED  NOT NULL,
  observaciones    VARCHAR(255)  NOT NULL DEFAULT '',
  creado_en        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_mov_producto (producto_id),
  KEY ix_mov_fecha (creado_en),
  KEY ix_mov_tipo (tipo),
  KEY ix_mov_usuario (usuario_id),
  CONSTRAINT fk_mov_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  CONSTRAINT fk_mov_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alertas_stock (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  producto_id      INT UNSIGNED  NOT NULL,
  tipo             ENUM('Bajo','Critico','Agotado') NOT NULL,
  stock_en_alerta  DECIMAL(12,3) NOT NULL,
  punto_reorden    DECIMAL(12,3) NOT NULL,
  mensaje          VARCHAR(255)  NOT NULL DEFAULT '',
  estado           ENUM('Pendiente','Vista','Resuelta') NOT NULL DEFAULT 'Pendiente',
  generada_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vista_en         DATETIME      DEFAULT NULL,
  vista_por        INT UNSIGNED  DEFAULT NULL,
  resuelta_en      DATETIME      DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ix_alertas_producto (producto_id),
  KEY ix_alertas_estado (estado),
  KEY ix_alertas_fecha (generada_en),
  CONSTRAINT fk_alertas_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
  CONSTRAINT fk_alertas_usuario  FOREIGN KEY (vista_por)   REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bitacora (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id      INT UNSIGNED  DEFAULT NULL,
  usuario_nombre  VARCHAR(120)  NOT NULL DEFAULT 'Sistema',
  accion          VARCHAR(60)   NOT NULL,
  modulo          VARCHAR(40)   NOT NULL,
  descripcion     VARCHAR(255)  NOT NULL DEFAULT '',
  nivel           ENUM('Info','Advertencia','Error','Sistema') NOT NULL DEFAULT 'Info',
  entidad         VARCHAR(40)   NOT NULL DEFAULT '',
  entidad_id      VARCHAR(40)   NOT NULL DEFAULT '',
  datos_antes     LONGTEXT      DEFAULT NULL,
  datos_despues   LONGTEXT      DEFAULT NULL,
  ip              VARCHAR(45)   NOT NULL DEFAULT '',
  user_agent      VARCHAR(255)  NOT NULL DEFAULT '',
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_bitacora_usuario (usuario_id),
  KEY ix_bitacora_fecha (creado_en),
  KEY ix_bitacora_modulo (modulo),
  KEY ix_bitacora_nivel (nivel),
  CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE respaldos (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nombre_archivo  VARCHAR(180)  NOT NULL,
  ruta            VARCHAR(255)  NOT NULL DEFAULT '',
  tamano_bytes    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  tipo            ENUM('Automatico','Manual') NOT NULL DEFAULT 'Automatico',
  estado          ENUM('En proceso','Completado','Fallido') NOT NULL DEFAULT 'En proceso',
  iniciado_en     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finalizado_en   DATETIME      DEFAULT NULL,
  mensaje         VARCHAR(255)  NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY ix_respaldos_fecha (iniciado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

CREATE OR REPLACE VIEW v_estado_inventario AS
SELECT
  p.id,
  p.sku,
  p.nombre,
  c.nombre AS categoria,
  pr.nombre_empresa AS proveedor,
  p.unidad_medida,
  p.precio_costo,
  p.precio_venta,
  p.iva_porcentaje,
  p.stock_actual,
  p.punto_reorden,
  p.stock_maximo,
  p.es_insumo,
  p.imagen,
  p.activo,
  (p.stock_actual * p.precio_costo) AS valor_inventario,
  CASE
    WHEN p.stock_actual <= 0 THEN 'Agotado'
    WHEN p.punto_reorden > 0 AND p.stock_actual <= p.punto_reorden * 0.5 THEN 'Critico'
    WHEN p.punto_reorden > 0 AND p.stock_actual <= p.punto_reorden THEN 'Bajo'
    ELSE 'Disponible'
  END AS estado_stock
FROM productos p
JOIN categorias c ON c.id = p.categoria_id
LEFT JOIN proveedores pr ON pr.id = p.proveedor_id
WHERE p.deleted_at IS NULL;

CREATE OR REPLACE VIEW v_rentabilidad_producto AS
SELECT
  p.id AS producto_id,
  p.sku,
  p.nombre,
  c.nombre AS categoria,
  COALESCE(SUM(vd.cantidad), 0) AS unidades_vendidas,
  COALESCE(SUM(vd.subtotal), 0) AS ingresos,
  COALESCE(SUM(vd.costo_unitario * vd.cantidad), 0) AS costo_total,
  COALESCE(SUM(vd.subtotal) - SUM(vd.costo_unitario * vd.cantidad), 0) AS utilidad_bruta,
  CASE
    WHEN COALESCE(SUM(vd.subtotal), 0) = 0 THEN 0
    ELSE ROUND(((SUM(vd.subtotal) - SUM(vd.costo_unitario * vd.cantidad)) / SUM(vd.subtotal)) * 100, 2)
  END AS margen_porcentaje
FROM productos p
JOIN categorias c ON c.id = p.categoria_id
LEFT JOIN venta_detalle vd ON vd.producto_id = p.id
LEFT JOIN ventas v ON v.id = vd.venta_id AND v.estado = 'Completada'
GROUP BY p.id, p.sku, p.nombre, c.nombre;

CREATE OR REPLACE VIEW v_ventas_diarias AS
SELECT
  DATE(v.fecha) AS dia,
  COUNT(DISTINCT v.id) AS transacciones,
  SUM(v.subtotal) AS subtotal,
  SUM(v.descuento_total) AS descuentos,
  SUM(v.impuesto_total) AS impuestos,
  SUM(v.total) AS total,
  SUM(v.costo_total) AS costo,
  SUM(v.total - v.impuesto_total - v.costo_total) AS utilidad
FROM ventas v
WHERE v.estado = 'Completada'
GROUP BY DATE(v.fecha);

CREATE OR REPLACE VIEW v_rentabilidad_categoria AS
SELECT
  c.id AS categoria_id,
  c.nombre AS categoria,
  COALESCE(SUM(vd.cantidad), 0) AS unidades_vendidas,
  COALESCE(SUM(vd.subtotal), 0) AS ingresos,
  COALESCE(SUM(vd.costo_unitario * vd.cantidad), 0) AS costo_total,
  COALESCE(SUM(vd.subtotal) - SUM(vd.costo_unitario * vd.cantidad), 0) AS utilidad_bruta,
  CASE
    WHEN COALESCE(SUM(vd.subtotal), 0) = 0 THEN 0
    ELSE ROUND(((SUM(vd.subtotal) - SUM(vd.costo_unitario * vd.cantidad)) / SUM(vd.subtotal)) * 100, 2)
  END AS margen_porcentaje
FROM categorias c
LEFT JOIN productos p ON p.categoria_id = c.id
LEFT JOIN venta_detalle vd ON vd.producto_id = p.id
LEFT JOIN ventas v ON v.id = vd.venta_id AND v.estado = 'Completada'
GROUP BY c.id, c.nombre;



INSERT INTO roles (id, nombre, descripcion) VALUES
  (1, 'Administrador', 'Control total del sistema, reportes y precios'),
  (2, 'Gerente', 'Supervisión de operación, proveedores e inventario'),
  (3, 'Cajero', 'Registro de ventas y emisión de comprobantes'),
  (4, 'Inventario', 'Gestión de productos, compras y movimientos de stock');

INSERT INTO permisos (id, codigo, modulo, descripcion) VALUES
  (1, 'ventas.registrar', 'Ventas', 'Registrar ventas'),
  (2, 'ventas.ver', 'Ventas', 'Consultar ventas'),
  (3, 'ventas.anular', 'Ventas', 'Anular ventas'),
  (4, 'comprobantes.emitir', 'Comprobantes', 'Emitir comprobantes'),
  (5, 'comprobantes.ver', 'Comprobantes', 'Consultar comprobantes'),
  (6, 'descuentos.aplicar', 'Descuentos', 'Aplicar descuentos en una venta'),
  (7, 'descuentos.gestionar', 'Descuentos', 'Crear y desactivar promociones'),
  (8, 'inventario.ver', 'Inventario', 'Consultar inventario'),
  (9, 'inventario.ajustar', 'Inventario', 'Registrar entradas, salidas y ajustes'),
  (10, 'productos.gestionar', 'Inventario', 'Crear y editar productos'),
  (11, 'productos.precio', 'Inventario', 'Modificar precios de venta y costo'),
  (12, 'alertas.ver', 'Alertas', 'Consultar alertas de stock'),
  (13, 'alertas.configurar', 'Alertas', 'Configurar puntos de reorden'),
  (14, 'proveedores.ver', 'Proveedores', 'Consultar proveedores'),
  (15, 'proveedores.gestionar', 'Proveedores', 'Crear y editar proveedores'),
  (16, 'compras.registrar', 'Compras', 'Registrar compras a proveedores'),
  (17, 'reportes.ver', 'Reportes', 'Consultar reportes de rentabilidad'),
  (18, 'reportes.exportar', 'Reportes', 'Exportar reportes en PDF y Excel'),
  (19, 'bitacora.ver', 'Bitácora', 'Consultar el registro de actividad'),
  (20, 'usuarios.gestionar', 'Usuarios', 'Crear y editar usuarios'),
  (21, 'respaldos.gestionar', 'Sistema', 'Ejecutar y consultar respaldos'),
  (22, 'configuracion.editar', 'Sistema', 'Editar la configuración del sistema');

INSERT INTO rol_permisos (rol_id, permiso_id) VALUES
  (1, 1),
  (1, 2),
  (1, 3),
  (1, 4),
  (1, 5),
  (1, 6),
  (1, 7),
  (1, 8),
  (1, 9),
  (1, 10),
  (1, 11),
  (1, 12),
  (1, 13),
  (1, 14),
  (1, 15),
  (1, 16),
  (1, 17),
  (1, 18),
  (1, 19),
  (1, 20),
  (1, 21),
  (1, 22),
  (2, 2),
  (2, 5),
  (2, 6),
  (2, 7),
  (2, 8),
  (2, 12),
  (2, 13),
  (2, 14),
  (2, 15),
  (2, 16),
  (2, 19),
  (3, 1),
  (3, 2),
  (3, 4),
  (3, 5),
  (3, 8),
  (3, 12),
  (3, 6),
  (4, 8),
  (4, 9),
  (4, 10),
  (4, 12),
  (4, 13),
  (4, 14),
  (4, 16),
  (4, 2);

INSERT INTO usuarios (id, usuario, password_hash, nombre, apellido, tipo_documento, documento, fecha_nac, correo, telefono, cargo, rol_id, activo) VALUES
  (1, 'admin', '$2y$10$jrUeJPp9c0fls7V9VMe0r.DpfJCHIROb8tbcYeCEtxdAxrabngusC', 'Juan Steban', 'Peña Cárdenas', 'TI', '1147485267', '2005-03-18', 'admin@kerico.co', '3177965569', 'Product Owner', 1, 1),
  (2, 'gerente', '$2y$10$H7Ftznsfe4z9MBA887MRu.2QpHajK8kWOxhAAnmkOiblL0.4APmwu', 'Cristian David', 'Molano Pérez', 'CC', '1014739550', '2003-11-02', 'gerente@kerico.co', '3106654412', 'Scrum Master', 2, 1),
  (3, 'cajero', '$2y$10$HZNtZFKuA5rvP45P1N5sZui6KAZHAjC.erUJmAMYR23lJvmAfclMG', 'Andrés', 'Rodríguez', 'CC', '1020458877', '2004-06-25', 'cajero@kerico.co', '3204471188', 'Asistente de tienda', 3, 1),
  (4, 'inventario', '$2y$10$nkTEmPnsPf3gR4HfKnVwuOtBYkD3B4slONhD8GnWilLxKseCuxrYe', 'Esteban', 'Torres', 'CC', '1015998233', '2004-01-14', 'inventario@kerico.co', '3115582074', 'Auxiliar de cocina', 4, 1);

INSERT INTO configuracion (clave, valor, tipo, descripcion) VALUES
  ('empresa_nombre', 'Ke-Rico!', 'texto', 'Nombre comercial del negocio'),
  ('empresa_razon_social', 'Ke-Rico! S.A.S.', 'texto', 'Razón social'),
  ('empresa_nit', '901.456.789-3', 'texto', 'NIT del emisor'),
  ('empresa_direccion', 'Cra. 59 # 132A - 7, Suba, Bogotá D.C.', 'texto', 'Dirección del establecimiento'),
  ('empresa_telefono', '(+57) 317 796 5569', 'texto', 'Teléfono de contacto'),
  ('empresa_correo', 'contacto@kerico.co', 'texto', 'Correo de contacto'),
  ('empresa_ciudad', 'Bogotá D.C.', 'texto', 'Ciudad'),
  ('resolucion_dian', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', 'texto', 'Resolución de facturación'),
  ('comprobante_prefijo', 'KR', 'texto', 'Prefijo del consecutivo de comprobantes'),
  ('comprobante_consecutivo', '1', 'entero', 'Siguiente consecutivo disponible'),
  ('impuesto_alimentos', '8.00', 'decimal', 'Impuesto al consumo para alimentos preparados'),
  ('impuesto_bebidas', '19.00', 'decimal', 'IVA general para bebidas envasadas'),
  ('sesion_minutos_inactividad', '15', 'entero', 'Cierre automático de sesión por inactividad'),
  ('intentos_maximos_login', '5', 'entero', 'Intentos fallidos antes de bloquear la cuenta'),
  ('bloqueo_minutos', '15', 'entero', 'Duración del bloqueo por intentos fallidos'),
  ('respaldo_intervalo_horas', '24', 'entero', 'Frecuencia de respaldo automático'),
  ('respaldo_directorio', 'respaldos', 'texto', 'Carpeta donde se guardan los respaldos'),
  ('alerta_factor_critico', '0.5', 'decimal', 'Fracción del punto de reorden que marca estado crítico'),
  ('moneda_simbolo', '$', 'texto', 'Símbolo de moneda'),
  ('moneda_codigo', 'COP', 'texto', 'Código ISO de moneda');

INSERT INTO categorias (id, nombre, descripcion) VALUES
  (1, 'Empanadas', 'Empanadas de maíz fritas, rellenas'),
  (2, 'Arepas', 'Arepas rellenas y arepa de huevo'),
  (3, 'Fritos', 'Papa rellena, pasteles de yuca y carimañolas'),
  (4, 'Bebidas', 'Gaseosas, jugos naturales y agua'),
  (5, 'Combos', 'Combinaciones para compartir'),
  (6, 'Insumos', 'Materia prima y empaques');

INSERT INTO proveedores (id, nombre_empresa, nit, telefono, correo, direccion, contacto_nombre, tipo_productos, estado, calificacion) VALUES
  (1, 'Mac Pollo', '900.100.001-1', '(+57) 601 744 1100', 'ventas@macpollo.demo', 'Cl. 13 # 68-35, Bogotá D.C.', 'Marcela Ruiz', 'Pechuga, pierna, vísceras y derivados de pollo', 'Activo', 4.60),
  (2, 'Carnes Finas F.F', '900.100.002-2', '(+57) 601 355 8820', 'pedidos@carnesff.demo', 'Cra. 30 # 22-47, Bogotá D.C.', 'Fabio Fernández', 'Carne de res y derivados', 'Activo', 4.40),
  (3, 'Nico Carnes', '900.100.003-3', '(+57) 601 288 4471', 'contacto@nicocarnes.demo', 'Cl. 68 # 55-12, Bogotá D.C.', 'Nicolás Ayala', 'Tocino, chuleta, lomo, chorizo, salchichón, morcilla y jamón', 'Activo', 4.10),
  (4, 'Distribuidora Don Germán', '900.100.004-4', '(+57) 320 447 9911', 'dongerman@correo.demo', 'Corabastos Bodega 14, Bogotá D.C.', 'Germán Pineda', 'Papa, yuca y verduras', 'Activo', 4.75),
  (5, 'Surtimayorista', '900.100.005-5', '(+57) 601 410 2233', 'mayorista@surti.demo', 'Av. Cra. 68 # 75-88, Bogotá D.C.', 'Sandra Lozano', 'Arroz, condimentos, harina de trigo y maíz, elementos de aseo', 'Activo', 4.20),
  (6, 'Masa y Arepa Malagón', '900.100.006-6', '(+57) 311 558 2074', 'malagon@arepas.demo', 'Cl. 132A # 58-20, Suba, Bogotá D.C.', 'Luis Malagón', 'Masa de maíz peto y arepas crudas', 'Activo', 4.90),
  (7, 'Lácteos La Sabana Caqueteño', '900.100.007-7', '(+57) 310 665 4412', 'lasabana@lacteos.demo', 'Cl. 80 # 100-14, Bogotá D.C.', 'Diana Caqueteño', 'Queso campesino, quesillo y cuajada', 'Activo', 4.30),
  (8, 'Distribuidora Ibérica', '900.100.008-8', '(+57) 601 622 1904', 'iberica@embutidos.demo', 'Cra. 7 # 127-55, Bogotá D.C.', 'Alberto Sanz', 'Embutidos y carnes frías', 'En revision', 3.50),
  (9, 'Sitcass', '900.100.009-9', '(+57) 601 703 8845', 'sitcass@seguridad.demo', 'Cl. 45 # 13-60, Bogotá D.C.', 'Paola Cárdenas', 'Elementos de seguridad y dotación', 'Activo', 4.00),
  (10, 'Coca-Cola FEMSA Colombia', '900.100.010-0', '(+57) 601 425 9000', 'pedidos@ccfemsa.demo', 'Av. El Dorado # 92-30, Bogotá D.C.', 'Ricardo Pardo', 'Gaseosas y bebidas embotelladas', 'Activo', 4.55),
  (11, 'Postobón', '900.100.011-1', '(+57) 601 337 7700', 'pedidos@postobon.demo', 'Cl. 17 # 68-90, Bogotá D.C.', 'Elena Marín', 'Gaseosas, jugos y agua embotellada', 'Activo', 4.35),
  (12, 'Nutresa', '900.100.012-2', '(+57) 601 520 6600', 'pedidos@nutresa.demo', 'Cra. 43A # 1-50, Bogotá D.C.', 'Héctor Bedoya', 'Galletas, café y aromáticas', 'Inactivo', 3.80);

INSERT INTO productos (id, sku, nombre, descripcion, categoria_id, proveedor_id, unidad_medida, precio_costo, precio_venta, iva_porcentaje, stock_actual, punto_reorden, stock_maximo, es_insumo, imagen, activo) VALUES
  (1, 'EMP-CAR', 'Empanada de carne', 'Masa de maíz crujiente rellena de carne mechada.', 1, 2, 'unidad', 1400.00, 3900.00, 8.00, 0.000, 60.000, 400.000, 0, 'marca/productos/empanaditas.jpg', 1),
  (2, 'EMP-POL', 'Empanada de pollo', 'Rellena de pollo desmechado con guiso de la casa.', 1, 1, 'unidad', 1350.00, 3900.00, 8.00, 0.000, 60.000, 400.000, 0, 'marca/productos/empanaditas.jpg', 1),
  (3, 'EMP-QUE', 'Empanada de queso', 'Rellena de queso campesino fundido.', 1, 7, 'unidad', 1200.00, 3500.00, 8.00, 0.000, 50.000, 300.000, 0, 'marca/productos/empanaditas.jpg', 1),
  (4, 'EMP-HAW', 'Empanada hawaiana', 'Jamón, queso y piña caramelizada.', 1, 8, 'unidad', 1500.00, 4200.00, 8.00, 0.000, 40.000, 250.000, 0, 'marca/productos/empanaditas.jpg', 1),
  (5, 'ARE-REL', 'Arepa rellena', 'Arepa de maíz con queso y chicharrón crujiente.', 2, 6, 'unidad', 1600.00, 3900.00, 8.00, 0.000, 40.000, 250.000, 0, 'marca/productos/arepa-huevo.jpg', 1),
  (6, 'ARE-HUE', 'Arepa e\' Huevo', 'Arepa frita con huevo y carne desmechada por dentro.', 2, 6, 'unidad', 2100.00, 5500.00, 8.00, 0.000, 30.000, 200.000, 0, 'marca/productos/arepa-huevo.jpg', 1),
  (7, 'PAP-REL', 'Papa rellena', 'De papa, rellena con carne desmechada y huevo.', 3, 4, 'unidad', 1900.00, 5000.00, 8.00, 0.000, 30.000, 200.000, 0, 'marca/productos/carimanola.jpg', 1),
  (8, 'PAS-YUC', 'Pastel de yuca', 'Masa de yuca rellena, hecha como en casa.', 3, 4, 'unidad', 1500.00, 4000.00, 8.00, 0.000, 30.000, 200.000, 0, 'marca/productos/pasteles.jpg', 1),
  (9, 'CAR-CAR', 'Carimañola', 'Rollo de yuca relleno de carne y queso.', 3, 4, 'unidad', 1700.00, 4500.00, 8.00, 0.000, 25.000, 150.000, 0, 'marca/productos/carimanola.jpg', 1),
  (10, 'BEB-G2L', 'Gaseosa 2 L', 'Botella familiar de 2 litros.', 4, 10, 'unidad', 4200.00, 6500.00, 19.00, 0.000, 12.000, 60.000, 0, '', 1),
  (11, 'BEB-GPE', 'Gaseosa personal', 'Botella personal de 400 ml.', 4, 10, 'unidad', 1800.00, 3000.00, 19.00, 0.000, 24.000, 120.000, 0, '', 1),
  (12, 'BEB-JUG', 'Jugo natural', 'Jugo natural de mora, maracuyá o lulo.', 4, 11, 'unidad', 1500.00, 4000.00, 19.00, 0.000, 20.000, 100.000, 0, '', 1),
  (13, 'BEB-AGU', 'Agua 600 ml', 'Agua sin gas.', 4, 11, 'unidad', 900.00, 2000.00, 19.00, 0.000, 24.000, 120.000, 0, '', 1),
  (14, 'COM-FAM', 'Combo familiar', '10 empanadas más bebida de 2 L. Incluye ají de la casa.', 5, NULL, 'combo', 12000.00, 30000.00, 8.00, 0.000, 10.000, 60.000, 0, 'marca/productos/empanaditas.jpg', 1),
  (15, 'INS-MAS', 'Masa de maíz peto', 'Masa lista para empanadas y arepas.', 6, 6, 'kg', 4100.00, 0.00, 0.00, 0.000, 25.000, 150.000, 1, '', 1),
  (16, 'INS-CAR', 'Carne molida de res', 'Carne para relleno.', 6, 2, 'kg', 18500.00, 0.00, 0.00, 0.000, 15.000, 80.000, 1, '', 1),
  (17, 'INS-POL', 'Pechuga de pollo', 'Pechuga para desmechar.', 6, 1, 'kg', 14200.00, 0.00, 0.00, 0.000, 12.000, 70.000, 1, '', 1),
  (18, 'INS-QUE', 'Queso campesino', 'Queso para relleno.', 6, 7, 'kg', 12800.00, 0.00, 0.00, 0.000, 10.000, 50.000, 1, '', 1),
  (19, 'INS-ACE', 'Aceite de girasol', 'Aceite para freidora.', 6, 5, 'litro', 9500.00, 0.00, 0.00, 0.000, 20.000, 120.000, 1, '', 1),
  (20, 'INS-YUC', 'Yuca', 'Yuca fresca para pasteles y carimañolas.', 6, 4, 'kg', 2800.00, 0.00, 0.00, 0.000, 20.000, 120.000, 1, '', 1),
  (21, 'INS-PAP', 'Papa pastusa', 'Papa para papa rellena.', 6, 4, 'kg', 2400.00, 0.00, 0.00, 0.000, 25.000, 150.000, 1, '', 1),
  (22, 'INS-HUE', 'Huevos', 'Huevo AA.', 6, 5, 'unidad', 650.00, 0.00, 0.00, 0.000, 120.000, 600.000, 1, '', 1),
  (23, 'INS-EMP', 'Empaque kraft', 'Bolsa y caja kraft con marca.', 6, 5, 'unidad', 180.00, 0.00, 0.00, 0.000, 200.000, 1500.000, 1, '', 1);

INSERT INTO clientes (id, tipo_documento, documento, nombre, telefono, correo, direccion) VALUES
  (1, 'NA', '', 'Consumidor final', '', '', ''),
  (2, 'CC', '52887431', 'Laura Gómez', '3115582074', 'laura.gomez@correo.demo', 'Cl. 134 # 55-20, Suba'),
  (3, 'CC', '80114522', 'Andrés Rojas', '3004471189', 'andres.rojas@correo.demo', 'Cra. 58 # 128-40, Suba'),
  (4, 'NIT', '830.077.441-2', 'Inversiones La Esquina S.A.S.', '6017442210', 'compras@laesquina.demo', 'Cl. 140 # 60-11, Bogotá D.C.'),
  (5, 'CC', '1019887654', 'Camila Torres', '3126654890', 'camila.torres@correo.demo', 'Cra. 92 # 147-30, Suba'),
  (6, 'CC', '79554120', 'Juan Pablo Díaz', '3187745521', 'jp.diaz@correo.demo', 'Cl. 127 # 45-18, Bogotá D.C.');

INSERT INTO promociones (id, codigo, descripcion, tipo, valor, alcance, producto_id, categoria_id, monto_minimo, fecha_inicio, fecha_fin, usos_maximos, usos_actuales, requiere_autorizacion, activa, creado_por) VALUES
  (1, 'BIENVENIDO10', 'Diez por ciento de bienvenida', 'porcentaje', 10.00, 'venta', NULL, NULL, 15000.00, '2026-01-01', '2026-12-31', 500, 0, 0, 1, 1),
  (2, 'COMBO5000', 'Cinco mil de rebaja en combo familiar', 'fijo', 5000.00, 'producto', 14, NULL, 0.00, '2026-01-01', '2026-12-31', 300, 0, 0, 1, 1),
  (3, 'EMPANADA15', 'Quince por ciento en empanadas', 'porcentaje', 15.00, 'categoria', NULL, 1, 0.00, '2026-09-01', '2026-09-30', 400, 0, 0, 1, 2),
  (4, 'ESTUDIANTE', 'Descuento estudiante', 'porcentaje', 12.00, 'venta', NULL, NULL, 10000.00, '2026-01-01', '2026-12-31', 0, 0, 1, 1, 1),
  (5, 'LLUVIA20', 'Promoción temporada de lluvia', 'porcentaje', 20.00, 'venta', NULL, NULL, 25000.00, '2026-04-01', '2026-05-31', 200, 0, 1, 0, 2);

INSERT INTO compras (id, numero, proveedor_id, usuario_id, fecha, subtotal, impuesto, total, estado) VALUES
  (1, 'OC-00001', 6, 4, '2026-09-02 06:00:00', 365000.00, 0.00, 365000.00, 'Recibida'),
  (2, 'OC-00002', 4, 4, '2026-09-03 06:00:00', 294000.00, 0.00, 294000.00, 'Recibida'),
  (3, 'OC-00003', 2, 4, '2026-09-04 07:00:00', 462500.00, 0.00, 462500.00, 'Recibida'),
  (4, 'OC-00004', 10, 4, '2026-09-05 06:00:00', 187200.00, 0.00, 187200.00, 'Recibida'),
  (5, 'OC-00005', 1, 4, '2026-09-07 06:00:00', 284000.00, 0.00, 284000.00, 'Recibida'),
  (6, 'OC-00006', 5, 4, '2026-09-08 07:00:00', 379000.00, 0.00, 379000.00, 'Recibida'),
  (7, 'OC-00007', 7, 4, '2026-09-09 06:00:00', 192000.00, 0.00, 192000.00, 'Recibida'),
  (8, 'OC-00008', 11, 4, '2026-09-10 06:00:00', 114000.00, 0.00, 114000.00, 'Pendiente');

INSERT INTO compra_detalle (compra_id, producto_id, cantidad, costo_unitario, subtotal) VALUES
  (1, 15, 40.000, 4100.00, 164000.00),
  (1, 5, 60.000, 1600.00, 96000.00),
  (1, 6, 50.000, 2100.00, 105000.00),
  (2, 20, 30.000, 2800.00, 84000.00),
  (2, 21, 40.000, 2400.00, 96000.00),
  (2, 7, 60.000, 1900.00, 114000.00),
  (3, 16, 25.000, 18500.00, 462500.00),
  (4, 10, 24.000, 4200.00, 100800.00),
  (4, 11, 48.000, 1800.00, 86400.00),
  (5, 17, 20.000, 14200.00, 284000.00),
  (6, 19, 20.000, 9500.00, 190000.00),
  (6, 22, 180.000, 650.00, 117000.00),
  (6, 23, 400.000, 180.00, 72000.00),
  (7, 18, 15.000, 12800.00, 192000.00),
  (8, 12, 40.000, 1500.00, 60000.00),
  (8, 13, 60.000, 900.00, 54000.00);

INSERT INTO ventas (id, folio, usuario_id, cliente_id, fecha, subtotal, descuento_total, base_gravable, impuesto_total, total, costo_total, metodo_pago, monto_recibido, cambio, estado) VALUES
  (1, 'V-000001', 3, 1, '2026-09-02 11:12:00', 21600.00, 0.00, 19486.46, 2113.54, 21600.00, 9200.00, 'Efectivo', 22000.00, 400.00, 'Completada'),
  (2, 'V-000002', 3, 2, '2026-09-02 13:40:00', 26500.00, 0.00, 23980.70, 2519.30, 26500.00, 11800.00, 'Efectivo', 27000.00, 500.00, 'Completada'),
  (3, 'V-000003', 3, 1, '2026-09-03 10:05:00', 45500.00, 4550.00, 37415.96, 3534.04, 40950.00, 18200.00, 'Tarjeta', 40950.00, 0.00, 'Completada'),
  (4, 'V-000004', 3, 3, '2026-09-03 12:55:00', 35400.00, 0.00, 31750.70, 3649.30, 35400.00, 12600.00, 'Efectivo', 36000.00, 600.00, 'Completada'),
  (5, 'V-000005', 3, 5, '2026-09-04 11:30:00', 22500.00, 0.00, 20319.80, 2180.20, 22500.00, 9000.00, 'Efectivo', 23000.00, 500.00, 'Completada'),
  (6, 'V-000006', 3, 4, '2026-09-04 15:18:00', 60000.00, 5000.00, 50925.93, 4074.07, 55000.00, 24000.00, 'Transferencia', 55000.00, 0.00, 'Completada'),
  (7, 'V-000007', 3, 1, '2026-09-05 09:45:00', 40000.00, 6000.00, 30608.47, 3391.53, 34000.00, 16800.00, 'Efectivo', 34000.00, 0.00, 'Completada'),
  (8, 'V-000008', 3, 6, '2026-09-05 14:22:00', 32000.00, 0.00, 28944.91, 3055.09, 32000.00, 12000.00, 'Tarjeta', 32000.00, 0.00, 'Completada'),
  (9, 'V-000009', 3, 1, '2026-09-07 10:40:00', 59800.00, 5980.00, 48831.93, 4988.07, 53820.00, 25200.00, 'Efectivo', 54000.00, 180.00, 'Completada'),
  (10, 'V-000010', 3, 2, '2026-09-07 16:10:00', 29500.00, 0.00, 26458.92, 3041.08, 29500.00, 12500.00, 'Efectivo', 30000.00, 500.00, 'Completada'),
  (11, 'V-000011', 3, 1, '2026-09-08 11:05:00', 45000.00, 0.00, 40126.05, 4873.95, 45000.00, 21000.00, 'Efectivo', 45000.00, 0.00, 'Completada'),
  (12, 'V-000012', 3, 4, '2026-09-08 18:30:00', 90000.00, 5000.00, 78703.70, 6296.30, 85000.00, 36000.00, 'Transferencia', 85000.00, 0.00, 'Completada'),
  (13, 'V-000013', 3, 5, '2026-09-09 12:15:00', 45400.00, 6810.00, 34567.46, 4022.54, 38590.00, 16500.00, 'Tarjeta', 38590.00, 0.00, 'Completada'),
  (14, 'V-000014', 3, 1, '2026-09-09 17:50:00', 40500.00, 0.00, 36387.33, 4112.67, 40500.00, 18900.00, 'Efectivo', 41000.00, 500.00, 'Completada'),
  (15, 'V-000015', 3, 3, '2026-09-10 10:20:00', 117000.00, 11700.00, 95997.90, 9302.10, 105300.00, 47100.00, 'Efectivo', 106000.00, 700.00, 'Completada'),
  (16, 'V-000016', 3, 1, '2026-09-10 13:05:00', 64000.00, 0.00, 57205.11, 6794.89, 64000.00, 29600.00, 'Efectivo', 64000.00, 0.00, 'Completada'),
  (17, 'V-000017', 3, 6, '2026-09-11 11:44:00', 52000.00, 0.00, 47121.07, 4878.93, 52000.00, 20400.00, 'Tarjeta', 52000.00, 0.00, 'Completada'),
  (18, 'V-000018', 3, 1, '2026-09-11 16:25:00', 55100.00, 8265.00, 41910.71, 4924.29, 46835.00, 21900.00, 'Efectivo', 47000.00, 165.00, 'Completada'),
  (19, 'V-000019', 3, 2, '2026-09-12 10:08:00', 104000.00, 10400.00, 84663.86, 8936.14, 93600.00, 44800.00, 'Efectivo', 94000.00, 400.00, 'Completada'),
  (20, 'V-000020', 3, 1, '2026-09-12 15:35:00', 67500.00, 0.00, 60189.08, 7310.92, 67500.00, 31500.00, 'Efectivo', 68000.00, 500.00, 'Completada'),
  (21, 'V-000021', 3, 5, '2026-09-13 11:50:00', 60000.00, 0.00, 54186.12, 5813.88, 60000.00, 24000.00, 'Tarjeta', 60000.00, 0.00, 'Completada'),
  (22, 'V-000022', 3, 1, '2026-09-13 17:15:00', 73000.00, 10950.00, 55707.67, 6342.33, 62050.00, 25800.00, 'Efectivo', 63000.00, 950.00, 'Completada');

INSERT INTO venta_detalle (venta_id, producto_id, sku, nombre_producto, cantidad, precio_unitario, costo_unitario, descuento_unitario, iva_porcentaje, subtotal, impuesto, total) VALUES
  (1, 1, 'EMP-CAR', 'Empanada de carne', 4.000, 3900.00, 1400.00, 0.00, 8.00, 14444.44, 1155.56, 15600.00),
  (1, 11, 'BEB-GPE', 'Gaseosa personal', 2.000, 3000.00, 1800.00, 0.00, 19.00, 5042.02, 957.98, 6000.00),
  (2, 7, 'PAP-REL', 'Papa rellena', 4.000, 5000.00, 1900.00, 0.00, 8.00, 18518.52, 1481.48, 20000.00),
  (2, 10, 'BEB-G2L', 'Gaseosa 2 L', 1.000, 6500.00, 4200.00, 0.00, 19.00, 5462.18, 1037.82, 6500.00),
  (3, 1, 'EMP-CAR', 'Empanada de carne', 10.000, 3900.00, 1400.00, 0.00, 8.00, 36111.11, 2888.89, 39000.00),
  (3, 10, 'BEB-G2L', 'Gaseosa 2 L', 1.000, 6500.00, 4200.00, 0.00, 19.00, 5462.18, 1037.82, 6500.00),
  (4, 2, 'EMP-POL', 'Empanada de pollo', 6.000, 3900.00, 1350.00, 0.00, 8.00, 21666.67, 1733.33, 23400.00),
  (4, 12, 'BEB-JUG', 'Jugo natural', 3.000, 4000.00, 1500.00, 0.00, 19.00, 10084.03, 1915.97, 12000.00),
  (5, 6, 'ARE-HUE', 'Arepa e\' Huevo', 3.000, 5500.00, 2100.00, 0.00, 8.00, 15277.78, 1222.22, 16500.00),
  (5, 13, 'BEB-AGU', 'Agua 600 ml', 3.000, 2000.00, 900.00, 0.00, 19.00, 5042.02, 957.98, 6000.00),
  (6, 14, 'COM-FAM', 'Combo familiar', 2.000, 30000.00, 12000.00, 0.00, 8.00, 55555.56, 4444.44, 60000.00),
  (7, 3, 'EMP-QUE', 'Empanada de queso', 8.000, 3500.00, 1200.00, 0.00, 8.00, 25925.93, 2074.07, 28000.00),
  (7, 11, 'BEB-GPE', 'Gaseosa personal', 4.000, 3000.00, 1800.00, 0.00, 19.00, 10084.03, 1915.97, 12000.00),
  (8, 8, 'PAS-YUC', 'Pastel de yuca', 6.000, 4000.00, 1500.00, 0.00, 8.00, 22222.22, 1777.78, 24000.00),
  (8, 12, 'BEB-JUG', 'Jugo natural', 2.000, 4000.00, 1500.00, 0.00, 19.00, 6722.69, 1277.31, 8000.00),
  (9, 1, 'EMP-CAR', 'Empanada de carne', 12.000, 3900.00, 1400.00, 0.00, 8.00, 43333.33, 3466.67, 46800.00),
  (9, 10, 'BEB-G2L', 'Gaseosa 2 L', 2.000, 6500.00, 4200.00, 0.00, 19.00, 10924.37, 2075.63, 13000.00),
  (10, 5, 'ARE-REL', 'Arepa rellena', 5.000, 3900.00, 1600.00, 0.00, 8.00, 18055.56, 1444.44, 19500.00),
  (10, 13, 'BEB-AGU', 'Agua 600 ml', 5.000, 2000.00, 900.00, 0.00, 19.00, 8403.36, 1596.64, 10000.00),
  (11, 9, 'CAR-CAR', 'Carimañola', 6.000, 4500.00, 1700.00, 0.00, 8.00, 25000.00, 2000.00, 27000.00),
  (11, 11, 'BEB-GPE', 'Gaseosa personal', 6.000, 3000.00, 1800.00, 0.00, 19.00, 15126.05, 2873.95, 18000.00),
  (12, 14, 'COM-FAM', 'Combo familiar', 3.000, 30000.00, 12000.00, 0.00, 8.00, 83333.33, 6666.67, 90000.00),
  (13, 4, 'EMP-HAW', 'Empanada hawaiana', 7.000, 4200.00, 1500.00, 0.00, 8.00, 27222.22, 2177.78, 29400.00),
  (13, 12, 'BEB-JUG', 'Jugo natural', 4.000, 4000.00, 1500.00, 0.00, 19.00, 13445.38, 2554.62, 16000.00),
  (14, 6, 'ARE-HUE', 'Arepa e\' Huevo', 5.000, 5500.00, 2100.00, 0.00, 8.00, 25462.96, 2037.04, 27500.00),
  (14, 10, 'BEB-G2L', 'Gaseosa 2 L', 2.000, 6500.00, 4200.00, 0.00, 19.00, 10924.37, 2075.63, 13000.00),
  (15, 1, 'EMP-CAR', 'Empanada de carne', 15.000, 3900.00, 1400.00, 0.00, 8.00, 54166.67, 4333.33, 58500.00),
  (15, 2, 'EMP-POL', 'Empanada de pollo', 10.000, 3900.00, 1350.00, 0.00, 8.00, 36111.11, 2888.89, 39000.00),
  (15, 10, 'BEB-G2L', 'Gaseosa 2 L', 3.000, 6500.00, 4200.00, 0.00, 19.00, 16386.55, 3113.45, 19500.00),
  (16, 7, 'PAP-REL', 'Papa rellena', 8.000, 5000.00, 1900.00, 0.00, 8.00, 37037.04, 2962.96, 40000.00),
  (16, 11, 'BEB-GPE', 'Gaseosa personal', 8.000, 3000.00, 1800.00, 0.00, 19.00, 20168.07, 3831.93, 24000.00),
  (17, 8, 'PAS-YUC', 'Pastel de yuca', 10.000, 4000.00, 1500.00, 0.00, 8.00, 37037.04, 2962.96, 40000.00),
  (17, 13, 'BEB-AGU', 'Agua 600 ml', 6.000, 2000.00, 900.00, 0.00, 19.00, 10084.03, 1915.97, 12000.00),
  (18, 5, 'ARE-REL', 'Arepa rellena', 9.000, 3900.00, 1600.00, 0.00, 8.00, 32500.00, 2600.00, 35100.00),
  (18, 12, 'BEB-JUG', 'Jugo natural', 5.000, 4000.00, 1500.00, 0.00, 19.00, 16806.72, 3193.28, 20000.00),
  (19, 1, 'EMP-CAR', 'Empanada de carne', 20.000, 3900.00, 1400.00, 0.00, 8.00, 72222.22, 5777.78, 78000.00),
  (19, 10, 'BEB-G2L', 'Gaseosa 2 L', 4.000, 6500.00, 4200.00, 0.00, 19.00, 21848.74, 4151.26, 26000.00),
  (20, 9, 'CAR-CAR', 'Carimañola', 9.000, 4500.00, 1700.00, 0.00, 8.00, 37500.00, 3000.00, 40500.00),
  (20, 11, 'BEB-GPE', 'Gaseosa personal', 9.000, 3000.00, 1800.00, 0.00, 19.00, 22689.08, 4310.92, 27000.00),
  (21, 6, 'ARE-HUE', 'Arepa e\' Huevo', 8.000, 5500.00, 2100.00, 0.00, 8.00, 40740.74, 3259.26, 44000.00),
  (21, 13, 'BEB-AGU', 'Agua 600 ml', 8.000, 2000.00, 900.00, 0.00, 19.00, 13445.38, 2554.62, 16000.00),
  (22, 3, 'EMP-QUE', 'Empanada de queso', 14.000, 3500.00, 1200.00, 0.00, 8.00, 45370.37, 3629.63, 49000.00),
  (22, 12, 'BEB-JUG', 'Jugo natural', 6.000, 4000.00, 1500.00, 0.00, 19.00, 20168.07, 3831.93, 24000.00);

INSERT INTO descuentos_aplicados (venta_id, promocion_id, codigo, tipo, valor, motivo, valor_original, valor_descuento, valor_final, autorizado_por, creado_en) VALUES
  (3, 1, 'BIENVENIDO10', 'porcentaje', 10.00, 'Diez por ciento de bienvenida', 45500.00, 4550.00, 40950.00, 1, '2026-09-03 10:05:00'),
  (6, 2, 'COMBO5000', 'fijo', 5000.00, 'Cinco mil de rebaja en combo familiar', 60000.00, 5000.00, 55000.00, 1, '2026-09-04 15:18:00'),
  (7, 3, 'EMPANADA15', 'porcentaje', 15.00, 'Quince por ciento en empanadas', 40000.00, 6000.00, 34000.00, 1, '2026-09-05 09:45:00'),
  (9, 1, 'BIENVENIDO10', 'porcentaje', 10.00, 'Diez por ciento de bienvenida', 59800.00, 5980.00, 53820.00, 1, '2026-09-07 10:40:00'),
  (12, 2, 'COMBO5000', 'fijo', 5000.00, 'Cinco mil de rebaja en combo familiar', 90000.00, 5000.00, 85000.00, 1, '2026-09-08 18:30:00'),
  (13, 3, 'EMPANADA15', 'porcentaje', 15.00, 'Quince por ciento en empanadas', 45400.00, 6810.00, 38590.00, 1, '2026-09-09 12:15:00'),
  (15, 1, 'BIENVENIDO10', 'porcentaje', 10.00, 'Diez por ciento de bienvenida', 117000.00, 11700.00, 105300.00, 1, '2026-09-10 10:20:00'),
  (18, 3, 'EMPANADA15', 'porcentaje', 15.00, 'Quince por ciento en empanadas', 55100.00, 8265.00, 46835.00, 1, '2026-09-11 16:25:00'),
  (19, 1, 'BIENVENIDO10', 'porcentaje', 10.00, 'Diez por ciento de bienvenida', 104000.00, 10400.00, 93600.00, 1, '2026-09-12 10:08:00'),
  (22, 3, 'EMPANADA15', 'porcentaje', 15.00, 'Quince por ciento en empanadas', 73000.00, 10950.00, 62050.00, 1, '2026-09-13 17:15:00');

UPDATE promociones SET usos_actuales = 4 WHERE id = 1;
UPDATE promociones SET usos_actuales = 2 WHERE id = 2;
UPDATE promociones SET usos_actuales = 4 WHERE id = 3;

INSERT INTO comprobantes (venta_id, numero, prefijo, consecutivo, tipo, resolucion_dian, fecha_emision, emisor, cliente, detalle, subtotal, descuento, base_gravable, impuesto, total, estado) VALUES
  (1, 'KR-00000001', 'KR', 1, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-02 11:12:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NA","documento":"","nombre":"Consumidor final","telefono":"","correo":"","direccion":""}', '[{"sku":"EMP-CAR","nombre":"Empanada de carne","cantidad":4,"precio_unitario":3900,"iva_porcentaje":8,"subtotal":14444.44,"impuesto":1155.56,"total":15600},{"sku":"BEB-GPE","nombre":"Gaseosa personal","cantidad":2,"precio_unitario":3000,"iva_porcentaje":19,"subtotal":5042.02,"impuesto":957.98,"total":6000}]', 21600.00, 0.00, 19486.46, 2113.54, 21600.00, 'Emitido'),
  (2, 'KR-00000002', 'KR', 2, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-02 13:40:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"CC","documento":"52887431","nombre":"Laura Gómez","telefono":"3115582074","correo":"laura.gomez@correo.demo","direccion":"Cl. 134 # 55-20, Suba"}', '[{"sku":"PAP-REL","nombre":"Papa rellena","cantidad":4,"precio_unitario":5000,"iva_porcentaje":8,"subtotal":18518.52,"impuesto":1481.48,"total":20000},{"sku":"BEB-G2L","nombre":"Gaseosa 2 L","cantidad":1,"precio_unitario":6500,"iva_porcentaje":19,"subtotal":5462.18,"impuesto":1037.82,"total":6500}]', 26500.00, 0.00, 23980.70, 2519.30, 26500.00, 'Emitido'),
  (3, 'KR-00000003', 'KR', 3, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-03 10:05:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NA","documento":"","nombre":"Consumidor final","telefono":"","correo":"","direccion":""}', '[{"sku":"EMP-CAR","nombre":"Empanada de carne","cantidad":10,"precio_unitario":3900,"iva_porcentaje":8,"subtotal":36111.11,"impuesto":2888.89,"total":39000},{"sku":"BEB-G2L","nombre":"Gaseosa 2 L","cantidad":1,"precio_unitario":6500,"iva_porcentaje":19,"subtotal":5462.18,"impuesto":1037.82,"total":6500}]', 45500.00, 4550.00, 37415.96, 3534.04, 40950.00, 'Emitido'),
  (4, 'KR-00000004', 'KR', 4, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-03 12:55:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"CC","documento":"80114522","nombre":"Andrés Rojas","telefono":"3004471189","correo":"andres.rojas@correo.demo","direccion":"Cra. 58 # 128-40, Suba"}', '[{"sku":"EMP-POL","nombre":"Empanada de pollo","cantidad":6,"precio_unitario":3900,"iva_porcentaje":8,"subtotal":21666.67,"impuesto":1733.33,"total":23400},{"sku":"BEB-JUG","nombre":"Jugo natural","cantidad":3,"precio_unitario":4000,"iva_porcentaje":19,"subtotal":10084.03,"impuesto":1915.97,"total":12000}]', 35400.00, 0.00, 31750.70, 3649.30, 35400.00, 'Emitido'),
  (5, 'KR-00000005', 'KR', 5, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-04 11:30:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"CC","documento":"1019887654","nombre":"Camila Torres","telefono":"3126654890","correo":"camila.torres@correo.demo","direccion":"Cra. 92 # 147-30, Suba"}', '[{"sku":"ARE-HUE","nombre":"Arepa e\' Huevo","cantidad":3,"precio_unitario":5500,"iva_porcentaje":8,"subtotal":15277.78,"impuesto":1222.22,"total":16500},{"sku":"BEB-AGU","nombre":"Agua 600 ml","cantidad":3,"precio_unitario":2000,"iva_porcentaje":19,"subtotal":5042.02,"impuesto":957.98,"total":6000}]', 22500.00, 0.00, 20319.80, 2180.20, 22500.00, 'Emitido'),
  (6, 'KR-00000006', 'KR', 6, 'Factura de venta', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-04 15:18:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NIT","documento":"830.077.441-2","nombre":"Inversiones La Esquina S.A.S.","telefono":"6017442210","correo":"compras@laesquina.demo","direccion":"Cl. 140 # 60-11, Bogotá D.C."}', '[{"sku":"COM-FAM","nombre":"Combo familiar","cantidad":2,"precio_unitario":30000,"iva_porcentaje":8,"subtotal":55555.56,"impuesto":4444.44,"total":60000}]', 60000.00, 5000.00, 50925.93, 4074.07, 55000.00, 'Emitido'),
  (7, 'KR-00000007', 'KR', 7, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-05 09:45:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NA","documento":"","nombre":"Consumidor final","telefono":"","correo":"","direccion":""}', '[{"sku":"EMP-QUE","nombre":"Empanada de queso","cantidad":8,"precio_unitario":3500,"iva_porcentaje":8,"subtotal":25925.93,"impuesto":2074.07,"total":28000},{"sku":"BEB-GPE","nombre":"Gaseosa personal","cantidad":4,"precio_unitario":3000,"iva_porcentaje":19,"subtotal":10084.03,"impuesto":1915.97,"total":12000}]', 40000.00, 6000.00, 30608.47, 3391.53, 34000.00, 'Emitido'),
  (8, 'KR-00000008', 'KR', 8, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-05 14:22:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"CC","documento":"79554120","nombre":"Juan Pablo Díaz","telefono":"3187745521","correo":"jp.diaz@correo.demo","direccion":"Cl. 127 # 45-18, Bogotá D.C."}', '[{"sku":"PAS-YUC","nombre":"Pastel de yuca","cantidad":6,"precio_unitario":4000,"iva_porcentaje":8,"subtotal":22222.22,"impuesto":1777.78,"total":24000},{"sku":"BEB-JUG","nombre":"Jugo natural","cantidad":2,"precio_unitario":4000,"iva_porcentaje":19,"subtotal":6722.69,"impuesto":1277.31,"total":8000}]', 32000.00, 0.00, 28944.91, 3055.09, 32000.00, 'Emitido'),
  (9, 'KR-00000009', 'KR', 9, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-07 10:40:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NA","documento":"","nombre":"Consumidor final","telefono":"","correo":"","direccion":""}', '[{"sku":"EMP-CAR","nombre":"Empanada de carne","cantidad":12,"precio_unitario":3900,"iva_porcentaje":8,"subtotal":43333.33,"impuesto":3466.67,"total":46800},{"sku":"BEB-G2L","nombre":"Gaseosa 2 L","cantidad":2,"precio_unitario":6500,"iva_porcentaje":19,"subtotal":10924.37,"impuesto":2075.63,"total":13000}]', 59800.00, 5980.00, 48831.93, 4988.07, 53820.00, 'Emitido'),
  (10, 'KR-00000010', 'KR', 10, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-07 16:10:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"CC","documento":"52887431","nombre":"Laura Gómez","telefono":"3115582074","correo":"laura.gomez@correo.demo","direccion":"Cl. 134 # 55-20, Suba"}', '[{"sku":"ARE-REL","nombre":"Arepa rellena","cantidad":5,"precio_unitario":3900,"iva_porcentaje":8,"subtotal":18055.56,"impuesto":1444.44,"total":19500},{"sku":"BEB-AGU","nombre":"Agua 600 ml","cantidad":5,"precio_unitario":2000,"iva_porcentaje":19,"subtotal":8403.36,"impuesto":1596.64,"total":10000}]', 29500.00, 0.00, 26458.92, 3041.08, 29500.00, 'Emitido'),
  (11, 'KR-00000011', 'KR', 11, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-08 11:05:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NA","documento":"","nombre":"Consumidor final","telefono":"","correo":"","direccion":""}', '[{"sku":"CAR-CAR","nombre":"Carimañola","cantidad":6,"precio_unitario":4500,"iva_porcentaje":8,"subtotal":25000,"impuesto":2000,"total":27000},{"sku":"BEB-GPE","nombre":"Gaseosa personal","cantidad":6,"precio_unitario":3000,"iva_porcentaje":19,"subtotal":15126.05,"impuesto":2873.95,"total":18000}]', 45000.00, 0.00, 40126.05, 4873.95, 45000.00, 'Emitido'),
  (12, 'KR-00000012', 'KR', 12, 'Factura de venta', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-08 18:30:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NIT","documento":"830.077.441-2","nombre":"Inversiones La Esquina S.A.S.","telefono":"6017442210","correo":"compras@laesquina.demo","direccion":"Cl. 140 # 60-11, Bogotá D.C."}', '[{"sku":"COM-FAM","nombre":"Combo familiar","cantidad":3,"precio_unitario":30000,"iva_porcentaje":8,"subtotal":83333.33,"impuesto":6666.67,"total":90000}]', 90000.00, 5000.00, 78703.70, 6296.30, 85000.00, 'Emitido'),
  (13, 'KR-00000013', 'KR', 13, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-09 12:15:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"CC","documento":"1019887654","nombre":"Camila Torres","telefono":"3126654890","correo":"camila.torres@correo.demo","direccion":"Cra. 92 # 147-30, Suba"}', '[{"sku":"EMP-HAW","nombre":"Empanada hawaiana","cantidad":7,"precio_unitario":4200,"iva_porcentaje":8,"subtotal":27222.22,"impuesto":2177.78,"total":29400},{"sku":"BEB-JUG","nombre":"Jugo natural","cantidad":4,"precio_unitario":4000,"iva_porcentaje":19,"subtotal":13445.38,"impuesto":2554.62,"total":16000}]', 45400.00, 6810.00, 34567.46, 4022.54, 38590.00, 'Emitido'),
  (14, 'KR-00000014', 'KR', 14, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-09 17:50:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NA","documento":"","nombre":"Consumidor final","telefono":"","correo":"","direccion":""}', '[{"sku":"ARE-HUE","nombre":"Arepa e\' Huevo","cantidad":5,"precio_unitario":5500,"iva_porcentaje":8,"subtotal":25462.96,"impuesto":2037.04,"total":27500},{"sku":"BEB-G2L","nombre":"Gaseosa 2 L","cantidad":2,"precio_unitario":6500,"iva_porcentaje":19,"subtotal":10924.37,"impuesto":2075.63,"total":13000}]', 40500.00, 0.00, 36387.33, 4112.67, 40500.00, 'Emitido'),
  (15, 'KR-00000015', 'KR', 15, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-10 10:20:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"CC","documento":"80114522","nombre":"Andrés Rojas","telefono":"3004471189","correo":"andres.rojas@correo.demo","direccion":"Cra. 58 # 128-40, Suba"}', '[{"sku":"EMP-CAR","nombre":"Empanada de carne","cantidad":15,"precio_unitario":3900,"iva_porcentaje":8,"subtotal":54166.67,"impuesto":4333.33,"total":58500},{"sku":"EMP-POL","nombre":"Empanada de pollo","cantidad":10,"precio_unitario":3900,"iva_porcentaje":8,"subtotal":36111.11,"impuesto":2888.89,"total":39000},{"sku":"BEB-G2L","nombre":"Gaseosa 2 L","cantidad":3,"precio_unitario":6500,"iva_porcentaje":19,"subtotal":16386.55,"impuesto":3113.45,"total":19500}]', 117000.00, 11700.00, 95997.90, 9302.10, 105300.00, 'Emitido'),
  (16, 'KR-00000016', 'KR', 16, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-10 13:05:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NA","documento":"","nombre":"Consumidor final","telefono":"","correo":"","direccion":""}', '[{"sku":"PAP-REL","nombre":"Papa rellena","cantidad":8,"precio_unitario":5000,"iva_porcentaje":8,"subtotal":37037.04,"impuesto":2962.96,"total":40000},{"sku":"BEB-GPE","nombre":"Gaseosa personal","cantidad":8,"precio_unitario":3000,"iva_porcentaje":19,"subtotal":20168.07,"impuesto":3831.93,"total":24000}]', 64000.00, 0.00, 57205.11, 6794.89, 64000.00, 'Emitido'),
  (17, 'KR-00000017', 'KR', 17, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-11 11:44:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"CC","documento":"79554120","nombre":"Juan Pablo Díaz","telefono":"3187745521","correo":"jp.diaz@correo.demo","direccion":"Cl. 127 # 45-18, Bogotá D.C."}', '[{"sku":"PAS-YUC","nombre":"Pastel de yuca","cantidad":10,"precio_unitario":4000,"iva_porcentaje":8,"subtotal":37037.04,"impuesto":2962.96,"total":40000},{"sku":"BEB-AGU","nombre":"Agua 600 ml","cantidad":6,"precio_unitario":2000,"iva_porcentaje":19,"subtotal":10084.03,"impuesto":1915.97,"total":12000}]', 52000.00, 0.00, 47121.07, 4878.93, 52000.00, 'Emitido'),
  (18, 'KR-00000018', 'KR', 18, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-11 16:25:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NA","documento":"","nombre":"Consumidor final","telefono":"","correo":"","direccion":""}', '[{"sku":"ARE-REL","nombre":"Arepa rellena","cantidad":9,"precio_unitario":3900,"iva_porcentaje":8,"subtotal":32500,"impuesto":2600,"total":35100},{"sku":"BEB-JUG","nombre":"Jugo natural","cantidad":5,"precio_unitario":4000,"iva_porcentaje":19,"subtotal":16806.72,"impuesto":3193.28,"total":20000}]', 55100.00, 8265.00, 41910.71, 4924.29, 46835.00, 'Emitido'),
  (19, 'KR-00000019', 'KR', 19, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-12 10:08:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"CC","documento":"52887431","nombre":"Laura Gómez","telefono":"3115582074","correo":"laura.gomez@correo.demo","direccion":"Cl. 134 # 55-20, Suba"}', '[{"sku":"EMP-CAR","nombre":"Empanada de carne","cantidad":20,"precio_unitario":3900,"iva_porcentaje":8,"subtotal":72222.22,"impuesto":5777.78,"total":78000},{"sku":"BEB-G2L","nombre":"Gaseosa 2 L","cantidad":4,"precio_unitario":6500,"iva_porcentaje":19,"subtotal":21848.74,"impuesto":4151.26,"total":26000}]', 104000.00, 10400.00, 84663.86, 8936.14, 93600.00, 'Emitido'),
  (20, 'KR-00000020', 'KR', 20, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-12 15:35:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NA","documento":"","nombre":"Consumidor final","telefono":"","correo":"","direccion":""}', '[{"sku":"CAR-CAR","nombre":"Carimañola","cantidad":9,"precio_unitario":4500,"iva_porcentaje":8,"subtotal":37500,"impuesto":3000,"total":40500},{"sku":"BEB-GPE","nombre":"Gaseosa personal","cantidad":9,"precio_unitario":3000,"iva_porcentaje":19,"subtotal":22689.08,"impuesto":4310.92,"total":27000}]', 67500.00, 0.00, 60189.08, 7310.92, 67500.00, 'Emitido'),
  (21, 'KR-00000021', 'KR', 21, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-13 11:50:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"CC","documento":"1019887654","nombre":"Camila Torres","telefono":"3126654890","correo":"camila.torres@correo.demo","direccion":"Cra. 92 # 147-30, Suba"}', '[{"sku":"ARE-HUE","nombre":"Arepa e\' Huevo","cantidad":8,"precio_unitario":5500,"iva_porcentaje":8,"subtotal":40740.74,"impuesto":3259.26,"total":44000},{"sku":"BEB-AGU","nombre":"Agua 600 ml","cantidad":8,"precio_unitario":2000,"iva_porcentaje":19,"subtotal":13445.38,"impuesto":2554.62,"total":16000}]', 60000.00, 0.00, 54186.12, 5813.88, 60000.00, 'Emitido'),
  (22, 'KR-00000022', 'KR', 22, 'Tirilla POS', 'Resolución DIAN 18764003456789 del 2026-01-15, prefijo KR, rango 1 al 50000', '2026-09-13 17:15:00', '{"nombre":"Ke-Rico!","razon_social":"Ke-Rico! S.A.S.","nit":"901.456.789-3","direccion":"Cra. 59 # 132A - 7, Suba, Bogotá D.C.","telefono":"(+57) 317 796 5569","ciudad":"Bogotá D.C."}', '{"tipo_documento":"NA","documento":"","nombre":"Consumidor final","telefono":"","correo":"","direccion":""}', '[{"sku":"EMP-QUE","nombre":"Empanada de queso","cantidad":14,"precio_unitario":3500,"iva_porcentaje":8,"subtotal":45370.37,"impuesto":3629.63,"total":49000},{"sku":"BEB-JUG","nombre":"Jugo natural","cantidad":6,"precio_unitario":4000,"iva_porcentaje":19,"subtotal":20168.07,"impuesto":3831.93,"total":24000}]', 73000.00, 10950.00, 55707.67, 6342.33, 62050.00, 'Emitido');

UPDATE configuracion SET valor = '23' WHERE clave = 'comprobante_consecutivo';

INSERT INTO movimientos_inventario (producto_id, tipo, motivo, cantidad, stock_anterior, stock_nuevo, costo_unitario, referencia_tipo, referencia_id, usuario_id, observaciones, creado_en) VALUES
  (1, 'Entrada', 'Carga inicial de inventario', 320.000, 0.000, 320.000, 1400.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (2, 'Entrada', 'Carga inicial de inventario', 260.000, 0.000, 260.000, 1350.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (3, 'Entrada', 'Carga inicial de inventario', 150.000, 0.000, 150.000, 1200.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (4, 'Entrada', 'Carga inicial de inventario', 90.000, 0.000, 90.000, 1500.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (5, 'Entrada', 'Carga inicial de inventario', 140.000, 0.000, 140.000, 1600.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (6, 'Entrada', 'Carga inicial de inventario', 110.000, 0.000, 110.000, 2100.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (7, 'Entrada', 'Carga inicial de inventario', 120.000, 0.000, 120.000, 1900.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (8, 'Entrada', 'Carga inicial de inventario', 80.000, 0.000, 80.000, 1500.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (9, 'Entrada', 'Carga inicial de inventario', 60.000, 0.000, 60.000, 1700.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (10, 'Entrada', 'Carga inicial de inventario', 40.000, 0.000, 40.000, 4200.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (11, 'Entrada', 'Carga inicial de inventario', 90.000, 0.000, 90.000, 1800.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (12, 'Entrada', 'Carga inicial de inventario', 70.000, 0.000, 70.000, 1500.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (13, 'Entrada', 'Carga inicial de inventario', 110.000, 0.000, 110.000, 900.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (14, 'Entrada', 'Carga inicial de inventario', 40.000, 0.000, 40.000, 12000.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (15, 'Entrada', 'Carga inicial de inventario', 60.000, 0.000, 60.000, 4100.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (16, 'Entrada', 'Carga inicial de inventario', 40.000, 0.000, 40.000, 18500.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (17, 'Entrada', 'Carga inicial de inventario', 35.000, 0.000, 35.000, 14200.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (18, 'Entrada', 'Carga inicial de inventario', 25.000, 0.000, 25.000, 12800.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (19, 'Entrada', 'Carga inicial de inventario', 45.000, 0.000, 45.000, 9500.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (20, 'Entrada', 'Carga inicial de inventario', 60.000, 0.000, 60.000, 2800.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (21, 'Entrada', 'Carga inicial de inventario', 80.000, 0.000, 80.000, 2400.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (22, 'Entrada', 'Carga inicial de inventario', 300.000, 0.000, 300.000, 650.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (23, 'Entrada', 'Carga inicial de inventario', 600.000, 0.000, 600.000, 180.00, 'Inicial', NULL, 4, 'Inventario inicial registrado en la puesta en marcha del sistema', '2026-09-01 07:00:00'),
  (15, 'Entrada', 'Compra OC-00001', 40.000, 60.000, 100.000, 4100.00, 'Compra', 1, 4, 'Ingreso de mercancía del proveedor', '2026-09-02 06:00:00'),
  (5, 'Entrada', 'Compra OC-00001', 60.000, 140.000, 200.000, 1600.00, 'Compra', 1, 4, 'Ingreso de mercancía del proveedor', '2026-09-02 06:00:00'),
  (6, 'Entrada', 'Compra OC-00001', 50.000, 110.000, 160.000, 2100.00, 'Compra', 1, 4, 'Ingreso de mercancía del proveedor', '2026-09-02 06:00:00'),
  (1, 'Salida', 'Venta V-000001', 4.000, 320.000, 316.000, 1400.00, 'Venta', 1, 3, 'Salida automática por venta', '2026-09-02 11:12:00'),
  (11, 'Salida', 'Venta V-000001', 2.000, 138.000, 136.000, 1800.00, 'Venta', 1, 3, 'Salida automática por venta', '2026-09-02 11:12:00'),
  (7, 'Salida', 'Venta V-000002', 4.000, 180.000, 176.000, 1900.00, 'Venta', 2, 3, 'Salida automática por venta', '2026-09-02 13:40:00'),
  (10, 'Salida', 'Venta V-000002', 1.000, 64.000, 63.000, 4200.00, 'Venta', 2, 3, 'Salida automática por venta', '2026-09-02 13:40:00'),
  (20, 'Entrada', 'Compra OC-00002', 30.000, 60.000, 90.000, 2800.00, 'Compra', 2, 4, 'Ingreso de mercancía del proveedor', '2026-09-03 06:00:00'),
  (21, 'Entrada', 'Compra OC-00002', 40.000, 80.000, 120.000, 2400.00, 'Compra', 2, 4, 'Ingreso de mercancía del proveedor', '2026-09-03 06:00:00'),
  (7, 'Entrada', 'Compra OC-00002', 60.000, 120.000, 180.000, 1900.00, 'Compra', 2, 4, 'Ingreso de mercancía del proveedor', '2026-09-03 06:00:00'),
  (1, 'Salida', 'Venta V-000003', 10.000, 316.000, 306.000, 1400.00, 'Venta', 3, 3, 'Salida automática por venta', '2026-09-03 10:05:00'),
  (10, 'Salida', 'Venta V-000003', 1.000, 63.000, 62.000, 4200.00, 'Venta', 3, 3, 'Salida automática por venta', '2026-09-03 10:05:00'),
  (2, 'Salida', 'Venta V-000004', 6.000, 260.000, 254.000, 1350.00, 'Venta', 4, 3, 'Salida automática por venta', '2026-09-03 12:55:00'),
  (12, 'Salida', 'Venta V-000004', 3.000, 70.000, 67.000, 1500.00, 'Venta', 4, 3, 'Salida automática por venta', '2026-09-03 12:55:00'),
  (16, 'Entrada', 'Compra OC-00003', 25.000, 40.000, 65.000, 18500.00, 'Compra', 3, 4, 'Ingreso de mercancía del proveedor', '2026-09-04 07:00:00'),
  (6, 'Salida', 'Venta V-000005', 3.000, 160.000, 157.000, 2100.00, 'Venta', 5, 3, 'Salida automática por venta', '2026-09-04 11:30:00'),
  (13, 'Salida', 'Venta V-000005', 3.000, 110.000, 107.000, 900.00, 'Venta', 5, 3, 'Salida automática por venta', '2026-09-04 11:30:00'),
  (14, 'Salida', 'Venta V-000006', 2.000, 40.000, 38.000, 12000.00, 'Venta', 6, 3, 'Salida automática por venta', '2026-09-04 15:18:00'),
  (10, 'Entrada', 'Compra OC-00004', 24.000, 40.000, 64.000, 4200.00, 'Compra', 4, 4, 'Ingreso de mercancía del proveedor', '2026-09-05 06:00:00'),
  (11, 'Entrada', 'Compra OC-00004', 48.000, 90.000, 138.000, 1800.00, 'Compra', 4, 4, 'Ingreso de mercancía del proveedor', '2026-09-05 06:00:00'),
  (3, 'Salida', 'Venta V-000007', 8.000, 150.000, 142.000, 1200.00, 'Venta', 7, 3, 'Salida automática por venta', '2026-09-05 09:45:00'),
  (11, 'Salida', 'Venta V-000007', 4.000, 136.000, 132.000, 1800.00, 'Venta', 7, 3, 'Salida automática por venta', '2026-09-05 09:45:00'),
  (8, 'Salida', 'Venta V-000008', 6.000, 80.000, 74.000, 1500.00, 'Venta', 8, 3, 'Salida automática por venta', '2026-09-05 14:22:00'),
  (12, 'Salida', 'Venta V-000008', 2.000, 67.000, 65.000, 1500.00, 'Venta', 8, 3, 'Salida automática por venta', '2026-09-05 14:22:00'),
  (1, 'Merma', 'Producto quemado en freidora', 18.000, 259.000, 241.000, 1400.00, 'Ajuste', NULL, 4, 'Lote perdido por exceso de temperatura', '2026-09-06 20:00:00'),
  (17, 'Entrada', 'Compra OC-00005', 20.000, 35.000, 55.000, 14200.00, 'Compra', 5, 4, 'Ingreso de mercancía del proveedor', '2026-09-07 06:00:00'),
  (1, 'Salida', 'Venta V-000009', 12.000, 306.000, 294.000, 1400.00, 'Venta', 9, 3, 'Salida automática por venta', '2026-09-07 10:40:00'),
  (10, 'Salida', 'Venta V-000009', 2.000, 62.000, 60.000, 4200.00, 'Venta', 9, 3, 'Salida automática por venta', '2026-09-07 10:40:00'),
  (5, 'Salida', 'Venta V-000010', 5.000, 200.000, 195.000, 1600.00, 'Venta', 10, 3, 'Salida automática por venta', '2026-09-07 16:10:00'),
  (13, 'Salida', 'Venta V-000010', 5.000, 107.000, 102.000, 900.00, 'Venta', 10, 3, 'Salida automática por venta', '2026-09-07 16:10:00'),
  (19, 'Entrada', 'Compra OC-00006', 20.000, 45.000, 65.000, 9500.00, 'Compra', 6, 4, 'Ingreso de mercancía del proveedor', '2026-09-08 07:00:00'),
  (22, 'Entrada', 'Compra OC-00006', 180.000, 300.000, 480.000, 650.00, 'Compra', 6, 4, 'Ingreso de mercancía del proveedor', '2026-09-08 07:00:00'),
  (23, 'Entrada', 'Compra OC-00006', 400.000, 600.000, 1000.000, 180.00, 'Compra', 6, 4, 'Ingreso de mercancía del proveedor', '2026-09-08 07:00:00'),
  (9, 'Salida', 'Venta V-000011', 6.000, 60.000, 54.000, 1700.00, 'Venta', 11, 3, 'Salida automática por venta', '2026-09-08 11:05:00'),
  (11, 'Salida', 'Venta V-000011', 6.000, 132.000, 126.000, 1800.00, 'Venta', 11, 3, 'Salida automática por venta', '2026-09-08 11:05:00'),
  (14, 'Salida', 'Venta V-000012', 3.000, 38.000, 35.000, 12000.00, 'Venta', 12, 3, 'Salida automática por venta', '2026-09-08 18:30:00'),
  (18, 'Entrada', 'Compra OC-00007', 15.000, 25.000, 40.000, 12800.00, 'Compra', 7, 4, 'Ingreso de mercancía del proveedor', '2026-09-09 06:00:00'),
  (4, 'Salida', 'Venta V-000013', 7.000, 90.000, 83.000, 1500.00, 'Venta', 13, 3, 'Salida automática por venta', '2026-09-09 12:15:00'),
  (12, 'Salida', 'Venta V-000013', 4.000, 65.000, 61.000, 1500.00, 'Venta', 13, 3, 'Salida automática por venta', '2026-09-09 12:15:00'),
  (6, 'Salida', 'Venta V-000014', 5.000, 157.000, 152.000, 2100.00, 'Venta', 14, 3, 'Salida automática por venta', '2026-09-09 17:50:00'),
  (10, 'Salida', 'Venta V-000014', 2.000, 60.000, 58.000, 4200.00, 'Venta', 14, 3, 'Salida automática por venta', '2026-09-09 17:50:00'),
  (15, 'Merma', 'Masa vencida', 6.000, 100.000, 94.000, 4100.00, 'Ajuste', NULL, 4, 'Masa fuera de fecha de uso', '2026-09-09 21:00:00'),
  (1, 'Salida', 'Venta V-000015', 15.000, 294.000, 279.000, 1400.00, 'Venta', 15, 3, 'Salida automática por venta', '2026-09-10 10:20:00'),
  (2, 'Salida', 'Venta V-000015', 10.000, 254.000, 244.000, 1350.00, 'Venta', 15, 3, 'Salida automática por venta', '2026-09-10 10:20:00'),
  (10, 'Salida', 'Venta V-000015', 3.000, 58.000, 55.000, 4200.00, 'Venta', 15, 3, 'Salida automática por venta', '2026-09-10 10:20:00'),
  (7, 'Salida', 'Venta V-000016', 8.000, 176.000, 168.000, 1900.00, 'Venta', 16, 3, 'Salida automática por venta', '2026-09-10 13:05:00'),
  (11, 'Salida', 'Venta V-000016', 8.000, 126.000, 118.000, 1800.00, 'Venta', 16, 3, 'Salida automática por venta', '2026-09-10 13:05:00'),
  (8, 'Salida', 'Venta V-000017', 10.000, 74.000, 64.000, 1500.00, 'Venta', 17, 3, 'Salida automática por venta', '2026-09-11 11:44:00'),
  (13, 'Salida', 'Venta V-000017', 6.000, 102.000, 96.000, 900.00, 'Venta', 17, 3, 'Salida automática por venta', '2026-09-11 11:44:00'),
  (5, 'Salida', 'Venta V-000018', 9.000, 195.000, 186.000, 1600.00, 'Venta', 18, 3, 'Salida automática por venta', '2026-09-11 16:25:00'),
  (12, 'Salida', 'Venta V-000018', 5.000, 61.000, 56.000, 1500.00, 'Venta', 18, 3, 'Salida automática por venta', '2026-09-11 16:25:00'),
  (8, 'Ajuste', 'Corrección de conteo físico', 4.000, 64.000, 60.000, 1500.00, 'Ajuste', NULL, 4, 'Diferencia detectada en conteo de cierre', '2026-09-11 20:00:00'),
  (1, 'Salida', 'Venta V-000019', 20.000, 279.000, 259.000, 1400.00, 'Venta', 19, 3, 'Salida automática por venta', '2026-09-12 10:08:00'),
  (10, 'Salida', 'Venta V-000019', 4.000, 55.000, 51.000, 4200.00, 'Venta', 19, 3, 'Salida automática por venta', '2026-09-12 10:08:00'),
  (9, 'Salida', 'Venta V-000020', 9.000, 54.000, 45.000, 1700.00, 'Venta', 20, 3, 'Salida automática por venta', '2026-09-12 15:35:00'),
  (11, 'Salida', 'Venta V-000020', 9.000, 118.000, 109.000, 1800.00, 'Venta', 20, 3, 'Salida automática por venta', '2026-09-12 15:35:00'),
  (3, 'Merma', 'Caída de bandeja', 9.000, 128.000, 119.000, 1200.00, 'Ajuste', NULL, 4, 'Unidades no aptas para venta', '2026-09-12 21:00:00'),
  (6, 'Salida', 'Venta V-000021', 8.000, 152.000, 144.000, 2100.00, 'Venta', 21, 3, 'Salida automática por venta', '2026-09-13 11:50:00'),
  (13, 'Salida', 'Venta V-000021', 8.000, 96.000, 88.000, 900.00, 'Venta', 21, 3, 'Salida automática por venta', '2026-09-13 11:50:00'),
  (3, 'Salida', 'Venta V-000022', 14.000, 142.000, 128.000, 1200.00, 'Venta', 22, 3, 'Salida automática por venta', '2026-09-13 17:15:00'),
  (12, 'Salida', 'Venta V-000022', 6.000, 56.000, 50.000, 1500.00, 'Venta', 22, 3, 'Salida automática por venta', '2026-09-13 17:15:00'),
  (18, 'Merma', 'Queso en mal estado', 3.000, 40.000, 37.000, 12800.00, 'Ajuste', NULL, 4, 'Cadena de frío interrumpida', '2026-09-13 20:00:00'),
  (1, 'Salida', 'Conteo físico de cierre', 1.000, 241.000, 240.000, 1400.00, 'Ajuste', NULL, 4, 'Cierre de jornada, conteo unidad por unidad', '2026-09-13 21:00:00'),
  (5, 'Salida', 'Conteo físico de cierre', 100.000, 186.000, 86.000, 1600.00, 'Ajuste', NULL, 4, 'Cierre de jornada, conteo unidad por unidad', '2026-09-13 21:00:00'),
  (4, 'Salida', 'Consumo de servicio no registrado', 47.000, 83.000, 36.000, 1500.00, 'Ajuste', NULL, 4, 'Unidades despachadas en el turno de la tarde', '2026-09-13 21:00:00'),
  (8, 'Salida', 'Consumo de servicio no registrado', 34.000, 60.000, 26.000, 1500.00, 'Ajuste', NULL, 4, 'Unidades despachadas en el turno de la tarde', '2026-09-13 21:00:00'),
  (9, 'Salida', 'Consumo de servicio no registrado', 35.000, 45.000, 10.000, 1700.00, 'Ajuste', NULL, 4, 'Unidades despachadas en el turno de la tarde', '2026-09-13 21:00:00'),
  (10, 'Salida', 'Consumo de servicio no registrado', 51.000, 51.000, 0.000, 4200.00, 'Ajuste', NULL, 4, 'Se agotaron las botellas de dos litros en el turno de la tarde', '2026-09-13 21:00:00'),
  (15, 'Salida', 'Consumo de producción', 86.000, 94.000, 8.000, 4100.00, 'Ajuste', NULL, 4, 'Masa utilizada en la producción del día', '2026-09-13 21:00:00'),
  (18, 'Salida', 'Consumo de producción', 28.000, 37.000, 9.000, 12800.00, 'Ajuste', NULL, 4, 'Queso utilizado en la producción del día', '2026-09-13 21:00:00'),
  (19, 'Salida', 'Consumo de producción', 48.000, 65.000, 17.000, 9500.00, 'Ajuste', NULL, 4, 'Recambio de aceite de las freidoras', '2026-09-13 21:00:00');

UPDATE productos SET stock_actual = CASE id
  WHEN 1 THEN 240.000
  WHEN 2 THEN 244.000
  WHEN 3 THEN 119.000
  WHEN 4 THEN 36.000
  WHEN 5 THEN 86.000
  WHEN 6 THEN 144.000
  WHEN 7 THEN 168.000
  WHEN 8 THEN 26.000
  WHEN 9 THEN 10.000
  WHEN 10 THEN 0.000
  WHEN 11 THEN 109.000
  WHEN 12 THEN 50.000
  WHEN 13 THEN 88.000
  WHEN 14 THEN 35.000
  WHEN 15 THEN 8.000
  WHEN 16 THEN 65.000
  WHEN 17 THEN 55.000
  WHEN 18 THEN 9.000
  WHEN 19 THEN 17.000
  WHEN 20 THEN 90.000
  WHEN 21 THEN 120.000
  WHEN 22 THEN 480.000
  WHEN 23 THEN 1000.000
END WHERE id BETWEEN 1 AND 23;

INSERT INTO alertas_stock (producto_id, tipo, stock_en_alerta, punto_reorden, mensaje, estado, generada_en) VALUES
  (4, 'Bajo', 36.000, 40.000, 'Empanada hawaiana esta en 36.000 unidad frente a un punto de reorden de 40.000 unidad.', 'Pendiente', '2026-09-13 21:30:00'),
  (8, 'Bajo', 26.000, 30.000, 'Pastel de yuca esta en 26.000 unidad frente a un punto de reorden de 30.000 unidad.', 'Pendiente', '2026-09-13 21:30:00'),
  (9, 'Critico', 10.000, 25.000, 'Carimañola esta en 10.000 unidad frente a un punto de reorden de 25.000 unidad.', 'Pendiente', '2026-09-13 21:30:00'),
  (10, 'Agotado', 0.000, 12.000, 'Gaseosa 2 L se quedo sin existencias. Reponer de inmediato.', 'Pendiente', '2026-09-13 21:30:00'),
  (15, 'Critico', 8.000, 25.000, 'Masa de maíz peto esta en 8.000 kg frente a un punto de reorden de 25.000 kg.', 'Pendiente', '2026-09-13 21:30:00'),
  (18, 'Bajo', 9.000, 10.000, 'Queso campesino esta en 9.000 kg frente a un punto de reorden de 10.000 kg.', 'Pendiente', '2026-09-13 21:30:00'),
  (19, 'Bajo', 17.000, 20.000, 'Aceite de girasol esta en 17.000 litro frente a un punto de reorden de 20.000 litro.', 'Pendiente', '2026-09-13 21:30:00');

INSERT INTO bitacora (usuario_id, usuario_nombre, accion, modulo, descripcion, nivel, entidad, entidad_id, ip, user_agent, creado_en) VALUES
  (1, 'Juan Steban Peña Cárdenas', 'INSTALACION', 'Sistema', 'Base de datos inicializada con datos de arranque', 'Sistema', 'sistema', '', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-01 07:00:00'),
  (4, 'Esteban Torres', 'INVENTARIO_CARGA', 'Inventario', 'Carga inicial de 23 productos en el inventario', 'Info', 'productos', '', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-01 07:05:00'),
  (4, 'Esteban Torres', 'COMPRA_REGISTRAR', 'Compras', 'Compra OC-00001 recibida del proveedor Masa y Arepa Malagón', 'Info', 'compras', '1', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-02 06:00:00'),
  (3, 'Andrés Rodríguez', 'LOGIN', 'Autenticacion', 'Inicio de sesión correcto', 'Info', 'usuarios', '3', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-02 10:55:00'),
  (3, 'Andrés Rodríguez', 'VENTA_REGISTRAR', 'Ventas', 'Venta V-000001 registrada por 21.600', 'Info', 'ventas', '1', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-02 11:12:00'),
  (3, 'Andrés Rodríguez', 'DESCUENTO_APLICAR', 'Descuentos', 'Descuento BIENVENIDO10 aplicado en la venta V-000003', 'Info', 'ventas', '3', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-03 10:05:00'),
  (4, 'Esteban Torres', 'INVENTARIO_MERMA', 'Inventario', 'Merma de 18 unidades de Empanada de carne por quemado en freidora', 'Advertencia', 'productos', '1', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-06 20:00:00'),
  (2, 'Cristian David Molano Pérez', 'LOGIN', 'Autenticacion', 'Inicio de sesión correcto', 'Info', 'usuarios', '2', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-07 08:30:00'),
  (2, 'Cristian David Molano Pérez', 'REPORTE_GENERAR', 'Reportes', 'Reporte de rentabilidad del periodo generado', 'Info', 'reportes', '', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-07 09:00:00'),
  (1, 'Juan Steban Peña Cárdenas', 'PRECIO_MODIFICAR', 'Inventario', 'Precio de venta de Empanada hawaiana ajustado a 4.200', 'Advertencia', 'productos', '4', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-08 09:15:00'),
  (3, 'Andrés Rodríguez', 'LOGIN_FALLIDO', 'Autenticacion', 'Intento de inicio de sesión fallido para el usuario cajero', 'Error', 'usuarios', '3', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-09 08:02:00'),
  (3, 'Andrés Rodríguez', 'LOGIN', 'Autenticacion', 'Inicio de sesión correcto', 'Info', 'usuarios', '3', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-09 08:04:00'),
  (4, 'Esteban Torres', 'ALERTA_STOCK', 'Alertas', 'Masa de maíz peto alcanzó el nivel crítico de existencias', 'Advertencia', 'productos', '15', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-09 21:10:00'),
  (1, 'Juan Steban Peña Cárdenas', 'RESPALDO', 'Sistema', 'Respaldo automático de la base de datos completado', 'Sistema', 'respaldos', '1', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-12 02:00:00'),
  (4, 'Esteban Torres', 'INVENTARIO_AJUSTE', 'Inventario', 'Ajuste de 4 unidades en Pastel de yuca por corrección de conteo', 'Info', 'productos', '8', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-11 20:00:00'),
  (2, 'Cristian David Molano Pérez', 'PROVEEDOR_ESTADO', 'Proveedores', 'Distribuidora Ibérica pasa a estado En revisión', 'Advertencia', 'proveedores', '8', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-12 10:30:00'),
  (3, 'Andrés Rodríguez', 'COMPROBANTE_EMITIR', 'Comprobantes', 'Comprobante KR-00000019 emitido para la venta V-000019', 'Info', 'comprobantes', '19', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-12 10:08:00'),
  (1, 'Juan Steban Peña Cárdenas', 'SESION_EXPIRADA', 'Autenticacion', 'Sesión cerrada automáticamente por 15 minutos de inactividad', 'Sistema', 'sesiones', '', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-13 12:00:00'),
  (4, 'Esteban Torres', 'ALERTA_STOCK', 'Alertas', 'Gaseosa 2 L quedó sin existencias', 'Error', 'productos', '10', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-13 18:00:00'),
  (1, 'Juan Steban Peña Cárdenas', 'RESPALDO', 'Sistema', 'Respaldo automático de la base de datos completado', 'Sistema', 'respaldos', '2', '127.0.0.1', 'Ke-Rico Sistema', '2026-09-13 02:00:00');

INSERT INTO respaldos (nombre_archivo, ruta, tamano_bytes, tipo, estado, iniciado_en, finalizado_en, mensaje) VALUES
  ('kerico-20260912-020000.sql', 'respaldos/kerico-20260912-020000.sql', 486233, 'Automatico', 'Completado', '2026-09-12 02:00:00', '2026-09-12 02:01:00', 'Respaldo automático de 24 horas'),
  ('kerico-20260913-020000.sql', 'respaldos/kerico-20260913-020000.sql', 502118, 'Automatico', 'Completado', '2026-09-13 02:00:00', '2026-09-13 02:01:00', 'Respaldo automático de 24 horas');

