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
                <button class="tab-btn active" data-profile-tab="datos" onclick="switchProfileTab('datos')">Mis Datos</button>
                <button class="tab-btn" data-profile-tab="favoritos" onclick="switchProfileTab('favoritos')">Lista de Deseos</button>
                <button class="tab-btn" data-profile-tab="historial" onclick="switchProfileTab('historial')">Historial de Lecturas / Descargas</button>
            </div>
            <div id="tab-datos" class="profile-tab">
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
            <div id="tab-favoritos" class="profile-tab" style="display: none;">
                <div class="book-grid" id="wishlist-books"></div>
            </div>
            <div id="tab-historial" class="profile-tab" style="display: none;">
                <div class="table-scroll" tabindex="0" role="region" aria-label="Tabla de datos">
                <table class="wf-table">
                    <thead><tr><th>ID Orden</th><th>Fecha</th><th>Costo</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody id="order-history-body"></tbody>
                </table>
                </div>
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