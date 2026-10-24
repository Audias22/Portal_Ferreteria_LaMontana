# Portal Ferretería La Montaña

Sistema web de una ferretería ficticia: catálogo público, ventas, inventario y clientes. Es el **sitio monitoreado** del laboratorio de [SecureWatch SIEM](https://github.com/Audias22/SecureWatch_SIEM): cada acción importante se envía como evento al SIEM, que la analiza y genera alertas.

Proyecto académico del curso de Seguridad y Auditoría de Sistemas (UMG). La empresa y sus datos son inventados.

## Qué tiene

**Parte pública (sin iniciar sesión)**
- Catálogo con búsqueda, categorías y detalle de producto.
- Datos de contacto y horario.

**Sistema interno**

| Módulo | Quién lo usa |
|---|---|
| Ventas: nueva venta, comprobante, anulación | Ventas y administrador (anular solo el administrador) |
| Clientes | Ventas y administrador |
| Productos y precios | Bodega y administrador |
| Inventario: entradas, ajustes y kárdex | Bodega y administrador |
| Reportes CSV de inventario, ventas y clientes | Según el rol |
| Usuarios y roles | Administrador |
| Mi perfil y recuperación de contraseña | Todos |

## Eventos que recibe el SIEM

Inicio y cierre de sesión, accesos denegados, búsquedas y parámetros sospechosos, descargas y exportaciones, ventas y anulaciones, cambios de precio, entradas y ajustes de inventario, alta de clientes, cambios de rol y de contraseña, y solicitudes de recuperación de contraseña.

Con esos eventos SecureWatch detecta, entre otros: fuerza bruta, SQL Injection, XSS, Path Traversal, rebajas de precio sospechosas, **venta de un producto poco después de rebajar su precio**, anulaciones repetidas, ajustes de inventario grandes y abuso de la recuperación de contraseña.

## Seguridad aplicada

- Consultas preparadas en todo el sistema y salida escapada en las vistas.
- Token CSRF en todos los formularios.
- Contraseñas con `password_hash`, política mínima de 10 caracteres con letras y números.
- Control de acceso por rol en cada página; los intentos sin permiso quedan registrados.
- El precio de una venta lo pone el servidor, nunca el formulario.
- Enlaces de recuperación de un solo uso, válidos 30 minutos, guardados solo como hash.
- Usuario de base de datos sin permiso `DELETE`: ventas y productos se anulan o desactivan, no se borran.
- Encabezados de seguridad (CSP, X-Frame-Options, nosniff) y cierre de sesión por inactividad.

## Instalación (XAMPP)

1. Copiar la carpeta a `C:\xampp\htdocs\portal`.
2. En phpMyAdmin: cambiar `CAMBIE_ESTA_CLAVE` en `database/portal.sql`, importarlo, y después importar `database/portal_v2.sql`.
3. Copiar `config.example.php` como `config.php` y completar la clave de la base de datos.
4. En SecureWatch → **Fuentes**, crear o regenerar la fuente `WEB-PORTAL` y pegar su API Key en `config.php`.
5. Abrir `http://IP_DEL_SERVIDOR/portal/`.

Usuarios de prueba:

| Usuario | Contraseña | Rol |
|---|---|---|
| admin | Portal2026! | Administrador |
| jlopez | Empleado2026! | Ventas |
| mramirez | Bodega2026! | Bodega |

Cambie estas contraseñas después de instalar.

Como el laboratorio no tiene servidor de correo, los mensajes de recuperación de contraseña se pueden ver en `http://127.0.0.1/portal/correo.php` (solo desde la propia PC del servidor).
