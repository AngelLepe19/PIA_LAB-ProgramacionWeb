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
} elseif (isset($_GET['resena']) && $_GET['resena'] === 'enviada') {
    $alerta_texto = 'Tu reseña se envió y está pendiente de aprobación.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['error'])) {    $alerta_tipo = 'error';
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
        case 'resena_invalida':
            $alerta_texto = 'La reseña necesita calificación de 1 a 5 y un comentario de máximo 500 caracteres.';
            break;
        case 'resena_duplicada':
            $alerta_texto = 'Ya escribiste una reseña para este libro.';
            break;
        default:
            $alerta_texto = 'Ocurrió un error al procesar tu solicitud.';
            break;
    }
}
// ---- Detalle: libro que llega por la URL (detalle.php?id=3) ----
$id_libro = (int)($_GET['id'] ?? 0);

// Datos del libro con su autor, editorial, categorías y promedio de reseñas aprobadas
$stmt = $conn->prepare("
    SELECT l.id_libro, l.titulo, l.isbn, l.sinopsis, l.fecha_publicacion, l.precio, l.lecturas_acumuladas,
           a.nombre_artistico AS autor, a.biografia, e.nombre AS editorial,
           (SELECT GROUP_CONCAT(c.nombre SEPARATOR ', ')
              FROM libro_categoria lc JOIN categorias c ON c.id_categoria = lc.id_categoria
             WHERE lc.id_libro = l.id_libro) AS categorias,
           (SELECT ROUND(AVG(r.puntuacion), 1) FROM resenas r
             WHERE r.id_libro = l.id_libro AND r.estado = 'aprobada') AS promedio
    FROM libros l
    INNER JOIN autores a ON l.id_autor = a.id_autor
    LEFT JOIN editoriales e ON l.id_editorial = e.id_editorial
    WHERE l.id_libro = ? AND l.estado = 'aprobado'
");
$stmt->bind_param("i", $id_libro);
$stmt->execute();
$libro = $stmt->get_result()->fetch_assoc();   // null si no existe o no está aprobado
$stmt->close();

// Reseñas aprobadas (las pendientes no se muestran)
$resenas = [];
if ($libro) {
    $stmt_r = $conn->prepare("
        SELECT r.puntuacion, r.comentario, r.fecha_creacion, u.nombre_completo
        FROM resenas r
        INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
        WHERE r.id_libro = ? AND r.estado = 'aprobada'
        ORDER BY r.fecha_creacion DESC
    ");
    $stmt_r->bind_param("i", $id_libro);
    $stmt_r->execute();
    $resenas = $stmt_r->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_r->close();
}

// Si hay sesión: ¿es favorito? ¿ya lo reseñó?
$es_favorito = false;
$mi_resena = null;
if ($logueado && $libro) {
    $stmt_f = $conn->prepare("SELECT 1 FROM favoritos WHERE id_usuario = ? AND id_libro = ?");
    $stmt_f->bind_param("ii", $_SESSION['id_usuario'], $id_libro);
    $stmt_f->execute();
    $es_favorito = $stmt_f->get_result()->num_rows > 0;
    $stmt_f->close();

    $stmt_m = $conn->prepare("SELECT estado FROM resenas WHERE id_usuario = ? AND id_libro = ?");
    $stmt_m->bind_param("ii", $_SESSION['id_usuario'], $id_libro);
    $stmt_m->execute();
    $mi_resena = $stmt_m->get_result()->fetch_assoc();
    $stmt_m->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LecturaLibre - Plataforma Gratuita</title>
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
                <?= htmlspecialchars($alerta_texto) ?>
            </div>
        </div>
    <?php endif; ?>

   <main class="container">
    <a href="catalogo.php" class="btn" style="margin-bottom: 15px;">&larr; Volver al catálogo</a>

    <?php if (!$libro): ?>
        <!-- El id no existe o el libro no está aprobado -->
        <div style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
            <h2>Libro no encontrado</h2>
            <p>El libro no existe o todavía no ha sido aprobado.</p>
        </div>
    <?php else: ?>
        <?php $precio = ((float)$libro['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$libro['precio'], 2); ?>

        <section id="detalle" style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div class="placeholder-box" style="width: 200px; height: 280px;">PORTADA</div>

                <div style="flex: 1; min-width: 250px;">
                    <h2><?= htmlspecialchars($libro['titulo']) ?></h2>
                    <p><strong>Autor:</strong> <?= htmlspecialchars($libro['autor']) ?></p>
                    <p><strong>Categorías:</strong> <?= htmlspecialchars($libro['categorias'] ?? 'Sin categoría') ?></p>
                    <?php if ($libro['editorial']): ?>
                        <p><strong>Editorial:</strong> <?= htmlspecialchars($libro['editorial']) ?></p>
                    <?php endif; ?>
                    <?php if ($libro['fecha_publicacion']): ?>
                        <p><strong>Publicación:</strong> <?= date('d/m/Y', strtotime($libro['fecha_publicacion'])) ?></p>
                    <?php endif; ?>
                    <?php if ($libro['isbn']): ?>
                        <p><strong>ISBN:</strong> <?= htmlspecialchars($libro['isbn']) ?></p>
                    <?php endif; ?>
                    <div class="stars">⭐ <?= $libro['promedio'] ?? 'Sin reseñas' ?> (<?= count($resenas) ?> reseñas)</div>

                    <p style="margin: 15px 0;"><?= nl2br(htmlspecialchars($libro['sinopsis'] ?? '')) ?></p>
                    <h3 style="color: #27ae60;"><?= $precio ?></h3>
                    <p style="font-size: 0.85rem; color: var(--wf-gray);">Lecturas: <?= (int)$libro['lecturas_acumuladas'] ?></p>

                    <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                        <a href="php/carrito_agregar.php?id_libro=<?= $id_libro ?>" class="btn btn-primary">
                            <i class="fa-solid fa-cart-shopping"></i> Agregar al carrito
                        </a>
                        <a href="php/favorito_toggle.php?id_libro=<?= $id_libro ?>" class="btn">
                            <i class="<?= $es_favorito ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                            <?= $es_favorito ? 'Quitar de favoritos' : 'Agregar a favoritos' ?>
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($libro['biografia']): ?>
                <hr style="margin: 20px 0;">
                <h3>Sobre el autor</h3>
                <p><?= nl2br(htmlspecialchars($libro['biografia'])) ?></p>
            <?php endif; ?>

            <!-- Reseñas aprobadas -->
            <hr style="margin: 20px 0;">
            <h3 id="resenas">Reseñas y Valoraciones</h3>
            <div style="margin: 15px 0;">
                <?php foreach ($resenas as $r): ?>
                    <div style="background: var(--wf-bg); padding: 10px; border-radius: var(--radius); margin-bottom: 10px;">
                        <strong><?= htmlspecialchars($r['nombre_completo']) ?></strong>
                        - <?= str_repeat('⭐', (int)$r['puntuacion']) ?>
                        <small style="color: var(--wf-gray);"><?= date('d/m/Y', strtotime($r['fecha_creacion'])) ?></small>
                        <p><?= nl2br(htmlspecialchars($r['comentario'])) ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (count($resenas) === 0): ?>
                    <p>Este libro aún no tiene reseñas.</p>
                <?php endif; ?>
            </div>

            <!-- Formulario de reseña (se guarda en php/resena_guardar.php) -->
            <?php if (!$logueado): ?>
                <p style="color: var(--wf-gray);"><em>Inicia sesión para escribir una reseña.</em></p>
            <?php elseif ($mi_resena): ?>
                <p style="color: var(--wf-gray);"><em>Ya enviaste tu reseña de este libro (estado: <?= htmlspecialchars($mi_resena['estado']) ?>).</em></p>
            <?php else: ?>
                <h4>Escribir una reseña</h4>
                <form action="php/resena_guardar.php" method="POST" style="margin-top: 10px;">
                    <input type="hidden" name="id_libro" value="<?= $id_libro ?>">
                    <div class="form-group">
                        <label for="puntuacion">Calificación</label>
                        <select id="puntuacion" name="puntuacion">
                            <option value="5">⭐⭐⭐⭐⭐ (5/5)</option>
                            <option value="4">⭐⭐⭐⭐ (4/5)</option>
                            <option value="3">⭐⭐⭐ (3/5)</option>
                            <option value="2">⭐⭐ (2/5)</option>
                            <option value="1">⭐ (1/5)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <textarea name="comentario" rows="3" maxlength="500" required placeholder="Escribe tu opinión sobre el libro..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Enviar reseña para moderación</button>
                </form>
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