<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_role('admin', 'bodega');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $tipo = ($_POST['tipo'] ?? '') === 'ajuste' ? 'ajuste' : 'entrada';
    $pid = (int)($_POST['producto_id'] ?? 0);
    $cant = (int)($_POST['cantidad'] ?? 0);
    $ref = mb_substr(trim((string)($_POST['referencia'] ?? '')), 0, 150);
    if ($tipo === 'entrada' && $cant <= 0) { flash('err', 'La cantidad de la entrada debe ser mayor que cero.'); redirect('inventario.php'); }
    if ($tipo === 'ajuste' && ($cant === 0 || $ref === '')) { flash('err', 'El ajuste necesita una cantidad distinta de cero y un motivo.'); redirect('inventario.php'); }
    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT id,codigo,nombre,existencia FROM productos WHERE id=? FOR UPDATE');
    $st->execute([$pid]);
    $p = $st->fetch();
    if (!$p) { $pdo->rollBack(); flash('err', 'Producto no encontrado.'); redirect('inventario.php'); }
    $nueva = (int)$p['existencia'] + $cant;
    if ($nueva < 0) { $pdo->rollBack(); flash('err', 'El ajuste dejaría la existencia en negativo.'); redirect('inventario.php'); }
    $pdo->prepare('UPDATE productos SET existencia=?, actualizado=NOW() WHERE id=?')->execute([$nueva, $pid]);
    $pdo->prepare('INSERT INTO movimientos(producto_id,tipo,cantidad,existencia_resultante,referencia,usuario_id) VALUES(?,?,?,?,?,?)')
        ->execute([$pid, $tipo, $cant, $nueva, $ref ?: null, $u['id']]);
    $pdo->commit();
    if ($tipo === 'entrada') {
        siem('INVENTORY_IN', sprintf('Entrada de %d unidades de %s (%s)', $cant, $p['codigo'], $ref ?: 'sin referencia'), 'SUCCESS',
            ['metadata' => ['codigo'=>$p['codigo'],'cantidad'=>$cant,'existencia'=>$nueva,'referencia'=>$ref]]);
    } else {
        siem('INVENTORY_ADJUSTMENT', sprintf('Ajuste de %+d unidades en %s. Motivo: %s', $cant, $p['codigo'], $ref), 'SUCCESS',
            ['metadata' => ['codigo'=>$p['codigo'],'cantidad'=>$cant,'existencia'=>$nueva,'motivo'=>$ref]]);
    }
    flash('ok', ($tipo === 'entrada' ? 'Entrada registrada. ' : 'Ajuste registrado. ') . $p['nombre'] . ': existencia ' . $nueva . '.');
    redirect('inventario.php');
}

$productos = db()->query('SELECT id,codigo,nombre,existencia FROM productos WHERE activo=1 ORDER BY nombre')->fetchAll();
$c = (int)db()->query('SELECT COUNT(*) FROM movimientos')->fetchColumn();
[$page, $pages, $offset] = paginate($c, 25);
$movs = db()->query("SELECT m.*, p.codigo, p.nombre, u.usuario FROM movimientos m JOIN productos p ON p.id=m.producto_id LEFT JOIN usuarios u ON u.id=m.usuario_id ORDER BY m.id DESC LIMIT 25 OFFSET $offset")->fetchAll();
$title = 'Inventario';
require __DIR__ . '/lib/header.php';
$opts = '';
foreach ($productos as $p) $opts .= '<option value="' . (int)$p['id'] . '">' . e($p['codigo'] . ' · ' . $p['nombre'] . ' (' . $p['existencia'] . ')') . '</option>';
?>
<h1>Inventario</h1>
<div class="cols">
  <form method="post" class="box stack">
    <h2>Entrada de mercadería</h2>
    <?= csrf_field() ?><input type="hidden" name="tipo" value="entrada">
    <label>Producto<select name="producto_id" required><?= $opts ?></select></label>
    <label>Cantidad recibida<input type="number" name="cantidad" min="1" required></label>
    <label>Factura o referencia del proveedor<input name="referencia" maxlength="150" placeholder="Ej.: Factura A-1234 Distribuidora XYZ"></label>
    <button>Registrar entrada</button>
  </form>
  <form method="post" class="box stack">
    <h2>Ajuste de inventario</h2>
    <?= csrf_field() ?><input type="hidden" name="tipo" value="ajuste">
    <label>Producto<select name="producto_id" required><?= $opts ?></select></label>
    <label>Cantidad (negativa para restar)<input type="number" name="cantidad" required placeholder="Ej.: -2"></label>
    <label>Motivo<input name="referencia" maxlength="150" required placeholder="Ej.: producto dañado, conteo físico"></label>
    <button class="ghost">Registrar ajuste</button>
  </form>
</div>
<div class="box">
  <h2>Kárdex (movimientos)</h2>
  <table>
    <thead><tr><th>Fecha</th><th>Tipo</th><th>Producto</th><th class="num">Cantidad</th><th class="num">Existencia</th><th>Referencia</th><th>Usuario</th></tr></thead>
    <tbody>
    <?php foreach ($movs as $m): ?>
      <tr><td><?= e($m['creado']) ?></td><td><span class="tag t-<?= e($m['tipo']) ?>"><?= e(ucfirst($m['tipo'])) ?></span></td><td><?= e($m['codigo'] . ' · ' . $m['nombre']) ?></td>
      <td class="num"><?= $m['cantidad'] > 0 ? '+' : '' ?><?= (int)$m['cantidad'] ?></td><td class="num"><?= (int)$m['existencia_resultante'] ?></td><td><?= e($m['referencia'] ?? '') ?></td><td><?= e($m['usuario'] ?? '') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$movs): ?><tr><td colspan="7" class="muted">Sin movimientos.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pager($page, $pages) ?>
</div>
<?php require __DIR__ . '/lib/footer.php'; ?>
