<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_login();
require __DIR__ . '/lib/documentos_lista.php';

$archivo = (string)($_GET['archivo'] ?? '');
siem_request('Solicitud de descarga', 'archivo=' . $archivo);

// Solo se aceptan nombres de la lista blanca; cualquier otra ruta (por ejemplo ../../) se rechaza.
if (!array_key_exists($archivo, DOCUMENTOS) || !is_file(__DIR__ . '/docs/' . $archivo)) {
    http_response_code(404);
    $title = 'No encontrado';
    require __DIR__ . '/lib/header.php';
    echo '<div class="box"><h2>Documento no encontrado</h2><p><a href="' . url('documentos.php') . '">Volver a documentos</a></p></div>';
    require __DIR__ . '/lib/footer.php';
    exit;
}
siem('FILE_DOWNLOAD', 'Descarga de ' . $archivo, 'SUCCESS');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . filesize(__DIR__ . '/docs/' . $archivo));
readfile(__DIR__ . '/docs/' . $archivo);
