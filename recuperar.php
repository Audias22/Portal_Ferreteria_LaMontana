<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $who = mb_substr(trim((string)($_POST['usuario'] ?? '')), 0, 150);
    siem_request('Solicitud de recuperación de contraseña', 'usuario=' . $who);
    $st = db()->prepare('SELECT id,usuario,correo FROM usuarios WHERE (usuario=? OR correo=?) AND activo=1 LIMIT 1');
    $st->execute([$who, $who]);
    $u = $st->fetch();
    if ($u && $u['correo']) {
        // El enlace solo se guarda como hash: si alguien lee la base de datos no puede usarlo.
        $token = bin2hex(random_bytes(32));
        db()->prepare('INSERT INTO password_resets(usuario_id,token_hash,expira,ip) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE),?)')
            ->execute([$u['id'], hash('sha256', $token), client_ip()]);
        $link = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') . url('restablecer.php?token=' . $token);
        send_mail_lab($u['correo'], 'Recuperación de contraseña', "Se solicitó cambiar la contraseña de su cuenta.\nEnlace (válido 30 minutos): $link\nSi no fue usted, ignore este mensaje.");
    }
    // El SIEM no recibe si la cuenta existe: así un atacante tampoco puede averiguarlo por aquí.
    siem('PASSWORD_RESET_REQUEST', 'Solicitud de recuperación de contraseña para "' . $who . '"', 'INFO', ['username' => $who !== '' ? $who : 'desconocido']);
    $sent = true;
}
$title = 'Recuperar contraseña';
require __DIR__ . '/lib/header.php';
?>
<div class="login">
  <h1>Recuperar contraseña</h1>
  <?php if ($sent): ?>
    <div class="msg ok">Si los datos corresponden a una cuenta activa, enviamos un enlace al correo registrado. El enlace vence en 30 minutos.</div>
    <p class="login-links"><a href="<?= url('login.php') ?>">Volver al inicio de sesión</a></p>
  <?php else: ?>
  <p class="muted">Escriba su usuario o su correo. Le enviaremos un enlace para crear una contraseña nueva.</p>
  <form method="post">
    <?= csrf_field() ?>
    <label>Usuario o correo<input name="usuario" maxlength="150" required></label>
    <button>Enviar enlace</button>
  </form>
  <p class="login-links"><a href="<?= url('login.php') ?>">Volver</a></p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/lib/footer.php'; ?>
