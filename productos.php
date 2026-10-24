<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_login();
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 200);
$bajo = isset($_GET['bajo']);
$inactivos = isset($_GET['inactivos']) && has_role('admin','bodega');
if ($q !== '') siem_request('Búsqueda de productos (sistema interno)', 'q=' . $q);

$where = [$inactivos ? 'activo=0' : 'activo=1'];
$params = [];
if ($q !== '') { $where[] = '(nombre LIKE ? OR codigo LIKE ? OR categoria LIKE ?)'; $like = '%' . $q . '%'; array_push($params, $like, $like, $like); }
if ($bajo) $where[] = 'existencia <= minimo';
$w = ' WHERE ' . implode(' AND ', $where);
$c = db()->prepare('SELECT COUNT(*) FROM productos' . $w); $c->execute($params);
[$page, $pages, $offset] = paginate((int)$c->fetchColumn(), 20);
$st = db()->prepare('SELECT * FROM productos' . $w . " ORDER BY categoria, nombre LIMIT 20 OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();
$canEdit = has_role('admin','bodega');
$title = 'Productos';
require __DIR__ . '/lib/header.php';
?>
<div class="head-row"><h1>Productos</h1><?php if ($canEdit): ?><div class="actions"><a class="btn" href="<?= url('producto_editar.php') ?>">Nuevo producto</a></div><?php endif; ?></div>
<form class="search" method="get">
  <input name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, código o categoría">
  <button>Buscar</button>
  <a href="<?= url('productos.php?bajo=1') ?>">Existencia baja</a>
  <?php if ($canEdit): ?><a href="<?= url('productos.php?inactivos=1') ?>">Inactivos</a><?php endif; ?>
  <?php if ($q !== '' || $bajo || $inactivos): ?><a href="<?= url('productos.php') ?>">Quitar filtro</a><?php endif; ?>
</form>
<table>
  <thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th class="num">Precio</th><th class="num">Existencia</th><th class="num">Mínimo</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($rows as $p): ?>
    <tr<?= $p['existencia'] <= $p['minimo'] ? ' class="low"' : '' ?>>
      <td><?= e($p['codigo']) ?></td><td><?= e($p['nombre']) ?></td><td><?= e($p['categoria']) ?></td>
      <td class="num"><?= money($p['precio']) ?></td><td class="num"><?= (int)$p['existencia'] ?></td><td class="num"><?= (int)$p['minimo'] ?></td>
      <?php if ($canEdit): ?><td><a href="<?= url('producto_editar.php?id=' . (int)$p['id']) ?>">Editar</a></td><?php endif; ?>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted">No se encontraron productos.</td></tr><?php endif; ?>
  </tbody>
</table>
<?= pager($page, $pages) ?>
<?php require __DIR__ . '/lib/footer.php'; ?>
