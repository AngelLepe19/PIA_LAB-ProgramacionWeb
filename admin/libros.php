<?php
declare(strict_types=1);
/**
 * CRUD 1 — Libros (parte Leer + dar de baja + flujo de aprobación).
 * Crear y editar están en libro_form.php.
 *  - Administrador: ve y gestiona todos los libros.
 *  - Autor: solo ve y gestiona los suyos (sus libros nuevos quedan "pendiente").
 *  - Trabajador: ve todos y puede aprobar / rechazar los pendientes (no edita ni da de baja).
 */
require_once __DIR__ . '/inc/bootstrap.php';
$u = require_rol(['Administrador', 'Autor', 'Trabajador']);
$pdo = db();

/** id_autor del usuario con rol Autor (null si todavía no tiene perfil de autor). */
function id_autor_de_usuario(PDO $pdo, int $idUsuario): ?int
{
    $st = $pdo->prepare('SELECT id_autor FROM autores WHERE id_usuario = ?');
    $st->execute([$idUsuario]);
    $v = $st->fetchColumn();
    return $v === false ? null : (int)$v;
}

$miAutor = es_rol($u, 'Autor') ? id_autor_de_usuario($pdo, (int)$u['id']) : null;

/* ---------- Acciones POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion  = $_POST['accion'] ?? '';
    $idLibro = (int)($_POST['id_libro'] ?? 0);

    $st = $pdo->prepare('SELECT id_libro, id_autor, estado FROM libros WHERE id_libro = ?');
    $st->execute([$idLibro]);
    $libro = $st->fetch();

    if (!$libro) {
        flash('danger', 'El libro no existe.');
        redirect('libros.php');
    }

    $esDueno = $miAutor !== null && (int)$libro['id_autor'] === $miAutor;
    $puedeRevisar = es_rol($u, 'Administrador', 'Trabajador');
    $puedeGestionar = es_rol($u, 'Administrador') || $esDueno;

    switch ($accion) {
        case 'aprobar':
        case 'rechazar':
            if (!$puedeRevisar) { http_response_code(403); exit('Sin permiso.'); }
            if ($libro['estado'] !== 'pendiente' && !($accion === 'aprobar' && $libro['estado'] === 'rechazado')) {
                flash('warning', 'Solo se pueden revisar libros en estado pendiente.');
                break;
            }
            $nuevo = $accion === 'aprobar' ? 'aprobado' : 'rechazado';
            $pdo->prepare('UPDATE libros SET estado = ?, id_revisor = ?, fecha_revision = NOW() WHERE id_libro = ?')
                ->execute([$nuevo, $u['id'], $idLibro]);
            flash('success', 'Libro ' . ($nuevo === 'aprobado' ? 'aprobado.' : 'rechazado.'));
            break;

        case 'baja':
            if (!$puedeGestionar) { http_response_code(403); exit('Sin permiso.'); }
            $pdo->prepare("UPDATE libros SET estado = 'baja' WHERE id_libro = ?")->execute([$idLibro]);
            flash('success', 'El libro se dio de baja del catálogo.');
            break;

        case 'reactivar':
            if (!$puedeGestionar || $libro['estado'] !== 'baja') { http_response_code(403); exit('Sin permiso.'); }
            // Si lo reactiva un autor, vuelve a revisión; si es admin, queda aprobado.
            $nuevo = es_rol($u, 'Administrador') ? 'aprobado' : 'pendiente';
            $pdo->prepare('UPDATE libros SET estado = ? WHERE id_libro = ?')->execute([$nuevo, $idLibro]);
            flash('success', 'Libro reactivado (' . $nuevo . ').');
            break;

        default:
            flash('danger', 'Acción no válida.');
    }
    redirect('libros.php?' . http_build_query(array_filter([
        'q' => $_POST['q'] ?? '', 'estado' => $_POST['estado'] ?? '', 'p' => $_POST['p'] ?? '',
    ])));
}

/* ---------- Listado con filtros ---------- */
$q      = trim((string)($_GET['q'] ?? ''));
$estado = (string)($_GET['estado'] ?? '');
$estadosValidos = ['pendiente', 'aprobado', 'rechazado', 'baja'];

