<?php
/*
 * Página de perfil de usuario en LecturaLibre.
 * Solo es accesible para usuarios que ya iniciaron sesión. Desde aquí el lector
 * (o autor) puede actualizar su nombre, cambiar su contraseña, ver y quitar libros
 * de su lista de favoritos, consultar sus pedidos anteriores y volver a descargar
 * cualquier libro que ya haya adquirido.
 */
session_start();
require_once 'config/conexion.php';

// Datos básicos de la sesión activa
$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = isset($_SESSION['id_usuario']);
$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

// Si alguien intenta entrar directo escribiendo perfil.php en el navegador sin tener sesión,
// lo redirigimos al inicio con un aviso para que inicie sesión primero
if (!$logueado) {
    header('Location: index.php?error=inicia_sesion');
    exit;
}

// Contamos cuántos artículos tiene actualmente en su carrito para actualizar el ícono superior
$cart_count = 0;
$stmt_cart = $conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
$stmt_cart->bind_param("i", $_SESSION['id_usuario']);
$stmt_cart->execute();
$res_cart = $stmt_cart->get_result()->fetch_assoc();
$cart_count = (int)($res_cart['total'] ?? 0);
$stmt_cart->close();

// Revisamos los parámetros GET de la URL para mostrar notificaciones después de guardar cambios
// (por ejemplo, cuando actualiza su nombre, cambia su clave o falla alguna validación)
$alerta_texto = '';
$alerta_tipo = '';
if (isset($_GET['actualizado']) && $_GET['actualizado'] === 'datos') {
    $alerta_texto = 'Tus datos personales se actualizaron correctamente.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['actualizado']) && $_GET['actualizado'] === 'password') {
    $alerta_texto = 'Tu contraseña se cambió con éxito.';
    $alerta_tipo = 'exito';
} elseif (isset($_GET['error'])) {
    $alerta_tipo = 'error';
    switch ($_GET['error']) {
        case 'campos_vacios':
            $alerta_texto = 'Por favor completa todos los campos obligatorios.';
            break;
        case 'password_actual':
            $alerta_texto = 'La contraseña actual que ingresaste no es correcta.';
            break;
        case 'password_no_coincide':
            $alerta_texto = 'La nueva contraseña y su confirmación no coinciden.';
            break;
        case 'password_corta':
            $alerta_texto = 'La nueva contraseña debe tener al menos 6 caracteres.';
            break;
        default:
            $alerta_texto = 'No se pudieron guardar los cambios. Intenta de nuevo.';
            break;
    }
}

