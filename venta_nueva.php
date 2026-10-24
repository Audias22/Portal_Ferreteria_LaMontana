<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_role('admin', 'empleado');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $clienteId = (int)($_POST['cliente_id'] ?? 1);
    // Se agrupan las líneas por producto; el precio SIEMPRE se toma de la base de datos, nunca del formulario.
    $lineas = [];
    foreach ((array)($_POST['producto_id'] ?? []) as $i => $pid) {
        $pid = (int)$pid; $cant = (int)($_POST['cantidad'][$i] ?? 0);
        if ($pid > 0 && $cant > 0) $lineas[$pid] = ($lineas[$pid] ?? 0) + $cant;
    }
    if (!$lineas) $error = 'Agregue al menos un producto con cantidad.';
    else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $cli = $pdo->prepare('SELECT id,nit,nombre FROM clientes WHERE id=?'); $cli->execute([$clienteId]);
            $cliente = $cli->fetch();
            if (!$cliente) throw new RuntimeException('Cliente no válido.');
            $items = []; $total = 0.0;
            $st = $pdo->prepare('SELECT id,codigo,nombre,precio,existencia FROM productos WHERE id=? AND activo=1 FOR UPDATE');
            foreach ($lineas as $pid => $cant) {
                $st->execute([$pid]);
                $p = $st->fetch();
                if (!$p) throw new RuntimeException('Un producto seleccionado no existe o no está disponible.');
                if ($p['existencia'] < $cant) throw new RuntimeException('Existencia insuficiente de ' . $p['nombre'] . ' (hay ' . $p['existencia'] . ').');
                $sub = round((float)$p['precio'] * $cant, 2);
                $items[] = ['p' => $p, 'cant' => $cant, 'sub' => $sub];
                $total += $sub;
            }
            $pdo->prepare('INSERT INTO ventas(cliente_id,usuario_id,total) VALUES(?,?,?)')->execute([$clienteId, $u['id'], $total]);
            $vid = (int)$pdo->lastInsertId();
            $numero = 'A-' . str_pad((string)$vid, 6, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE ventas SET numero=? WHERE id=?')->execute([$numero, $vid]);
            $det = $pdo->prepare('INSERT INTO venta_detalle(venta_id,producto_id,cantidad,precio_unitario,subtotal) VALUES(?,?,?,?,?)');
            $upd = $pdo->prepare('UPDATE productos SET existencia=existencia-? WHERE id=?');
            $mov = $pdo->prepare('INSERT INTO movimientos(producto_id,tipo,cantidad,existencia_resultante,referencia,usuario_id) VALUES(?,?,?,?,?,?)');
            foreach ($items as $it) {
                $det->execute([$vid, $it['p']['id'], $it['cant'], $it['p']['precio'], $it['sub']]);
                $upd->execute([$it['cant'], $it['p']['id']]);
                $mov->execute([$it['p']['id'], 'venta', -$it['cant'], (int)$it['p']['existencia'] - $it['cant'], 'Venta ' . $numero, $u['id']]);
            }
            $pdo->commit();
            siem('SALE_CREATED', sprintf('Venta %s por %s a %s (%d producto(s))', $numero, money($total), $cliente['nit'], count($items)), 'SUCCESS', ['metadata' => [
                'venta' => $numero, 'total' => $total, 'cliente_nit' => $cliente['nit'],
                'items' => array_map(fn($it) => ['codigo' => $it['p']['codigo'], 'cantidad' => $it['cant'], 'precio' => (float)$it['p']['precio']], $items),
            ]]);
            flash('ok', 'Venta ' . $numero . ' registrada.');
            redirect('venta.php?id=' . $vid);
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
$clientes = db()->query('SELECT id,nit,nombre FROM clientes ORDER BY id=1 DESC, nombre')->fetchAll();
$productos = db()->query('SELECT id,codigo,nombre,precio,existencia FROM productos WHERE activo=1 AND existencia>0 ORDER BY nombre')->fetchAll();
$title = 'Nueva venta';
require __DIR__ . '/lib/header.php';
$opts = '<option value="">— Seleccione —</option>';
foreach ($productos as $p) $opts .= '<option value="' . (int)$p['id'] . '" data-precio="' . e((string)$p['precio']) . '">' . e($p['codigo'] . ' · ' . $p['nombre'] . ' · ' . money($p['precio']) . ' (hay ' . $p['existencia'] . ')') . '</option>';
?>
<p class="crumbs"><a href="<?= url('ventas.php') ?>">Ventas</a> / Nueva venta</p>
<h1>Nueva venta</h1>
<?php if ($error): ?><div class="msg err"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="box sale-form">
  <?= csrf_field() ?>
  <label>Cliente<select name="cliente_id"><?php foreach ($clientes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nit'] . ' · ' . $c['nombre']) ?></option><?php endforeach; ?></select></label>
  <p class="muted small">¿Cliente nuevo? Regístrelo primero en <a href="<?= url('clientes.php') ?>">Clientes</a>.</p>
  <table class="lines">
    <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">Subtotal</th></tr></thead>
    <tbody id="lines">
    <?php for ($i = 0; $i < 4; $i++): ?>
      <tr><td><select name="producto_id[]" class="js-prod"><?= $opts ?></select></td><td class="num"><input type="number" name="cantidad[]" min="1" value="1" class="js-cant"></td><td class="num js-sub">—</td></tr>
    <?php endfor; ?>
    </tbody>
    <tfoot><tr><td><button type="button" class="ghost small" id="add-line">Agregar línea</button></td><td class="num"><strong>Total</strong></td><td class="num"><strong id="total">Q 0.00</strong></td></tr></tfoot>
  </table>
  <p class="muted small">El total final lo calcula el sistema con los precios vigentes al guardar.</p>
  <button>Registrar venta</button> <a href="<?= url('ventas.php') ?>">Cancelar</a>
</form>
<?php require __DIR__ . '/lib/footer.php'; ?>