$where = [];
$params = [];
if (es_rol($u, 'Autor')) {
    // Un autor sin perfil todavía no tiene libros
    $where[] = 'l.id_autor = ?';
    $params[] = $miAutor ?? 0;
}
if ($q !== '') {
    $where[] = '(l.titulo LIKE ? OR a.nombre_artistico LIKE ? OR l.isbn LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if (in_array($estado, $estadosValidos, true)) {
    $where[] = 'l.estado = ?';
    $params[] = $estado;
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = $pdo->prepare("SELECT COUNT(*) FROM libros l JOIN autores a ON a.id_autor = l.id_autor $sqlWhere");
$st->execute($params);
[$pagina, $paginas, $offset] = paginar((int)$st->fetchColumn(), (int)($_GET['p'] ?? 1));

$sql = "SELECT l.id_libro, l.titulo, l.isbn, l.precio, l.copias_disponibles, l.lecturas_acumuladas,
               l.estado, l.portada, l.archivo_pdf, a.nombre_artistico AS autor,
               (SELECT GROUP_CONCAT(c.nombre ORDER BY c.nombre SEPARATOR ', ')
                  FROM libro_categoria lc JOIN categorias c ON c.id_categoria = lc.id_categoria
                 WHERE lc.id_libro = l.id_libro) AS categorias,
               (SELECT COUNT(*) FROM descargas d WHERE d.id_libro = l.id_libro) AS descargas
          FROM libros l
          JOIN autores a ON a.id_autor = l.id_autor
          $sqlWhere
         ORDER BY l.id_libro DESC
         LIMIT " . POR_PAGINA . " OFFSET " . $offset;
$st = $pdo->prepare($sql);
$st->execute($params);
$libros = $st->fetchAll();

$titulo = 'Libros / Inventario';
$tab = 'libros';
require __DIR__ . '/inc/header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2 no-print" method="get">
        <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Título, autor o ISBN">
        <select name="estado" class="form-select form-select-sm">
            <option value="">Todos los estados</option>
            <?php foreach ($estadosValidos as $es): ?>
                <option value="<?= $es ?>" <?= $estado === $es ? 'selected' : '' ?>><?= ucfirst($es) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-secondary">Filtrar</button>
    </form>
    <?php if (es_rol($u, 'Administrador', 'Autor')): ?>
        <a class="btn btn-oscuro btn-sm" href="libro_form.php">+ <?= es_rol($u, 'Autor') ? 'Publicar nuevo libro' : 'Agregar libro' ?></a>
    <?php endif; ?>
</div>

<div class="table-responsive">
<table class="table table-sm table-bordered bg-white align-middle">
    <thead>
        <tr><th>ID</th><th></th><th>Título</th><th>Autor</th><th>Categorías</th><th>Descargas</th><th>Lecturas</th><th>Estado</th><th class="text-end">Acciones</th></tr>
    </thead>
    <tbody>
    <?php if (!$libros): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No hay libros para mostrar.</td></tr>
    <?php endif; ?>
    <?php foreach ($libros as $l):
        // Los autores solo ven sus propios libros (ya filtrado en la consulta)
        $puedeGestionar = es_rol($u, 'Administrador', 'Autor');
        $puedeRevisar = es_rol($u, 'Administrador', 'Trabajador');
    ?>
        <tr>
            <td>#<?= (int)$l['id_libro'] ?></td>
            <td>
                <?php if ($l['portada']): ?><img class="portada-mini" src="../<?= e($l['portada']) ?>" alt=""><?php endif; ?>
            </td>
            <td>
                <?= e($l['titulo']) ?>
                <?php if ($l['isbn']): ?><div class="small text-muted">ISBN <?= e($l['isbn']) ?></div><?php endif; ?>
            </td>
            <td><?= e($l['autor']) ?></td>
            <td><?= $l['categorias'] ? e($l['categorias']) : '<span class="text-muted">—</span>' ?></td>
            <td><?= (int)$l['descargas'] ?></td>
            <td><?= (int)$l['lecturas_acumuladas'] ?></td>
            <td><?= badge_estado($l['estado']) ?></td>
            <td class="text-end text-nowrap">
                <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_libro" value="<?= (int)$l['id_libro'] ?>">
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                    <input type="hidden" name="estado" value="<?= e($estado) ?>">
                    <input type="hidden" name="p" value="<?= (int)$pagina ?>">

                    <?php if ($puedeRevisar && in_array($l['estado'], ['pendiente', 'rechazado'], true)): ?>
                        <button name="accion" value="aprobar" class="btn btn-sm btn-success">Aprobar</button>
                    <?php endif; ?>
                    <?php if ($puedeRevisar && $l['estado'] === 'pendiente'): ?>
                        <button name="accion" value="rechazar" class="btn btn-sm btn-outline-danger">Rechazar</button>
                    <?php endif; ?>

                    <?php if ($puedeGestionar): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="libro_form.php?id=<?= (int)$l['id_libro'] ?>">Editar</a>
                        <?php if ($l['estado'] === 'baja'): ?>
                            <button name="accion" value="reactivar" class="btn btn-sm btn-outline-success">Reactivar</button>
                        <?php else: ?>
                            <button name="accion" value="baja" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('¿Dar de baja «<?= e(addslashes($l['titulo'])) ?>» del catálogo?')">Dar de baja</button>
                        <?php endif; ?>
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
