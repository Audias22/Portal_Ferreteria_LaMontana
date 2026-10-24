<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
// Bandeja de correo del laboratorio: el portal no tiene servidor de correo real,
// así que los mensajes se guardan en logs/correo_saliente.log. Solo se puede ver desde la propia PC del servidor.
if (!in_array(client_ip(), ['127.0.0.1', '::1'], true)) {
    siem_request('Intento de abrir la bandeja de correo del laboratorio desde la red');
    http_response_code(404);
    exit;
}
$file = __DIR__ . '/logs/correo_saliente.log';
$msgs = is_file($file) ? array_reverse(array_filter(array_map(fn($l) => json_decode($l, true), file($file, FILE_IGNORE_NEW_LINES)))) : [];
$msgs = array_slice($msgs, 0, 20);
$title = 'Correo del laboratorio';
require __DIR__ . '/lib/header.php';
?>
<h1>Correo saliente (laboratorio)</h1>
<p class="muted">El portal no tiene servidor de correo. Aquí se ven los mensajes que habría enviado. Solo se puede abrir desde la PC del servidor.</p>
<?php foreach ($msgs as $m): ?>
<div class="box mail"><div class="muted small"><?= e($m['fecha'] ?? '') ?> · Para: <?= e($m['para'] ?? '') ?></div><h2><?= e($m['asunto'] ?? '') ?></h2><pre><?= e($m['cuerpo'] ?? '') ?></pre></div>
<?php endforeach; ?>
<?php if (!$msgs): ?><div class="box">No hay mensajes.</div><?php endif; ?>
<?php require __DIR__ . '/lib/footer.php'; ?>
