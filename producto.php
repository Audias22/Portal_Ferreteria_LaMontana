<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$raw = (string)($_GET['id'] ?? '');
// Cualquier valor que no sea un número entero se registra (posible manipulación del parámetro).
if (!ctype_digit($raw)) {
    siem_request('Parámetro inválido en detalle de producto', 'id=' . $raw);
    http_response_code(404);
    $p = null;
} else {
    $st = db()->prepare('SELECT * FROM productos WHERE id=? AND activo=1');
    $st->execute([(int)$raw]);
    $p = $st->fetch() ?: null;
    if (!$p) http_response_code(404);
}
$publicArea = true;
$title = $p['nombre'] ?? 'Producto no encontrado';
require __DIR__ . '/lib/header.php';
?>
<p class="crumbs"><a href="<?= url('index.php') ?>">Catálogo</a><?php if ($p): ?> / <a href="<?= url('index.php?cat=' . urlencode($p['categoria'])) ?>"><?= e($p['categoria']) ?></a><?php endif; ?></p>
<?php if (!$p): ?>
<div class="box"><h2>Producto no encontrado</h2><p><a href="<?= url('index.php') ?>">Volver al catálogo</a></p></div>
<?php else: ?>
<div class="detail">
  <span class="thumb big cat-<?= e(cat_class($p['categoria'])) ?>"><?= e(mb_substr($p['categoria'], 0, 1)) ?></span>
  <div>
    <span class="p-code"><?= e($p['codigo']) ?></span>
    <h1><?= e($p['nombre']) ?></h1>
    <p><?= e($p['descripcion'] ?? '') ?></p>
    <p class="price"><?= money($p['precio']) ?></p>
    <p><?= $p['existencia'] > 0 ? '<span class="tag ok">Disponible en tienda</span>' : '<span class="tag no">Agotado</span>' ?></p>
    <p class="muted">Para pedidos llame al <?= e(PORTAL_PHONE) ?>.</p>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/lib/footer.php'; ?>
