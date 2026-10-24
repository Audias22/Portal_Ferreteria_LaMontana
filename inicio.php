<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_login();
$hoy = db()->query("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM ventas WHERE estado='emitida' AND DATE(creado)=CURDATE()")->fetch();
$bajos = db()->query('SELECT codigo,nombre,existencia,minimo FROM productos WHERE activo=1 AND existencia<=minimo ORDER BY existencia ASC LIMIT 6')->fetchAll();
$nBajos = (int)db()->query('SELECT COUNT(*) FROM productos WHERE activo=1 AND existencia<=minimo')->fetchColumn();
$ultimas = db()->query("SELECT v.id,v.numero,v.total,v.estado,v.creado,c.nombre cliente FROM ventas v JOIN clientes c ON c.id=v.cliente_id ORDER BY v.id DESC LIMIT 6")->fetchAll();
$movs = db()->query("SELECT m.tipo,m.cantidad,m.creado,p.nombre FROM movimientos m JOIN productos p ON p.id=m.producto_id ORDER BY m.id DESC LIMIT 6")->fetchAll();
$title = 'Panel';
require __DIR__ . '/lib/header.php';
?>
<div class="head-row">
  <h1>Buen día, <?= e(explode(' ', $u['nombre'])[0]) ?></h1>
  <div class="actions">
    <?php if (has_role('admin','empleado')): ?><a class="btn" href="<?= url('venta_nueva.php') ?>">Nueva venta</a><?php endif; ?>
    <?php if (has_role('admin','bodega')): ?><a class="btn ghost" href="<?= url('inventario.php') ?>">Registrar entrada</a><?php endif; ?>
  </div>
</div>
<div class="cards">
  <div class="card"><strong><?= (int)$hoy['n'] ?></strong><span>Ventas de hoy</span></div>
  <div class="card"><strong><?= money($hoy['t']) ?></strong><span>Vendido hoy</span></div>
  <a class="card warn" href="<?= url('productos.php?bajo=1') ?>"><strong><?= $nBajos ?></strong><span>Productos con existencia baja</span></a>
</div>
<div class="cols">
  <div class="box">
    <div class="box-head"><h2>Últimas ventas</h2><?php if (has_role('admin','empleado')): ?><a href="<?= url('ventas.php') ?>">Ver todas</a><?php endif; ?></div>
    <table class="compact"><tbody>
    <?php foreach ($ultimas as $v): ?>
      <tr><td><?= has_role('admin','empleado') ? '<a href="' . url('venta.php?id=' . (int)$v['id']) . '">' . e($v['numero']) . '</a>' : e($v['numero']) ?></td><td><?= e($v['cliente']) ?></td><td class="num"><?= money($v['total']) ?></td><td><?= $v['estado'] === 'anulada' ? '<span class="tag no">Anulada</span>' : '' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$ultimas): ?><tr><td class="muted">Todavía no hay ventas.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <div class="box">
    <div class="box-head"><h2>Existencia baja</h2><a href="<?= url('productos.php?bajo=1') ?>">Ver lista</a></div>
    <table class="compact"><tbody>
    <?php foreach ($bajos as $b): ?>
      <tr><td><?= e($b['codigo']) ?></td><td><?= e($b['nombre']) ?></td><td class="num"><strong><?= (int)$b['existencia'] ?></strong> / <?= (int)$b['minimo'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$bajos): ?><tr><td class="muted">Todo el inventario está sobre el mínimo.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<?php if (has_role('admin','bodega')): ?>
<div class="box">
  <div class="box-head"><h2>Últimos movimientos de inventario</h2><a href="<?= url('inventario.php') ?>">Ver kárdex</a></div>
  <table class="compact"><tbody>
  <?php foreach ($movs as $m): ?><tr><td><?= e($m['creado']) ?></td><td><?= e(ucfirst($m['tipo'])) ?></td><td><?= e($m['nombre']) ?></td><td class="num"><?= $m['cantidad'] > 0 ? '+' : '' ?><?= (int)$m['cantidad'] ?></td></tr><?php endforeach; ?>
  <?php if (!$movs): ?><tr><td class="muted">Sin movimientos registrados.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/lib/footer.php'; ?>
