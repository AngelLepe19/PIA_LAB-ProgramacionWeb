<?php
declare(strict_types=1);
/** Panel de moderación de reseñas: aprobar o rechazar. Administrador y Trabajador. */
require_once __DIR__ . '/inc/bootstrap.php';
$u = require_rol(['Administrador', 'Trabajador']);
$pdo = db();

$estadosValidos = ['pendiente', 'aprobada', 'rechazada'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id_resena'] ?? 0);
    $accion = $_POST['accion'] ?? '';
    $nuevo = ['aprobar' => 'aprobada', 'rechazar' => 'rechazada'][$accion] ?? null;

    if ($nuevo === null) {
        flash('danger', 'Acción no válida.');
    } else {
        $st = $pdo->prepare('UPDATE resenas SET estado = ?, id_moderador = ?, fecha_moderacion = NOW() WHERE id_resena = ?');
        $st->execute([$nuevo, $u['id'], $id]);
        flash($st->rowCount() ? 'success' : 'warning', $st->rowCount() ? 'Reseña ' . $nuevo . '.' : 'La reseña no existe o ya tenía ese estado.');
    }
    redirect('resenas.php?' . http_build_query(['estado' => $_POST['estado'] ?? 'pendiente']));
}

$estado = (string)($_GET['estado'] ?? 'pendiente');
if (!in_array($estado, $estadosValidos, true)) {
    $estado = 'pendiente';
}

$st = $pdo->prepare('SELECT COUNT(*) FROM resenas WHERE estado = ?');
$st->execute([$estado]);
[$pagina, $paginas, $offset] = paginar((int)$st->fetchColumn(), (int)($_GET['p'] ?? 1));

$st = $pdo->prepare(
    'SELECT r.id_resena, r.puntuacion, r.comentario, r.fecha_creacion, r.estado, r.fecha_moderacion,
            l.titulo, us.nombre_completo AS lector, mo.nombre_completo AS moderador
       FROM resenas r
       JOIN libros l ON l.id_libro = r.id_libro
       JOIN usuarios us ON us.id_usuario = r.id_usuario
  LEFT JOIN usuarios mo ON mo.id_usuario = r.id_moderador
      WHERE r.estado = ?
      ORDER BY r.fecha_creacion DESC
      LIMIT ' . POR_PAGINA . ' OFFSET ' . $offset
);
$st->execute([$estado]);
$resenas = $st->fetchAll();

$conteos = $pdo->query('SELECT estado, COUNT(*) n FROM resenas GROUP BY estado')->fetchAll(PDO::FETCH_KEY_PAIR);

$titulo = 'Moderación de reseñas';
$tab = 'resenas';
require __DIR__ . '/inc/header.php';
?>
<ul class="nav nav-pills mb-3 no-print">
    <?php foreach ($estadosValidos as $es): ?>
        <li class="nav-item">
            <a class="nav-link py-1 <?= $estado === $es ? 'active btn-ll' : '' ?>" href="?estado=<?= $es ?>">
                <?= ucfirst($es) ?>s <span class="badge bg-light text-dark"><?= (int)($conteos[$es] ?? 0) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="table-responsive">
<table class="table table-sm table-bordered bg-white align-middle">
    <thead><tr><th>Libro</th><th>Lector</th><th>Calificación</th><th>Comentario</th><th>Fecha</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
    <tbody>
    <?php if (!$resenas): ?><tr><td colspan="7" class="text-center text-muted py-4">No hay reseñas <?= e($estado) ?>s.</td></tr><?php endif; ?>
    <?php foreach ($resenas as $r): ?>
        <tr>
            <td><?= e($r['titulo']) ?></td>
            <td><?= e($r['lector']) ?></td>
            <td><?= estrellas((int)$r['puntuacion']) ?></td>
            <td style="max-width: 320px;"><?= nl2br(e($r['comentario'])) ?></td>
            <td class="text-nowrap"><?= e(substr((string)$r['fecha_creacion'], 0, 10)) ?></td>
            <td>
                <?= badge_estado($r['estado']) ?>
                <?php if ($r['moderador']): ?><div class="small text-muted">por <?= e($r['moderador']) ?></div><?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
                <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_resena" value="<?= (int)$r['id_resena'] ?>">
                    <input type="hidden" name="estado" value="<?= e($estado) ?>">
                    <?php if ($r['estado'] !== 'aprobada'): ?>
                        <button name="accion" value="aprobar" class="btn btn-sm btn-success">Aprobar</button>
                    <?php endif; ?>
                    <?php if ($r['estado'] !== 'rechazada'): ?>
                        <button name="accion" value="rechazar" class="btn btn-sm btn-outline-danger">Rechazar</button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= paginacion_html($pagina, $paginas) ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
