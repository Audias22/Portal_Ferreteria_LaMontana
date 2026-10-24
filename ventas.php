<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_role('admin', 'empleado');
$desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['desde'] ?? '')) ? $_GET['desde'] : date('Y-m-d', strtotime('-7 days'));
$hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['hasta'] ?? '')) ? $_GET['hasta'] : date('Y-m-d');
$estado = in_array($_GET['estado'] ?? '', ['emitida','anulada'], true) ? $_GET['estado'] : '';
$where = ' WHERE v.creado BETWEEN ? AND ?'; $params = [$desde . ' 00:00:00', $hasta . ' 23:59:59'];
if ($estado) { $where .= ' AND v.estado=?'; $params[] = $estado; }
$c = db()->prepare('SELECT COUNT(*), COALESCE(SUM(CASE WHEN v.estado=\'emitida\' THEN v.total END),0) FROM ventas v' . $where); $c->execute($params);
[$n, $suma] = $c->fetch(PDO::FETCH_NUM);
[$page, $pages, $offset] = paginate((int)$n, 25);
$st = db()->prepare('SELECT v.id,v.numero,v.total,v.estado,v.creado,c.nombre cliente,c.nit,us.usuario FROM ventas v JOIN clientes c ON c.id=v.cliente_id JOIN usuarios us ON us.id=v.usuario_id' . $where . " ORDER BY v.id DESC LIMIT 25 OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();
$title = 'Ventas';
require __DIR__ . '/lib/header.php';
?>
<div class="head-row"><h1>Ventas</h1><div class="actions"><a class="btn" href="<?= url('venta_nueva.php') ?>">Nueva venta</a></div></div>
<form class="search" method="get">
  <label class="inline">Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
  <label class="inline">Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
  <select name="estado"><option value="">Todas</option><option value="emitida" <?= $estado === 'emitida' ? 'selected' : '' ?>>Emitidas</option><option value="anulada" <?= $estado === 'anulada' ? 'selected' : '' ?>>Anuladas</option></select>
  <button>Filtrar</button>
</form>
<p class="muted"><?= (int)$n ?> venta(s) en el período · Total emitido: <strong><?= money($suma) ?></strong></p>
<table>
  <thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th class="num">Total</th><th>Estado</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="<?= url('venta.php?id=' . (int)$r['id']) ?>"><?= e($r['numero']) ?></a></td><td><?= e($r['creado']) ?></td><td><?= e($r['nit'] . ' · ' . $r['cliente']) ?></td><td><?= e($r['usuario']) ?></td><td class="num"><?= money($r['total']) ?></td>
    <td><?= $r['estado'] === 'anulada' ? '<span class="tag no">Anulada</span>' : '<span class="tag ok">Emitida</span>' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted">No hay ventas en el período.</td></tr><?php endif; ?>
  </tbody>
</table>
<?= pager($page, $pages) ?>
<?php require __DIR__ . '/lib/footer.php'; ?>
