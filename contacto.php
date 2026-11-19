<?php
/*
 * Página de contacto y soporte técnico de LecturaLibre.
 * Permite tanto a visitantes como a usuarios registrados enviar dudas, sugerencias
 * o reportes de libros (por ejemplo, cuando llegan desde el botón "Reportar" en detalle.php).
 * Los mensajes se guardan en la tabla mensajes_contacto con estado 'pendiente' para que
 * el equipo de trabajadores o administradores los atienda desde su panel.
 */
session_start();
require_once 'config/conexion.php';

// Datos básicos de la sesión activa
$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = isset($_SESSION['id_usuario']);
$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

// Si el usuario tiene sesión iniciada, traemos su correo de la base de datos para
// rellenar automáticamente los campos del formulario y ahorrarle trabajo
$correo_usuario = '';
if ($logueado) {
    $stmt_u = $conn->prepare("SELECT correo FROM usuarios WHERE id_usuario = ?");
    $stmt_u->bind_param("i", $_SESSION['id_usuario']);
    $stmt_u->execute();
    $fila_u = $stmt_u->get_result()->fetch_assoc();
    $correo_usuario = $fila_u['correo'] ?? '';
    $stmt_u->close();
}

// Procesamos el envío del formulario cuando llega por método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');

    // Validamos que no haya campos vacíos, que el correo tenga formato válido y que no excedan los límites
    if ($nombre === '' || $correo === '' || $asunto === '' || $mensaje === '') {
        header('Location: contacto.php?error=campos_vacios');
        exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        header('Location: contacto.php?error=correo_invalido');
        exit;
    }

    if (mb_strlen($asunto) > 150 || mb_strlen($mensaje) > 1500) {
        header('Location: contacto.php?error=longitud');
        exit;
    }

    // Guardamos el mensaje en la base de datos usando una consulta preparada.
    // Todos los mensajes nuevos entran con estado 'pendiente' hasta que un moderador los marque como atendidos.
    $stmt_ins = $conn->prepare("
        INSERT INTO mensajes_contacto (nombre, correo, asunto, mensaje, estado)
        VALUES (?, ?, ?, ?, 'pendiente')
    ");
    $stmt_ins->bind_param("ssss", $nombre, $correo, $asunto, $mensaje);
    $ok = $stmt_ins->execute();
    $stmt_ins->close();

    // Usamos redirección después de guardar (patrón Post-Redirect-Get) para evitar que,
    // si el usuario recarga la página con F5, el navegador vuelva a enviar el mismo mensaje
    if ($ok) {
        header('Location: contacto.php?enviado=1');
    } else {
        header('Location: contacto.php?error=bd');
    }
    exit;
}

// Contamos los artículos en el carrito para mantener actualizado el ícono del encabezado
$cart_count = 0;
if ($logueado) {
    $stmt_cart = $conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
    $stmt_cart->bind_param("i", $_SESSION['id_usuario']);
    $stmt_cart->execute();
    $res_cart = $stmt_cart->get_result()->fetch_assoc();
    $cart_count = (int)($res_cart['total'] ?? 0);
    $stmt_cart->close();
}

// Si el usuario llegó aquí desde el enlace "Reportar" de detalle.php, precargamos el asunto
$asunto_inicial = isset($_GET['asunto']) && is_string($_GET['asunto']) ? trim($_GET['asunto']) : '';

