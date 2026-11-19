<?php
/*
 * Página de detalle de un libro en LecturaLibre.
 * Recibe el ID del libro por la URL (detalle.php?id=X), verifica que el libro exista
 * y esté aprobado, muestra su información completa, las reseñas ya moderadas y adapta
 * los botones según si el usuario ya lo compró, lo tiene en favoritos o ya lo reseñó.
 */
session_start();
require_once 'config/conexion.php';

// Datos básicos de la sesión para controlar la barra de navegación y permisos
$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = isset($_SESSION['id_usuario']);
$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

// Contar libros en el carrito real del usuario
// Si el usuario inició sesión, contamos cuántos libros tiene en su carrito para mostrarlo arriba
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
// Revisamos los parámetros GET de la URL para mostrar avisos de éxito o error al usuario
// (por ejemplo, cuando envía una reseña o intenta agregar un libro que ya había comprado)
$alerta_texto = '';
$alerta_tipo = '';
if (isset($_GET['registro']) && $_GET['registro'] === 'exitoso') {
    $alerta_texto = '¡Cuenta creada con éxito! Bienvenido a LecturaLibre.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['resena']) && $_GET['resena'] === 'enviada') {
    $alerta_texto = 'Tu reseña se envió y está pendiente de aprobación.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['compra']) && $_GET['compra'] === 'ya_tienes') {
    $alerta_texto = 'Ya compraste este libro. Puedes descargarlo de nuevo con el botón "Descargar PDF".';
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
        case 'resena_invalida':
            $alerta_texto = 'La reseña necesita calificación de 1 a 5 y un comentario de máximo 500 caracteres.';
            break;
        case 'resena_sin_compra':
            $alerta_texto = 'Solo puedes reseñar libros que hayas comprado.';
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
// Convertimos el parámetro 'id' de la URL a entero para evitar valores inválidos o inyección
$id_libro = (int)($_GET['id'] ?? 0);

// Consulta principal del libro:
// - Se usa INNER JOIN con autores porque todo libro tiene un autor obligatorio.
// - Se usa LEFT JOIN con editoriales porque un libro independiente puede no tener editorial (NULL).
// - Las subconsultas juntan todas sus categorías en un texto y calculan el promedio solo de reseñas aprobadas.
// - El filtro "l.estado = 'aprobado'" impide que alguien vea libros pendientes o dados de baja cambiando el ID en la URL.
$stmt = $conn->prepare("
    SELECT l.id_libro, l.id_autor, l.titulo, l.isbn, l.sinopsis, l.fecha_publicacion, l.precio, l.lecturas_acumuladas, l.portada,
           l.num_paginas, l.idioma, l.tamano_archivo_mb, l.clasificacion_edad,
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
$libro = $stmt->get_result()->fetch_assoc();   // Devuelve null si el libro no existe o no está aprobado
$stmt->close();

// Si el libro sí existe, traemos todas sus reseñas aprobadas ordenadas de la más reciente a la más antigua.
// Las reseñas en estado 'pendiente' o 'rechazada' nunca se muestran en esta vista pública.
$resenas = [];
if ($libro) {
    $stmt_r = $conn->prepare("
        SELECT r.puntuacion, r.comentario, r.fecha_creacion, u.nombre_completo,
               EXISTS (
                   SELECT 1 FROM pedidos p
                   INNER JOIN detalle_pedido dp ON dp.id_pedido = p.id_pedido
                   WHERE p.id_usuario = r.id_usuario AND dp.id_libro = r.id_libro AND p.estado <> 'cancelado'
               ) AS compra_verificada
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
// Si el usuario tiene sesión iniciada y el libro existe, revisamos 3 cosas personales:
// 1. Si ya lo marcó como favorito (para cambiar el ícono del corazón).
// 2. Si ya escribió una reseña (la base de datos solo permite una reseña por usuario y libro).
// 3. Si ya compró este libro en algún pedido válido (para habilitar la descarga directa del PDF).
$es_favorito = false;
$mi_resena = null;
$pedido_comprado = 0;
if ($logueado && $libro) {
    // 1. Verificar si está en la tabla favoritos
    $stmt_f = $conn->prepare("SELECT 1 FROM favoritos WHERE id_usuario = ? AND id_libro = ?");
    $stmt_f->bind_param("ii", $_SESSION['id_usuario'], $id_libro);
    $stmt_f->execute();
    $es_favorito = $stmt_f->get_result()->num_rows > 0;
    $stmt_f->close();

    // 2. Verificar si ya existe una reseña de este usuario para este libro (sin importar su estado)
    $stmt_m = $conn->prepare("SELECT estado FROM resenas WHERE id_usuario = ? AND id_libro = ?");
    $stmt_m->bind_param("ii", $_SESSION['id_usuario'], $id_libro);
    $stmt_m->execute();
    $mi_resena = $stmt_m->get_result()->fetch_assoc();
    $stmt_m->close();

    // Si ya lo compró se guarda el pedido para mostrar "Descargar PDF" en vez del carrito
    // 3. Buscar si el libro aparece en el detalle de algún pedido no cancelado del usuario.
    // Si ya lo tiene, guardamos el id_pedido para enlazar directo a php/descargar.php.
    $stmt_c = $conn->prepare("
        SELECT p.id_pedido FROM pedidos p
        INNER JOIN detalle_pedido dp ON dp.id_pedido = p.id_pedido
        WHERE p.id_usuario = ? AND dp.id_libro = ? AND p.estado <> 'cancelado'
        ORDER BY p.id_pedido DESC LIMIT 1
    ");
    $stmt_c->bind_param("ii", $_SESSION['id_usuario'], $id_libro);
    $stmt_c->execute();
    $compra = $stmt_c->get_result()->fetch_assoc();
    $pedido_comprado = $compra ? (int)$compra['id_pedido'] : 0;
    $stmt_c->close();
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

    <!-- Barra de navegación principal compartida en todo el sitio público -->
    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
            </a>

            <!-- Botón de menú responsivo para móvil usando HTML nativo -->
            <details class="nav-menu-toggle">
                <summary class="btn search-icon" aria-label="Menú" aria-controls="nav-menu">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </summary>
            </details>

            <!-- Buscador global que redirige al catálogo con el término escrito -->
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

                    <!-- Enlace dinámico al panel de administración según el rol de la sesión -->
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

    <!-- Banner superior para mostrar notificaciones de éxito o error -->
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
        <!-- Mensaje de respaldo cuando el ID no existe o el libro sigue pendiente/dado de baja -->
        <div style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
            <h2>Libro no encontrado</h2>
            <p>El libro no existe o todavía no ha sido aprobado.</p>
        </div>
    <?php else: ?>
        <?php $precio = ((float)$libro['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$libro['precio'], 2); ?>

        <!-- Tarjeta principal con los datos completos del libro -->
        <section id="detalle" style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <!-- Verificamos con is_file() que la imagen exista físicamente antes de pintarla -->
                <?php if (!empty($libro['portada']) && is_file(__DIR__ . '/' . $libro['portada'])): ?>
                    <img src="<?= htmlspecialchars($libro['portada']) ?>" alt="Portada de <?= htmlspecialchars($libro['titulo']) ?>" class="portada-detalle">
                <?php else: ?>
                    <div class="placeholder-box" style="width: 200px; height: 280px;">PORTADA</div>
                <?php endif; ?>

                <div style="flex: 1; min-width: 250px;">
                    <h2><?= htmlspecialchars($libro['titulo']) ?></h2>
                    <p><strong>Autor:</strong>
                        <a href="autor.php?id=<?= (int)$libro['id_autor'] ?>"><?= htmlspecialchars($libro['autor']) ?></a>
                    </p>
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
                    <?php
                    $edades = ['ninos' => 'Niños', 'jovenes' => 'Jóvenes', 'adultos' => 'Adultos'];
                    $edad_texto = $edades[$libro['clasificacion_edad'] ?? ''] ?? 'Todo público';
                    ?>
                    <?php if (!empty($libro['num_paginas'])): ?>
                        <p><strong>Páginas:</strong> <?= (int)$libro['num_paginas'] ?></p>
                    <?php endif; ?>
                    <?php if (!empty($libro['idioma'])): ?>
                        <p><strong>Idioma:</strong> <?= htmlspecialchars($libro['idioma']) ?></p>
                    <?php endif; ?>
                    <?php if ($libro['tamano_archivo_mb'] !== null): ?>
                        <p><strong>Tamaño del archivo:</strong> <?= number_format((float)$libro['tamano_archivo_mb'], 2) ?> MB</p>
                    <?php endif; ?>
                    <p><strong>Clasificación por edad:</strong>
                        <span style="background:#eef2ff;color:#3730a3;padding:2px 10px;border-radius:12px;font-size:0.8rem;"><?= $edad_texto ?></span>
                    </p>
                    <div class="stars">⭐ <?= $libro['promedio'] ?? 'Sin reseñas' ?> (<?= count($resenas) ?> reseñas)</div>

                    <!-- nl2br convierte los saltos de línea del texto en <br> después de limpiar el HTML -->
                    <p style="margin: 15px 0;"><?= nl2br(htmlspecialchars($libro['sinopsis'] ?? '')) ?></p>
                    <h3 style="color: #27ae60;"><?= $precio ?></h3>
                    <p style="font-size: 0.85rem; color: var(--wf-gray);">Lecturas: <?= (int)$libro['lecturas_acumuladas'] ?></p>

                    <!-- Botones de acción: descargar (si ya lo compró) o agregar al carrito, favoritos y reportar -->
                    <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                        <?php if ($pedido_comprado > 0): ?>
                            <a href="php/descargar.php?pedido=<?= $pedido_comprado ?>&libro=<?= $id_libro ?>" class="btn btn-primary">
                                <i class="fa-solid fa-download"></i> Descargar PDF
                            </a>
                        <?php else: ?>
                            <a href="php/carrito_agregar.php?id_libro=<?= $id_libro ?>" class="btn btn-primary">
                                <i class="fa-solid fa-cart-shopping"></i> Agregar al carrito
                            </a>
                        <?php endif; ?>
                        <a href="php/favorito_toggle.php?id_libro=<?= $id_libro ?>" class="btn">
                            <i class="<?= $es_favorito ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                            <?= $es_favorito ? 'Quitar de favoritos' : 'Agregar a favoritos' ?>
                        </a>
                        <!-- Redirige a contacto.php precargando el asunto con el título del libro a reportar -->
                        <a href="contacto.php?asunto=<?= urlencode('Reporte de contenido: ' . $libro['titulo']) ?>" class="btn" title="Reportar este libro">
                            <i class="fa-regular fa-flag"></i> Reportar
                        </a>
                    </div>
                </div>
            </div>

            <!-- Biografía del autor (solo se muestra si el autor tiene biografía registrada) -->
            <?php if ($libro['biografia']): ?>
                <hr style="margin: 20px 0;">
                <h3>Sobre el autor</h3>
                <p><?= nl2br(htmlspecialchars($libro['biografia'])) ?></p>
            <?php endif; ?>

            <!-- Reseñas aprobadas -->
            <!-- Sección de reseñas aprobadas -->
            <hr style="margin: 20px 0;">
            <h3 id="resenas">Reseñas y Valoraciones</h3>
            <div style="margin: 15px 0;">
                <?php foreach ($resenas as $r): ?>
                    <div style="background: var(--wf-bg); padding: 10px; border-radius: var(--radius); margin-bottom: 10px;">
                        <strong><?= htmlspecialchars($r['nombre_completo']) ?></strong>
                        <?php if (!empty($r['compra_verificada'])): ?>
                            <span style="background:#d4edda;color:#155724;font-size:0.75rem;padding:2px 8px;border-radius:12px;margin-left:4px;">
                                <i class="fa-solid fa-circle-check"></i> Compra verificada
                            </span>
                        <?php endif; ?>
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
            <!-- Control del formulario para escribir reseña:
                 - Sin sesión: pide iniciar sesión.
                 - Si ya reseñó este libro: muestra el estado de su reseña (pendiente, aprobada o rechazada).
                 - Si tiene sesión y aún no ha opinado: muestra el formulario hacia php/resena_guardar.php. -->
            <?php if (!$logueado): ?>
                <p style="color: var(--wf-gray);"><em>Inicia sesión para escribir una reseña.</em></p>
            <?php elseif ($pedido_comprado === 0): ?>
                <p style="color: var(--wf-gray);"><em>Solo quienes compraron este libro pueden escribir una reseña.</em></p>
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

    <!-- Ventana modal de inicio de sesión, registro y recuperación de contraseña -->
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