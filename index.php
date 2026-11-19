<?php
session_start();
require_once 'config/conexion.php';

$pagina_actual = basename($_SERVER['PHP_SELF']);
$busqueda = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
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

// Mensajes de aviso tras login, registro o recuperación de contraseña
$alerta_texto = '';
$alerta_html_extra = '';
$alerta_tipo = '';

if (isset($_GET['registro']) && $_GET['registro'] === 'exitoso') {
    $alerta_texto = '¡Cuenta creada con éxito! Bienvenido a LecturaLibre.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['registro']) && $_GET['registro'] === 'autor') {
    $alerta_texto = '¡Cuenta de autor creada! Entra a "Panel Autor" para publicar tu primer libro.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['recuperar']) && $_GET['recuperar'] === 'enviado') {
    $alerta_texto = 'Si el correo está registrado, se ha generado un enlace de restablecimiento (válido por 1 hora).';
    $alerta_tipo = 'exito';
    if (!empty($_SESSION['link_recuperacion_demo'])) {
        $link_demo = htmlspecialchars($_SESSION['link_recuperacion_demo']);
        $alerta_html_extra = ' <a href="' . $link_demo . '" style="font-weight:bold; text-decoration:underline;">Abrir enlace de recuperación</a>';
        unset($_SESSION['link_recuperacion_demo']);
    }
} elseif (isset($_GET['restablecido']) && $_GET['restablecido'] === 'exitoso') {
    $alerta_texto = 'Tu contraseña ha sido actualizada. Ya puedes iniciar sesión.';
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
        case 'acceso_denegado':
            $alerta_texto = 'No tienes permisos para acceder a esa sección.';
            break;
        case 'terminos':
            $alerta_texto = 'Debes aceptar los Términos de uso y el Aviso de privacidad para registrarte.';
            break;
        case 'token_invalido':
            $alerta_texto = 'El enlace de recuperación es inválido o ya expiró.';
            break;
        default:
            $alerta_texto = 'Ocurrió un error al procesar tu solicitud.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LecturaLibre - Plataforma Gratuita</title>
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

            <form class="search-bar" action="index.php" method="get" role="search">
                <input type="search" id="global-search" name="q" aria-label="Buscar por título, autor o categoría"
                       placeholder="Buscar libros…" value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>">
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

    <?php if ($alerta_texto !== ''): ?>
        <div class="container" style="margin-top: 15px;">
            <div style="padding: 12px 16px; border-radius: 6px; font-size: 0.9rem; background: <?= $alerta_tipo === 'exito' ? '#d4edda' : '#f8d7da' ?>; color: <?= $alerta_tipo === 'exito' ? '#155724' : '#721c24' ?>; border: 1px solid <?= $alerta_tipo === 'exito' ? '#c3e6cb' : '#f5c6cb' ?>;">
                <?= htmlspecialchars($alerta_texto) ?><?= $alerta_html_extra ?>
            </div>
        </div>
    <?php endif; ?>

    <main class="container">
        <?php
        // Obtener IDs de favoritos del usuario actual (si inició sesión)
        $mis_favoritos = [];
        if ($logueado) {
            $stmt_fav = $conn->prepare("SELECT id_libro FROM favoritos WHERE id_usuario = ?");
            $stmt_fav->bind_param("i", $_SESSION['id_usuario']);
            $stmt_fav->execute();
            $res_fav = $stmt_fav->get_result();
            while ($f = $res_fav->fetch_assoc()) {
                $mis_favoritos[] = (int)$f['id_libro'];
            }
            $stmt_fav->close();
        }
        // Libros que el usuario ya compró (no se pueden volver a agregar al carrito)
        $mis_comprados = [];
        if ($logueado) {
            $stmt_comp = $conn->prepare("
                SELECT DISTINCT dp.id_libro FROM detalle_pedido dp
                INNER JOIN pedidos p ON p.id_pedido = dp.id_pedido
                WHERE p.id_usuario = ? AND p.estado <> 'cancelado'
            ");
            $stmt_comp->bind_param("i", $_SESSION['id_usuario']);
            $stmt_comp->execute();
            $mis_comprados = array_map('intval', array_column($stmt_comp->get_result()->fetch_all(MYSQLI_ASSOC), 'id_libro'));
            $stmt_comp->close();
        }

        // Consultar libros aprobados desde MySQL
        $sql_libros = "
            SELECT l.id_libro, l.titulo, l.precio, l.portada, a.nombre_artistico AS autor,
                   ROUND(AVG(CASE WHEN r.estado = 'aprobada' THEN r.puntuacion END), 1) AS promedio
            FROM libros l
            INNER JOIN autores a ON l.id_autor = a.id_autor
            LEFT JOIN resenas r ON l.id_libro = r.id_libro
            WHERE l.estado = 'aprobado'
        ";

        if ($busqueda !== '') {
            $sql_libros .= "
                AND (l.titulo LIKE ? ESCAPE '!' OR a.nombre_artistico LIKE ? ESCAPE '!'
                     OR EXISTS (
                         SELECT 1 FROM libro_categoria lc
                         INNER JOIN categorias c ON c.id_categoria = lc.id_categoria
                         WHERE lc.id_libro = l.id_libro AND c.nombre LIKE ? ESCAPE '!'
                     ))
            ";
        }

        $sql_libros .= "
            GROUP BY l.id_libro, l.titulo, l.precio, l.portada, a.nombre_artistico
            ORDER BY l.fecha_alta DESC
        ";

        if ($busqueda === '') {
            $res_libros = $conn->query($sql_libros . " LIMIT 4");
        } else {
            $patron = '%' . strtr($busqueda, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $stmt_libros = $conn->prepare($sql_libros);
            $stmt_libros->bind_param("sss", $patron, $patron, $patron);
            $stmt_libros->execute();
            $res_libros = $stmt_libros->get_result();
            $stmt_libros->close();
        }
        ?>

        <section id="inicio">
            <div class="hero">
                <div class="hero-content">
                    <div>
                        <h1>Lectura Libre y Digital</h1>
                        <p>Explora y descarga libros electrónicos de manera 100% gratuita.</p>
                        <button class="btn btn-primary" style="margin-top: 15px;" onclick="window.location.href='catalogo.php'">Explorar Catálogo</button>
                    </div>
                    <div class="placeholder-box" style="width: 250px; height: 150px;">BIBLIOTECA LIBRE</div>
                </div>
            </div>

            <h2 class="section-title"><?= $busqueda === '' ? 'Novedades y Destacados' : 'Resultados de búsqueda' ?></h2>
            <?php if ($busqueda !== '' && $res_libros && $res_libros->num_rows > 0): ?>
                <p><a class="search-clear" href="index.php">Limpiar búsqueda</a></p>
            <?php endif; ?>
            <div class="book-grid" id="featured-books">
                <?php if ($res_libros && $res_libros->num_rows > 0): ?>
                    <?php while ($libro = $res_libros->fetch_assoc()): ?>
                        <?php
                        $id_lib = (int)$libro['id_libro'];
                        $es_fav = in_array($id_lib, $mis_favoritos, true);
                        $rating_texto = $libro['promedio'] !== null ? $libro['promedio'] : 'Nuevo';
                        $precio_texto = ((float)$libro['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$libro['precio'], 2);
                        ?>
                        <div class="book-card">
                            <a href="php/favorito_toggle.php?id_libro=<?= $id_lib ?>" title="Favorito" style="color: inherit;">
                                <i class="<?= $es_fav ? 'fa-solid' : 'fa-regular' ?> fa-heart wishlist-icon"></i>
                            </a>

                            <?php if (!empty($libro['portada']) && is_file(__DIR__ . '/' . $libro['portada'])): ?>
                                <img src="<?= htmlspecialchars($libro['portada']) ?>" alt="Portada de <?= htmlspecialchars($libro['titulo']) ?>" class="book-card-img" onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'">
                            <?php else: ?>
                                <div class="placeholder-box book-card-img" onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'">PORTADA</div>
                            <?php endif; ?>

                            <h4 onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'" style="cursor:pointer;">
                                <?= htmlspecialchars($libro['titulo']) ?>
                            </h4>
                            <p style="font-size:0.8rem; color: var(--wf-gray);">
                                <?= htmlspecialchars($libro['autor']) ?>
                            </p>
                            <div class="stars">⭐ <?= htmlspecialchars($rating_texto) ?></div>

                            <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center;">
                                <strong style="color: #27ae60;"><?= $precio_texto ?></strong>
                                <?php if (in_array($id_lib, $mis_comprados, true)): ?>
                                    <a href="detalle.php?id=<?= $id_lib ?>" class="btn" title="Ya tienes este libro"><i class="fa-solid fa-check"></i> Comprado</a>
                                <?php else: ?>
                                    <a href="php/carrito_agregar.php?id_libro=<?= $id_lib ?>" class="btn" title="Agregar al carrito">
                                        <i class="fa-solid fa-cart-shopping"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="search-feedback">
                        <?php if ($busqueda !== ''): ?>
                            <p>No se encontraron libros para esta búsqueda.</p>
                            <a class="search-clear" href="index.php">Limpiar búsqueda</a>
                        <?php else: ?>
                            <p>No hay libros disponibles por el momento.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
