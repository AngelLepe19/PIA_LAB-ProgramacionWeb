<?php
session_start();
require_once 'config/conexion.php';

$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = isset($_SESSION['id_usuario']);
$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

// Contador del carrito (igual que en detalle.php)
$cart_count = 0;
if ($logueado) {
    $stmt_cart = $conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
    $stmt_cart->bind_param("i", $_SESSION['id_usuario']);
    $stmt_cart->execute();
    $res_cart = $stmt_cart->get_result()->fetch_assoc();
    $cart_count = (int)($res_cart['total'] ?? 0);
    $stmt_cart->close();
}

// ---- Autor que llega por la URL (autor.php?id=2) ----
$id_autor = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT id_autor, nombre_artistico, biografia FROM autores WHERE id_autor = ?");
$stmt->bind_param("i", $id_autor);
$stmt->execute();
$autor = $stmt->get_result()->fetch_assoc(); // null si no existe
$stmt->close();

// Todos sus libros aprobados, con promedio de reseñas aprobadas
$libros = [];
if ($autor) {
    $stmt_l = $conn->prepare("
        SELECT l.id_libro, l.titulo, l.precio, l.portada, l.lecturas_acumuladas,
               (SELECT ROUND(AVG(r.puntuacion), 1) FROM resenas r
                 WHERE r.id_libro = l.id_libro AND r.estado = 'aprobada') AS promedio
        FROM libros l
        WHERE l.id_autor = ? AND l.estado = 'aprobado'
        ORDER BY l.fecha_publicacion DESC, l.titulo ASC
    ");
    $stmt_l->bind_param("i", $id_autor);
    $stmt_l->execute();
    $libros = $stmt_l->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_l->close();
}
$total_lecturas = array_sum(array_map(fn($l) => (int)$l['lecturas_acumuladas'], $libros));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LecturaLibre - <?= htmlspecialchars($autor['nombre_artistico'] ?? 'Autor') ?></title>
    <link rel="stylesheet" href="assets/css/estilos.css?v=5">
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
    <a href="catalogo.php" class="btn" style="margin-bottom: 15px;">&larr; Volver al catálogo</a>

    <?php if (!$autor): ?>
        <div style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
            <h2>Autor no encontrado</h2>
            <p>El autor que buscas no existe.</p>
        </div>
    <?php else: ?>

        <!-- Perfil del autor -->
        <section style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border); margin-bottom: 20px;">
            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                <div style="width: 90px; height: 90px; border-radius: 50%; background: var(--wf-bg); display: flex; align-items: center; justify-content: center; font-size: 2.2rem; color: var(--wf-gray);">
                    <i class="fa-solid fa-user-pen"></i>
                </div>
                <div>
                    <h2 style="margin: 0;"><?= htmlspecialchars($autor['nombre_artistico']) ?></h2>
                    <p style="color: var(--wf-gray); margin-top: 6px;">
                        <?= count($libros) ?> <?= count($libros) === 1 ? 'libro publicado' : 'libros publicados' ?>
                        &middot; <?= $total_lecturas ?> lecturas en total
                    </p>
                </div>
            </div>

            <hr style="margin: 20px 0;">
            <h3>Biografía</h3>
            <?php if (!empty($autor['biografia'])): ?>
                <p><?= nl2br(htmlspecialchars($autor['biografia'])) ?></p>
            <?php else: ?>
                <p style="color: var(--wf-gray);"><em>Este autor aún no ha agregado su biografía.</em></p>
            <?php endif; ?>
        </section>

        <!-- Libros del autor -->
        <section style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
            <h3 style="margin-bottom: 15px;">Libros de <?= htmlspecialchars($autor['nombre_artistico']) ?></h3>

            <?php if (count($libros) === 0): ?>
                <p>Este autor todavía no tiene libros publicados.</p>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px;">
                    <?php foreach ($libros as $l): ?>
                        <?php $precio = ((float)$l['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$l['precio'], 2); ?>
                        <a href="detalle.php?id=<?= (int)$l['id_libro'] ?>"
                           style="display: block; text-decoration: none; color: inherit; background: white; border: 1px solid var(--wf-border); border-radius: var(--radius); padding: 12px;">
                            <?php if (!empty($l['portada']) && is_file(__DIR__ . '/' . $l['portada'])): ?>
                                <img src="<?= htmlspecialchars($l['portada']) ?>"
                                     alt="Portada de <?= htmlspecialchars($l['titulo']) ?>"
                                     style="width: 100%; height: 230px; object-fit: cover; border-radius: 6px;">
                            <?php else: ?>
                                <div class="placeholder-box" style="width: 100%; height: 230px;">PORTADA</div>
                            <?php endif; ?>
                            <h4 style="margin: 10px 0 4px;"><?= htmlspecialchars($l['titulo']) ?></h4>
                            <div class="stars" style="font-size: 0.85rem;">⭐ <?= $l['promedio'] ?? 'Sin reseñas' ?></div>
                            <strong style="color: #27ae60;"><?= $precio ?></strong>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    <?php endif; ?>
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
