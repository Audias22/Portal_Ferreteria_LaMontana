<?php
$u = user();
$publicArea = $publicArea ?? false;
$here = basename($_SERVER['SCRIPT_NAME'] ?? '');
$nav = function (string $file, string $label) use ($here): string {
    $active = $here === $file || ($file === 'ventas.php' && in_array($here, ['venta.php','venta_nueva.php'], true))
        || ($file === 'productos.php' && $here === 'producto_editar.php');
    return '<a href="' . url($file) . '"' . ($active ? ' class="active"' : '') . '>' . e($label) . '</a>';
};
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '') ?> · <?= e(PORTAL_NAME) ?></title>
<link rel="stylesheet" href="<?= url('assets/estilo.css') ?>">
</head>
<body class="<?= $publicArea ? 'public' : 'internal' ?>">
<header class="top">
  <div class="wrap top-in">
    <a class="brand" href="<?= url($publicArea || !$u ? 'index.php' : 'inicio.php') ?>"><?php require __DIR__ . '/logo.php'; ?><span><?= e(PORTAL_NAME) ?><small><?= $publicArea ? 'Materiales y herramientas' : 'Sistema interno' ?></small></span></a>
    <?php if ($publicArea): ?>
    <nav>
      <a href="<?= url('index.php') ?>"<?= $here === 'index.php' ? ' class="active"' : '' ?>>Catálogo</a>
      <a href="<?= url('index.php#contacto') ?>">Contacto</a>
      <a class="out" href="<?= url($u ? 'inicio.php' : 'login.php') ?>"><?= $u ? 'Ir al sistema' : 'Acceso empleados' ?></a>
    </nav>
    <?php elseif ($u): ?>
    <nav>
      <?= $nav('inicio.php', 'Panel') ?>
      <?php if (has_role('admin','empleado')): ?><?= $nav('ventas.php', 'Ventas') ?><?= $nav('clientes.php', 'Clientes') ?><?php endif; ?>
      <?= $nav('productos.php', 'Productos') ?>
      <?php if (has_role('admin','bodega')): ?><?= $nav('inventario.php', 'Inventario') ?><?php endif; ?>
      <?= $nav('documentos.php', 'Documentos') ?>
      <?= $nav('reportes.php', 'Reportes') ?>
      <?php if (has_role('admin')): ?><a href="<?= url('admin/usuarios.php') ?>"<?= $here === 'usuarios.php' ? ' class="active"' : '' ?>>Usuarios</a><?php endif; ?>
      <a class="who" href="<?= url('perfil.php') ?>" title="Mi perfil"><?= e($u['nombre']) ?> <em><?= e(ROLES[$u['rol']] ?? $u['rol']) ?></em></a>
      <a class="out" href="<?= url('logout.php') ?>">Salir</a>
    </nav>
    <?php endif; ?>
  </div>
</header>
<main class="wrap">
<?= flashes() ?>
