<?php
/** Variables esperadas: $titulo (string) y $tab (string con la pestaña activa). */
$hdrUser = usuario_actual();
$hdrTabs = [
    'reportes'   => ['Reportes',            'reportes.php',   ['Administrador']],
    'libros'     => ['Libros / Inventario', 'libros.php',     ['Administrador', 'Autor', 'Trabajador']],
    'ventas'     => ['Ventas',              'ventas.php',     ['Administrador', 'Autor']],
    'categorias' => ['Categorías',          'categorias.php', ['Administrador']],
    'usuarios'   => ['Usuarios / Roles',    'usuarios.php',   ['Administrador']],
    'resenas'    => ['Moderación Reseñas',  'resenas.php',    ['Administrador', 'Trabajador']],
    'mensajes'   => ['Mensajes',            'mensajes.php',   ['Administrador', 'Trabajador']],
];

$hdrCartCount = 0;
if ($hdrUser) {
    try {
        $stCart = db()->prepare('SELECT COUNT(*) FROM carrito WHERE id_usuario = ?');
        $stCart->execute([(int)$hdrUser['id']]);
        $hdrCartCount = (int)$stCart->fetchColumn();
    } catch (Throwable $ignored) {
        $hdrCartCount = 0;
    }
}

$hdrPanelInfo = [
    'Administrador' => ['Panel Admin',      'reportes.php'],
    'Trabajador'    => ['Panel Trabajador', 'resenas.php'],
    'Autor'         => ['Panel Autor',      'libros.php'],
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> - LecturaLibre</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/estilos.css?v=4" rel="stylesheet">
  <link href="assets/admin.css?v=2" rel="stylesheet">
</head>
<body>

<header class="no-print">
  <div class="container navbar">
    <a href="../index.php" class="logo">
      <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
    </a>

    <details class="nav-menu-toggle">
      <summary class="btn search-icon" aria-label="Menú" aria-controls="nav-menu">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
      </summary>
    </details>

    <form class="search-bar" action="../catalogo.php" method="get" role="search">
      <input type="search" id="global-search" name="q" aria-label="Buscar por título, autor o categoría"
             placeholder="Buscar libros…">
      <button type="submit" class="btn search-icon" aria-label="Buscar">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
      </button>
    </form>

    <div class="nav-menu" id="nav-menu">
      <ul class="nav-links">
        <li><a href="../index.php">Inicio</a></li>
        <li><a href="../catalogo.php">Catálogo</a></li>
        <li><a href="../nosotros.php">Nosotros</a></li>
        <li><a href="../contacto.php">Contacto</a></li>
        <?php if ($hdrUser && isset($hdrPanelInfo[$hdrUser['rol']])):
          [$panelLabel, $panelHref] = $hdrPanelInfo[$hdrUser['rol']]; ?>
          <li><a href="<?= e($panelHref) ?>" class="active-link"><?= e($panelLabel) ?></a></li>
        <?php endif; ?>
      </ul>

      <div class="user-actions">
        <button type="button" class="btn" onclick="window.location.href='../carrito.php'">
          <i class="fa-solid fa-cart-shopping"></i> <span id="cart-count" class="badge"><?= $hdrCartCount ?></span>
        </button>

        <?php if ($hdrUser): ?>
          <div id="user-menu" style="display: flex; gap: 10px; align-items: center;">
            <button type="button" class="btn" onclick="window.location.href='../perfil.php'">
              <i class="fa-solid fa-circle-user"></i> <span><?= e($hdrUser['nombre']) ?></span>
            </button>
            <a href="../auth/logout.php" class="btn btn-danger" title="Cerrar sesión">
              <i class="fa-solid fa-right-from-bracket"></i>
            </a>
          </div>
        <?php else: ?>
          <a href="../index.php" class="btn btn-primary">Volver al inicio</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<main class="container">
  <?php if ($hdrUser): ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
      <div>
        <h2 class="section-title mb-1">
          Panel de <?= $hdrUser['rol'] === 'Administrador' ? 'Administración Global' : e($hdrUser['rol']) ?>
        </h2>
        <div class="small text-muted">
          Sesión activa: <strong><?= e($hdrUser['nombre']) ?></strong> (<?= e($hdrUser['rol']) ?>) · <?= e($titulo) ?>
        </div>
      </div>
    </div>

    <ul class="nav nav-tabs panel-tabs mb-4 no-print">
      <?php foreach ($hdrTabs as $hdrClave => [$hdrTexto, $hdrArchivo, $hdrRoles]):
        if (!es_rol($hdrUser, ...$hdrRoles)) continue; ?>
        <li class="nav-item">
          <a class="nav-link<?= ($tab ?? '') === $hdrClave ? ' active' : '' ?>" href="<?= e($hdrArchivo) ?>">
            <?= e($hdrTexto) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <h2 class="section-title"><?= e($titulo) ?></h2>
  <?php endif; ?>

  <?php foreach (pull_flash() as [$hdrTipo, $hdrMsg]): ?>
    <div class="alert alert-<?= e($hdrTipo) ?> alert-dismissible fade show no-print" role="alert">
      <?= e($hdrMsg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
  <?php endforeach; ?>