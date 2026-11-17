<?php
session_start();
require_once 'config/conexion.php';

if (!isset($_SESSION['id_usuario']) || (int)$_SESSION['id_rol'] !== 4) {
    header("Location: index.php?error=acceso_denegado");
    exit;
}

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
        <section id="admin-dashboard">
            <h2 class="section-title">Panel de Administración Global</h2>
            <div class="tab-menu">
                <button class="tab-btn active" onclick="switchAdminTab('adm-reportes',this)">Reportes</button>
                <button class="tab-btn" onclick="switchAdminTab('adm-libros',this)">Libros / Inventario</button>
                <button class="tab-btn" onclick="switchAdminTab('adm-cats',this)">Categorías</button>
                <button class="tab-btn" onclick="switchAdminTab('adm-usuarios',this)">Usuarios / Roles</button>
                <button class="tab-btn" onclick="switchAdminTab('adm-resenas',this)">Moderación Reseñas</button>
            </div>

            <!-- TAB REPORTES (ADMIN) -->
            <div id="adm-reportes" class="admin-tab">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3>Reporte de Descargas y Plataforma</h3>
                    <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir Reporte</button>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card"><h3 id="stat-total-downloads">0</h3><p>Descargas Totales Generales</p></div>
                    <div class="stat-card"><h3 id="stat-total-books">0</h3><p>Libros Registrados</p></div>
                    <div class="stat-card"><h3 id="stat-total-categories">0</h3><p>Categorías Activas</p></div>
                    <div class="stat-card"><h3 id="stat-total-users">0</h3><p>Usuarios Registrados</p></div>
                </div>

                <h3 style="margin-top: 25px;">Descargas Totales por Categoría</h3>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Tabla de datos">
                <table class="wf-table" style="margin-bottom: 30px;">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th>Total Libros</th>
                            <th>Total Descargas</th>
                        </tr>
                    </thead>
                    <tbody id="admin-category-reports-table"></tbody>
                </table>
                </div>

                <h3>Libros Más Descargados / Leídos</h3>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Tabla de datos">
                <table class="wf-table">
                    <thead><tr><th>Título</th><th>Autor</th><th>Categoría</th><th>Descargas / Lecturas</th><th>Valoración</th></tr></thead>
                    <tbody id="admin-reports-table"></tbody>
                </table>
                </div>
            </div>

            <!-- TAB LIBROS -->
            <div id="adm-libros" class="admin-tab" style="display: none;">
                <button class="btn btn-primary" onclick="openBookModal()"><i class="fa-solid fa-plus"></i> Agregar Libro</button>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Tabla de datos">
                <table class="wf-table">
                    <thead><tr><th>ID</th><th>Título</th><th>Autor</th><th>Categoría</th><th>Descargas</th><th>Acciones</th></tr></thead>
                    <tbody id="admin-books-table"></tbody>
                </table>
                </div>
            </div>

            <!-- TAB CATEGORÍAS -->
            <div id="adm-cats" class="admin-tab" style="display: none;">
                <form onsubmit="addCategory(event)" style="display: flex; gap: 10px; max-width: 400px; margin-bottom: 15px;">
                    <input type="text" id="new-cat-input" placeholder="Nueva categoría..." required style="padding: 8px;">
                    <button type="submit" class="btn btn-primary">Añadir</button>
                </form>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Tabla de datos">
                <table class="wf-table" style="max-width: 500px;">
                    <thead><tr><th>Categoría</th><th>Acción</th></tr></thead>
                    <tbody id="admin-cats-table"></tbody>
                </table>
                </div>
            </div>

            <!-- TAB USUARIOS -->
            <div id="adm-usuarios" class="admin-tab" style="display: none;">
                <div class="table-scroll" tabindex="0" role="region" aria-label="Tabla de datos">
                <table class="wf-table">
                    <thead><tr><th>Nombre</th><th>Email</th><th>Rol Actual</th><th>Acción</th></tr></thead>
                    <tbody id="admin-users-table"></tbody>
                </table>
                </div>
            </div>

            <!-- TAB MODERACIÓN DE RESEÑAS -->
            <div id="adm-resenas" class="admin-tab" style="display: none;">
                <div class="table-scroll" tabindex="0" role="region" aria-label="Tabla de datos">
                <table class="wf-table">
                    <thead><tr><th>Libro</th><th>Usuario</th><th>Comentario</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody id="admin-reviews-table"></tbody>
                </table>
                </div>
            </div>
        </section>

            <!-- MODAL LIBRO (CREAR/EDITAR POR ADMIN O AUTOR) -->
            <div class="modal-overlay" id="bookModal">
                <div class="modal">
                    <span class="close-modal" onclick="closeBookModal()">&times;</span>
                    <h3 id="book-modal-title">Agregar Libro</h3>
                    <form onsubmit="saveBook(event)">
                        <input type="hidden" id="book-id">
                        <div class="form-group"><label>Título</label><input type="text" id="bm-title" required></div>
                        <div class="form-group"><label>Autor</label><input type="text" id="bm-author" required></div>
                        <div class="form-group"><label>Categoría</label><select id="bm-category"></select></div>
                        <div class="form-group"><label>Sinopsis</label><textarea id="bm-synopsis" rows="3"></textarea></div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Guardar Libro</button>
                    </form>
                </div>
            </div>
        </main>

    <div class="modal-overlay" id="authModal">
        <div class="modal">
            <span class="close-modal" onclick="closeAuthModal()">&times;</span>
            
            <form id="loginForm" onsubmit="handleLogin(event)">
                <h3 style="margin-bottom: 15px;">Iniciar Sesión</h3>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" id="login-email" placeholder="usuario@correo.com" required>
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label>Seleccionar Rol de Prueba:</label>
                    <select id="user-role">
                        <option value="user">Usuario Lector</option>
                        <option value="author">Autor</option>
                        <option value="worker">Trabajador</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Ingresar</button>
                <p class="auth-toggle">¿Olvidaste tu contraseña? <a onclick="toggleAuthMode('recover')">Recuperar aquí</a></p>
                <p class="auth-toggle">¿No tienes una cuenta? <a onclick="toggleAuthMode('signup')">Regístrate aquí</a></p>
            </form>

            <form id="signupForm" style="display: none;" onsubmit="handleSignUp(event)">
                <h3 style="margin-bottom: 15px;">Crear una Cuenta</h3>
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" id="signup-name" placeholder="Tu Nombre" required>
                </div>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" id="signup-email" placeholder="usuario@correo.com" required>
                </div>
                <div class="form-group">
                    <label for="signup-password">Contraseña</label>
                    <input type="password" id="signup-password" autocomplete="new-password" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label for="signup-password-confirm">Confirmar contraseña</label>
                    <input type="password" id="signup-password-confirm" autocomplete="new-password" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label>Tipo de Cuenta:</label>
                    <select id="signup-role">
                        <option value="user">Usuario Lector</option>
                        <option value="author">Autor</option>
                        <option value="worker">Trabajador</option>
                        <option value="admin">Administrador</option>
                    </select>
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
            <p style="font-size: 0.85rem;">&copy; <span id="footer-year"></span> LecturaLibre</p>
        </div>

        <nav aria-label="Navegación del pie de página">
            <h4>Navegación</h4>
            <p style="font-size: 0.85rem;">
                <a href="index.html">Inicio</a>
                <span aria-hidden="true"> | </span>
                <a href="catalogo.html">Catálogo</a>
                <span aria-hidden="true"> | </span>
                <a href="contacto.html">Contacto</a>
            </p>
        </nav>
    </div>
</footer>
</body>
</html>
