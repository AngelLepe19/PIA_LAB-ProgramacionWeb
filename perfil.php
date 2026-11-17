<?php
session_start();
require_once 'config/conexion.php';

// Protección de ruta: solo usuarios con sesión iniciada pueden ver su perfil
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php?error=inicia_sesion");
    exit;
}

$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = true;
$id_usuario = (int)$_SESSION['id_usuario'];
$id_rol = (int)$_SESSION['id_rol'];
$nombre_usuario = $_SESSION['nombre_completo'];

// Pestaña inicial (datos | favoritos | historial)
$tab_activa = $_GET['tab'] ?? 'datos';
if (!in_array($tab_activa, ['datos', 'favoritos', 'historial'], true)) {
    $tab_activa = 'datos';
}

// Obtener datos reales del usuario desde MySQL
$sql_user = "SELECT u.nombre_completo, u.correo, r.nombre AS rol_nombre
             FROM usuarios u
             INNER JOIN roles r ON u.id_rol = r.id_rol
             WHERE u.id_usuario = ?";
$stmt_u = $conn->prepare($sql_user);
$stmt_u->bind_param("i", $id_usuario);
$stmt_u->execute();
$datos_usuario = $stmt_u->get_result()->fetch_assoc();
$stmt_u->close();

// Contar libros en el carrito real del usuario
$stmt_cart = $conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
$stmt_cart->bind_param("i", $id_usuario);
$stmt_cart->execute();
$res_cart = $stmt_cart->get_result()->fetch_assoc();
$cart_count = (int)($res_cart['total'] ?? 0);
$stmt_cart->close();

// Lista de deseos real desde MySQL
$sql_fav = "SELECT l.id_libro, l.titulo, l.precio, l.portada, a.nombre_artistico AS autor,
                   (SELECT ROUND(AVG(r.puntuacion), 1)
                      FROM resenas r
                     WHERE r.id_libro = l.id_libro AND r.estado = 'aprobada') AS promedio
            FROM favoritos f
            INNER JOIN libros l ON f.id_libro = l.id_libro
            INNER JOIN autores a ON l.id_autor = a.id_autor
            WHERE f.id_usuario = ? AND l.estado = 'aprobado'
            ORDER BY f.fecha_agregado DESC";
$stmt_fav = $conn->prepare($sql_fav);
$stmt_fav->bind_param("i", $id_usuario);
$stmt_fav->execute();
$favoritos = $stmt_fav->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_fav->close();

// Historial de pedidos y descargas reales desde MySQL
$sql_hist = "SELECT p.id_pedido, p.fecha_pedido, p.estado,
                    dp.id_libro, dp.precio_unitario,
                    l.titulo, a.nombre_artistico AS autor,
                    (SELECT COUNT(*)
                       FROM descargas d
                      WHERE d.id_pedido = dp.id_pedido AND d.id_libro = dp.id_libro) AS veces_descargado
             FROM pedidos p
             INNER JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido
             INNER JOIN libros l ON dp.id_libro = l.id_libro
             INNER JOIN autores a ON l.id_autor = a.id_autor
             WHERE p.id_usuario = ? AND p.estado <> 'cancelado'
             ORDER BY p.fecha_pedido DESC, p.id_pedido DESC";
$stmt_hist = $conn->prepare($sql_hist);
$stmt_hist->bind_param("i", $id_usuario);
$stmt_hist->execute();
$historial = $stmt_hist->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_hist->close();

