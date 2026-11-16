<?php
session_start();
require_once 'config/conexion.php';

$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = isset($_SESSION['id_usuario']);
$id_usuario = $logueado ? (int)$_SESSION['id_usuario'] : 0;
$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

$id_libro = (int)($_GET['id'] ?? $_POST['id_libro'] ?? 0);

// Procesar envío de reseña (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'enviar_resena') {
    if (!$logueado) {
        header("Location: detalle.php?id=" . $id_libro . "&error=inicia_sesion");
        exit;
    }

    $puntuacion = (int)($_POST['puntuacion'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');

    if ($id_libro <= 0 || $puntuacion < 1 || $puntuacion > 5 || $comentario === '') {
        header("Location: detalle.php?id=" . $id_libro . "&error=resena_invalida");
        exit;
    }

    if (mb_strlen($comentario) > 500) {
        $comentario = mb_substr($comentario, 0, 500);
    }

    // La tabla resenas tiene PK compuesta (id_libro, id_usuario); si ya reseñó antes, actualiza y vuelve a 'pendiente'
    $sql_res = "INSERT INTO resenas (id_libro, id_usuario, puntuacion, comentario, fecha_creacion, estado, id_moderador, fecha_moderacion)
                VALUES (?, ?, ?, ?, NOW(), 'pendiente', NULL, NULL)
                ON DUPLICATE KEY UPDATE
                    puntuacion = VALUES(puntuacion),
                    comentario = VALUES(comentario),
                    fecha_creacion = NOW(),
                    estado = 'pendiente',
                    id_moderador = NULL,
                    fecha_moderacion = NULL";
    $stmt_r = $conn->prepare($sql_res);
    $stmt_r->bind_param("iiis", $id_libro, $id_usuario, $puntuacion, $comentario);
    $ok = $stmt_r->execute();
    $stmt_r->close();

    if ($ok) {
        header("Location: detalle.php?id=" . $id_libro . "&resena=enviada");
    } else {
        header("Location: detalle.php?id=" . $id_libro . "&error=error_resena");
    }
    exit;
}

// Contar libros en el carrito real del usuario
$cart_count = 0;
if ($logueado) {
    $stmt_cart = $conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
    $stmt_cart->bind_param("i", $id_usuario);
    $stmt_cart->execute();
    $res_cart = $stmt_cart->get_result()->fetch_assoc();
    $cart_count = (int)($res_cart['total'] ?? 0);
    $stmt_cart->close();
}

// Mensajes de aviso
$alerta_texto = '';
$alerta_tipo = '';
if (isset($_GET['resena']) && $_GET['resena'] === 'enviada') {
    $alerta_texto = 'Tu reseña fue enviada y está pendiente de aprobación por un moderador.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['error'])) {
    $alerta_tipo = 'error';
    switch ($_GET['error']) {
        case 'inicia_sesion':
            $alerta_texto = 'Debes iniciar sesión para realizar esta acción.';
            break;
        case 'resena_invalida':
            $alerta_texto = 'Selecciona una calificación válida y escribe tu comentario.';
            break;
        default:
            $alerta_texto = 'Ocurrió un error al procesar tu solicitud.';
            break;
    }
}

// Consultar datos completos del libro en MySQL
$libro = null;
if ($id_libro > 0) {
    $sql_libro = "
        SELECT l.id_libro, l.titulo, l.isbn, l.sinopsis, l.fecha_publicacion, l.portada,
               l.precio, l.lecturas_acumuladas, l.estado,
               a.nombre_artistico AS autor,
               e.nombre AS editorial,
               GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', ') AS categorias,
               (SELECT COUNT(*) FROM descargas d WHERE d.id_libro = l.id_libro) AS total_descargas,
               ROUND((SELECT AVG(r.puntuacion) FROM resenas r WHERE r.id_libro = l.id_libro AND r.estado = 'aprobada'), 1) AS promedio
        FROM libros l
        INNER JOIN autores a ON l.id_autor = a.id_autor
        LEFT JOIN editoriales e ON l.id_editorial = e.id_editorial
        LEFT JOIN libro_categoria lc ON l.id_libro = lc.id_libro
        LEFT JOIN categorias c ON lc.id_categoria = c.id_categoria
        WHERE l.id_libro = ? AND l.estado = 'aprobado'
        GROUP BY l.id_libro, l.titulo, l.isbn, l.sinopsis, l.fecha_publicacion, l.portada,
                 l.precio, l.lecturas_acumuladas, l.estado, a.nombre_artistico, e.nombre
        LIMIT 1
    ";
    $stmt_l = $conn->prepare($sql_libro);
    $stmt_l->bind_param("i", $id_libro);
    $stmt_l->execute();
    $libro = $stmt_l->get_result()->fetch_assoc();
    $stmt_l->close();
}

