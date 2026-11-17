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
            <a href="javascript:void(0)" class="logo" onclick="window.location.href='index.html'">
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
                    <li><a href="javascript:void(0)" id="link-inicio" onclick="window.location.href='index.html'">Inicio</a></li>
                    <li><a href="javascript:void(0)" id="link-catalogo" onclick="window.location.href='catalogo.html'">Catálogo</a></li>
                    <li><a href="javascript:void(0)" id="link-nosotros" onclick="window.location.href='nosotros.html'">Nosotros</a></li>
                    <li><a href="javascript:void(0)" id="link-contacto" onclick="window.location.href='contacto.html'">Contacto</a></li>
                    <li id="author-nav-item" style="display: none;"><a href="javascript:void(0)" id="link-author-dashboard" onclick="window.location.href='panelautor.html'">Panel Autor</a></li>
                    <li id="worker-nav-item" style="display: none;"><a href="javascript:void(0)" id="link-worker-dashboard" onclick="window.location.href='paneltrabajador.html'">Panel Trabajador</a></li>
                    <li id="admin-nav-item" style="display: none;"><a href="javascript:void(0)" id="link-admin-dashboard" onclick="window.location.href='paneladmin.html'">Panel Admin</a></li>
                </ul>

                <div class="user-actions">
                    <button class="btn" onclick="window.location.href='carrito.html'"><i class="fa-solid fa-cart-shopping"></i> <span id="cart-count" class="badge">0</span></button>
                    <div id="auth-buttons">
                        <button class="btn" onclick="openAuthModal('login')"><i class="fa-regular fa-user"></i> Iniciar Sesión</button>
                        <button class="btn btn-primary" onclick="openAuthModal('signup')">Registrarse</button>
                    </div>
                    <div id="user-menu" style="display: none; gap: 10px; align-items: center;">
                        <button class="btn" onclick="window.location.href='perfil.html'"><i class="fa-solid fa-circle-user"></i> <span id="user-display-name">Usuario</span></button>
                        <button class="btn btn-danger" onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </header>

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