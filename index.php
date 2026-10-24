<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
// Catálogo público: lo que ve cualquier cliente sin iniciar sesión.
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 120);
$cat = mb_substr(trim((string)($_GET['cat'] ?? '')), 0, 60);

$where = ['activo=1'];
$params = [];
if ($q !== '') { $where[] = '(nombre LIKE ? OR descripcion LIKE ? OR codigo LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
if ($cat !== '') { $where[] = 'categoria=?'; $params[] = $cat; }
$w = ' WHERE ' . implode(' AND ', $where);
$c = db()->prepare('SELECT COUNT(*) FROM productos' . $w); $c->execute($params);
[$page, $pages, $offset] = paginate((int)$c->fetchColumn(), 12);
$st = db()->prepare('SELECT id,codigo,nombre,descripcion,categoria,precio,existencia FROM productos' . $w . " ORDER BY categoria,nombre LIMIT 12 OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();
$cats = db()->query('SELECT categoria, COUNT(*) n FROM productos WHERE activo=1 GROUP BY categoria ORDER BY categoria')->fetchAll();
// Se registran las búsquedas y cualquier categoría que no exista (parámetro manipulado).
if ($q !== '' || ($cat !== '' && !in_array($cat, array_column($cats, 'categoria'), true))) {
    siem_request('Búsqueda en catálogo público', 'q=' . $q . ($cat !== '' ? '&cat=' . $cat : ''));
}
$publicArea = true;
$title = 'Catálogo';
require __DIR__ . '/lib/header.php';
?>
<section class="hero">
  <div>
    <h1>Todo para su obra, en un solo lugar</h1>
    <p>Herramientas, electricidad, plomería, pintura y materiales de construcción. Consulte precios y existencias antes de venir.</p>
  </div>
  <form class="search hero-search" method="get" action="<?= url('index.php') ?>">
    <input name="q" value="<?= e($q) ?>" placeholder="¿Qué está buscando? Ej.: cemento, taladro, tubo PVC" aria-label="Buscar productos">
    <button>Buscar</button>
  </form>
</section>

<div class="catalog">
  <aside class="cats">
    <h3>Categorías</h3>
    <a href="<?= url('index.php') ?>"<?= $cat === '' ? ' class="active"' : '' ?>>Todas</a>
    <?php foreach ($cats as $k): ?>
      <a href="<?= url('index.php?cat=' . urlencode($k['categoria'])) ?>"<?= $cat === $k['categoria'] ? ' class="active"' : '' ?>><?= e($k['categoria']) ?> <span><?= (int)$k['n'] ?></span></a>
    <?php endforeach; ?>
  </aside>
  <section>
    <?php if ($q !== ''): ?><p class="muted">Resultados para <strong><?= e($q) ?></strong></p><?php endif; ?>
    <div class="grid">
      <?php foreach ($rows as $p): ?>
      <a class="product" href="<?= url('producto.php?id=' . (int)$p['id']) ?>">
        <span class="thumb cat-<?= e(cat_class($p['categoria'])) ?>"><?= e(mb_substr($p['categoria'], 0, 1)) ?></span>
        <span class="p-name"><?= e($p['nombre']) ?></span>
        <span class="p-code"><?= e($p['codigo']) ?></span>
        <span class="p-foot"><strong><?= money($p['precio']) ?></strong><em class="<?= $p['existencia'] > 0 ? 'ok' : 'no' ?>"><?= $p['existencia'] > 0 ? 'Disponible' : 'Agotado' ?></em></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if (!$rows): ?><div class="box">No encontramos productos con esa búsqueda.</div><?php endif; ?>
    <?= pager($page, $pages) ?>
  </section>
</div>

<section class="contact" id="contacto">
  <div><h2>Visítenos</h2><p><?= e(PORTAL_ADDRESS) ?></p><p><?= e(PORTAL_HOURS) ?></p></div>
  <div><h2>Pedidos y cotizaciones</h2><p>Tel. <?= e(PORTAL_PHONE) ?></p><p>Entregas a domicilio en el casco urbano para compras mayores a Q 500.</p></div>
</section>
<?php require __DIR__ . '/lib/footer.php'; ?>
