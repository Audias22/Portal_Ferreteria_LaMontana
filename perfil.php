<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_login();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $row = db()->prepare('SELECT password_hash FROM usuarios WHERE id=?');
    $row->execute([$u['id']]);
    $hash = (string)$row->fetchColumn();
    $new = (string)($_POST['nueva'] ?? '');
    if (!password_verify((string)($_POST['actual'] ?? ''), $hash)) {
        $error = 'La contraseña actual no es correcta.';
        siem('PASSWORD_CHANGE_FAILED', 'Intento de cambio de contraseña con clave actual incorrecta', 'FAILED');
    } elseif ($new !== (string)($_POST['confirmar'] ?? '')) $error = 'Las contraseñas nuevas no coinciden.';
    elseif ($p = password_problem($new)) $error = $p;
    elseif (password_verify($new, $hash)) $error = 'La nueva contraseña debe ser distinta de la actual.';
    else {
        db()->prepare('UPDATE usuarios SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        siem('PASSWORD_CHANGE', 'Cambio de contraseña propia', 'SUCCESS');
        flash('ok', 'Contraseña actualizada.');
        redirect('perfil.php');
    }
}
$info = db()->prepare('SELECT usuario,nombre,correo,rol,ultimo_acceso,creado FROM usuarios WHERE id=?');
$info->execute([$u['id']]);
$info = $info->fetch();
$title = 'Mi perfil';
require __DIR__ . '/lib/header.php';
?>
<h1>Mi perfil</h1>
<div class="cols">
  <div class="box">
    <h2>Datos de la cuenta</h2>
    <dl class="kv"><dt>Usuario</dt><dd><?= e($info['usuario']) ?></dd><dt>Nombre</dt><dd><?= e($info['nombre']) ?></dd><dt>Correo</dt><dd><?= e($info['correo'] ?? '—') ?></dd><dt>Rol</dt><dd><?= e(ROLES[$info['rol']] ?? $info['rol']) ?></dd><dt>Último acceso</dt><dd><?= e($info['ultimo_acceso'] ?? '—') ?></dd></dl>
  </div>
  <div class="box">
    <h2>Cambiar contraseña</h2>
    <?php if ($error): ?><div class="msg err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <label>Contraseña actual<input type="password" name="actual" autocomplete="current-password" required></label>
      <label>Nueva contraseña<input type="password" name="nueva" minlength="10" autocomplete="new-password" required></label>
      <label>Confirmar nueva<input type="password" name="confirmar" minlength="10" autocomplete="new-password" required></label>
      <p class="muted small">Mínimo 10 caracteres, con letras y números.</p>
      <button>Guardar</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/lib/footer.php'; ?>
