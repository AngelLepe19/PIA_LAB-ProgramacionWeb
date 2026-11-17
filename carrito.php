<?php
session_start();
require_once 'config/conexion.php';

$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = isset($_SESSION['id_usuario']);
$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

// Contar libros en el carrito real del usuario
$cart_count = 0;
if ($logueado) {
    $stmt_cart = $conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
    $stmt_cart->bind_param("i", $_SESSION['id_usuario']);
    $stmt_cart->execute();
    $res_cart = $stmt_cart->get_result()->fetch_assoc();
    $cart_count = (int)($res_cart['total'] ?? 0);
    $stmt_cart->close();
}

// Mensajes de aviso tras login o registro
$alerta_texto = '';
$alerta_tipo = '';
if (isset($_GET['registro']) && $_GET['registro'] === 'exitoso') {
    $alerta_texto = '¡Cuenta creada con éxito! Bienvenido a LecturaLibre.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['error'])) {
    $alerta_tipo = 'error';
    switch ($_GET['error']) {
        case 'credenciales_invalidas':
            $alerta_texto = 'Correo o contraseña incorrectos.';
            break;
        case 'correo_existente':
            $alerta_texto = 'Ese correo electrónico ya está registrado.';
            break;
        case 'cuenta_inactiva':
            $alerta_texto = 'Tu cuenta está pendiente o inactiva. Contacta al administrador.';
            break;
        case 'campos_vacios':
            $alerta_texto = 'Por favor completa todos los campos obligatorios.';
            break;
        case 'inicia_sesion':
            $alerta_texto = 'Inicia sesión para continuar.';
            break;
        case 'pedido':
            $alerta_texto = 'No se pudo generar el pedido. Intenta de nuevo.';
            break;
        case 'sin_permiso':
            $alerta_texto = 'No tienes acceso a ese archivo.';
            break;
        default:
            $alerta_texto = 'Ocurrió un error al procesar tu solicitud.';
            break;
    }
}

