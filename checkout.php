<?php
/*
 * Página de checkout (confirmación de pedido) de LecturaLibre.
 * Revisa que el usuario haya iniciado sesión, verifica qué libros de su carrito
 * siguen disponibles y aún no ha adquirido, muestra el resumen final con el total
 * y envía la orden a php/checkout_procesar.php para registrarla en la base de datos.
 */
session_start();
require_once 'config/conexion.php';

// Datos básicos de la sesión para controlar permisos y la barra de navegación
$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = isset($_SESSION['id_usuario']);
$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

// Nadie puede entrar al checkout sin haber iniciado sesión; si no está conectado,
// lo regresamos al carrito con un aviso para que ingrese primero
if (!$logueado) {
    header('Location: carrito.php?error=inicia_sesion');
    exit;
}

// Contamos los libros que tiene en su carrito para mostrar el número en el ícono superior
$cart_count = 0;
$stmt_cart = $conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
$stmt_cart->bind_param("i", $_SESSION['id_usuario']);
$stmt_cart->execute();
$res_cart = $stmt_cart->get_result()->fetch_assoc();
$cart_count = (int)($res_cart['total'] ?? 0);
$stmt_cart->close();

// Revisamos si llegó algún error por la URL (por ejemplo, si no aceptó los términos o falló el proceso)
$alerta_texto = '';
$alerta_tipo = '';
if (isset($_GET['error'])) {
    $alerta_tipo = 'error';
    switch ($_GET['error']) {
        case 'terminos':
            $alerta_texto = 'Debes aceptar los Términos de uso y el Aviso de privacidad para completar el pedido.';
            break;
        case 'metodo_invalido':
            $alerta_texto = 'Selecciona un método de pago válido para continuar.';
            break;
        case 'pedido':
            $alerta_texto = 'Ocurrió un problema al procesar tu pedido. Inténtalo de nuevo.';
            break;
        default:
            $alerta_texto = 'No se pudo completar la operación.';
            break;
    }
}

