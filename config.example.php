<?php
// Configuración del portal. Este archivo no es accesible desde el navegador (.htaccess).
declare(strict_types=1);

const PORTAL_NAME = 'Ferretería La Montaña';
const PORTAL_TIMEZONE = 'America/Guatemala';
const PORTAL_BASE = '/portal';          // carpeta dentro de htdocs
const PORTAL_PHONE = '7941-0000';
const PORTAL_ADDRESS = 'Barrio El Centro, frente al parque';
const PORTAL_HOURS = 'Lunes a sábado, 7:00 a 18:00';

// Base de datos propia del portal (MariaDB de XAMPP, puerto 3307)
const PORTAL_DB_HOST = '127.0.0.1';
const PORTAL_DB_PORT = '3307';
const PORTAL_DB_NAME = 'portal_db';
const PORTAL_DB_USER = 'portal_app';
const PORTAL_DB_PASS = 'CAMBIE_ESTA_CLAVE';

// Envío de eventos a SecureWatch SIEM (misma PC, por eso 127.0.0.1)
const SIEM_ENDPOINT = 'http://127.0.0.1/SecureWatch_SIEM/api/events.php';
const SIEM_SOURCE   = 'WEB-PORTAL';
const SIEM_API_KEY  = 'PEGUE_AQUI_LA_API_KEY_DE_LA_FUENTE';
