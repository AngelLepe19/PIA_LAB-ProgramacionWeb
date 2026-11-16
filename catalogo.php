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
            $alerta_texto = 'Inicia sesión para guardar favoritos o agregar libros al carrito.';
            break;
        default:
            $alerta_texto = 'Ocurrió un error al procesar tu solicitud.';
            break;
    }
}

// Obtener IDs de favoritos del usuario actual
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

// Obtener categorías reales desde MySQL
$categorias_lista = [];
$res_cats = $conn->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre ASC");
if ($res_cats) {
    while ($cat = $res_cats->fetch_assoc()) {
        $categorias_lista[] = $cat;
    }
}

// Consultar todos los libros aprobados desde MySQL con sus categorías y calificación promedio
$sql_libros = "
    SELECT l.id_libro, l.titulo, l.precio, l.portada, a.nombre_artistico AS autor,
           ROUND(AVG(CASE WHEN r.estado = 'aprobada' THEN r.puntuacion END), 1) AS promedio,
           GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', ') AS categorias
    FROM libros l
    INNER JOIN autores a ON l.id_autor = a.id_autor
    LEFT JOIN resenas r ON l.id_libro = r.id_libro
    LEFT JOIN libro_categoria lc ON l.id_libro = lc.id_libro
    LEFT JOIN categorias c ON lc.id_categoria = c.id_categoria
    WHERE l.estado = 'aprobado'
    GROUP BY l.id_libro, l.titulo, l.precio, l.portada, a.nombre_artistico
    ORDER BY l.fecha_alta DESC
";
$res_libros = $conn->query($sql_libros);

// Búsqueda opcional si viene ?q= desde otra página
$busqueda_inicial = trim($_GET['q'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo - LecturaLibre</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <script src="assets/js/main.js"></script>
</head>
<body>

    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
            </a>

            <div class="search-bar">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="global-search" value="<?= htmlspecialchars($busqueda_inicial) ?>" placeholder="Buscar título, autor o categoría..." onkeyup="filtrarCatalogo()">
            </div>

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
        <section id="catalogo">
            <h2 class="section-title">Catálogo Completo</h2>
            <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                <select id="category-filter" class="form-group" style="width: 220px; padding: 8px; border-radius: var(--radius); border: 1px solid var(--wf-border);" onchange="filtrarCatalogo()">
                    <option value="all">Todas las Categorías</option>
                    <?php foreach ($categorias_lista as $cat): ?>
                        <option value="<?= htmlspecialchars(mb_strtolower($cat['nombre'])) ?>">
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="book-grid" id="catalog-books-mysql">
                <?php if ($res_libros && $res_libros->num_rows > 0): ?>
                    <?php while ($libro = $res_libros->fetch_assoc()): ?>
                        <?php
                        $id_lib = (int)$libro['id_libro'];
                        $es_fav = in_array($id_lib, $mis_favoritos, true);
                        $rating_texto = $libro['promedio'] !== null ? $libro['promedio'] : 'Nuevo';
                        $precio_texto = ((float)$libro['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$libro['precio'], 2);
                        $cats_libro = $libro['categorias'] ?? '';
                        $portada_ruta = trim((string)($libro['portada'] ?? ''));
                        ?>
                        <div class="book-card"
                             data-titulo="<?= htmlspecialchars(mb_strtolower($libro['titulo'])) ?>"
                             data-autor="<?= htmlspecialchars(mb_strtolower($libro['autor'])) ?>"
                             data-categorias="<?= htmlspecialchars(mb_strtolower($cats_libro)) ?>">

                            <a href="php/favorito_toggle.php?id_libro=<?= $id_lib ?>" title="Favorito" style="color: inherit;">
                                <i class="<?= $es_fav ? 'fa-solid' : 'fa-regular' ?> fa-heart wishlist-icon"></i>
                            </a>

                            <?php if ($portada_ruta !== '' && file_exists($portada_ruta)): ?>
                                <img src="<?= htmlspecialchars($portada_ruta) ?>"
                                     alt="Portada de <?= htmlspecialchars($libro['titulo']) ?>"
                                     class="book-card-img"
                                     style="object-fit: cover; border-radius: var(--radius); cursor: pointer;"
                                     onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'">
                            <?php else: ?>
                                <div class="placeholder-box book-card-img" style="cursor: pointer;" onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'">
                                    PORTADA
                                </div>
                            <?php endif; ?>

                            <h4 onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'" style="cursor:pointer;">
                                <?= htmlspecialchars($libro['titulo']) ?>
                            </h4>
                            <p style="font-size:0.8rem; color: var(--wf-gray);">
                                <?= htmlspecialchars($libro['autor']) ?>
                            </p>
                            <?php if ($cats_libro !== ''): ?>
                                <p style="font-size:0.75rem; color: var(--wf-accent); margin-top: 2px;">
                                    <?= htmlspecialchars($cats_libro) ?>
                                </p>
                            <?php endif; ?>
                            <div class="stars">⭐⭐⭐⭐⭐ (<?= htmlspecialchars($rating_texto) ?>)</div>

                            <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center;">
                                <strong style="color: #27ae60;"><?= $precio_texto ?></strong>
                                <a href="php/carrito_agregar.php?id_libro=<?= $id_lib ?>" class="btn" title="Agregar al carrito">
                                    <i class="fa-solid fa-cart-shopping"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No hay libros disponibles en el catálogo por el momento.</p>
                <?php endif; ?>
            </div>

            <p id="no-results-msg" style="display: none; color: var(--wf-gray); margin-top: 10px;">
                No se encontraron libros que coincidan con tu búsqueda.
            </p>
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

    <script>
        function filtrarCatalogo() {
            const texto = document.getElementById('global-search').value.toLowerCase().trim();
            const categoria = document.getElementById('category-filter').value;
            const tarjetas = document.querySelectorAll('#catalog-books-mysql .book-card');
            let visibles = 0;

            tarjetas.forEach(card => {
                const titulo = card.dataset.titulo || '';
                const autor = card.dataset.autor || '';
                const cats = card.dataset.categorias || '';

                const coincideTexto = texto === '' || titulo.includes(texto) || autor.includes(texto) || cats.includes(texto);
                const listaCats = cats.split(',').map(c => c.trim());
                const coincideCat = categoria === 'all' || listaCats.includes(categoria);

                if (coincideTexto && coincideCat) {
                    card.style.display = 'flex';
                    visibles++;
                } else {
                    card.style.display = 'none';
                }
            });

            const msgVacio = document.getElementById('no-results-msg');
            if (msgVacio) {
                msgVacio.style.display = (tarjetas.length > 0 && visibles === 0) ? 'block' : 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', filtrarCatalogo);
    </script>
</body>
</html>