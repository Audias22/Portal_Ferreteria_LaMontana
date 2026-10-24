<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$u = require_role('admin', 'bodega');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = ['id'=>0,'codigo'=>'','nombre'=>'','descripcion'=>'','categoria'=>'','precio'=>'','existencia'=>0,'minimo'=>5,'activo'=>1];
if ($id) {
    $st = db()->prepare('SELECT * FROM productos WHERE id=?'); $st->execute([$id]);
    $p = $st->fetch() ?: redirect('productos.php');
}
$cats = db()->query('SELECT DISTINCT categoria FROM productos ORDER BY categoria')->fetchAll(PDO::FETCH_COLUMN);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $d = [
        'codigo' => strtoupper(mb_substr(trim((string)($_POST['codigo'] ?? '')), 0, 20)),
        'nombre' => mb_substr(trim((string)($_POST['nombre'] ?? '')), 0, 150),
        'descripcion' => mb_substr(trim((string)($_POST['descripcion'] ?? '')), 0, 400),
        'categoria' => mb_substr(trim((string)($_POST['categoria'] ?? '')), 0, 60),
        'precio' => round((float)str_replace(',', '.', (string)($_POST['precio'] ?? '0')), 2),
        'minimo' => max(0, (int)($_POST['minimo'] ?? 0)),
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ];
    if (!preg_match('/^[A-Z0-9-]{3,20}$/', $d['codigo'])) $error = 'El código debe tener de 3 a 20 letras, números o guiones.';
    elseif ($d['nombre'] === '' || $d['categoria'] === '') $error = 'Nombre y categoría son obligatorios.';
    elseif ($d['precio'] <= 0) $error = 'El precio debe ser mayor que cero.';
    else {
        try {
            if ($id) {
                db()->prepare('UPDATE productos SET codigo=?,nombre=?,descripcion=?,categoria=?,precio=?,minimo=?,activo=?,actualizado=NOW() WHERE id=?')
                    ->execute([$d['codigo'],$d['nombre'],$d['descripcion'],$d['categoria'],$d['precio'],$d['minimo'],$d['activo'],$id]);
                $old = (float)$p['precio'];
                if (abs($old - $d['precio']) >= 0.005) {
                    $var = $old > 0 ? round(($d['precio'] - $old) / $old * 100, 1) : 0;
                    siem('PRICE_CHANGED', sprintf('Precio de %s cambiado de Q%.2f a Q%.2f (%+.1f%%)', $d['codigo'], $old, $d['precio'], $var), 'SUCCESS',
                        ['metadata' => ['codigo'=>$d['codigo'],'producto_id'=>$id,'precio_anterior'=>$old,'precio_nuevo'=>$d['precio'],'variacion_pct'=>$var]]);
                }
                if ((int)$p['activo'] !== $d['activo']) siem('ADMIN_ACTION', ($d['activo'] ? 'Reactivación' : 'Desactivación') . ' del producto ' . $d['codigo'], 'SUCCESS');
                flash('ok', 'Producto actualizado.');
            } else {
                db()->prepare('INSERT INTO productos(codigo,nombre,descripcion,categoria,precio,existencia,minimo,activo,actualizado) VALUES(?,?,?,?,?,0,?,?,NOW())')
                    ->execute([$d['codigo'],$d['nombre'],$d['descripcion'],$d['categoria'],$d['precio'],$d['minimo'],$d['activo']]);
                siem('PRODUCT_CREATED', 'Producto creado: ' . $d['codigo'] . ' ' . $d['nombre'] . ' a Q' . number_format($d['precio'], 2), 'SUCCESS',
                    ['metadata' => ['codigo'=>$d['codigo'],'precio'=>$d['precio']]]);
                flash('ok', 'Producto creado. Registre su existencia en Inventario.');
            }
            redirect('productos.php');
        } catch (PDOException $e) {
            $error = 'No se pudo guardar. Verifique que el código no esté repetido.';
        }
    }
    $p = array_merge($p, $d);
}
$title = $id ? 'Editar producto' : 'Nuevo producto';
require __DIR__ . '/lib/header.php';
?>
<p class="crumbs"><a href="<?= url('productos.php') ?>">Productos</a> / <?= e($title) ?></p>
<h1><?= e($title) ?></h1>
<?php if ($error): ?><div class="msg err"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="box form-grid">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <label>Código<input name="codigo" value="<?= e($p['codigo']) ?>" maxlength="20" required></label>
  <label>Categoría<input name="categoria" value="<?= e($p['categoria']) ?>" list="cats" maxlength="60" required><datalist id="cats"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></label>
  <label class="wide">Nombre<input name="nombre" value="<?= e($p['nombre']) ?>" maxlength="150" required></label>
  <label class="wide">Descripción<input name="descripcion" value="<?= e($p['descripcion'] ?? '') ?>" maxlength="400"></label>
  <label>Precio (Q)<input name="precio" value="<?= e((string)$p['precio']) ?>" inputmode="decimal" required></label>
  <label>Existencia mínima<input type="number" name="minimo" min="0" value="<?= (int)$p['minimo'] ?>"></label>
  <?php if ($id): ?><label>Existencia actual<input value="<?= (int)$p['existencia'] ?>" disabled><small class="muted">Se modifica desde Inventario.</small></label><?php endif; ?>
  <label class="check"><input type="checkbox" name="activo" <?= $p['activo'] ? 'checked' : '' ?>> Visible y disponible para la venta</label>
  <div class="wide"><button>Guardar</button> <a href="<?= url('productos.php') ?>">Cancelar</a></div>
</form>
<?php require __DIR__ . '/lib/footer.php'; ?>
