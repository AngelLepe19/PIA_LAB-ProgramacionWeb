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
        default:
            $alerta_texto = 'Ocurrió un error al procesar tu solicitud.';
            break;
    }
}
// ---- Catálogo: filtros que llegan por la URL ----
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$id_categoria = (int)($_GET['categoria'] ?? 0);   // 0 = todas
$orden = $_GET['orden'] ?? 'recientes';

// Ordenes permitidos (no se mete texto del usuario al SQL)
$ordenes = [
    'recientes'  => 'l.fecha_alta DESC',
    'populares'  => 'l.lecturas_acumuladas DESC',
    'valoracion' => 'promedio DESC',
    'titulo'     => 'l.titulo ASC',
];
if (!isset($ordenes[$orden])) {
    $orden = 'recientes';
}

// Categorías para el select
$categorias = $conn->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Libros aprobados con autor, categorías y promedio de reseñas aprobadas
$sql = "SELECT l.id_libro, l.titulo, l.precio, l.portada, a.nombre_artistico AS autor,
               (SELECT GROUP_CONCAT(c.nombre SEPARATOR ', ')
                  FROM libro_categoria lc JOIN categorias c ON c.id_categoria = lc.id_categoria
                 WHERE lc.id_libro = l.id_libro) AS categorias,
               (SELECT ROUND(AVG(r.puntuacion), 1) FROM resenas r
                 WHERE r.id_libro = l.id_libro AND r.estado = 'aprobada') AS promedio
        FROM libros l
        INNER JOIN autores a ON l.id_autor = a.id_autor
        WHERE l.estado = 'aprobado'";
$tipos = '';
$params = [];

// Buscar por título, autor o categoría
if ($q !== '') {
    $sql .= " AND (l.titulo LIKE ? ESCAPE '!' OR a.nombre_artistico LIKE ? ESCAPE '!'
              OR EXISTS (SELECT 1 FROM libro_categoria lc2
                         JOIN categorias c2 ON c2.id_categoria = lc2.id_categoria
                         WHERE lc2.id_libro = l.id_libro AND c2.nombre LIKE ? ESCAPE '!'))";
    $like = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $tipos .= 'sss';
    array_push($params, $like, $like, $like);
}

// Filtrar por categoría
if ($id_categoria > 0) {
    $sql .= " AND EXISTS (SELECT 1 FROM libro_categoria lc3
                          WHERE lc3.id_libro = l.id_libro AND lc3.id_categoria = ?)";
    $tipos .= 'i';
    $params[] = $id_categoria;
}

$sql .= " ORDER BY " . $ordenes[$orden];

$stmt = $conn->prepare($sql);
if ($tipos !== '') {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$libros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Favoritos del usuario para pintar el corazón
$mis_favoritos = [];
if ($logueado) {
    $stmt_fav = $conn->prepare("SELECT id_libro FROM favoritos WHERE id_usuario = ?");
    $stmt_fav->bind_param("i", $_SESSION['id_usuario']);
    $stmt_fav->execute();
    $mis_favoritos = array_map('intval', array_column($stmt_fav->get_result()->fetch_all(MYSQLI_ASSOC), 'id_libro'));
    $stmt_fav->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LecturaLibre - Plataforma Gratuita</title>
    <link rel="stylesheet" href="assets/css/estilos.css?v=2">
    <script src="assets/js/main.js"></script>
</head>
<body>

    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
            </a>

            <details class="search-toggle" name="header-controls">
                <summary class="btn search-icon" aria-label="Abrir búsqueda" title="Buscar">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </summary>
                <form class="search-bar" action="catalogo.php" method="get" role="search">
                    <input type="search" id="global-search" name="q" aria-label="Buscar por título, autor o categoría"
                           placeholder="Buscar título, autor o categoría..." value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn search-icon" aria-label="Buscar">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    </button>
                </form>
            </details>

            <details class="nav-menu-toggle" name="header-controls">
                <summary class="btn search-icon" aria-label="Menú" aria-controls="nav-menu">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </summary>
            </details>
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
    <section id="catalogo">
        <h2 class="section-title">Catálogo Completo</h2>

        <!-- Filtros (se mandan por GET) -->
        <form class="catalog-filters" method="get" action="catalogo.php">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Título, autor o categoría" aria-label="Buscar por título, autor o categoría">
            <select name="categoria" aria-label="Categoría">
                <option value="0">Todas las categorías</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= (int)$cat['id_categoria'] ?>" <?= $id_categoria === (int)$cat['id_categoria'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="orden" aria-label="Orden de resultados">
                <option value="recientes" <?= $orden === 'recientes' ? 'selected' : '' ?>>Más recientes</option>
                <option value="populares" <?= $orden === 'populares' ? 'selected' : '' ?>>Más leídos</option>
                <option value="valoracion" <?= $orden === 'valoracion' ? 'selected' : '' ?>>Mejor valorados</option>
                <option value="titulo" <?= $orden === 'titulo' ? 'selected' : '' ?>>Título (A-Z)</option>
            </select>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="catalogo.php" class="btn">Limpiar</a>
        </form>

        <p style="color: var(--wf-gray); margin-bottom: 10px;"><?= count($libros) ?> libro(s) encontrado(s)</p>

        <!-- id distinto a "catalog-books" para que main.js no lo sobrescriba -->
        <div class="book-grid" id="catalogo-libros">
            <?php foreach ($libros as $libro): ?>
                <?php
                $id_lib = (int)$libro['id_libro'];
                $es_fav = in_array($id_lib, $mis_favoritos, true);
                $rating = $libro['promedio'] !== null ? $libro['promedio'] : 'Nuevo';
                $precio = ((float)$libro['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$libro['precio'], 2);
                ?>
                <div class="book-card">
                    <a href="php/favorito_toggle.php?id_libro=<?= $id_lib ?>" title="Favorito" style="color: inherit;">
                        <i class="<?= $es_fav ? 'fa-solid' : 'fa-regular' ?> fa-heart wishlist-icon"></i>
                    </a>
                    <div class="placeholder-box book-card-img" onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'">PORTADA</div>
                    <h4 onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'" style="cursor: pointer;">
                        <?= htmlspecialchars($libro['titulo']) ?>
                    </h4>
                    <p style="font-size: 0.8rem; color: var(--wf-gray);"><?= htmlspecialchars($libro['autor']) ?></p>
                    <p style="font-size: 0.75rem; color: var(--wf-gray);"><?= htmlspecialchars($libro['categorias'] ?? '') ?></p>
                    <div class="stars">⭐ <?= htmlspecialchars($rating) ?></div>
                    <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center;">
                        <strong style="color: #27ae60;"><?= $precio ?></strong>
                        <a href="php/carrito_agregar.php?id_libro=<?= $id_lib ?>" class="btn" title="Agregar al carrito">
                            <i class="fa-solid fa-cart-shopping"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (count($libros) === 0): ?>
                <div class="search-feedback">
                    <p>No se encontraron libros con esos filtros.</p>
                    <?php if ($q !== '' || $id_categoria > 0): ?>
                        <a class="search-clear" href="catalogo.php">Limpiar búsqueda</a>
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