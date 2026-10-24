<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
if (user()) redirect('inicio.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $usuario = mb_substr(trim((string)($_POST['usuario'] ?? '')), 0, 60);
    $clave = (string)($_POST['clave'] ?? '');

    // Se registra lo que el usuario escribió en el campo "usuario" (nunca la contraseña).
    siem_request('Solicitud de inicio de sesión', 'usuario=' . $usuario);

    $st = db()->prepare('SELECT * FROM usuarios WHERE usuario=? AND activo=1 LIMIT 1');
    $st->execute([$usuario]);
    $row = $st->fetch();
    if ($row && password_verify($clave, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['portal_user'] = ['id'=>(int)$row['id'], 'usuario'=>$row['usuario'], 'nombre'=>$row['nombre'], 'rol'=>$row['rol']];
        $_SESSION['last_activity'] = time();
        db()->prepare('UPDATE usuarios SET ultimo_acceso=NOW() WHERE id=?')->execute([$row['id']]);
        siem('LOGIN_SUCCESS', 'Inicio de sesión en el portal', 'SUCCESS');
        redirect('inicio.php');
    }
    siem('LOGIN_FAILED', $row ? 'Contraseña incorrecta' : 'Usuario inexistente', 'FAILED', ['username' => $usuario !== '' ? $usuario : 'desconocido']);
    $error = 'Usuario o contraseña incorrectos.';
}
$title = 'Acceso de empleados';
require __DIR__ . '/lib/header.php';
?>
<div class="login">
  <h1>Acceso de empleados</h1>
  <p class="muted">Ventas, inventario y reportes de la ferretería.</p>
  <?php if ($error): ?><div class="msg err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Usuario<input name="usuario" maxlength="60" autocomplete="username" required></label>
    <label>Contraseña<input type="password" name="clave" autocomplete="current-password" required></label>
    <button>Entrar</button>
  </form>
  <p class="login-links"><a href="<?= url('recuperar.php') ?>">¿Olvidó su contraseña?</a> · <a href="<?= url('index.php') ?>">Ver catálogo</a></p>
</div>
<?php require __DIR__ . '/lib/footer.php'; ?>
