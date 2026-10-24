<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$reset = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $st = db()->prepare('SELECT r.*, u.usuario FROM password_resets r JOIN usuarios u ON u.id=r.usuario_id WHERE r.token_hash=? AND r.usado IS NULL AND r.expira > NOW() AND u.activo=1');
    $st->execute([hash('sha256', $token)]);
    $reset = $st->fetch() ?: null;
}
if (!$reset) {
    siem('PASSWORD_RESET_INVALID_TOKEN', 'Enlace de recuperación inválido, vencido o ya usado', 'FAILED', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET', 'endpoint' => mb_substr(strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '', 0, 200)]);
}
$error = '';
if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $new = (string)($_POST['nueva'] ?? '');
    if ($new !== (string)($_POST['confirmar'] ?? '')) $error = 'Las contraseñas no coinciden.';
    elseif ($p = password_problem($new)) $error = $p;
    else {
        db()->prepare('UPDATE usuarios SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $reset['usuario_id']]);
        db()->prepare('UPDATE password_resets SET usado=NOW() WHERE id=?')->execute([$reset['id']]);
        siem('PASSWORD_RESET_COMPLETED', 'Contraseña restablecida con enlace de recuperación', 'SUCCESS', ['username' => $reset['usuario']]);
        flash('ok', 'Contraseña actualizada. Ya puede iniciar sesión.');
        redirect('login.php');
    }
}
$title = 'Nueva contraseña';
require __DIR__ . '/lib/header.php';
?>
<div class="login">
  <h1>Nueva contraseña</h1>
  <?php if (!$reset): ?>
    <div class="msg err">El enlace no es válido, ya se usó o venció. Solicite uno nuevo.</div>
    <p class="login-links"><a href="<?= url('recuperar.php') ?>">Solicitar otro enlace</a></p>
  <?php else: ?>
    <p class="muted">Cuenta: <strong><?= e($reset['usuario']) ?></strong></p>
    <?php if ($error): ?><div class="msg err"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <label>Nueva contraseña<input type="password" name="nueva" minlength="10" autocomplete="new-password" required></label>
      <label>Confirmar<input type="password" name="confirmar" minlength="10" autocomplete="new-password" required></label>
      <p class="muted small">Mínimo 10 caracteres, con letras y números.</p>
      <button>Guardar contraseña</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/lib/footer.php'; ?>
