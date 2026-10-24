<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
// Toda ruta inexistente o prohibida queda registrada: así el SIEM detecta escaneos de rutas sensibles.
$uri = $_SERVER['REQUEST_URI'] ?? '';
siem_request('Ruta no encontrada o prohibida', '');
http_response_code(404);
$publicArea = !user();
$title = 'No encontrado';
require __DIR__ . '/lib/header.php';
?>
<div class="box"><h2>Página no encontrada</h2><p>La dirección <code><?= e($uri) ?></code> no existe.</p><p><a href="<?= url(user() ? "inicio.php" : "index.php") ?>">Ir al inicio</a></p></div>
<?php require __DIR__ . '/lib/footer.php'; ?>
