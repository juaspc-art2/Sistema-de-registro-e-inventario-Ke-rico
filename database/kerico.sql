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

SET FOREIGN_KEY_CHECKS = 1;
