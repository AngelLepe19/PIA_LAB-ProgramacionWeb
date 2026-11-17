<?php
session_start();
require_once 'config/conexion.php';

$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = isset($_SESSION['id_usuario']);
$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';
$correo_usuario = $logueado ? ($_SESSION['correo'] ?? '') : '';

// Procesar envío del formulario de contacto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_contacto'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');

    if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL) || $asunto === '' || $mensaje === '') {
        header("Location: contacto.php?error=campos_invalidos");
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO mensajes_contacto (nombre, correo, asunto, mensaje, estado) VALUES (?, ?, ?, ?, 'pendiente')");
    $stmt->bind_param("ssss", $nombre, $correo, $asunto, $mensaje);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        header("Location: contacto.php?enviado=1");
    } else {
        header("Location: contacto.php?error=error_bd");
    }
    exit;
}

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

// Mensajes de aviso
$alerta_texto = '';
$alerta_tipo = '';
if (isset($_GET['enviado']) && $_GET['enviado'] === '1') {
    $alerta_texto = '¡Mensaje enviado con éxito! Un trabajador o administrador revisará tu consulta pronto.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['error'])) {
    $alerta_tipo = 'error';
    switch ($_GET['error']) {
        case 'campos_invalidos':
            $alerta_texto = 'Por favor completa todos los campos con un correo electrónico válido.';
            break;
        default:
            $alerta_texto = 'Ocurrió un error al enviar tu mensaje. Inténtalo de nuevo.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - LecturaLibre</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <script src="assets/js/main.js"></script>
</head>
<body>

    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
            </a>

            <form class="search-bar" action="catalogo.php" method="get" role="search">
                <input type="search" id="global-search" name="q" aria-label="Buscar por título, autor o categoría"
                       placeholder="Buscar título, autor o categoría...">
                <button type="submit" class="btn" aria-label="Buscar">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Buscar
                </button>
            </form>

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
    </header>

    <?php if ($alerta_texto !== ''): ?>
        <div class="container" style="margin-top: 15px;">
            <div style="padding: 12px 16px; border-radius: 6px; font-size: 0.9rem; background: <?= $alerta_tipo === 'exito' ? '#d4edda' : '#f8d7da' ?>; color: <?= $alerta_tipo === 'exito' ? '#155724' : '#721c24' ?>; border: 1px solid <?= $alerta_tipo === 'exito' ? '#c3e6cb' : '#f5c6cb' ?>;">
                <?= htmlspecialchars($alerta_texto) ?>
            </div>
        </div>
    <?php endif; ?>

    <main class="container">
        <section id="contacto">
            <div style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                <h2 class="section-title">Formulario de Contacto</h2>
                <p style="font-size: 0.9rem; color: var(--wf-gray); margin-bottom: 15px;">
                    ¿Tienes alguna duda, sugerencia o problema con un libro? Escríbenos y nuestro equipo lo atenderá.
                </p>
                <form action="contacto.php" method="POST" style="display: flex; flex-direction: column; gap: 12px; max-width: 520px;">
                    <input type="hidden" name="enviar_contacto" value="1">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="cnt-name">Tu Nombre *</label>
                        <input type="text" id="cnt-name" name="nombre" maxlength="100" value="<?= htmlspecialchars($nombre_usuario) ?>" placeholder="Tu Nombre" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="cnt-email">Correo Electrónico *</label>
                        <input type="email" id="cnt-email" name="correo" maxlength="120" value="<?= htmlspecialchars($correo_usuario) ?>" placeholder="tu@correo.com" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="cnt-subject">Asunto *</label>
                        <input type="text" id="cnt-subject" name="asunto" maxlength="150" placeholder="Asunto de tu consulta" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="cnt-message">Mensaje *</label>
                        <textarea id="cnt-message" name="mensaje" placeholder="Escribe tu mensaje aquí..." rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="align-self: flex-start;">Enviar Mensaje</button>
                </form>
            </div>
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

            <form id="recoverForm" action="auth/recuperar.php" method="POST" style="display: none;">
                <h3 style="margin-bottom: 15px;">Recuperar Contraseña</h3>
                <p style="font-size:0.85rem; color: var(--wf-gray); margin-bottom:15px;">Ingresa tu correo registrado para enviarte un enlace de restablecimiento.</p>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="correo" id="recover-email" placeholder="usuario@correo.com" required>
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