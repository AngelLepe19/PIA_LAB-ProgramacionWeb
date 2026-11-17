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
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'Panel') ?> · LecturaLibre</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/admin.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white border-bottom no-print">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.php"><span class="brand-mark">▮▮</span> LecturaLibre</a>
        <?php if ($hdrUser): ?>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border"><?= e($hdrUser['nombre'] ?? 'Usuario') ?> · <?= e($hdrUser['rol']) ?></span>
                <a class="btn btn-sm btn-outline-danger" href="logout.php">Cerrar sesión</a>
            </div>
        <?php endif; ?>
    </div>
</nav>

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