// Revisamos los parámetros GET para mostrar avisos de confirmación o error
$alerta_texto = '';
$alerta_tipo = '';
if (isset($_GET['enviado']) && $_GET['enviado'] === '1') {
    $alerta_texto = '¡Mensaje enviado! Nuestro equipo lo revisará y te responderá a la brevedad.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['error'])) {
    $alerta_tipo = 'error';
    switch ($_GET['error']) {
        case 'campos_vacios':
            $alerta_texto = 'Por favor completa todos los campos del formulario.';
            break;
        case 'correo_invalido':
            $alerta_texto = 'Ingresa una dirección de correo electrónico válida.';
            break;
        case 'longitud':
            $alerta_texto = 'El asunto o el mensaje superan el límite de caracteres permitido.';
            break;
        default:
            $alerta_texto = 'No se pudo enviar tu mensaje. Inténtalo de nuevo más tarde.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto | LecturaLibre</title>
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

    <!-- Banner de confirmación o error al enviar el mensaje -->
    <?php if ($alerta_texto !== ''): ?>
        <div class="container" style="margin-top: 15px;">
            <div style="padding: 12px 16px; border-radius: 6px; font-size: 0.9rem; background: <?= $alerta_tipo === 'exito' ? '#d4edda' : '#f8d7da' ?>; color: <?= $alerta_tipo === 'exito' ? '#155724' : '#721c24' ?>; border: 1px solid <?= $alerta_tipo === 'exito' ? '#c3e6cb' : '#f5c6cb' ?>;">
                <?= htmlspecialchars($alerta_texto) ?>
            </div>
        </div>
    <?php endif; ?>

    <main class="container">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
            <!-- Formulario principal que se envía a esta misma página -->
            <section style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                <h2 class="section-title">Contáctanos</h2>
                <p style="font-size: 0.92rem; color: var(--wf-gray); margin-bottom: 18px;">
                    ¿Tienes dudas sobre alguna descarga, problemas con tu cuenta o quieres reportar un libro? Escríbenos y te ayudaremos.
                </p>

                <form action="contacto.php" method="POST">
                    <div class="form-group">
                        <label for="contacto-nombre">Tu nombre</label>
                        <input type="text" id="contacto-nombre" name="nombre" required maxlength="100"
                               placeholder="Nombre completo" value="<?= htmlspecialchars($nombre_usuario) ?>">
                    </div>

                    <div class="form-group">
                        <label for="contacto-correo">Correo electrónico</label>
                        <input type="email" id="contacto-correo" name="correo" required maxlength="120"
                               placeholder="tu@correo.com" value="<?= htmlspecialchars($correo_usuario) ?>">
                    </div>

                    <div class="form-group">
                        <label for="contacto-asunto">Asunto</label>
                        <input type="text" id="contacto-asunto" name="asunto" required maxlength="150"
                               placeholder="Ej. Consulta sobre descarga o reporte de libro"
                               value="<?= htmlspecialchars($asunto_inicial) ?>">
                    </div>

                    <div class="form-group">
                        <label for="contacto-mensaje">Mensaje</label>
                        <textarea id="contacto-mensaje" name="mensaje" rows="5" required maxlength="1500"
                                  placeholder="Describe tu consulta o reporte con el mayor detalle posible..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-paper-plane"></i> Enviar Mensaje
                    </button>
                </form>
            </section>

            <!-- Columna lateral con canales de atención y preguntas frecuentes -->
            <aside style="background: white; padding: 25px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                <h3 style="margin-bottom: 15px;">Información de Soporte</h3>
                <p style="font-size: 0.9rem; margin-bottom: 12px;">
                    <i class="fa-solid fa-envelope"></i> <strong>Correo general:</strong> soporte@lecturalibre.local
                </p>
                <p style="font-size: 0.9rem; margin-bottom: 20px;">
                    <i class="fa-solid fa-clock"></i> <strong>Horario de atención:</strong> Lunes a viernes de 9:00 a 18:00 hrs.
                </p>

                <hr style="margin: 18px 0;">

                <h4 style="margin-bottom: 10px;">Preguntas Frecuentes</h4>
                <div style="font-size: 0.88rem; color: var(--wf-gray); line-height: 1.55;">
                    <p style="margin-bottom: 10px;">
                        <strong style="color: #222;">¿Dónde descargo un libro que ya pedí?</strong><br>
                        Ingresa a <a href="perfil.php">Mi Perfil</a>; en la sección de pedidos encontrarás el botón para descargar cada archivo PDF en cualquier momento.
                    </p>
                    <p style="margin-bottom: 10px;">
                        <strong style="color: #222;">¿Cuánto tarda en aprobarse mi libro o reseña?</strong><br>
                        Nuestro equipo revisa las solicitudes en un plazo habitual de 24 a 48 horas hábiles.
                    </p>
                    <p>
                        <strong style="color: #222;">¿Cómo reporto un problema de derechos de autor?</strong><br>
                        Usa este formulario indicando el título exacto del libro y los motivos de tu solicitud.
                    </p>
                </div>
            </aside>
        </div>
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