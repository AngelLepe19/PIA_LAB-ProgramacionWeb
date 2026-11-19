<?php
/*
 * Página principal de LecturaLibre.
 * Carga la sesión del usuario, muestra alertas de acceso/registro,
 * lista los libros destacados o resultados de búsqueda y contiene los modales de autenticación.
 */

session_start();
require_once 'config/conexion.php';

// Datos básicos de la página y la sesión activa
$pagina_actual  = basename($_SERVER['PHP_SELF']);
$busqueda       = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$logueado       = isset($_SESSION['id_usuario']);
$id_rol         = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

// Contar cuántos libros tiene el usuario en su carrito
$cart_count = 0;
if ($logueado) {
    $stmt_cart = $conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
    $stmt_cart->bind_param("i", $_SESSION['id_usuario']);
    $stmt_cart->execute();
    $res_cart = $stmt_cart->get_result()->fetch_assoc();
    $cart_count = (int)($res_cart['total'] ?? 0);
    $stmt_cart->close();
}

// Mensajes de alerta según los parámetros recibidos por URL
$alerta_texto      = '';
$alerta_html_extra = '';
$alerta_tipo       = ''; // 'exito' o 'error'

if (isset($_GET['registro']) && $_GET['registro'] === 'exitoso') {
    $alerta_texto = '¡Cuenta creada con éxito! Bienvenido a LecturaLibre.';
    $alerta_tipo  = 'exito';
} elseif (isset($_GET['registro']) && $_GET['registro'] === 'autor') {
    $alerta_texto = '¡Cuenta de autor creada! Entra a "Panel Autor" para publicar tu primer libro.';
    $alerta_tipo  = 'exito';
} elseif (isset($_GET['recuperar']) && $_GET['recuperar'] === 'enviado') {
    $alerta_texto = 'Si el correo está registrado, se ha generado un enlace de restablecimiento (válido por 1 hora).';
    $alerta_tipo  = 'exito';
    // En entorno local mostramos el enlace directo para poder probar sin servidor de correo
    if (!empty($_SESSION['link_recuperacion_demo'])) {
        $link_demo = htmlspecialchars($_SESSION['link_recuperacion_demo']);
        $alerta_html_extra = ' <a href="' . $link_demo . '" style="font-weight:bold; text-decoration:underline;">Abrir enlace de recuperación</a>';
        unset($_SESSION['link_recuperacion_demo']);
    }
} elseif (isset($_GET['restablecido']) && $_GET['restablecido'] === 'exitoso') {
    $alerta_texto = 'Tu contraseña ha sido actualizada. Ya puedes iniciar sesión.';
    $alerta_tipo  = 'exito';
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

    <!-- Barra de navegación principal -->
    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
            </a>

            <!-- Menú móvil desplegable sin depender de JS -->
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

                    <!-- Accesos extra según el rol del usuario (2: Autor, 3: Trabajador, 4: Admin) -->
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

    <!-- Banner de notificaciones -->
    <?php if ($alerta_texto !== ''): ?>
        <div class="container" style="margin-top: 15px;">
            <div style="padding: 12px 16px; border-radius: 6px; font-size: 0.9rem; background: <?= $alerta_tipo === 'exito' ? '#d4edda' : '#f8d7da' ?>; color: <?= $alerta_tipo === 'exito' ? '#155724' : '#721c24' ?>; border: 1px solid <?= $alerta_tipo === 'exito' ? '#c3e6cb' : '#f5c6cb' ?>;">
                <?= htmlspecialchars($alerta_texto) ?><?= $alerta_html_extra ?>
            </div>
        </div>
    <?php endif; ?>

    <main class="container">
        <?php
        // Obtenemos los IDs de favoritos y libros ya adquiridos por el usuario para reflejarlo en las tarjetas
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

        // Consulta de libros aprobados; el promedio solo toma en cuenta reseñas ya aprobadas
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

        // Sin búsqueda mostramos solo los 4 más recientes; con búsqueda usamos consulta preparada
        if ($busqueda === '') {
            $res_libros = $conn->query($sql_libros . " LIMIT 4");
        } else {
            // Escapamos comodines SQL para evitar coincidencias inesperadas en el LIKE
            $patron = '%' . strtr($busqueda, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $stmt_libros = $conn->prepare($sql_libros);
            $stmt_libros->bind_param("sss", $patron, $patron, $patron);
            $stmt_libros->execute();
            $res_libros = $stmt_libros->get_result();
            $stmt_libros->close();
        }
        ?>

        <!-- Banner principal y catálogo destacado -->
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
                        $id_lib       = (int)$libro['id_libro'];
                        $es_fav       = in_array($id_lib, $mis_favoritos, true);
                        $rating_texto = $libro['promedio'] !== null ? $libro['promedio'] : 'Nuevo';
                        $precio_texto = ((float)$libro['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$libro['precio'], 2);
                        ?>
                        <div class="book-card">
                            <a href="php/favorito_toggle.php?id_libro=<?= $id_lib ?>" title="Favorito" style="color: inherit;">
                                <i class="<?= $es_fav ? 'fa-solid' : 'fa-regular' ?> fa-heart wishlist-icon"></i>
                            </a>

                            <!-- Validamos que la imagen exista en el servidor antes de renderizarla -->
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

    <!-- Modal de autenticación (Login, Registro y Recuperación de contraseña) -->
    <div class="modal-overlay" id="authModal">
        <div class="modal">
            <span class="close-modal" onclick="closeAuthModal()">&times;</span>

            <!-- Formulario de inicio de sesión -->
            <form id="loginForm" action="auth/login.php" method="POST">
                <h2>Iniciar Sesión</h2>
                <div class="form-group">
                    <label for="login-email">Correo electrónico</label>
                    <input type="email" id="login-email" name="correo" required placeholder="tu@correo.com">
                </div>
                <div class="form-group">
                    <label for="login-pass">Contraseña</label>
                    <input type="password" id="login-pass" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Entrar</button>
                <p style="margin-top: 12px; font-size: 0.85rem; text-align: center;">
                    <a href="#" onclick="openAuthModal('recover'); return false;">¿Olvidaste tu contraseña?</a>
                </p>
                <p style="margin-top: 8px; font-size: 0.85rem; text-align: center;">
                    ¿No tienes cuenta? <a href="#" onclick="openAuthModal('signup'); return false;">Regístrate aquí</a>
                </p>
            </form>

            <!-- Formulario de registro -->
            <form id="signupForm" action="auth/registro.php" method="POST" style="display: none;">
                <h2>Crear Cuenta</h2>
                <div class="form-group">
                    <label for="reg-nombre">Nombre completo</label>
                    <input type="text" id="reg-nombre" name="nombre_completo" required placeholder="Ej. Ana López">
                </div>
                <div class="form-group">
                    <label for="reg-email">Correo electrónico</label>
                    <input type="email" id="reg-email" name="correo" required placeholder="tu@correo.com">
                </div>
                <div class="form-group">
                    <label for="reg-pass">Contraseña</label>
                    <input type="password" id="reg-pass" name="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                </div>
                <div class="form-group">
                    <label for="reg-rol">Tipo de cuenta</label>
                    <select id="reg-rol" name="id_rol">
                        <option value="1">Lector</option>
                        <option value="2">Autor (publicar libros)</option>
                    </select>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-start; gap: 8px;">
                    <input type="checkbox" id="reg-terminos" name="terminos" value="1" required style="width: auto; margin-top: 4px;">
                    <label for="reg-terminos" style="font-size: 0.82rem; font-weight: normal;">
                        Acepto los <a href="terminos.php" target="_blank">Términos de uso</a> y el <a href="privacidad.php" target="_blank">Aviso de privacidad</a>.
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Registrarse</button>
                <p style="margin-top: 12px; font-size: 0.85rem; text-align: center;">
                    ¿Ya tienes cuenta? <a href="#" onclick="openAuthModal('login'); return false;">Inicia sesión</a>
                </p>
            </form>

            <!-- Formulario de recuperación de contraseña -->
            <form id="recoverForm" action="auth/recuperar.php" method="POST" style="display: none;">
                <h2>Recuperar Contraseña</h2>
                <p style="font-size: 0.85rem; color: var(--wf-gray); margin-bottom: 12px;">
                    Ingresa tu correo y te enviaremos un enlace para restablecer tu clave.
                </p>
                <div class="form-group">
                    <label for="rec-email">Correo electrónico</label>
                    <input type="email" id="rec-email" name="correo" required placeholder="tu@correo.com">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Enviar enlace</button>
                <p style="margin-top: 12px; font-size: 0.85rem; text-align: center;">
                    <a href="#" onclick="openAuthModal('login'); return false;">Volver a iniciar sesión</a>
                </p>
            </form>
        </div>
    </div>

    <!-- Pie de página -->
    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> LecturaLibre - Plataforma de Lectura Digital Gratuita.</p>
            <div style="margin-top: 8px; font-size: 0.85rem;">
                <a href="nosotros.php">Nosotros</a> |
                <a href="contacto.php">Contacto</a> |
                <a href="terminos.php">Términos de uso</a> |
                <a href="privacidad.php">Aviso de privacidad</a>
            </div>
        </div>
    </footer>

</body>
</html>