// 1. Consultamos los datos actuales del usuario junto con el nombre de su rol
// Siempre usamos $_SESSION['id_usuario'] para que cada quien vea únicamente su propia cuenta
$stmt_u = $conn->prepare("
    SELECT u.nombre_completo, u.correo, u.fecha_registro, r.nombre AS rol
    FROM usuarios u
    INNER JOIN roles r ON u.id_rol = r.id_rol
    WHERE u.id_usuario = ?
");
$stmt_u->bind_param("i", $_SESSION['id_usuario']);
$stmt_u->execute();
$usuario = $stmt_u->get_result()->fetch_assoc();
$stmt_u->close();

// Si la cuenta tiene rol de Autor (id_rol = 2), traemos también su perfil público de escritor
// para permitirle editar su seudónimo y biografía desde esta misma pantalla
$perfil_autor = null;
if ($id_rol === 2) {
    $stmt_a = $conn->prepare("SELECT nombre_artistico, biografia FROM autores WHERE id_usuario = ?");
    $stmt_a->bind_param("i", $_SESSION['id_usuario']);
    $stmt_a->execute();
    $perfil_autor = $stmt_a->get_result()->fetch_assoc();
    $stmt_a->close();
}

// 2. Consultamos la lista de libros marcados como favoritos por el usuario
// Filtramos por l.estado = 'aprobado' por si algún libro guardado fue dado de baja después
$stmt_fav = $conn->prepare("
    SELECT l.id_libro, l.titulo, l.precio, l.portada, a.nombre_artistico AS autor
    FROM favoritos f
    INNER JOIN libros l ON f.id_libro = l.id_libro
    INNER JOIN autores a ON l.id_autor = a.id_autor
    WHERE f.id_usuario = ? AND l.estado = 'aprobado'
    ORDER BY f.fecha_agregado DESC
");
$stmt_fav->bind_param("i", $_SESSION['id_usuario']);
$stmt_fav->execute();
$favoritos = $stmt_fav->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_fav->close();

// 3. Consultamos el historial de pedidos del usuario, ordenando del más reciente al más antiguo
$stmt_ped = $conn->prepare("
    SELECT id_pedido, fecha_pedido, total, metodo_pago, estado
    FROM pedidos
    WHERE id_usuario = ?
    ORDER BY fecha_pedido DESC
");
$stmt_ped->bind_param("i", $_SESSION['id_usuario']);
$stmt_ped->execute();
$pedidos = $stmt_ped->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ped->close();

// Preparamos una consulta reutilizable para traer los libros de cada pedido sin tener que
// volver a compilar el SQL dentro del ciclo
$stmt_det = $conn->prepare("
    SELECT dp.id_libro, dp.precio_unitario, l.titulo, a.nombre_artistico AS autor
    FROM detalle_pedido dp
    INNER JOIN libros l ON dp.id_libro = l.id_libro
    INNER JOIN autores a ON l.id_autor = a.id_autor
    WHERE dp.id_pedido = ?
");
foreach ($pedidos as &$ped) {
    $id_ped_actual = (int)$ped['id_pedido'];
    $stmt_det->bind_param("i", $id_ped_actual);
    $stmt_det->execute();
    $ped['libros'] = $stmt_det->get_result()->fetch_all(MYSQLI_ASSOC);
}
unset($ped); // Rompemos la referencia del ciclo foreach por seguridad
$stmt_det->close();

// 4. Consultamos las últimas 10 descargas realizadas por el usuario para mostrar su actividad reciente
$stmt_desc = $conn->prepare("
    SELECT d.fecha_descarga, l.id_libro, l.titulo
    FROM descargas d
    INNER JOIN libros l ON d.id_libro = l.id_libro
    WHERE d.id_usuario = ?
    ORDER BY d.fecha_descarga DESC
    LIMIT 10
");
$stmt_desc->bind_param("i", $_SESSION['id_usuario']);
$stmt_desc->execute();
$descargas = $stmt_desc->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_desc->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | LecturaLibre</title>
    <link rel="stylesheet" href="assets/css/estilos.css?v=5">
    <script src="assets/js/main.js"></script>
</head>
<body>

    <!-- Barra de navegación principal compartida en todo el sitio -->
    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
            </a>

            <!-- Menú desplegable para pantallas móviles -->
            <details class="nav-menu-toggle">
                <summary class="btn search-icon" aria-label="Menú" aria-controls="nav-menu">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </summary>
            </details>

            <!-- Buscador global que redirige al catálogo -->
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

                    <!-- Acceso directo al panel de administración según el rol de la cuenta -->
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

                    <div id="user-menu" style="display: flex; gap: 10px; align-items: center;">
                        <button class="btn" onclick="window.location.href='perfil.php'">
                            <i class="fa-solid fa-circle-user"></i> <span id="user-display-name"><?= htmlspecialchars($nombre_usuario) ?></span>
                        </button>
                        <a href="auth/logout.php" class="btn btn-danger" title="Cerrar sesión">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Banner para mostrar avisos de éxito o error al actualizar el perfil -->
    <?php if ($alerta_texto !== ''): ?>
        <div class="container" style="margin-top: 15px;">
            <div style="padding: 12px 16px; border-radius: 6px; font-size: 0.9rem; background: <?= $alerta_tipo === 'exito' ? '#d4edda' : '#f8d7da' ?>; color: <?= $alerta_tipo === 'exito' ? '#155724' : '#721c24' ?>; border: 1px solid <?= $alerta_tipo === 'exito' ? '#c3e6cb' : '#f5c6cb' ?>;">
                <?= htmlspecialchars($alerta_texto) ?>
            </div>
        </div>
    <?php endif; ?>

    <main class="container">
        <h2 class="section-title">Mi Perfil y Biblioteca</h2>

        <!-- Bloque 1: Datos personales y cambio de contraseña en dos columnas adaptables -->
        <section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">

            <!-- Formulario para actualizar nombre y datos de autor (si aplica) -->
            <div style="background: white; padding: 20px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                <h3 style="margin-bottom: 10px;">Datos de la Cuenta</h3>
                <p style="font-size: 0.85rem; color: var(--wf-gray); margin-bottom: 15px;">
                    Rol: <strong><?= htmlspecialchars(ucfirst($usuario['rol'] ?? 'Lector')) ?></strong>
                    &bull; Miembro desde <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?>
                </p>

                <form action="php/perfil_actualizar.php" method="POST">
                    <!-- Indicamos al script destino que esta petición es para editar datos generales -->
                    <input type="hidden" name="accion" value="datos">

                    <div class="form-group">
                        <label for="perfil-nombre">Nombre completo</label>
                        <input type="text" id="perfil-nombre" name="nombre_completo" required
                               value="<?= htmlspecialchars($usuario['nombre_completo'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="perfil-correo">Correo electrónico</label>
                        <!-- El correo se muestra solo como lectura para no romper la referencia de inicio de sesión -->
                        <input type="email" id="perfil-correo" value="<?= htmlspecialchars($usuario['correo'] ?? '') ?>" disabled>
                    </div>

                    <?php if ($id_rol === 2 && $perfil_autor): ?>
                        <!-- Campos adicionales exclusivos para cuentas con perfil de autor -->
                        <div class="form-group">
                            <label for="perfil-artistico">Nombre artístico o de autor</label>
                            <input type="text" id="perfil-artistico" name="nombre_artistico" required
                                   value="<?= htmlspecialchars($perfil_autor['nombre_artistico'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="perfil-bio">Biografía corta</label>
                            <textarea id="perfil-bio" name="biografia" rows="3" maxlength="600"
                                      placeholder="Cuéntale a los lectores sobre ti..."><?= htmlspecialchars($perfil_autor['biografia'] ?? '') ?></textarea>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </form>
            </div>

            <!-- Formulario independiente para cambiar la contraseña de acceso -->
            <div style="background: white; padding: 20px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
                <h3 style="margin-bottom: 10px;">Seguridad y Contraseña</h3>
                <p style="font-size: 0.85rem; color: var(--wf-gray); margin-bottom: 15px;">
                    Por seguridad, debes confirmar tu contraseña actual antes de establecer una nueva.
                </p>

                <form action="php/perfil_actualizar.php" method="POST">
                    <!-- Indicamos al script destino que esta petición es para cambiar la contraseña -->
                    <input type="hidden" name="accion" value="password">

                    <div class="form-group">
                        <label for="pass-actual">Contraseña actual</label>
                        <input type="password" id="pass-actual" name="password_actual" required placeholder="••••••••">
                    </div>

                    <div class="form-group">
                        <label for="pass-nueva">Nueva contraseña</label>
                        <input type="password" id="pass-nueva" name="password_nueva" required minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>

                    <div class="form-group">
                        <label for="pass-confirmar">Confirmar nueva contraseña</label>
                        <input type="password" id="pass-confirmar" name="password_confirmar" required minlength="6" placeholder="Repite la nueva contraseña">
                    </div>

                    <button type="submit" class="btn btn-primary">Actualizar Contraseña</button>
                </form>
            </div>
        </section>

        <!-- Bloque 2: Lista de libros guardados en favoritos -->
        <section style="background: white; padding: 20px; border-radius: var(--radius); border: 1px solid var(--wf-border); margin-bottom: 30px;">
            <h3 style="margin-bottom: 15px;">Mis Libros Favoritos (<?= count($favoritos) ?>)</h3>

            <?php if (count($favoritos) === 0): ?>
                <p style="color: var(--wf-gray);">Aún no has guardado libros en favoritos. Puedes marcarlos con el ícono de corazón desde el <a href="catalogo.php">catálogo</a>.</p>
            <?php else: ?>
                <div class="book-grid">
                    <?php foreach ($favoritos as $fav): ?>
                        <?php
                        $id_lib = (int)$fav['id_libro'];
                        $precio_txt = ((float)$fav['precio'] == 0) ? 'Gratis' : '$' . number_format((float)$fav['precio'], 2);
                        ?>
                        <div class="book-card">
                            <!-- Al pulsar el corazón aquí se elimina de favoritos y regresa a esta página -->
                            <a href="php/favorito_toggle.php?id_libro=<?= $id_lib ?>" title="Quitar de favoritos" style="color: inherit;">
                                <i class="fa-solid fa-heart wishlist-icon"></i>
                            </a>

                            <?php if (!empty($fav['portada']) && is_file(__DIR__ . '/' . $fav['portada'])): ?>
                                <img src="<?= htmlspecialchars($fav['portada']) ?>" alt="Portada de <?= htmlspecialchars($fav['titulo']) ?>" class="book-card-img" onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'">
                            <?php else: ?>
                                <div class="placeholder-box book-card-img" onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'">PORTADA</div>
                            <?php endif; ?>

                            <h4 onclick="window.location.href='detalle.php?id=<?= $id_lib ?>'" style="cursor: pointer;">
                                <?= htmlspecialchars($fav['titulo']) ?>
                            </h4>
                            <p style="font-size: 0.8rem; color: var(--wf-gray);"><?= htmlspecialchars($fav['autor']) ?></p>

                            <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center;">
                                <strong style="color: #27ae60;"><?= $precio_txt ?></strong>
                                <a href="detalle.php?id=<?= $id_lib ?>" class="btn">Ver ficha</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Bloque 3: Historial de pedidos y enlaces directos para descargar cada libro -->
        <section style="background: white; padding: 20px; border-radius: var(--radius); border: 1px solid var(--wf-border); margin-bottom: 30px;">
            <h3 style="margin-bottom: 15px;">Mis Pedidos y Libros Adquiridos (<?= count($pedidos) ?>)</h3>

            <?php if (count($pedidos) === 0): ?>
                <p style="color: var(--wf-gray);">Todavía no has realizado ningún pedido. Explora el <a href="catalogo.php">catálogo</a> para obtener tu primer libro.</p>
            <?php else: ?>
                <?php foreach ($pedidos as $ped): ?>
                    <?php
                    $id_ped = (int)$ped['id_pedido'];
                    $cancelado = ($ped['estado'] === 'cancelado');
                    ?>
                    <div style="border: 1px solid var(--wf-border); border-radius: var(--radius); padding: 15px; margin-bottom: 15px;">
                        <!-- Encabezado con los datos generales del pedido -->
                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--wf-border); margin-bottom: 10px;">
                            <div>
                                <strong>Pedido #<?= str_pad((string)$id_ped, 5, '0', STR_PAD_LEFT) ?></strong>
                                <span style="font-size: 0.85rem; color: var(--wf-gray); margin-left: 8px;">
                                    <?= date('d/m/Y H:i', strtotime($ped['fecha_pedido'])) ?>
                                </span>
                            </div>
                            <div style="font-size: 0.9rem;">
                                <span>Estado: <strong><?= htmlspecialchars(ucfirst($ped['estado'])) ?></strong></span>
                                &bull;
                                <span>Total: <strong style="color: #27ae60;"><?= (float)$ped['total'] == 0 ? 'Gratis' : '$' . number_format((float)$ped['total'], 2) ?></strong></span>
                            </div>
                        </div>

                        <!-- Lista de libros incluidos dentro de este pedido -->
                        <?php foreach ($ped['libros'] as $lib_p): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 6px 0;">
                                <div>
                                    <a href="detalle.php?id=<?= (int)$lib_p['id_libro'] ?>"><strong><?= htmlspecialchars($lib_p['titulo']) ?></strong></a>
                                    <span style="font-size: 0.82rem; color: var(--wf-gray);"> - <?= htmlspecialchars($lib_p['autor']) ?></span>
                                </div>

                                <!-- Solo habilitamos el botón de descarga si el pedido no fue cancelado -->
                                <?php if (!$cancelado): ?>
                                    <a href="php/descargar.php?pedido=<?= $id_ped ?>&libro=<?= (int)$lib_p['id_libro'] ?>" class="btn btn-primary" style="font-size: 0.82rem;">
                                        <i class="fa-solid fa-download"></i> Descargar PDF
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 0.82rem; color: var(--wf-gray);">Pedido cancelado</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Bloque 4: Registro reciente de descargas realizadas por el usuario -->
        <section style="background: white; padding: 20px; border-radius: var(--radius); border: 1px solid var(--wf-border);">
            <h3 style="margin-bottom: 12px;">Historial Reciente de Descargas</h3>

            <?php if (count($descargas) === 0): ?>
                <p style="color: var(--wf-gray);">Aún no has descargado ningún archivo PDF.</p>
            <?php else: ?>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($descargas as $desc): ?>
                        <li style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--wf-border); font-size: 0.9rem;">
                            <a href="detalle.php?id=<?= (int)$desc['id_libro'] ?>"><?= htmlspecialchars($desc['titulo']) ?></a>
                            <span style="color: var(--wf-gray); font-size: 0.82rem;">
                                <?= date('d/m/Y H:i', strtotime($desc['fecha_descarga'])) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>

    <!-- Pie de página compartido -->
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