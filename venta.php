<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_role('admin', 'empleado');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = db()->prepare('SELECT v.*, c.nit, c.nombre cliente, c.direccion, us.nombre vendedor, ua.nombre anulador FROM ventas v JOIN clientes c ON c.id=v.cliente_id JOIN usuarios us ON us.id=v.usuario_id LEFT JOIN usuarios ua ON ua.id=v.anulada_por WHERE v.id=?');
$st->execute([$id]);
$v = $st->fetch();
if (!$v) { http_response_code(404); $title = 'Venta no encontrada'; require __DIR__ . '/lib/header.php'; echo '<div class="box">Venta no encontrada.</div>'; require __DIR__ . '/lib/footer.php'; exit; }

// Anulación: solo administradores. Devuelve la mercadería al inventario y queda registrada.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'anular') {
    if (!has_role('admin')) require_role('admin');
    check_csrf();
    $motivo = mb_substr(trim((string)($_POST['motivo'] ?? '')), 0, 200);
    if ($motivo === '' || $v['estado'] === 'anulada') { flash('err', 'Indique el motivo de la anulación.'); redirect('venta.php?id=' . $id); }
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE ventas SET estado='anulada', anulada_por=?, anulada_motivo=?, anulada_en=NOW() WHERE id=? AND estado='emitida'")->execute([$u['id'], $motivo, $id]);
    $det = $pdo->prepare('SELECT d.producto_id, d.cantidad FROM venta_detalle d WHERE d.venta_id=?'); $det->execute([$id]);
    $sel = $pdo->prepare('SELECT existencia FROM productos WHERE id=? FOR UPDATE');
    $upd = $pdo->prepare('UPDATE productos SET existencia=existencia+? WHERE id=?');
    $mov = $pdo->prepare('INSERT INTO movimientos(producto_id,tipo,cantidad,existencia_resultante,referencia,usuario_id) VALUES(?,?,?,?,?,?)');
    foreach ($det->fetchAll() as $d) {
        $sel->execute([$d['producto_id']]); $ex = (int)$sel->fetchColumn();
        $upd->execute([$d['cantidad'], $d['producto_id']]);
        $mov->execute([$d['producto_id'], 'anulacion', $d['cantidad'], $ex + (int)$d['cantidad'], 'Anulación ' . $v['numero'], $u['id']]);
    }
    $pdo->commit();
    siem('SALE_VOIDED', sprintf('Venta %s por %s anulada. Motivo: %s', $v['numero'], money($v['total']), $motivo), 'SUCCESS',
        ['metadata' => ['venta' => $v['numero'], 'total' => (float)$v['total'], 'motivo' => $motivo, 'vendedor' => $v['vendedor']]]);
    flash('ok', 'Venta anulada y mercadería devuelta al inventario.');
    redirect('venta.php?id=' . $id);
}
$det = db()->prepare('SELECT d.*, p.codigo, p.nombre FROM venta_detalle d JOIN productos p ON p.id=d.producto_id WHERE d.venta_id=?');
$det->execute([$id]);
$lineas = $det->fetchAll();
$title = 'Venta ' . $v['numero'];
require __DIR__ . '/lib/header.php';
?>
<p class="crumbs no-print"><a href="<?= url('ventas.php') ?>">Ventas</a> / <?= e($v['numero']) ?></p>
<div class="receipt box">
  <div class="receipt-head">
    <div><?php require __DIR__ . '/lib/logo.php'; ?><strong><?= e(PORTAL_NAME) ?></strong><br><span class="muted small"><?= e(PORTAL_ADDRESS) ?> · Tel. <?= e(PORTAL_PHONE) ?></span></div>
    <div class="r-num">Comprobante<br><strong><?= e($v['numero']) ?></strong><br><span class="muted small"><?= e($v['creado']) ?></span></div>
  </div>
  <?php if ($v['estado'] === 'anulada'): ?><div class="msg err">ANULADA el <?= e($v['anulada_en']) ?> por <?= e($v['anulador']) ?>. Motivo: <?= e($v['anulada_motivo']) ?></div><?php endif; ?>
  <dl class="kv"><dt>Cliente</dt><dd><?= e($v['cliente']) ?></dd><dt>NIT</dt><dd><?= e($v['nit']) ?></dd><dt>Atendió</dt><dd><?= e($v['vendedor']) ?></dd></dl>
  <table>
    <thead><tr><th>Código</th><th>Descripción</th><th class="num">Cant.</th><th class="num">Precio</th><th class="num">Subtotal</th></tr></thead>
    <tbody><?php foreach ($lineas as $l): ?><tr><td><?= e($l['codigo']) ?></td><td><?= e($l['nombre']) ?></td><td class="num"><?= (int)$l['cantidad'] ?></td><td class="num"><?= money($l['precio_unitario']) ?></td><td class="num"><?= money($l['subtotal']) ?></td></tr><?php endforeach; ?></tbody>
    <tfoot><tr><td colspan="4" class="num"><strong>Total</strong></td><td class="num"><strong><?= money($v['total']) ?></strong></td></tr></tfoot>
  </table>
  <p class="muted small">Documento interno de control. Precios incluyen IVA.</p>
</div>
<div class="actions no-print">
  <button type="button" data-print>Imprimir</button>
  <a class="btn ghost" href="<?= url('venta_nueva.php') ?>">Nueva venta</a>
</div>
<?php if (has_role('admin') && $v['estado'] === 'emitida'): ?>
<form method="post" class="box stack narrow no-print">
  <h2>Anular venta</h2>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="accion" value="anular">
  <label>Motivo<input name="motivo" maxlength="200" required placeholder="Ej.: error en la cantidad, devolución del cliente"></label>
  <button class="danger" data-confirm="¿Anular la venta <?= e($v['numero']) ?>? La mercadería vuelve al inventario.">Anular venta</button>
</form>
<?php endif; ?>
<?php require __DIR__ . '/lib/footer.php'; ?>
