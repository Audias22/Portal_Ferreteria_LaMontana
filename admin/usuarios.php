<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $accion = (string)($_POST['accion'] ?? '');
    if ($accion === 'crear') {
        $usuario = strtolower(mb_substr(trim((string)($_POST['usuario'] ?? '')), 0, 60));
        $nombre = mb_substr(trim((string)($_POST['nombre'] ?? '')), 0, 120);
        $correo = mb_substr(trim((string)($_POST['correo'] ?? '')), 0, 150);
        $rol = array_key_exists($_POST['rol'] ?? '', ROLES) ? $_POST['rol'] : 'empleado';
        $clave = (string)($_POST['clave'] ?? '');
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $usuario) || $nombre === '') flash('err', 'Usuario (3 a 60 letras o números) y nombre son obligatorios.');
        elseif ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) flash('err', 'Correo inválido.');
        elseif ($p = password_problem($clave)) flash('err', $p);
        else {
            try {
                db()->prepare('INSERT INTO usuarios(usuario,nombre,correo,password_hash,rol) VALUES(?,?,?,?,?)')->execute([$usuario, $nombre, $correo ?: null, password_hash($clave, PASSWORD_DEFAULT), $rol]);
                siem('ADMIN_ACTION', 'Creación del usuario ' . $usuario . ' con rol ' . $rol, 'SUCCESS');
                flash('ok', 'Usuario creado.');
            } catch (PDOException $e) { flash('err', 'Ese usuario ya existe.'); }
        }
        redirect('admin/usuarios.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    $st = db()->prepare('SELECT * FROM usuarios WHERE id=?');
    $st->execute([$id]);
    $obj = $st->fetch();
    if ($obj && $accion === 'rol') {
        $rol = array_key_exists($_POST['rol'] ?? '', ROLES) ? $_POST['rol'] : 'empleado';
        if ($obj['id'] == $u['id']) flash('err', 'No puede cambiar su propio rol.');
        elseif ($rol !== $obj['rol']) {
            db()->prepare('UPDATE usuarios SET rol=? WHERE id=?')->execute([$rol, $id]);
            siem('ROLE_CHANGED', 'Rol de ' . $obj['usuario'] . ' cambiado de ' . $obj['rol'] . ' a ' . $rol, 'SUCCESS');
            flash('ok', 'Rol actualizado.');
        }
    }
    if ($obj && $accion === 'estado') {
        if ($obj['id'] == $u['id']) flash('err', 'No puede desactivar su propia cuenta.');
        else {
            $nuevo = $obj['activo'] ? 0 : 1;
            db()->prepare('UPDATE usuarios SET activo=? WHERE id=?')->execute([$nuevo, $id]);
            siem('ADMIN_ACTION', ($nuevo ? 'Activación' : 'Desactivación') . ' de la cuenta ' . $obj['usuario'], 'SUCCESS');
            flash('ok', 'Estado actualizado.');
        }
    }
    if ($obj && $accion === 'clave') {
        $clave = (string)($_POST['clave'] ?? '');
        if ($p = password_problem($clave)) flash('err', $p);
        else {
            db()->prepare('UPDATE usuarios SET password_hash=? WHERE id=?')->execute([password_hash($clave, PASSWORD_DEFAULT), $id]);
            siem('PASSWORD_RESET', 'Restablecimiento de contraseña de ' . $obj['usuario'] . ' por el administrador', 'SUCCESS');
            flash('ok', 'Contraseña restablecida.');
        }
    }
    redirect('admin/usuarios.php');
}
$rows = db()->query('SELECT id,usuario,nombre,correo,rol,activo,ultimo_acceso FROM usuarios ORDER BY id')->fetchAll();
$title = 'Usuarios';
require __DIR__ . '/../lib/header.php';
$roleOpts = fn(string $sel) => implode('', array_map(fn($k, $v) => '<option value="' . $k . '"' . ($k === $sel ? ' selected' : '') . '>' . e($v) . '</option>', array_keys(ROLES), ROLES));
?>
<h1>Usuarios del portal</h1>
<table>
  <thead><tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Contraseña</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['usuario']) ?></td>
    <td><?= e($r['nombre']) ?><br><span class="muted small"><?= e($r['correo'] ?? '') ?></span></td>
    <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="accion" value="rol">
      <select name="rol"><?= $roleOpts($r['rol']) ?></select> <button class="small">Guardar</button></form></td>
    <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="accion" value="estado">
      <?= $r['activo'] ? 'Activo' : '<span class="muted">Inactivo</span>' ?> <button class="small ghost"><?= $r['activo'] ? 'Desactivar' : 'Activar' ?></button></form></td>
    <td><?= e($r['ultimo_acceso'] ?? 'Nunca') ?></td>
    <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="accion" value="clave">
      <input type="password" name="clave" minlength="10" placeholder="Nueva" required> <button class="small">Restablecer</button></form></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<form method="post" class="box form-grid">
  <h2 class="wide">Nuevo usuario</h2>
  <?= csrf_field() ?><input type="hidden" name="accion" value="crear">
  <label>Usuario<input name="usuario" maxlength="60" required></label>
  <label>Nombre<input name="nombre" maxlength="120" required></label>
  <label>Correo<input type="email" name="correo" maxlength="150"></label>
  <label>Rol<select name="rol"><?= $roleOpts('empleado') ?></select></label>
  <label>Contraseña inicial<input type="password" name="clave" minlength="10" required></label>
  <div class="wide"><button>Crear usuario</button></div>
</form>
<?php require __DIR__ . '/../lib/footer.php'; ?>
