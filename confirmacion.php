<?php
/*
 * Página de confirmación de pedido de LecturaLibre.
 * Muestra el comprobante de una orden recién completada (confirmacion.php?pedido=X),
 * valida que el pedido pertenezca exclusivamente al usuario que inició sesión
 * y ofrece los botones directos para descargar cada libro en formato PDF.
 */
session_start();
require_once 'config/conexion.php';

// Datos básicos de la sesión actual
$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = isset($_SESSION['id_usuario']);
$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

// Si alguien entra sin sesión iniciada, lo mandamos al inicio
if (!$logueado) {
    header('Location: index.php?error=inicia_sesion');
    exit;
}

// Contamos los artículos actuales del carrito (después de confirmar el pedido debe aparecer en 0)
$cart_count = 0;
$stmt_cart = $conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
$stmt_cart->bind_param("i", $_SESSION['id_usuario']);
$stmt_cart->execute();
$res_cart = $stmt_cart->get_result()->fetch_assoc();
$cart_count = (int)($res_cart['total'] ?? 0);
$stmt_cart->close();

// Leemos el número de pedido que viene en la URL (aceptamos ?pedido= o ?id= por compatibilidad)
$id_pedido = (int)($_GET['pedido'] ?? $_GET['id'] ?? 0);

// Buscamos los datos generales del pedido filtrando tanto por id_pedido como por id_usuario.
// Filtrar por el usuario de la sesión evita que alguien vea pedidos de otras personas cambiando el número en la URL.
$pedido = null;
if ($id_pedido > 0) {
    $stmt_p = $conn->prepare("
        SELECT id_pedido, fecha_pedido, total, metodo_pago, estado
        FROM pedidos
        WHERE id_pedido = ? AND id_usuario = ?
    ");
    $stmt_p->bind_param("ii", $id_pedido, $_SESSION['id_usuario']);
    $stmt_p->execute();
    $pedido = $stmt_p->get_result()->fetch_assoc();
    $stmt_p->close();
}

// Si el pedido existe y pertenece al usuario, traemos todos los libros incluidos en esa orden
// junto con el precio con el que quedaron registrados en detalle_pedido
$libros_pedido = [];
if ($pedido) {
    $stmt_d = $conn->prepare("
        SELECT dp.id_libro, dp.precio_unitario, l.titulo, a.nombre_artistico AS autor
        FROM detalle_pedido dp
        INNER JOIN libros l ON dp.id_libro = l.id_libro
        INNER JOIN autores a ON l.id_autor = a.id_autor
        WHERE dp.id_pedido = ?
    ");
    $stmt_d->bind_param("i", $id_pedido);
    $stmt_d->execute();
    $libros_pedido = $stmt_d->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_d->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pedido | LecturaLibre</title>
    <link rel="stylesheet" href="assets/css/estilos.css?v=5">
    <script src="assets/js/main.js"></script>
</head>
<body>

    <!-- Barra de navegación principal compartida -->
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

    <main class="container">
        <?php if (!$pedido): ?>
            <!-- Aviso cuando el número de pedido no existe o no pertenece al usuario conectado -->
            <div style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                <h2>Pedido no encontrado</h2>
                <p style="margin: 10px 0;">No encontramos información para ese número de pedido en tu cuenta.</p>
                <a href="perfil.php" class="btn btn-primary">Ver mis pedidos en mi perfil</a>
            </div>
        <?php else: ?>
            <!-- Comprobante del pedido realizado con éxito -->
            <section id="confirmacion" style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                <div style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px 18px; border-radius: 6px; margin-bottom: 20px;">
                    <h2 style="margin-bottom: 5px;"><i class="fa-solid fa-circle-check"></i> ¡Pedido Confirmado!</h2>
                    <p style="font-size: 0.92rem;">Tus libros ya están disponibles en tu biblioteca. Puedes descargarlos ahora mismo o cuando quieras desde tu perfil.</p>
                </div>

                <!-- Datos generales de la orden (folio con ceros a la izquierda, fecha, método y total) -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; background: var(--wf-bg); padding: 15px; border-radius: var(--radius); margin-bottom: 20px;">
                    <div>
                        <small style="color: var(--wf-gray);">Folio de pedido</small>
                        <p><strong>#<?= str_pad((string)$pedido['id_pedido'], 5, '0', STR_PAD_LEFT) ?></strong></p>
                    </div>
                    <div>
                        <small style="color: var(--wf-gray);">Fecha</small>
                        <p><strong><?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></strong></p>
                    </div>
                    <div>
                        <small style="color: var(--wf-gray);">Método de pago</small>
                        <p><strong><?= htmlspecialchars(ucfirst($pedido['metodo_pago'] ?? 'Gratuito')) ?></strong></p>
                    </div>
                    <div>
                        <small style="color: var(--wf-gray);">Total</small>
                        <p><strong style="color: #27ae60;"><?= (float)$pedido['total'] == 0 ? 'Gratis ($0.00)' : '$' . number_format((float)$pedido['total'], 2) ?></strong></p>
                    </div>
                </div>

                <h3 style="margin-bottom: 12px;">Libros adquiridos</h3>

                <!-- Lista de libros del pedido con su enlace directo al script de descarga segura -->
                <div>
                    <?php foreach ($libros_pedido as $item): ?>
                        <?php $precio_u = ((float)$item['precio_unitario'] == 0) ? 'Gratis' : '$' . number_format((float)$item['precio_unitario'], 2); ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 12px 15px; border: 1px solid var(--wf-border); border-radius: var(--radius); margin-bottom: 10px;">
                            <div>
                                <h4><a href="detalle.php?id=<?= (int)$item['id_libro'] ?>"><?= htmlspecialchars($item['titulo']) ?></a></h4>
                                <p style="font-size: 0.85rem; color: var(--wf-gray);">
                                    <?= htmlspecialchars($item['autor']) ?> &bull; <?= $precio_u ?>
                                </p>
                            </div>
                            <!-- El enlace apunta a php/descargar.php, que valida el permiso y registra la descarga en la BD -->
                            <a href="php/descargar.php?pedido=<?= (int)$pedido['id_pedido'] ?>&libro=<?= (int)$item['id_libro'] ?>" class="btn btn-primary">
                                <i class="fa-solid fa-download"></i> Descargar PDF
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Botones finales para ir al historial del perfil o volver al catálogo -->
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px;">
                    <a href="perfil.php" class="btn btn-primary">Ir a Mi Biblioteca y Pedidos</a>
                    <a href="catalogo.php" class="btn">Seguir Explorando el Catálogo</a>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <!-- Pie de página compartido -->
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
                    <span aria-hidden="true"> | </span>
                    <a href="terminos.php">Términos</a>
                    <span aria-hidden="true"> | </span>
                    <a href="privacidad.php">Privacidad</a>
                </p>
            </nav>
        </div>
    </footer>
</body>
</html>