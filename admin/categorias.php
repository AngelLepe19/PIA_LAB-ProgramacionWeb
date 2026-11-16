<?php
declare(strict_types=1);
/** CRUD 2 (parte 1) — Categorías / géneros literarios. Solo Administrador. */
require_once __DIR__ . '/inc/bootstrap.php';
require_rol(['Administrador']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    $id     = (int)($_POST['id_categoria'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $desc   = trim((string)($_POST['descripcion'] ?? ''));

    try {
        if ($accion === 'crear' || $accion === 'editar') {
            if ($nombre === '' || mb_strlen($nombre) > 50) {
                throw new InvalidArgumentException('El nombre es obligatorio (máximo 50 caracteres).');
            }
            if (mb_strlen($desc) > 200) {
                throw new InvalidArgumentException('La descripción no puede pasar de 200 caracteres.');
            }
            if ($accion === 'crear') {
                $pdo->prepare('INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)')->execute([$nombre, $desc ?: null]);
                flash('success', 'Categoría «' . $nombre . '» creada.');
            } else {
                $pdo->prepare('UPDATE categorias SET nombre = ?, descripcion = ? WHERE id_categoria = ?')->execute([$nombre, $desc ?: null, $id]);
                flash('success', 'Categoría actualizada.');
            }
        } elseif ($accion === 'eliminar') {
            $st = $pdo->prepare('SELECT COUNT(*) FROM libro_categoria WHERE id_categoria = ?');
            $st->execute([$id]);
            if ((int)$st->fetchColumn() > 0) {
                throw new InvalidArgumentException('No se puede eliminar: la categoría tiene libros asignados. Reasígnalos primero.');
            }
            $pdo->prepare('DELETE FROM categorias WHERE id_categoria = ?')->execute([$id]);
            flash('success', 'Categoría eliminada.');
        } else {
            throw new InvalidArgumentException('Acción no válida.');
        }
    } catch (InvalidArgumentException $ex) {
        flash('danger', $ex->getMessage());
    } catch (PDOException $ex) {
        flash('danger', es_duplicado($ex) ? 'Ya existe una categoría con ese nombre.' : 'No se pudo completar la operación.');
        if (!es_duplicado($ex)) error_log($ex->getMessage());
    }
    redirect('categorias.php');
}

$categorias = $pdo->query(
    'SELECT c.id_categoria, c.nombre, c.descripcion, COUNT(lc.id_libro) AS total_libros
       FROM categorias c LEFT JOIN libro_categoria lc ON lc.id_categoria = c.id_categoria
      GROUP BY c.id_categoria, c.nombre, c.descripcion
      ORDER BY c.nombre'
)->fetchAll();

$titulo = 'Categorías';
$tab = 'categorias';
require __DIR__ . '/inc/header.php';
?>
<form method="post" class="row g-2 mb-3 no-print">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="crear">
    <div class="col-md-3"><input name="nombre" class="form-control form-control-sm" maxlength="50" placeholder="Nueva categoría..." required></div>
    <div class="col-md-5"><input name="descripcion" class="form-control form-control-sm" maxlength="200" placeholder="Descripción (opcional)"></div>
    <div class="col-auto"><button class="btn btn-oscuro btn-sm">Añadir</button></div>
</form>

<div class="table-responsive">
<table class="table table-sm table-bordered bg-white align-middle">
    <thead><tr><th>Categoría</th><th>Descripción</th><th>Libros</th><th class="text-end">Acciones</th></tr></thead>
    <tbody>
    <?php if (!$categorias): ?><tr><td colspan="4" class="text-center text-muted py-4">Aún no hay categorías.</td></tr><?php endif; ?>
    <?php foreach ($categorias as $c): ?>
        <tr>
            <td><?= e($c['nombre']) ?></td>
            <td><?= $c['descripcion'] ? e($c['descripcion']) : '<span class="text-muted">—</span>' ?></td>
            <td><?= (int)$c['total_libros'] ?></td>
            <td class="text-end text-nowrap">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditar"
                        data-id="<?= (int)$c['id_categoria'] ?>" data-nombre="<?= e($c['nombre']) ?>" data-desc="<?= e($c['descripcion']) ?>">Editar</button>
                <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id_categoria" value="<?= (int)$c['id_categoria'] ?>">
                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar la categoría seleccionada?')">Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- Modal editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog"><form method="post" class="modal-content">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id_categoria" id="edId">
        <div class="modal-header"><h5 class="modal-title">Editar categoría</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label">Nombre</label>
            <input name="nombre" id="edNombre" class="form-control mb-3" maxlength="50" required>
            <label class="form-label">Descripción</label>
            <input name="descripcion" id="edDesc" class="form-control" maxlength="200">
        </div>
        <div class="modal-footer"><button class="btn btn-ll">Guardar</button></div>
    </form></div>
</div>
<script>
document.getElementById('modalEditar').addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget;
    document.getElementById('edId').value = b.dataset.id;
    document.getElementById('edNombre').value = b.dataset.nombre;
    document.getElementById('edDesc').value = b.dataset.desc || '';
});
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
