<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_login();
require __DIR__ . '/lib/documentos_lista.php';
$title = 'Documentos';
require __DIR__ . '/lib/header.php';
?>
<h1>Documentos</h1>
<table>
  <thead><tr><th>Documento</th><th>Descripción</th><th></th></tr></thead>
  <tbody>
  <?php foreach (DOCUMENTOS as $archivo => $desc): ?>
    <tr><td><?= e($archivo) ?></td><td><?= e($desc) ?></td><td><a href="<?= url('descargar.php?archivo=' . urlencode($archivo)) ?>">Descargar</a></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require __DIR__ . '/lib/footer.php'; ?>