// Traemos los libros que el usuario tiene en su carrito, asegurándonos de que sigan aprobados
$stmt_items = $conn->prepare("
    SELECT l.id_libro, l.titulo, l.precio, l.portada, a.nombre_artistico AS autor
    FROM carrito c
    INNER JOIN libros l ON c.id_libro = l.id_libro
    INNER JOIN autores a ON l.id_autor = a.id_autor
    WHERE c.id_usuario = ? AND l.estado = 'aprobado'
    ORDER BY c.fecha_agregado DESC
");
$stmt_items->bind_param("i", $_SESSION['id_usuario']);
$stmt_items->execute();
$items_brutos = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_items->close();

// Si el carrito está vacío, no tiene sentido quedarse en el checkout; lo regresamos a carrito.php
if (count($items_brutos) === 0) {
    header('Location: carrito.php');
    exit;
}

// Consultamos qué libros ya compró este usuario anteriormente (en pedidos que no estén cancelados)
// para evitar que vuelva a adquirir un libro que ya tiene disponible en su cuenta
$stmt_comp = $conn->prepare("
    SELECT DISTINCT dp.id_libro
    FROM detalle_pedido dp
    INNER JOIN pedidos p ON p.id_pedido = dp.id_pedido
    WHERE p.id_usuario = ? AND p.estado <> 'cancelado'
");
$stmt_comp->bind_param("i", $_SESSION['id_usuario']);
$stmt_comp->execute();
$ya_comprados = array_map('intval', array_column($stmt_comp->get_result()->fetch_all(MYSQLI_ASSOC), 'id_libro'));
$stmt_comp->close();

// Filtramos la lista dejando únicamente los libros nuevos que el usuario aún no posee
// y aprovechamos el mismo recorrido para sumar el costo total del pedido
$libros_checkout = [];
$total_checkout = 0.00;
foreach ($items_brutos as $item) {
    if (!in_array((int)$item['id_libro'], $ya_comprados, true)) {
        $libros_checkout[] = $item;
        $total_checkout += (float)$item['precio'];
    }
}

// Si todos los libros que tenía en el carrito ya los había comprado antes, lo mandamos de vuelta
// al carrito con un mensaje explicándole que ya puede descargarlos desde su página de detalle
if (count($libros_checkout) === 0) {
    header('Location: carrito.php?error=ya_comprado');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Pedido | LecturaLibre</title>
    <link rel="stylesheet" href="assets/css/estilos.css?v=5">
    <script src="assets/js/main.js"></script>
</head>
<body>

    <!-- Barra de navegación principal compartida en todo el sitio público -->
    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
            </a>

            <!-- Botón de menú desplegable para pantallas móviles -->
            <details class="nav-menu-toggle">
                <summary class="btn search-icon" aria-label="Menú" aria-controls="nav-menu">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </summary>
            </details>

            <!-- Buscador global que redirige al catálogo -->
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

                    <!-- Enlace dinámico al panel según el rol del usuario conectado -->
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

    <!-- Aviso de error si el envío anterior tuvo algún problema de validación -->
    <?php if ($alerta_texto !== ''): ?>
        <div class="container" style="margin-top: 15px;">
            <div style="padding: 12px 16px; border-radius: 6px; font-size: 0.9rem; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                <?= htmlspecialchars($alerta_texto) ?>
            </div>
        </div>
    <?php endif; ?>

    <main class="container">
        <a href="carrito.php" class="btn" style="margin-bottom: 15px;">&larr; Volver al carrito</a>

        <section id="checkout" style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
            <h2 class="section-title">Resumen de tu Pedido</h2>
            <p style="color: var(--wf-gray); margin-bottom: 20px;">
                Verifica los libros seleccionados antes de confirmar. Una vez completado el pedido, podrás descargarlos en formato PDF de inmediato.
            </p>

            <!-- Desglose de los libros incluidos en este pedido -->
            <div style="margin-bottom: 20px;">
                <?php foreach ($libros_checkout as $libro): ?>
                    <?php $precio_txt = ((float)$libro['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$libro['precio'], 2); ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--wf-border);">
                        <div>
                            <strong><?= htmlspecialchars($libro['titulo']) ?></strong>
                            <p style="font-size: 0.85rem; color: var(--wf-gray);">Autor: <?= htmlspecialchars($libro['autor']) ?></p>
                        </div>
                        <span style="color: #27ae60; font-weight: bold;"><?= $precio_txt ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Total calculado en el servidor para evitar alteraciones desde el navegador -->
            <div style="display: flex; justify-content: space-between; align-items: center; background: var(--wf-bg); padding: 15px; border-radius: var(--radius); margin-bottom: 20px;">
                <span style="font-size: 1.05rem; font-weight: bold;">Total a pagar:</span>
                <span style="font-size: 1.2rem; font-weight: bold; color: #27ae60;">
                    <?= $total_checkout == 0 ? 'Gratis ($0.00)' : '$' . number_format($total_checkout, 2) ?>
                </span>
            </div>

            <!-- Formulario que envía la confirmación a php/checkout_procesar.php -->
            <form action="php/checkout_procesar.php" method="POST">
                <?php if ($total_checkout > 0): ?>
                    <!-- Si hay libros de pago, mostramos el selector de método de pago -->
                    <div class="form-group">
                        <label for="metodo_pago">Método de pago</label>
                        <select id="metodo_pago" name="metodo_pago" required>
                            <option value="tarjeta">Tarjeta de crédito o débito</option>
                            <option value="transferencia">Transferencia bancaria</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>
                <?php else: ?>
                    <!-- Si todos los libros son gratuitos, enviamos 'gratuito' automáticamente sin pedir datos de cobro -->
                    <input type="hidden" name="metodo_pago" value="gratuito">
                    <p style="font-size: 0.9rem; color: #155724; background: #d4edda; padding: 10px 14px; border-radius: 6px; margin-bottom: 15px;">
                        <i class="fa-solid fa-circle-check"></i> Este pedido es 100% gratuito. No se realizará ningún cargo.
                    </p>
                <?php endif; ?>

                <!-- Aceptación obligatoria de términos y privacidad antes de generar la orden -->
                <label style="display: flex; gap: 8px; align-items: flex-start; font-size: 0.85rem; margin-bottom: 20px;">
                    <input type="checkbox" name="acepto_terminos" value="1" required style="width: auto; margin-top: 3px;">
                    <span>
                        Confirmo que acepto los <a href="terminos.php" target="_blank">Términos de uso</a> y el
                        <a href="privacidad.php" target="_blank">Aviso de privacidad</a> para la descarga digital.
                    </span>
                </label>

                <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1rem;">
                    <i class="fa-solid fa-check"></i> Confirmar y Obtener Libros
                </button>
            </form>
        </section>
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