// Verificar si el libro está en favoritos del usuario y cargar reseñas aprobadas
$es_favorito = false;
$resenas_aprobadas = [];
$mi_resena = null;

if ($libro) {
    if ($logueado) {
        $stmt_f = $conn->prepare("SELECT 1 FROM favoritos WHERE id_usuario = ? AND id_libro = ?");
        $stmt_f->bind_param("ii", $id_usuario, $id_libro);
        $stmt_f->execute();
        $es_favorito = $stmt_f->get_result()->num_rows > 0;
        $stmt_f->close();

        $stmt_mr = $conn->prepare("SELECT puntuacion, comentario, estado FROM resenas WHERE id_libro = ? AND id_usuario = ?");
        $stmt_mr->bind_param("ii", $id_libro, $id_usuario);
        $stmt_mr->execute();
        $mi_resena = $stmt_mr->get_result()->fetch_assoc();
        $stmt_mr->close();
    }

    $sql_resenas = "
        SELECT r.puntuacion, r.comentario, r.fecha_creacion, u.nombre_completo AS lector
        FROM resenas r
        INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
        WHERE r.id_libro = ? AND r.estado = 'aprobada'
        ORDER BY r.fecha_creacion DESC
    ";
    $stmt_ra = $conn->prepare($sql_resenas);
    $stmt_ra->bind_param("i", $id_libro);
    $stmt_ra->execute();
    $resenas_aprobadas = $stmt_ra->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_ra->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $libro ? htmlspecialchars($libro['titulo']) . ' - LecturaLibre' : 'Detalle del libro | LecturaLibre' ?></title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <script src="assets/js/main.js"></script>
</head>
<body>

    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
            </a>

            <form class="search-bar" action="catalogo.php" method="GET">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="global-search" name="q"
                       aria-label="Buscar libros"
                       placeholder="Buscar título, autor o categoría...">
            </form>

            <ul class="nav-links">
                <li><a href="index.php" id="link-inicio">Inicio</a></li>
                <li><a href="catalogo.php" id="link-catalogo" class="active-link">Catálogo</a></li>
                <li><a href="nosotros.php" id="link-nosotros">Nosotros</a></li>
                <li><a href="contacto.php" id="link-contacto">Contacto</a></li>

                <?php if ($id_rol === 2 || $id_rol === 4): ?>
                    <li id="author-nav-item"><a href="panelautor.php">Panel Autor</a></li>
                <?php endif; ?>

                <?php if ($id_rol === 3 || $id_rol === 4): ?>
                    <li id="worker-nav-item"><a href="paneltrabajador.php">Panel Trabajador</a></li>
                <?php endif; ?>

                <?php if ($id_rol === 4): ?>
                    <li id="admin-nav-item"><a href="paneladmin.php">Panel Admin</a></li>
                <?php endif; ?>
            </ul>

            <div class="user-actions">
                <button type="button" class="btn" aria-label="Ver carrito" onclick="window.location.href='carrito.php'">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span id="cart-count" class="badge"><?= $cart_count ?></span>
                </button>

                <?php if (!$logueado): ?>
                    <div id="auth-buttons">
                        <button type="button" class="btn" onclick="openAuthModal('login')">
                            <i class="fa-regular fa-user"></i> Iniciar Sesión
                        </button>
                        <button type="button" class="btn btn-primary" onclick="openAuthModal('signup')">
                            Registrarse
                        </button>
                    </div>
                <?php else: ?>
                    <div id="user-menu" style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" class="btn" onclick="window.location.href='perfil.php'">
                            <i class="fa-solid fa-circle-user"></i>
                            <span id="user-display-name"><?= htmlspecialchars($nombre_usuario) ?></span>
                        </button>
                        <a href="auth/logout.php" class="btn btn-danger" aria-label="Cerrar sesión" title="Cerrar sesión">
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
        <section id="detalle">
            <a class="btn" style="margin-bottom: 15px;" href="catalogo.php">
                &larr; Volver al Catálogo
            </a>

            <div style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                <?php if (!$libro): ?>
                    <p>El libro solicitado no existe o no está disponible en el catálogo.</p>
                <?php else: ?>
                    <?php
                    $portada_ruta = trim((string)($libro['portada'] ?? ''));
                    $precio_texto = ((float)$libro['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$libro['precio'], 2);
                    $rating_texto = $libro['promedio'] !== null ? $libro['promedio'] . ' / 5' : 'Sin valoraciones aún';
                    $descargas_mostrar = max((int)$libro['total_descargas'], (int)$libro['lecturas_acumuladas']);
                    ?>
                    <div id="book-detail-content" style="display: flex; gap: 30px; margin-bottom: 25px; flex-wrap: wrap;">
                        <?php if ($portada_ruta !== '' && file_exists($portada_ruta)): ?>
                            <img src="<?= htmlspecialchars($portada_ruta) ?>"
                                 alt="Portada de <?= htmlspecialchars($libro['titulo']) ?>"
                                 style="width: 200px; height: 280px; object-fit: cover; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                        <?php else: ?>
                            <div class="placeholder-box" style="width: 200px; height: 280px; flex-shrink: 0;">
                                PORTADA
                            </div>
                        <?php endif; ?>

                        <div style="flex: 1; min-width: 260px;">
                            <h2><?= htmlspecialchars($libro['titulo']) ?></h2>
                            <p style="margin-top: 5px;">
                                <strong>Autor:</strong> <?= htmlspecialchars($libro['autor']) ?>
                                <?php if (!empty($libro['categorias'])): ?>
                                    | <strong>Categorías:</strong> <?= htmlspecialchars($libro['categorias']) ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($libro['editorial']) || !empty($libro['isbn'])): ?>
                                <p style="font-size: 0.85rem; color: var(--wf-gray); margin-top: 4px;">
                                    <?php if (!empty($libro['editorial'])): ?>
                                        <strong>Editorial:</strong> <?= htmlspecialchars($libro['editorial']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($libro['isbn'])): ?>
                                        <?= !empty($libro['editorial']) ? ' | ' : '' ?><strong>ISBN:</strong> <?= htmlspecialchars($libro['isbn']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <div class="stars" style="margin: 8px 0;">
                                ⭐⭐⭐⭐⭐ (<?= htmlspecialchars($rating_texto) ?>)
                            </div>

                            <p style="margin: 15px 0;">
                                <?= !empty($libro['sinopsis']) ? nl2br(htmlspecialchars($libro['sinopsis'])) : 'Sin sinopsis disponible.' ?>
                            </p>

                            <h3 style="color: #27ae60;"><?= $precio_texto ?></h3>
                            <p style="font-size: 0.85rem; color: var(--wf-gray);">
                                Descargas / Lecturas totales: <?= $descargas_mostrar ?>
                            </p>

                            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="php/carrito_agregar.php?id_libro=<?= $id_libro ?>" class="btn btn-primary">
                                    <i class="fa-solid fa-cart-shopping"></i> Agregar al Carrito de Lectura
                                </a>
                                <a href="php/favorito_toggle.php?id_libro=<?= $id_libro ?>" class="btn">
                                    <i class="<?= $es_favorito ? 'fa-solid' : 'fa-regular' ?> fa-heart" style="color: #e74c3c;"></i>
                                    <?= $es_favorito ? 'En Favoritos' : 'Guardar en Favoritos' ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div id="book-reviews">
                        <hr style="margin: 20px 0;">
                        <h3>Reseñas y Valoraciones</h3>

                        <div id="reviews-list" style="margin: 15px 0;">
                            <?php if (count($resenas_aprobadas) === 0): ?>
                                <p style="color: var(--wf-gray);">Sin reseñas aprobadas disponibles para este libro.</p>
                            <?php else: ?>
                                <?php foreach ($resenas_aprobadas as $rev): ?>
                                    <div style="background: var(--wf-bg); padding: 12px 15px; border-radius: var(--radius); margin-bottom: 10px; border: 1px solid var(--wf-light-gray);">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <strong><?= htmlspecialchars($rev['lector']) ?></strong>
                                            <small style="color: var(--wf-gray);"><?= htmlspecialchars(substr((string)$rev['fecha_creacion'], 0, 10)) ?></small>
                                        </div>
                                        <div style="margin: 4px 0;"><?= str_repeat('⭐', (int)$rev['puntuacion']) ?></div>
                                        <p style="font-size: 0.92rem;"><?= nl2br(htmlspecialchars($rev['comentario'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div id="add-review-box" style="margin-top: 20px;">
                            <h4><?= $mi_resena ? 'Actualizar tu reseña' : 'Escribir una reseña' ?></h4>

                            <?php if (!$logueado): ?>
                                <p style="color: var(--wf-gray); margin-top: 8px;">
                                    <em><a onclick="openAuthModal('login')" style="color: var(--wf-accent); font-weight: 600; cursor: pointer; text-decoration: underline;">Inicia sesión</a> para escribir una reseña.</em>
                                </p>
                            <?php else: ?>
                                <?php if ($mi_resena): ?>
                                    <p style="font-size: 0.85rem; color: var(--wf-gray); margin-top: 4px;">
                                        Estado actual de tu reseña: <strong><?= htmlspecialchars(ucfirst($mi_resena['estado'])) ?></strong> (si la editas volverá a revisión).
                                    </p>
                                <?php endif; ?>

                                <form action="detalle.php?id=<?= $id_libro ?>" method="POST" style="margin-top: 10px; max-width: 600px;">
                                    <input type="hidden" name="accion" value="enviar_resena">
                                    <input type="hidden" name="id_libro" value="<?= $id_libro ?>">

                                    <div class="form-group">
                                        <label for="review-rating">Calificación</label>
                                        <select id="review-rating" name="puntuacion" required>
                                            <?php $punt_actual = (int)($mi_resena['puntuacion'] ?? 5); ?>
                                            <option value="5" <?= $punt_actual === 5 ? 'selected' : '' ?>>⭐⭐⭐⭐⭐ (5/5)</option>
                                            <option value="4" <?= $punt_actual === 4 ? 'selected' : '' ?>>⭐⭐⭐⭐ (4/5)</option>
                                            <option value="3" <?= $punt_actual === 3 ? 'selected' : '' ?>>⭐⭐⭐ (3/5)</option>
                                            <option value="2" <?= $punt_actual === 2 ? 'selected' : '' ?>>⭐⭐ (2/5)</option>
                                            <option value="1" <?= $punt_actual === 1 ? 'selected' : '' ?>>⭐ (1/5)</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <textarea id="review-text" name="comentario" rows="3" maxlength="500" aria-label="Tu reseña"
                                                  placeholder="Escribe tu opinión sobre el libro (máx. 500 caracteres)..." required><?= htmlspecialchars($mi_resena['comentario'] ?? '') ?></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        Enviar Reseña para Moderación
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div class="modal-overlay" id="authModal">
        <div class="modal">
            <button type="button" class="close-modal" aria-label="Cerrar" onclick="closeAuthModal()">&times;</button>

            <form id="loginForm" action="auth/login.php" method="POST">
                <h3 style="margin-bottom: 15px;">Iniciar Sesión</h3>
                <div class="form-group">
                    <label for="login-email">Correo Electrónico</label>
                    <input type="email" name="correo" id="login-email" placeholder="usuario@correo.com" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Contraseña</label>
                    <input type="password" name="password" id="login-password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Ingresar</button>
                <p class="auth-toggle">
                    ¿Olvidaste tu contraseña? <a onclick="toggleAuthMode('recover')">Recuperar aquí</a>
                </p>
                <p class="auth-toggle">
                    ¿No tienes una cuenta? <a onclick="toggleAuthMode('signup')">Regístrate aquí</a>
                </p>
            </form>

            <form id="signupForm" action="auth/registro.php" method="POST" style="display: none;" onsubmit="return validarRegistro(event)">
                <h3 style="margin-bottom: 15px;">Crear una Cuenta</h3>
                <div class="form-group">
                    <label for="signup-name">Nombre Completo</label>
                    <input type="text" name="nombre_completo" id="signup-name" placeholder="Tu Nombre" required>
                </div>
                <div class="form-group">
                    <label for="signup-email">Correo Electrónico</label>
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
                <p class="auth-toggle">
                    ¿Ya tienes cuenta? <a onclick="toggleAuthMode('login')">Inicia sesión</a>
                </p>
            </form>

            <form id="recoverForm" action="auth/recuperar.php" method="POST" style="display: none;">
                <h3 style="margin-bottom: 15px;">Recuperar Contraseña</h3>
                <p style="font-size:0.85rem; color: var(--wf-gray); margin-bottom:15px;">Ingresa tu correo registrado para enviarte un enlace de restablecimiento.</p>
                <div class="form-group">
                    <label for="recover-email">Correo Electrónico</label>
                    <input type="email" name="correo" id="recover-email" placeholder="usuario@correo.com" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Enviar Correo</button>
                <p class="auth-toggle">
                    <a onclick="toggleAuthMode('login')">&larr; Volver a inicio de sesión</a>
                </p>
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