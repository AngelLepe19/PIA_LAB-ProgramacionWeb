<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$u = require_rol(['Administrador', 'Trabajador']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    $id_mensaje = (int)($_POST['id_mensaje'] ?? 0);

    if ($id_mensaje > 0 && $accion === 'atender') {
        $st = $pdo->prepare("UPDATE mensajes_contacto 
                             SET estado = 'atendido', id_trabajador = ?, fecha_atencion = NOW() 
                             WHERE id_mensaje = ?");
        $st->execute([$u['id'], $id_mensaje]);
        flash('success', 'Mensaje marcado como atendido.');
    } elseif ($id_mensaje > 0 && $accion === 'reabrir') {
        $st = $pdo->prepare("UPDATE mensajes_contacto 
                             SET estado = 'pendiente', id_trabajador = NULL, fecha_atencion = NULL 
                             WHERE id_mensaje = ?");
        $st->execute([$id_mensaje]);
        flash('success', 'Mensaje devuelto a estado pendiente.');
    } elseif ($id_mensaje > 0 && $accion === 'eliminar' && es_rol($u, 'Administrador')) {
        $st = $pdo->prepare("DELETE FROM mensajes_contacto WHERE id_mensaje = ?");
        $st->execute([$id_mensaje]);
        flash('success', 'Mensaje eliminado.');
    }

    redirect('mensajes.php?' . http_build_query([
        'estado' => $_POST['estado_filtro'] ?? 'pendiente',
        'q'      => $_POST['q_filtro'] ?? '',
    ]));
}

$estado = (string)($_GET['estado'] ?? 'pendiente');
$q = trim((string)($_GET['q'] ?? ''));
$where = [];
$params = [];

if (in_array($estado, ['pendiente', 'atendido'], true)) {
    $where[] = 'm.estado = ?';
    $params[] = $estado;
}
if ($q !== '') {
    $where[] = '(m.nombre LIKE ? OR m.correo LIKE ? OR m.asunto LIKE ? OR m.mensaje LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}

$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = $pdo->prepare("SELECT COUNT(*) FROM mensajes_contacto m $sqlWhere");
$st->execute($params);
[$pagina, $paginas, $offset] = paginar((int)$st->fetchColumn(), (int)($_GET['p'] ?? 1));

$sql = "SELECT m.*, u.nombre_completo AS atendido_por
        FROM mensajes_contacto m
        LEFT JOIN usuarios u ON m.id_trabajador = u.id_usuario
        $sqlWhere
        ORDER BY m.fecha_envio DESC
        LIMIT " . POR_PAGINA . " OFFSET " . $offset;
$st = $pdo->prepare($sql);
$st->execute($params);
$mensajes = $st->fetchAll();

$titulo = 'Mensajes de Contacto';
$tab = 'mensajes';
require __DIR__ . '/inc/header.php';
?>

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3 no-print">
    <form class="d-flex flex-wrap gap-2" method="get">
        <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Nombre, correo o asunto">
        <select name="estado" class="form-select form-select-sm">
            <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
            <option value="atendido" <?= $estado === 'atendido' ? 'selected' : '' ?>>Atendidos</option>
            <option value="todos" <?= $estado === 'todos' ? 'selected' : '' ?>>Todos</option>
        </select>
        <button class="btn btn-sm btn-outline-secondary">Filtrar</button>
    </form>
</div>

<?php if (!$mensajes): ?>
    <div class="alert alert-light border text-muted">No hay mensajes de contacto en esta vista.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($mensajes as $m): ?>
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <h6 class="mb-0 fw-bold"><?= e($m['asunto']) ?></h6>
                                <small class="text-muted">
                                    De: <strong><?= e($m['nombre']) ?></strong>
                                    (<a href="mailto:<?= e($m['correo']) ?>?subject=Re: <?= rawurlencode($m['asunto']) ?>"><?= e($m['correo']) ?></a>)
                                    &middot; <?= e(substr((string)$m['fecha_envio'], 0, 16)) ?>
                                </small>
                            </div>
                            <?= badge_estado($m['estado']) ?>
                        </div>

                        <p class="mb-3 bg-light p-2 rounded border" style="white-space: pre-line;"><?= e($m['mensaje']) ?></p>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <small class="text-muted">
                                <?php if ($m['estado'] === 'atendido' && !empty($m['atendido_por'])): ?>
                                    Atendido por <strong><?= e($m['atendido_por']) ?></strong> el <?= e(substr((string)$m['fecha_atencion'], 0, 16)) ?>
                                <?php endif; ?>
                            </small>

                            <div class="d-flex gap-2 no-print">
                                <a href="mailto:<?= e($m['correo']) ?>?subject=Re: <?= rawurlencode($m['asunto']) ?>" class="btn btn-sm btn-outline-primary">
                                    Responder por correo
                                </a>

                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_mensaje" value="<?= (int)$m['id_mensaje'] ?>">
                                    <input type="hidden" name="estado_filtro" value="<?= e($estado) ?>">
                                    <input type="hidden" name="q_filtro" value="<?= e($q) ?>">
                                    <?php if ($m['estado'] === 'pendiente'): ?>
                                        <button name="accion" value="atender" class="btn btn-sm btn-success">Marcar Atendido</button>
                                    <?php else: ?>
                                        <button name="accion" value="reabrir" class="btn btn-sm btn-outline-secondary">Reabrir</button>
                                    <?php endif; ?>

                                    <?php if (es_rol($u, 'Administrador')): ?>
                                        <button name="accion" value="eliminar" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este mensaje?')">Eliminar</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?= paginacion_html($pagina, $paginas) ?>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>