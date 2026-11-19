<?php
/*
 * Página informativa "Nosotros" de LecturaLibre.
 * Explica el propósito del proyecto, cómo funciona tanto para lectores como
 * para autores independientes y muestra cifras reales de la plataforma
 * consultadas directamente desde la base de datos.
 */
session_start();
require_once 'config/conexion.php';

// Datos básicos de la sesión para mantener sincronizada la barra de navegación
$pagina_actual = basename($_SERVER['PHP_SELF']);$logueado = isset($_SESSION['id_usuario']);$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

// Si el usuario tiene sesión iniciada, contamos cuántos libros tiene en su carrito
// para que el contador del encabezado no aparezca en cero al navegar aquí
$cart_count = 0;
if ($logueado) {
    $stmt_cart =$conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
    $stmt_cart->bind_param("i", $_SESSION['id_usuario']);
    $stmt_cart->execute();$res_cart = $stmt_cart->get_result()->fetch_assoc();$cart_count = (int)($res_cart['total'] ?? 0);$stmt_cart->close();
}

// Consultamos tres indicadores reales para mostrar el crecimiento de la comunidad:
// total de libros aprobados, autores registrados y lectores activos
$total_libros = (int)($conn->query("SELECT COUNT(*) AS total FROM libros WHERE estado = 'aprobado'")->fetch_assoc()['total'] ?? 0);
$total_autores = (int)($conn->query("SELECT COUNT(*) AS total FROM autores")->fetch_assoc()['total'] ?? 0);
$total_lectores = (int)($conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE activo = 1")->fetch_assoc()['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nosotros | LecturaLibre</title>
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

            <!-- Menú desplegable para pantallas móviles -->
            <details class="nav-menu-toggle">
                <summary class="btn search-icon" aria-label="Menú" aria-controls="nav-menu">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </summary>
            </details>

            <!-- Buscador global que redirige al catálogo con el texto escrito -->
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

                    <!-- Enlace dinámico al panel de gestión según el rol del usuario -->
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
        <section style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border); margin-bottom: 25px;">
            <h2 class="section-title">Sobre LecturaLibre</h2>
            <p style="margin-bottom: 15px; line-height: 1.6;">
                <strong>LecturaLibre</strong> es una plataforma digital creada para acercar la lectura a cualquier persona de forma sencilla, ordenada y accesible. Reunimos obras de dominio público y publicaciones de autores independientes en un mismo catálogo para que los lectores puedan explorar por género, guardar favoritos y descargar libros en formato PDF.
            </p>
            <p style="line-height: 1.6;">
                Al mismo tiempo, brindamos un espacio donde escritores emergentes pueden registrar sus obras, administrar su perfil de autor y recibir retroalimentación real de la comunidad mediante reseñas moderadas.
            </p>

            <!-- Tarjetas con los contadores reales obtenidos de la base de datos -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 25px;">
                <div style="background: var(--wf-bg); padding: 18px; border-radius: var(--radius); text-align: center; border: 1px solid var(--wf-border);">
                    <h3 style="font-size: 1.6rem; color: #27ae60;"><?= $total_libros ?></h3>
                    <p style="font-size: 0.9rem; color: var(--wf-gray);">Libros disponibles</p>
                </div>
                <div style="background: var(--wf-bg); padding: 18px; border-radius: var(--radius); text-align: center; border: 1px solid var(--wf-border);">
                    <h3 style="font-size: 1.6rem; color: #27ae60;"><?= $total_autores ?></h3>
                    <p style="font-size: 0.9rem; color: var(--wf-gray);">Autores publicados</p>
                </div>
                <div style="background: var(--wf-bg); padding: 18px; border-radius: var(--radius); text-align: center; border: 1px solid var(--wf-border);">
                    <h3 style="font-size: 1.6rem; color: #27ae60;"><?= $total_lectores ?></h3>
                    <p style="font-size: 0.9rem; color: var(--wf-gray);">Usuarios en la comunidad</p>
                </div>
            </div>
        </section>

        <!-- Explicación del funcionamiento según el tipo de usuario -->
        <section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <div style="background: white; padding: 20px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                <h3 style="margin-bottom: 10px;"><i class="fa-solid fa-book-reader"></i> Para Lectores</h3>
                <p style="font-size: 0.92rem; line-height: 1.6; color: var(--wf-gray);">
                    Explora el catálogo sin límites, filtra por categoría o calificación, guarda tus libros pendientes en favoritos y descárgalos en PDF desde tu historial de pedidos cada vez que los necesites.
                </p>
            </div>

            <div style="background: white; padding: 20px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                <h3 style="margin-bottom: 10px;"><i class="fa-solid fa-pen-nib"></i> Para Autores</h3>
                <p style="font-size: 0.92rem; line-height: 1.6; color: var(--wf-gray);">
                    Crea una cuenta de autor para subir tus propios libros con portada, sinopsis y archivo PDF. Cada obra pasa por una revisión previa antes de publicarse para cuidar la calidad del catálogo.
                </p>
            </div>

            <div style="background: white; padding: 20px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                <h3 style="margin-bottom: 10px;"><i class="fa-solid fa-shield-halved"></i> Moderación y Calidad</h3>
                <p style="font-size: 0.92rem; line-height: 1.6; color: var(--wf-gray);">
                    Nuestro equipo revisa tanto las nuevas publicaciones como las reseñas enviadas por los lectores para mantener un ambiente respetuoso, seguro y libre de contenido indebido.
                </p>
            </div>
        </section>
    </main>

    <!-- Ventana modal de inicio de sesión, registro y recuperación (disponible en todas las páginas públicas) -->
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