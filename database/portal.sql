-- =====================================================================
-- Portal Ferretería La Montaña — base de datos del sitio monitoreado
-- Ejecutar UNA vez en phpMyAdmin (pestaña Importar) con el usuario root.
-- =====================================================================
CREATE DATABASE IF NOT EXISTS portal_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portal_db;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(60) NOT NULL UNIQUE,
  nombre VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','empleado') NOT NULL DEFAULT 'empleado',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso DATETIME NULL,
  creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  nombre VARCHAR(150) NOT NULL,
  categoria VARCHAR(60) NOT NULL,
  precio DECIMAL(10,2) NOT NULL,
  existencia INT NOT NULL DEFAULT 0,
  minimo INT NOT NULL DEFAULT 5
) ENGINE=InnoDB;

-- Usuarios de prueba (contraseñas en la guía del laboratorio)
INSERT IGNORE INTO usuarios(usuario,nombre,password_hash,rol) VALUES
 ('admin','Administrador del portal','$2y$10$OjOrS8wfs1GE/qj.K1nKjOekZjt3B6Kbbhj3kavfkqcCjG8wudNoW','admin'),
 ('jlopez','Juan López (ventas)','$2y$10$0Yln60I8DOf1sDHx9MooU.9Pi0.0QqgqBiQACIWX9Um4KPrV.mBta','empleado'),
 ('mramirez','María Ramírez (bodega)','$2y$10$SvppZZkQ2Ng5PQrDo2KQsuby8MyhooNkRoQqzTa1tgGaV06deokX6','empleado');

INSERT IGNORE INTO productos(codigo,nombre,categoria,precio,existencia,minimo) VALUES
 ('HER-001','Martillo de uña 16 oz','Herramientas',65.00,24,5),
 ('HER-002','Desarmador de cruz #2','Herramientas',22.50,40,10),
 ('HER-003','Llave ajustable 10"','Herramientas',78.00,3,5),
 ('HER-004','Cinta métrica 5 m','Herramientas',35.00,18,6),
 ('HER-005','Sierra para metal con marco','Herramientas',55.00,9,4),
 ('ELE-010','Cable THHN 12 AWG (metro)','Electricidad',6.75,350,100),
 ('ELE-011','Tomacorriente doble polarizado','Electricidad',18.00,60,15),
 ('ELE-012','Interruptor sencillo','Electricidad',15.50,4,15),
 ('ELE-013','Foco LED 9 W','Electricidad',19.00,120,30),
 ('PLO-005','Tubo PVC 1/2" x 6 m','Plomería',38.00,45,10),
 ('PLO-006','Codo PVC 1/2" 90°','Plomería',2.50,300,50),
 ('PLO-007','Llave de chorro 1/2"','Plomería',42.00,7,8),
 ('PIN-020','Pintura látex blanca galón','Pintura',145.00,16,5),
 ('PIN-021','Brocha 3"','Pintura',17.00,2,6),
 ('CON-030','Cemento gris 42.5 kg','Construcción',82.00,60,20),
 ('CON-031','Varilla de hierro 3/8" x 6 m','Construcción',39.00,110,40);

-- Usuario propio del portal con permisos mínimos sobre su base de datos.
-- Antes de importar, cambie CAMBIE_ESTA_CLAVE y use la misma clave en config.php.
CREATE USER IF NOT EXISTS 'portal_app'@'localhost' IDENTIFIED BY 'CAMBIE_ESTA_CLAVE';
CREATE USER IF NOT EXISTS 'portal_app'@'127.0.0.1' IDENTIFIED BY 'CAMBIE_ESTA_CLAVE';
GRANT SELECT, INSERT, UPDATE ON portal_db.* TO 'portal_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON portal_db.* TO 'portal_app'@'127.0.0.1';
FLUSH PRIVILEGES;
