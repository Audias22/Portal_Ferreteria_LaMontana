<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_login();
$tipo = (string)($_GET['exportar'] ?? '');
$desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['desde'] ?? '')) ? $_GET['desde'] : date('Y-m-01');
$hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['hasta'] ?? '')) ? $_GET['hasta'] : date('Y-m-d');

function csv_out(string $name, array $head, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $head);
    foreach ($rows as $r) fputcsv($out, $r);
    exit;
}
if ($tipo === 'inventario') {
    $rows = db()->query('SELECT codigo,nombre,categoria,precio,existencia,minimo FROM productos WHERE activo=1 ORDER BY codigo')->fetchAll();
    siem('DATA_EXPORT', 'Exportación del inventario completo (' . count($rows) . ' productos)', 'SUCCESS');
    csv_out('inventario', ['codigo','nombre','categoria','precio','existencia','minimo'], $rows);
}
if ($tipo === 'ventas') {
    if (!has_role('admin','empleado')) require_role('admin','empleado');
    $st = db()->prepare("SELECT v.numero,v.creado,c.nit,c.nombre,us.usuario,v.total,v.estado FROM ventas v JOIN clientes c ON c.id=v.cliente_id JOIN usuarios us ON us.id=v.usuario_id WHERE v.creado BETWEEN ? AND ? ORDER BY v.id");
    $st->execute([$desde . ' 00:00:00', $hasta . ' 23:59:59']);
    $rows = $st->fetchAll();
    siem('DATA_EXPORT', "Exportación de ventas del $desde al $hasta (" . count($rows) . ' ventas)', 'SUCCESS');
    csv_out('ventas', ['numero','fecha','nit','cliente','vendedor','total','estado'], $rows);
}
if ($tipo === 'clientes') {
    if (!has_role('admin')) require_role('admin');
    $rows = db()->query('SELECT nit,nombre,telefono,correo,direccion FROM clientes ORDER BY nombre')->fetchAll();
    siem('DATA_EXPORT', 'Exportación de la base de clientes (' . count($rows) . ' registros)', 'SUCCESS');
    csv_out('clientes', ['nit','nombre','telefono','correo','direccion'], $rows);
}
$title = 'Reportes';
require __DIR__ . '/lib/header.php';
?>
<h1>Reportes</h1>
<div class="cards">
  <div class="box"><h2>Inventario</h2><p>Productos activos con precio y existencia.</p><a class="btn" href="<?= url('reportes.php?exportar=inventario') ?>">Descargar CSV</a></div>
  <?php if (has_role('admin','empleado')): ?>
  <form class="box" method="get"><h2>Ventas</h2><p>Detalle de ventas en un rango de fechas.</p>
    <input type="hidden" name="exportar" value="ventas">
    <p><label class="inline">Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label> <label class="inline">Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label></p>
    <button>Descargar CSV</button></form>
  <?php endif; ?>
  <?php if (has_role('admin')): ?>
  <div class="box"><h2>Clientes</h2><p>Base de clientes con datos de contacto. Contiene datos personales.</p><a class="btn" href="<?= url('reportes.php?exportar=clientes') ?>">Descargar CSV</a></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/lib/footer.php'; ?>
