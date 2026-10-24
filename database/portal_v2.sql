-- =====================================================================
-- Portal Ferretería La Montaña — actualización v2
-- Agrega: catálogo público, clientes, ventas, inventario con movimientos,
-- rol de bodega, correo de usuarios y recuperación de contraseña.
-- Ejecutar UNA vez en phpMyAdmin (pestaña Importar) con el usuario root.
-- Es seguro repetirlo: no borra datos.
-- =====================================================================
USE portal_db;

-- Usuarios: correo y nuevo rol de bodega
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS correo VARCHAR(150) NULL AFTER nombre,
  MODIFY rol ENUM('admin','empleado','bodega') NOT NULL DEFAULT 'empleado';
UPDATE usuarios SET correo='admin@ferreterialamontana.test' WHERE usuario='admin' AND correo IS NULL;
UPDATE usuarios SET correo='jlopez@ferreterialamontana.test' WHERE usuario='jlopez' AND correo IS NULL;
UPDATE usuarios SET correo='mramirez@ferreterialamontana.test', rol='bodega' WHERE usuario='mramirez';

-- Productos: descripción y estado
ALTER TABLE productos
  ADD COLUMN IF NOT EXISTS descripcion VARCHAR(400) NULL AFTER nombre,
  ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS actualizado DATETIME NULL;

UPDATE productos SET descripcion='Cabeza de acero forjado y mango de fibra de vidrio antivibración.' WHERE codigo='HER-001' AND descripcion IS NULL;
UPDATE productos SET descripcion='Punta magnética, mango ergonómico de dos materiales.' WHERE codigo='HER-002' AND descripcion IS NULL;
UPDATE productos SET descripcion='Acero cromo vanadio, apertura máxima de 30 mm.' WHERE codigo='HER-003' AND descripcion IS NULL;
UPDATE productos SET descripcion='Cinta de acero con freno y gancho reforzado.' WHERE codigo='HER-004' AND descripcion IS NULL;
UPDATE productos SET descripcion='Marco ajustable para hojas de 10 y 12 pulgadas.' WHERE codigo='HER-005' AND descripcion IS NULL;
UPDATE productos SET descripcion='Conductor de cobre con aislamiento THHN, se vende por metro.' WHERE codigo='ELE-010' AND descripcion IS NULL;
UPDATE productos SET descripcion='Toma doble de 15 A con tierra, color marfil.' WHERE codigo='ELE-011' AND descripcion IS NULL;
UPDATE productos SET descripcion='Interruptor de 15 A para caja rectangular.' WHERE codigo='ELE-012' AND descripcion IS NULL;
UPDATE productos SET descripcion='Luz blanca, rosca E27, equivalente a 60 W.' WHERE codigo='ELE-013' AND descripcion IS NULL;
UPDATE productos SET descripcion='Tubo para agua potable, cédula 40.' WHERE codigo='PLO-005' AND descripcion IS NULL;
UPDATE productos SET descripcion='Unión a 90 grados para tubo de media pulgada.' WHERE codigo='PLO-006' AND descripcion IS NULL;
UPDATE productos SET descripcion='Llave de bronce con rosca para manguera.' WHERE codigo='PLO-007' AND descripcion IS NULL;
UPDATE productos SET descripcion='Látex lavable para interiores, rendimiento 35 m² por galón.' WHERE codigo='PIN-020' AND descripcion IS NULL;
UPDATE productos SET descripcion='Cerda natural para pintura de aceite o látex.' WHERE codigo='PIN-021' AND descripcion IS NULL;
UPDATE productos SET descripcion='Saco de cemento de uso general.' WHERE codigo='CON-030' AND descripcion IS NULL;
UPDATE productos SET descripcion='Varilla corrugada legítima grado 40.' WHERE codigo='CON-031' AND descripcion IS NULL;