// Mensajes de aviso
$alerta_texto = '';
$alerta_tipo = '';
if (isset($_GET['perfil']) && $_GET['perfil'] === 'actualizado') {
    $alerta_texto = 'Tus datos se actualizaron correctamente.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['error'])) {
    $alerta_tipo = 'error';
    switch ($_GET['error']) {
        case 'campos_vacios':
            $alerta_texto = 'El nombre completo no puede quedar vacío.';
            break;
        default:
            $alerta_texto = 'Ocurrió un error al actualizar tus datos.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - LecturaLibre</title>
    <link rel="stylesheet" href="assets/css/estilos.css?v=4">
    <script src="assets/js/main.js"></script>
</head>
<body>

    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
            </a>

            <details class="nav-menu-toggle">
                <summary class="btn search-icon" aria-label="Menú" aria-controls="nav-menu">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </summary>
            </details>

            <form class="search-bar" action="catalogo.php" method="get" role="search">
                <input type="search" id="global-search" name="q" aria-label="Buscar por título, autor o categoría"
                       placeholder="Buscar libros…">
                <button type="submit" class="btn search-icon" aria-label="Buscar">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </button>
            </form>

            <div class="nav-menu" id="nav-menu">
                <ul class="nav-links">
                    <li><a href="index.php" id="link-inicio" class="<?= $pagina_actual === 'index.php' ? 'active-link' : '' ?>">Inicio</a></li>
                    <li><a href="catalogo.php" id="link-catalogo" class="<?= $pagina_actual === 'catalogo.php' ? 'active-link' : '' ?>">Catálogo</a></li>
                    <li><a href="nosotros.php" id="link-nosotros" class="<?= $pagina_actual === 'nosotros.php' ? 'active-link' : '' ?>">Nosotros</a></li>
                    <li><a href="contacto.php" id="link-contacto" class="<?= $pagina_actual === 'contacto.php' ? 'active-link' : '' ?>">Contacto</a></li>

                    <?php if ($id_rol === 2): ?>
                        <li id="author-nav-item"><a href="admin/libros.php">Panel Autor</a></li>
                    <?php elseif ($id_rol === 3): ?>
                        <li id="worker-nav-item"><a href="admin/resenas.php">Panel Trabajador</a></li>
                    <?php elseif ($id_rol === 4): ?>
                        <li id="admin-nav-item"><a href="admin/reportes.php">Panel Admin</a></li>
                    <?php endif; ?>
                </ul>

                <div class="user-actions">
                    <button class="btn" onclick="window.location.href='carrito.php'">
                        <i class="fa-solid fa-cart-shopping"></i> <span id="cart-count" class="badge"><?= $cart_count ?></span>
                    </button>

                    <div id="user-menu" style="display: flex; gap: 10px; align-items: center;">
                        <button class="btn" onclick="window.location.href='perfil.php'">
                            <i class="fa-solid fa-circle-user"></i> <span id="user-display-name"><?= htmlspecialchars($nombre_usuario) ?></span>
                        </button>
                        <a href="auth/logout.php" class="btn btn-danger" title="Cerrar sesión">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <?php if ($alerta_texto !== ''): ?>
        <div class="container" style="margin-top: 15px;">
            <div style="padding: 12px 16px; border-radius: 6px; font-size: 0.9rem; background: <?= $alerta_tipo === 'exito' ? '#d4edda' : '#f8d7da' ?>; color: <?= $alerta_tipo === 'exito' ? '#155724' : '#721c24' ?>; border: 1px solid <?= $alerta_tipo === 'exito' ? '#c3e6cb' : '#f5c6cb' ?>;">
                <?= htmlspecialchars($alerta_texto) ?>
            </div>
        </div>
    <?php endif; ?>

    <main class="container">
        <section id="perfil">
            <h2 class="section-title">Mi Cuenta</h2>
            <div class="tab-menu">
                <button class="tab-btn <?= $tab_activa === 'datos' ? 'active' : '' ?>" data-profile-tab="datos" onclick="switchProfileTab('datos')">Mis Datos</button>
                <button class="tab-btn <?= $tab_activa === 'favoritos' ? 'active' : '' ?>" data-profile-tab="favoritos" onclick="switchProfileTab('favoritos')">
                    Lista de Deseos (<?= count($favoritos) ?>)
                </button>
                <button class="tab-btn <?= $tab_activa === 'historial' ? 'active' : '' ?>" data-profile-tab="historial" onclick="switchProfileTab('historial')">
                    Historial de Lecturas / Descargas (<?= count($historial) ?>)
                </button>
            </div>

            <div id="tab-datos" class="profile-tab" style="display: <?= $tab_activa === 'datos' ? 'block' : 'none' ?>;">
                <form action="auth/actualizar_perfil.php" method="POST" style="max-width: 500px;">
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" name="nombre_completo" id="prof-name" value="<?= htmlspecialchars($datos_usuario['nombre_completo'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Correo Electrónico</label>
                        <input type="email" id="prof-email" value="<?= htmlspecialchars($datos_usuario['correo'] ?? '') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Rol de Usuario</label>
                        <input type="text" id="prof-role" value="<?= htmlspecialchars($datos_usuario['rol_nombre'] ?? '') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Nueva Contraseña</label>
                        <input type="password" name="nueva_password" id="prof-pass" autocomplete="new-password" placeholder="Dejar en blanco para conservar">
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </form>
            </div>

            <div id="tab-favoritos" class="profile-tab" style="display: <?= $tab_activa === 'favoritos' ? 'block' : 'none' ?>;">
                <?php if (count($favoritos) > 0): ?>
                    <div class="book-grid" id="perfil-favoritos">
                        <?php foreach ($favoritos as $libro): ?>
                            <?php
                            $id_lib = (int)$libro['id_libro'];
                            $rating = $libro['promedio'] !== null ? $libro['promedio'] : 'Nuevo';
                            $precio = ((float)$libro['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$libro['precio'], 2);
                            ?>
                            <div class="book-card">
                                <a href="php/favorito_toggle.php?id_libro=<?= $id_lib ?>" title="Quitar de favoritos" style="color: inherit;">
                                    <i class="fa-solid fa-heart wishlist-icon"></i>
                                </a>
                                <div class="placeholder-box book-card-img" onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'">PORTADA</div>
                                <h4 onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'" style="cursor: pointer;">
                                    <?= htmlspecialchars($libro['titulo']) ?>
                                </h4>
                                <p style="font-size: 0.8rem; color: var(--wf-gray);"><?= htmlspecialchars($libro['autor']) ?></p>
                                <div class="stars">⭐ <?= htmlspecialchars($rating) ?></div>
                                <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="color: #27ae60;"><?= $precio ?></strong>
                                    <a href="php/carrito_agregar.php?id_libro=<?= $id_lib ?>" class="btn" title="Agregar al carrito">
                                        <i class="fa-solid fa-cart-shopping"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--wf-gray); margin-bottom: 15px;">Aún no tienes libros en tu lista de deseos.</p>
                    <a href="catalogo.php" class="btn btn-primary">Explorar Catálogo</a>
                <?php endif; ?>
            </div>

            <div id="tab-historial" class="profile-tab" style="display: <?= $tab_activa === 'historial' ? 'block' : 'none' ?>;">
                <?php if (count($historial) > 0): ?>
                    <div class="table-scroll" tabindex="0" role="region" aria-label="Tabla de historial de lecturas y descargas">
                        <table class="wf-table">
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Libro</th>
                                    <th>Fecha</th>
                                    <th>Costo</th>
                                    <th>Descargas</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="perfil-historial-body">
                                <?php foreach ($historial as $item): ?>
                                    <tr>
                                        <td>
                                            <a href="confirmacion.php?pedido=<?= (int)$item['id_pedido'] ?>" style="text-decoration: underline;">
                                                #<?= (int)$item['id_pedido'] ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="detalle.php?id=<?= (int)$item['id_libro'] ?>" style="font-weight: 600;">
                                                <?= htmlspecialchars($item['titulo']) ?>
                                            </a>
                                            <div style="font-size: 0.8rem; color: var(--wf-gray);">
                                                <?= htmlspecialchars($item['autor']) ?>
                                            </div>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($item['fecha_pedido'])) ?></td>
                                        <td><?= (float)$item['precio_unitario'] == 0 ? 'Gratis' : '$' . number_format((float)$item['precio_unitario'], 2) ?></td>
                                        <td><?= (int)$item['veces_descargado'] ?></td>
                                        <td>
                                            <a href="php/descargar.php?pedido=<?= (int)$item['id_pedido'] ?>&libro=<?= (int)$item['id_libro'] ?>" class="btn btn-primary">
                                                <i class="fa-solid fa-download"></i> Descargar PDF
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--wf-gray); margin-bottom: 15px;">Aún no has adquirido ni descargado ningún libro.</p>
                    <a href="catalogo.php" class="btn btn-primary">Explorar Catálogo</a>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer>
        <div class="container footer-cols">
            <div>
                <h4>LecturaLibre</h4>
                <p style="font-size: 0.85rem;">Plataforma de lectura digital libre.</p>
                <p style="font-size: 0.85rem;">&copy; <?= date('Y') ?> LecturaLibre</p>
            </div>

            <nav aria-label="Navegación del pie de página">
                <h4>Navegación</h4>
                <p style="font-size: 0.85rem;">
                    <a href="index.php">Inicio</a>
                    <span aria-hidden="true"> | </span>
                    <a href="catalogo.php">Catálogo</a>
                    <span aria-hidden="true"> | </span>
                    <a href="contacto.php">Contacto</a>
                </p>
            </nav>
        </div>
    </footer>
</body>
</html>