<?php
/** Variables esperadas: $titulo (string) y $tab (string con la pestaña activa). */
$hdrUser = usuario_actual();
$hdrTabs = [
    'reportes'   => ['Reportes',            'reportes.php',   ['Administrador']],
    'libros'     => ['Libros / Inventario', 'libros.php',     ['Administrador', 'Autor', 'Trabajador']],
    'categorias' => ['Categorías',          'categorias.php', ['Administrador']],
    'usuarios'   => ['Usuarios / Roles',    'usuarios.php',   ['Administrador']],
    'resenas'    => ['Moderación Reseñas',  'resenas.php',    ['Administrador', 'Trabajador']],
    'mensajes'   => ['Mensajes',            'mensajes.php',   ['Administrador', 'Trabajador']],
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · LecturaLibre</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/admin.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 no-print">
  <div class="container">
    <a class="navbar-brand fw-bold" href="../index.php">LecturaLibre</a>
    <div class="ms-auto d-flex align-items-center gap-2 text-white">
      <a href="../index.php" class="btn btn-sm btn-outline-light">Volver al sitio</a>
      <?php if ($hdrUser): ?>
        <span class="small"><?= e($hdrUser['nombre']) ?> · <strong><?= e($hdrUser['rol']) ?></strong></span>
        <a href="../auth/logout.php" class="btn btn-sm btn-outline-danger">Cerrar sesión</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<main class="container mb-5">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($titulo) ?></h1>
  </div>
  <?php if ($hdrUser): ?>
  <ul class="nav nav-tabs mb-4 no-print">
    <?php foreach ($hdrTabs as $k => [$lbl, $href, $roles]):
      if (!es_rol($hdrUser, $roles)) continue; ?>
      <li class="nav-item">
        <a class="nav-link <?= ($tab ?? '') === $k ? 'active' : '' ?>" href="<?= $href ?>"><?= e($lbl) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

<main class="container py-4">
    <?php if ($hdrUser): ?>
        <h1 class="h4 fw-bold panel-title">Panel de <?= $hdrUser['rol'] === 'Administrador' ? 'Administración Global' : e($hdrUser['rol']) ?></h1>
        <ul class="nav nav-tabs panel-tabs mb-3 no-print">
            <?php foreach ($hdrTabs as $hdrClave => [$hdrTexto, $hdrArchivo, $hdrRoles]):
                if (!in_array($hdrUser['rol'], $hdrRoles, true)) continue; ?>
                <li class="nav-item">
                    <a class="nav-link<?= ($tab ?? '') === $hdrClave ? ' active' : '' ?>" href="<?= e($hdrArchivo) ?>"><?= e($hdrTexto) ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($tab ?? '') === 'mensajes' ? 'active' : '' ?>" href="mensajes.php">Mensajes</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php foreach (pull_flash() as [$hdrTipo, $hdrMsg]): ?>
        <div class="alert alert-<?= e($hdrTipo) ?> alert-dismissible fade show no-print" role="alert">
            <?= e($hdrMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>
