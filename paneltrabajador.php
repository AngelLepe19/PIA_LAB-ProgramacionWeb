<?php
session_start();
require_once 'config/conexion.php';

$pagina_actual = basename($_SERVER['PHP_SELF']);
$logueado = isset($_SESSION['id_usuario']);
$id_usuario = $logueado ? (int)$_SESSION['id_usuario'] : 0;
$id_rol = $logueado ? (int)$_SESSION['id_rol'] : 0;
$nombre_usuario = $logueado ? $_SESSION['nombre_completo'] : '';

// Solo Trabajador (3) o Administrador (4)
if (!$logueado || ($id_rol !== 3 && $id_rol !== 4)) {
    header("Location: index.php?error=inicia_sesion");
    exit;
}

// Procesar acción de atender / reabrir mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_mensaje'], $_POST['accion'])) {
    $id_mensaje = (int)$_POST['id_mensaje'];
    $accion = $_POST['accion'];

    if ($id_mensaje > 0 && $accion === 'atender') {
        $stmt_upd = $conn->prepare("UPDATE mensajes_contacto SET estado = 'atendido', id_trabajador = ?, fecha_atencion = NOW() WHERE id_mensaje = ?");
        $stmt_upd->bind_param("ii", $id_usuario, $id_mensaje);
        $stmt_upd->execute();
        $stmt_upd->close();
        header("Location: paneltrabajador.php?msg=atendido");
        exit;
    } elseif ($id_mensaje > 0 && $accion === 'reabrir') {
        $stmt_upd = $conn->prepare("UPDATE mensajes_contacto SET estado = 'pendiente', id_trabajador = NULL, fecha_atencion = NULL WHERE id_mensaje = ?");
        $stmt_upd->bind_param("i", $id_mensaje);
        $stmt_upd->execute();
        $stmt_upd->close();
        header("Location: paneltrabajador.php?msg=reabierto");
        exit;
    }
}

// Contar carrito
$cart_count = 0;
$stmt_cart = $conn->prepare("SELECT COUNT(*) AS total FROM carrito WHERE id_usuario = ?");
$stmt_cart->bind_param("i", $id_usuario);
$stmt_cart->execute();
$res_cart = $stmt_cart->get_result()->fetch_assoc();
$cart_count = (int)($res_cart['total'] ?? 0);
$stmt_cart->close();

// Consultar mensajes reales de contacto desde MySQL
$sql_mensajes = "
    SELECT m.id_mensaje, m.nombre, m.correo, m.asunto, m.mensaje, m.fecha_envio, m.estado,
           u.nombre_completo AS atendido_por
    FROM mensajes_contacto m
    LEFT JOIN usuarios u ON m.id_trabajador = u.id_usuario
    ORDER BY (m.estado = 'pendiente') DESC, m.fecha_envio DESC