INSERT IGNORE INTO productos(codigo,nombre,descripcion,categoria,precio,existencia,minimo) VALUES
 ('HER-006','Taladro percutor 1/2" 650 W','Velocidad variable y reversa, incluye maletín.','Herramientas',485.00,6,2),
 ('HER-007','Juego de llaves combinadas 12 piezas','Medidas de 8 a 19 mm en estuche.','Herramientas',165.00,10,3),
 ('ELE-014','Caja rectangular metálica','Para interruptores y tomacorrientes.','Electricidad',4.50,200,40),
 ('ELE-015','Cinta de aislar 3M','Rollo de 18 metros, color negro.','Electricidad',9.00,85,20),
 ('PLO-008','Pegamento para PVC 1/4 galón','Secado rápido, para agua fría.','Plomería',58.00,14,5),
 ('PLO-009','Sifón para lavamanos','Plástico con tapón de registro.','Plomería',26.00,22,6),
 ('PIN-022','Rodillo 9" con bandeja','Felpa de 3/8 para superficies lisas.','Pintura',48.00,18,5),
 ('PIN-023','Thinner galón','Para limpieza y dilución de pinturas de aceite.','Pintura',62.00,9,4),
 ('CON-032','Block de 15x20x40 cm','Block de concreto para muro, precio por unidad.','Construcción',5.25,1200,300),
 ('CON-033','Arena de río (m³)','Arena lavada para mezcla y repello.','Construcción',210.00,8,3),
 ('SEG-040','Guantes de cuero','Talla única, puño de lona.','Seguridad',28.00,40,10),
 ('SEG-041','Lentes de protección','Policarbonato transparente antiempañante.','Seguridad',22.00,3,8);

-- Clientes
CREATE TABLE IF NOT EXISTS clientes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nit VARCHAR(20) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  telefono VARCHAR(30) NULL,
  correo VARCHAR(150) NULL,
  direccion VARCHAR(200) NULL,
  creado_por INT UNSIGNED NULL,
  creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cli_nit(nit),
  INDEX idx_cli_nombre(nombre)
) ENGINE=InnoDB;

INSERT INTO clientes(id,nit,nombre,telefono,direccion)
SELECT 1,'CF','Consumidor Final',NULL,'Ciudad' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM clientes WHERE id=1);
INSERT INTO clientes(nit,nombre,telefono,correo,direccion)
SELECT * FROM (SELECT '4582193-1','Construcciones Pérez, S.A.','7941-2201','compras@construperez.test','Barrio El Centro' ) t
WHERE NOT EXISTS (SELECT 1 FROM clientes WHERE nit='4582193-1');
INSERT INTO clientes(nit,nombre,telefono,correo,direccion)
SELECT * FROM (SELECT '7735021-8','Carlos Méndez','5512-8840',NULL,'Aldea San Juan' ) t
WHERE NOT EXISTS (SELECT 1 FROM clientes WHERE nit='7735021-8');

-- Ventas
CREATE TABLE IF NOT EXISTS ventas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(20) NULL UNIQUE,
  cliente_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado ENUM('emitida','anulada') NOT NULL DEFAULT 'emitida',
  anulada_por INT UNSIGNED NULL,
  anulada_motivo VARCHAR(200) NULL,
  anulada_en DATETIME NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_venta_fecha(creado),
  CONSTRAINT fk_venta_cliente FOREIGN KEY(cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_venta_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS venta_detalle (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  venta_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_det_venta FOREIGN KEY(venta_id) REFERENCES ventas(id),
  CONSTRAINT fk_det_producto FOREIGN KEY(producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

-- Movimientos de inventario (kárdex)
CREATE TABLE IF NOT EXISTS movimientos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producto_id INT UNSIGNED NOT NULL,
  tipo ENUM('entrada','venta','ajuste','anulacion') NOT NULL,
  cantidad INT NOT NULL,
  existencia_resultante INT NOT NULL,
  referencia VARCHAR(150) NULL,
  usuario_id INT UNSIGNED NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mov_prod(producto_id, creado),
  CONSTRAINT fk_mov_producto FOREIGN KEY(producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

-- Recuperación de contraseña (solo se guarda el hash del enlace)
CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expira DATETIME NOT NULL,
  usado DATETIME NULL,
  ip VARCHAR(45) NULL,
  creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reset_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Los permisos del usuario técnico siguen siendo SELECT, INSERT y UPDATE (sin DELETE):
-- ventas y productos nunca se borran, solo se anulan o se desactivan.
GRANT SELECT, INSERT, UPDATE ON portal_db.* TO 'portal_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON portal_db.* TO 'portal_app'@'127.0.0.1';
FLUSH PRIVILEGES;