// ---- Carrito: libros que el usuario tiene en su carrito ----
$libros_carrito = [];
$total_carrito = 0.00;
if ($logueado) {
    $stmt_c = $conn->prepare("
        SELECT l.id_libro, l.titulo, l.precio, a.nombre_artistico AS autor
        FROM carrito c
        INNER JOIN libros l ON c.id_libro = l.id_libro
        INNER JOIN autores a ON l.id_autor = a.id_autor
        WHERE c.id_usuario = ? AND l.estado = 'aprobado'
        ORDER BY c.fecha_agregado DESC
    ");
    $stmt_c->bind_param("i", $_SESSION['id_usuario']);
    $stmt_c->execute();
    $libros_carrito = $stmt_c->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_c->close();

    foreach ($libros_carrito as $lc) {
        $total_carrito += (float)$lc['precio'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito | LecturaLibre</title>
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

                    <?php if ($id_rol === 2 || $id_rol === 4): ?>
                        <li id="author-nav-item"><a href="panelautor.php" class="<?= $pagina_actual === 'panelautor.php' ? 'active-link' : '' ?>">Panel Autor</a></li>
                    <?php endif; ?>

                    <?php if ($id_rol === 3 || $id_rol === 4): ?>
                        <li id="worker-nav-item"><a href="paneltrabajador.php" class="<?= $pagina_actual === 'paneltrabajador.php' ? 'active-link' : '' ?>">Panel Trabajador</a></li>
                    <?php endif; ?>

                    <?php if ($id_rol === 4): ?>
                        <li id="admin-nav-item"><a href="paneladmin.php" class="<?= $pagina_actual === 'paneladmin.php' ? 'active-link' : '' ?>">Panel Admin</a></li>
                    <?php endif; ?>
                </ul>

                <div class="user-actions">
                    <button class="btn" onclick="window.location.href='carrito.php'">
                        <i class="fa-solid fa-cart-shopping"></i> <span id="cart-count" class="badge"><?= $cart_count ?></span>
                    </button>

                    <?php if (!$logueado): ?>
                        <div id="auth-buttons">
                            <button class="btn" onclick="openAuthModal('login')"><i class="fa-regular fa-user"></i> Iniciar Sesión</button>
                            <button class="btn btn-primary" onclick="openAuthModal('signup')">Registrarse</button>
                        </div>
                    <?php else: ?>
                        <div id="user-menu" style="display: flex; gap: 10px; align-items: center;">
                            <button class="btn" onclick="window.location.href='perfil.php'">
                                <i class="fa-solid fa-circle-user"></i> <span id="user-display-name"><?= htmlspecialchars($nombre_usuario) ?></span>
                            </button>
                            <a href="auth/logout.php" class="btn btn-danger" title="Cerrar sesión">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </a>
                        </div>
                    <?php endif; ?>
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
        <section id="carrito">
            <h2 class="section-title">Tu Carrito de Selección</h2>

            <?php if (!$logueado): ?>
                <p>Inicia sesión para ver y gestionar tu carrito.</p>
            <?php elseif (count($libros_carrito) === 0): ?>
                <p>El carrito está vacío.</p>
                <a href="catalogo.php" class="btn btn-primary" style="margin-top: 10px;">Ir al catálogo</a>
            <?php else: ?>
                <div id="cart-items">
                    <?php foreach ($libros_carrito as $item): ?>
                        <?php $precio_txt = ((float)$item['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$item['precio'], 2); ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; background:white; padding:12px 15px; border:1px solid var(--wf-border); border-radius: var(--radius); margin-bottom:10px;">
                            <div>
                                <h4><a href="detalle.php?id=<?= (int)$item['id_libro'] ?>"><?= htmlspecialchars($item['titulo']) ?></a></h4>
                                <p style="font-size: 0.85rem; color: var(--wf-gray);"><?= htmlspecialchars($item['autor']) ?></p>
                                <p style="color:#27ae60; font-weight: bold;"><?= $precio_txt ?></p>
                            </div>
                            <a href="php/carrito_quitar.php?id_libro=<?= (int)$item['id_libro'] ?>" class="btn btn-danger">Quitar</a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="cart-summary" style="margin-top: 20px; background: white; padding: 20px; border: 1px solid var(--wf-border); border-radius: var(--radius);">
                    <h3>Total: <span style="color: #27ae60;"><?= $total_carrito == 0 ? 'Gratis ($0.00)' : '$' . number_format($total_carrito, 2) ?></span></h3>
                    <a href="checkout.php" class="btn btn-primary" style="margin-top: 15px;">Continuar al Checkout &rarr;</a>
                </div>
            <?php endif; ?>
        </section>
    </main>


    <div class="modal-overlay" id="authModal">
        <div class="modal">
            <span class="close-modal" onclick="closeAuthModal()">&times;</span>
            
            <form id="loginForm" action="auth/login.php" method="POST">
                <h3 style="margin-bottom: 15px;">Iniciar Sesión</h3>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="correo" id="login-email" placeholder="usuario@correo.com" required>
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Ingresar</button>
                <p class="auth-toggle">¿Olvidaste tu contraseña? <a onclick="toggleAuthMode('recover')">Recuperar aquí</a></p>
                <p class="auth-toggle">¿No tienes una cuenta? <a onclick="toggleAuthMode('signup')">Regístrate aquí</a></p>
            </form>

            <form id="signupForm" action="auth/registro.php" method="POST" style="display: none;" onsubmit="return validarRegistro(event)">
                <h3 style="margin-bottom: 15px;">Crear una Cuenta</h3>
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre_completo" id="signup-name" placeholder="Tu Nombre" required>
                </div>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="correo" id="signup-email" placeholder="usuario@correo.com" required>
                </div>
                <div class="form-group">
                    <label for="signup-password">Contraseña</label>
                    <input type="password" name="password" id="signup-password" autocomplete="new-password" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label for="signup-password-confirm">Confirmar contraseña</label>
                    <input type="password" id="signup-password-confirm" autocomplete="new-password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Registrarse</button>
                <p class="auth-toggle">¿Ya tienes cuenta? <a onclick="toggleAuthMode('login')">Inicia sesión</a></p>
            </form>

            <form id="recoverForm" style="display: none;" onsubmit="handleRecoverPass(event)">
                <h3 style="margin-bottom: 15px;">Recuperar Contraseña</h3>
                <p style="font-size:0.85rem; color: var(--wf-gray); margin-bottom:15px;">Ingresa tu correo registrado para enviarte un enlace de restablecimiento.</p>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" id="recover-email" placeholder="usuario@correo.com" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Enviar Correo</button>
                <p class="auth-toggle"><a onclick="toggleAuthMode('login')">&larr; Volver a inicio de sesión</a></p>
            </form>
        </div>
    </div>

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