";
$res_mensajes = $conn->query($sql_mensajes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Trabajador - LecturaLibre</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <script src="assets/js/main.js"></script>
    <style>
        /* Estilos limpios y profesionales para la tabla del panel */
        .panel-table-wrapper {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--wf-border);
            overflow-x: auto;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .panel-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .panel-table th {
            background: #f8f9fa;
            color: var(--wf-text);
            font-weight: 600;
            padding: 14px 16px;
            border-bottom: 2px solid var(--wf-border);
            font-size: 0.9rem;
        }
        .panel-table td {
            padding: 16px;
            border-bottom: 1px solid var(--wf-border);
            vertical-align: top;
            font-size: 0.9rem;
        }
        .panel-table tr:last-child td {
            border-bottom: none;
        }
        .badge-pendiente {
            color: #d35400;
            background: #fef5e7;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-atendido {
            color: #27ae60;
            background: #eafaf1;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>
<body>

    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
            </a>

            <form class="search-bar" action="catalogo.php" method="get" role="search">
                <input type="search" id="global-search" name="q" aria-label="Buscar por título, autor o categoría"
                       placeholder="Buscar título, autor o categoría...">
                <button type="submit" class="btn" aria-label="Buscar">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Buscar
                </button>
            </form>

            <ul class="nav-links">
                <li><a href="index.php" id="link-inicio">Inicio</a></li>
                <li><a href="catalogo.php" id="link-catalogo">Catálogo</a></li>
                <li><a href="nosotros.php" id="link-nosotros">Nosotros</a></li>
                <li><a href="contacto.php" id="link-contacto">Contacto</a></li>

                <?php if ($id_rol === 2 || $id_rol === 4): ?>
                    <li id="author-nav-item"><a href="panelautor.php">Panel Autor</a></li>
                <?php endif; ?>

                <?php if ($id_rol === 3 || $id_rol === 4): ?>
                    <li id="worker-nav-item"><a href="paneltrabajador.php" class="active-link">Panel Trabajador</a></li>
                <?php endif; ?>

                <?php if ($id_rol === 4): ?>
                    <li id="admin-nav-item"><a href="paneladmin.php">Panel Admin</a></li>
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
    </header>

    <main class="container" style="margin-top: 30px; margin-bottom: 40px;">
        <?php if (isset($_GET['msg'])): ?>
            <div style="margin-bottom: 20px; padding: 12px 16px; border-radius: 6px; font-size: 0.9rem; background: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
                <?= $_GET['msg'] === 'atendido' ? 'Mensaje marcado como atendido.' : 'Mensaje reabierto como pendiente.' ?>
            </div>
        <?php endif; ?>

        <section id="panel-trabajador">
            <h2 class="section-title" style="margin-bottom: 5px;">Panel Trabajador - Mensajes de Contacto</h2>
            <p style="font-size: 0.9rem; color: var(--wf-gray); margin-bottom: 20px;">
                Atención e interacciones recibidas desde el formulario público de Contacto.
            </p>

            <div class="panel-table-wrapper">
                <table class="panel-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th style="width: 160px;">Remitente</th>
                            <th style="width: 180px;">Email</th>
                            <th style="width: 150px;">Asunto</th>
                            <th>Mensaje</th>
                            <th style="width: 120px;">Estado</th>
                            <th style="width: 180px; text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res_mensajes && $res_mensajes->num_rows > 0): ?>
                            <?php while ($m = $res_mensajes->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>MSG-<?= (int)$m['id_mensaje'] ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($m['nombre']) ?></strong><br>
                                        <small style="color: var(--wf-gray); font-size: 0.8rem;"><?= htmlspecialchars(substr((string)$m['fecha_envio'], 0, 16)) ?></small>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($m['correo']) ?>?subject=Re:%20<?= rawurlencode($m['asunto']) ?>" style="color: var(--wf-accent); word-break: break-all;">
                                            <?= htmlspecialchars($m['correo']) ?>
                                        </a>
                                    </td>
                                    <td><strong><?= htmlspecialchars($m['asunto']) ?></strong></td>
                                    <td style="color: #333; line-height: 1.4; max-width: 320px; word-break: break-word;"><?= nl2br(htmlspecialchars($m['mensaje'])) ?></td>
                                    <td>
                                        <?php if ($m['estado'] === 'pendiente'): ?>
                                            <span class="badge-pendiente">Pendiente</span>
                                        <?php else: ?>
                                            <span class="badge-atendido">Atendido</span>
                                            <?php if (!empty($m['atendido_por'])): ?>
                                                <br><small style="color: var(--wf-gray); font-size: 0.75rem;">Por: <?= htmlspecialchars($m['atendido_por']) ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <form method="POST" action="paneltrabajador.php" style="display: inline-flex; flex-direction: column; gap: 6px; align-items: flex-end;">
                                            <input type="hidden" name="id_mensaje" value="<?= (int)$m['id_mensaje'] ?>">
                                            <a href="mailto:<?= htmlspecialchars($m['correo']) ?>?subject=Re:%20<?= rawurlencode($m['asunto']) ?>" class="btn" style="font-size: 0.8rem; padding: 6px 12px; width: 100%; text-align: center;">
                                                Responder
                                            </a>
                                            <?php if ($m['estado'] === 'pendiente'): ?>
                                                <button type="submit" name="accion" value="atender" class="btn btn-primary" style="font-size: 0.8rem; padding: 6px 12px; width: 100%;">
                                                    Marcar Atendido
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="accion" value="reabrir" class="btn" style="font-size: 0.8rem; padding: 6px 12px; width: 100%;">
                                                    Reabrir
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--wf-gray); padding: 30px;">
                                    No hay mensajes recibidos todavía.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

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