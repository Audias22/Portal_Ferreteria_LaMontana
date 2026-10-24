<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_role('admin', 'empleado');
$edit = null;
if (isset($_GET['editar'])) {
    $st = db()->prepare('SELECT * FROM clientes WHERE id=? AND id<>1'); $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch() ?: null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $d = [
        'nit' => strtoupper(mb_substr(trim((string)($_POST['nit'] ?? '')), 0, 20)),
        'nombre' => mb_substr(trim((string)($_POST['nombre'] ?? '')), 0, 150),
        'telefono' => mb_substr(trim((string)($_POST['telefono'] ?? '')), 0, 30) ?: null,
        'correo' => mb_substr(trim((string)($_POST['correo'] ?? '')), 0, 150) ?: null,
        'direccion' => mb_substr(trim((string)($_POST['direccion'] ?? '')), 0, 200) ?: null,
    ];
    if (!preg_match('/^(CF|[0-9]{1,12}-?[0-9K])$/', $d['nit'])) { flash('err', 'NIT inválido. Use el formato 1234567-8 o CF.'); redirect('clientes.php'); }
    if ($d['nombre'] === '') { flash('err', 'El nombre es obligatorio.'); redirect('clientes.php'); }
    if ($d['correo'] && !filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) { flash('err', 'Correo inválido.'); redirect('clientes.php'); }
    if ($id > 1) {
        db()->prepare('UPDATE clientes SET nit=?,nombre=?,telefono=?,correo=?,direccion=? WHERE id=?')->execute([...array_values($d), $id]);
        siem('CUSTOMER_UPDATED', 'Datos del cliente ' . $d['nit'] . ' actualizados', 'SUCCESS');
        flash('ok', 'Cliente actualizado.');
    } else {
        db()->prepare('INSERT INTO clientes(nit,nombre,telefono,correo,direccion,creado_por) VALUES(?,?,?,?,?,?)')->execute([...array_values($d), $u['id']]);
        siem('CUSTOMER_CREATED', 'Cliente registrado: ' . $d['nit'] . ' ' . $d['nombre'], 'SUCCESS');
        flash('ok', 'Cliente registrado.');
    }
    redirect('clientes.php');
}
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
if ($q !== '') siem_request('Búsqueda de clientes', 'q=' . $q);
$where = ''; $params = [];
if ($q !== '') { $where = ' WHERE nit LIKE ? OR nombre LIKE ?'; $params = ["%$q%", "%$q%"]; }
$c = db()->prepare('SELECT COUNT(*) FROM clientes' . $where); $c->execute($params);
[$page, $pages, $offset] = paginate((int)$c->fetchColumn(), 20);
$st = db()->prepare("SELECT c.*, (SELECT COUNT(*) FROM ventas v WHERE v.cliente_id=c.id AND v.estado='emitida') compras FROM clientes c" . $where . " ORDER BY c.id=1 DESC, c.nombre LIMIT 20 OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();
$f = $edit ?? ['id'=>0,'nit'=>'','nombre'=>'','telefono'=>'','correo'=>'','direccion'=>''];
$title = 'Clientes';
require __DIR__ . '/lib/header.php';
?>
<h1>Clientes</h1>
<div class="cols wide-left">
  <div>
    <form class="search" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Buscar por NIT o nombre"><button>Buscar</button><?php if ($q !== ''): ?><a href="<?= url('clientes.php') ?>">Quitar filtro</a><?php endif; ?></form>
    <table>
      <thead><tr><th>NIT</th><th>Nombre</th><th>Teléfono</th><th class="num">Compras</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td><?= e($r['nit']) ?></td><td><?= e($r['nombre']) ?><?php if ($r['correo']): ?><br><span class="muted small"><?= e($r['correo']) ?></span><?php endif; ?></td><td><?= e($r['telefono'] ?? '') ?></td><td class="num"><?= (int)$r['compras'] ?></td>
        <td><?php if ((int)$r['id'] !== 1): ?><a href="<?= url('clientes.php?editar=' . (int)$r['id']) ?>">Editar</a><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?= pager($page, $pages) ?>
  </div>
  <form method="post" class="box stack">
    <h2><?= $edit ? 'Editar cliente' : 'Nuevo cliente' ?></h2>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
    <label>NIT<input name="nit" value="<?= e($f['nit']) ?>" maxlength="20" required placeholder="1234567-8"></label>
    <label>Nombre o razón social<input name="nombre" value="<?= e($f['nombre']) ?>" maxlength="150" required></label>
    <label>Teléfono<input name="telefono" value="<?= e($f['telefono'] ?? '') ?>" maxlength="30"></label>
    <label>Correo<input type="email" name="correo" value="<?= e($f['correo'] ?? '') ?>" maxlength="150"></label>
    <label>Dirección<input name="direccion" value="<?= e($f['direccion'] ?? '') ?>" maxlength="200"></label>
    <button>Guardar</button><?php if ($edit): ?> <a href="<?= url('clientes.php') ?>">Cancelar</a><?php endif; ?>
  </form>
</div>
<?php require __DIR__ . '/lib/footer.php'; ?>
