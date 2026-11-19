<?php
/*
 * Página de Términos de Uso de LecturaLibre.
 * Establece las reglas generales para lectores y autores dentro de la plataforma:
 * uso personal de los archivos descargados, derechos sobre las obras publicadas
 * y criterios de moderación de reseñas y cuentas.
 */
session_start();
require_once 'config/conexion.php';

// Datos básicos de la sesión para mantener coherente la barra superior
$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = isset($_SESSION['id_usuario']);
$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

// Si el usuario tiene sesión activa, consultamos cuántos artículos tiene en su carrito
$cart_count = 0;
if ($logueado) {
    $stmt_cart = $conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
    $stmt_cart->bind_param("i", $_SESSION['id_usuario']);
    $stmt_cart->execute();
    $res_cart = $stmt_cart->get_result()->fetch_assoc();
    $cart_count = (int)($res_cart['total'] ?? 0);
    $stmt_cart->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos de Uso | LecturaLibre</title>
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

    <main class="container">
        <article style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border); line-height: 1.65;">
            <h2 class="section-title">Términos y Condiciones de Uso</h2>
            <p style="font-size: 0.85rem; color: var(--wf-gray); margin-bottom: 20px;">
                Última actualización: <?= date('Y') ?>
            </p>

            <h3 style="margin: 16px 0 8px;">1. Aceptación de los términos</h3>
            <p style="margin-bottom: 12px;">
                Al registrarte, navegar o descargar contenido desde <strong>LecturaLibre</strong>, aceptas cumplir con las condiciones descritas en este documento. Si no estás de acuerdo con alguna disposición, te pedimos abstenerte de utilizar los servicios de descarga y publicación.
            </p>

            <h3 style="margin: 16px 0 8px;">2. Cuentas de usuario y responsabilidades</h3>
            <p style="margin-bottom: 12px;">
                Cada usuario es responsable de mantener la confidencialidad de su contraseña y de la actividad realizada desde su cuenta. Al registrarte puedes elegir entre un perfil de <strong>Lector</strong> o de <strong>Autor</strong>, comprometiéndote a proporcionar información veraz.
            </p>

            <h3 style="margin: 16px 0 8px;">3. Uso permitido de los libros digitales</h3>
            <p style="margin-bottom: 12px;">
                Los archivos PDF obtenidos a través de la plataforma están destinados exclusivamente al uso personal y educativo del lector. Queda prohibida su reventa o redistribución comercial sin autorización expresa de su autor o titular de derechos.
            </p>

            <h3 style="margin: 16px 0 8px;">4. Publicaciones de autores y derechos de autor</h3>
            <p style="margin-bottom: 12px;">
                Los usuarios con cuenta de Autor declaran poseer los derechos necesarios sobre las obras que suben a LecturaLibre, o bien que dichas obras pertenecen al dominio público o cuentan con licencias libres compatibles. Toda obra pasa por una revisión administrativa antes de mostrarse al público.
            </p>

            <h3 style="margin: 16px 0 8px;">5. Reseñas y normas de convivencia</h3>
            <p style="margin-bottom: 12px;">
                Las opiniones publicadas en la ficha de cada libro deben ser respetuosas y centrarse en el contenido de la obra. El equipo de moderación puede rechazar reseñas que contengan lenguaje ofensivo, spam o información ajena al libro.
            </p>

            <h3 style="margin: 16px 0 8px;">6. Suspensión de contenido o cuentas</h3>
            <p>
                LecturaLibre se reserva el derecho de dar de baja libros o desactivar cuentas que incumplan estas normas o vulneren derechos de terceros. Para cualquier aclaración puedes comunicarte mediante nuestra sección de <a href="contacto.php">Contacto</a>.
            </p>
        </article>
    </main>

    <!-- Ventana modal de inicio de sesión, registro y recuperación -->
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
                <div class="form-group">
                    <label for="signup-tipo">Tipo de cuenta</label>
                    <select name="tipo_cuenta" id="signup-tipo">
                        <option value="lector">Lector</option>
                        <option value="autor">Autor (quiero publicar mis libros)</option>
                    </select>
                </div>
                <label style="display: flex; gap: 8px; align-items: flex-start; font-size: 0.85rem; margin-bottom: 15px;">
                    <input type="checkbox" name="acepto_terminos" value="1" required style="width: auto; margin-top: 3px;">
                    <span>Acepto los <a href="terminos.php" target="_blank">Términos de uso</a> y el <a href="privacidad.php" target="_blank">Aviso de privacidad</a>.</span>
                </label>
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