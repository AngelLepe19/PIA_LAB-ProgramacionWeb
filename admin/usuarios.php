<?php
declare(strict_types=1);
/**
 * CRUD 2 (parte 2) — Usuarios y roles. Solo Administrador.
 * Crear, ver, editar (datos, rol, estado, contraseña) y "eliminar" = desactivar la cuenta
 * (no se borra físicamente para conservar el historial de pedidos).
 */
require_once __DIR__ . '/inc/bootstrap.php';
$yo = require_rol(['Administrador']);
$pdo = db();

$roles = $pdo->query('SELECT id_rol, nombre FROM roles ORDER BY id_rol')->fetchAll();
$idsRol = array_map(fn($r) => (int)$r['id_rol'], $roles);
$estadosValidos = ['pendiente', 'activo', 'inactivo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id_usuario'] ?? 0);

    try {
        if ($accion === 'crear' || $accion === 'editar') {
            $nombre = trim((string)($_POST['nombre_completo'] ?? ''));
            $correo = trim((string)($_POST['correo'] ?? ''));
            $idRol  = (int)($_POST['id_rol'] ?? 0);
            $estado = (string)($_POST['estado'] ?? 'activo');
            $clave  = (string)($_POST['contrasena'] ?? '');

            if ($nombre === '' || mb_strlen($nombre) > 100) throw new InvalidArgumentException('El nombre es obligatorio (máximo 100 caracteres).');
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 120) throw new InvalidArgumentException('Escribe un correo válido.');
            if (!in_array($idRol, $idsRol, true)) throw new InvalidArgumentException('Selecciona un rol válido.');
            if (!in_array($estado, $estadosValidos, true)) throw new InvalidArgumentException('Estado no válido.');

            if ($accion === 'crear') {
                if (mb_strlen($clave) < 8) throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
                $pdo->prepare('INSERT INTO usuarios (nombre_completo, correo, contrasena_hash, id_rol, estado, fecha_registro) VALUES (?,?,?,?,?,NOW())')
                    ->execute([$nombre, $correo, password_hash($clave, PASSWORD_BCRYPT), $idRol, $estado]);
                flash('success', 'Usuario creado.');
            } else {
                // Evitar que el administrador se quite el acceso a sí mismo
                if ($id === (int)$yo['id']) {
                    $st = $pdo->prepare('SELECT r.nombre FROM roles r WHERE r.id_rol = ?');
                    $st->execute([$idRol]);
                    if ($st->fetchColumn() !== 'Administrador' || $estado !== 'activo') {
                        throw new InvalidArgumentException('No puedes quitarte tu propio rol de Administrador ni desactivar tu cuenta.');
                    }
                }
                if ($clave !== '' && mb_strlen($clave) < 8) throw new InvalidArgumentException('La nueva contraseña debe tener al menos 8 caracteres.');

                $pdo->prepare('UPDATE usuarios SET nombre_completo=?, correo=?, id_rol=?, estado=? WHERE id_usuario=?')
                    ->execute([$nombre, $correo, $idRol, $estado, $id]);
                if ($clave !== '') {
                    $pdo->prepare('UPDATE usuarios SET contrasena_hash=? WHERE id_usuario=?')
                        ->execute([password_hash($clave, PASSWORD_BCRYPT), $id]);
                }
                flash('success', 'Usuario actualizado.');
            }
        } elseif ($accion === 'desactivar' || $accion === 'activar') {
            if ($id === (int)$yo['id']) throw new InvalidArgumentException('No puedes cambiar el estado de tu propia cuenta.');
            $pdo->prepare('UPDATE usuarios SET estado = ? WHERE id_usuario = ?')
                ->execute([$accion === 'activar' ? 'activo' : 'inactivo', $id]);
            flash('success', $accion === 'activar' ? 'Cuenta activada.' : 'Cuenta desactivada.');
        } else {
            throw new InvalidArgumentException('Acción no válida.');
        }
    } catch (InvalidArgumentException $ex) {
        flash('danger', $ex->getMessage());
    } catch (PDOException $ex) {
        flash('danger', es_duplicado($ex) ? 'Ya existe un usuario con ese correo.' : 'No se pudo completar la operación.');
        if (!es_duplicado($ex)) error_log($ex->getMessage());
    }
    redirect('usuarios.php');
}

/* ---------- Listado ---------- */
$q = trim((string)($_GET['q'] ?? ''));
$fRol = (int)($_GET['rol'] ?? 0);
$fEstado = (string)($_GET['estado'] ?? '');

$where = []; $params = [];
if ($q !== '') { $where[] = '(u.nombre_completo LIKE ? OR u.correo LIKE ?)'; array_push($params, "%$q%", "%$q%"); }
if ($fRol > 0) { $where[] = 'u.id_rol = ?'; $params[] = $fRol; }
if (in_array($fEstado, $estadosValidos, true)) { $where[] = 'u.estado = ?'; $params[] = $fEstado; }
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = $pdo->prepare("SELECT COUNT(*) FROM usuarios u $sqlWhere");
$st->execute($params);
[$pagina, $paginas, $offset] = paginar((int)$st->fetchColumn(), (int)($_GET['p'] ?? 1));

$st = $pdo->prepare(
    "SELECT u.id_usuario, u.nombre_completo, u.correo, u.id_rol, r.nombre AS rol, u.estado, u.fecha_registro
       FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
       $sqlWhere ORDER BY u.id_usuario LIMIT " . POR_PAGINA . " OFFSET " . $offset
);
$st->execute($params);
$usuarios = $st->fetchAll();

$titulo = 'Usuarios / Roles';
$tab = 'usuarios';
require __DIR__ . '/inc/header.php';

function selector_roles(array $roles, string $id): string
{
    $h = '<select name="id_rol" id="' . $id . '" class="form-select" required>';
    foreach ($roles as $r) {
        $h .= '<option value="' . (int)$r['id_rol'] . '">' . e($r['nombre']) . '</option>';
    }
    return $h . '</select>';
}
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2 no-print" method="get">
        <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Nombre o correo">
        <select name="rol" class="form-select form-select-sm">
            <option value="">Todos los roles</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int)$r['id_rol'] ?>" <?= $fRol === (int)$r['id_rol'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="estado" class="form-select form-select-sm">
            <option value="">Todos los estados</option>
            <?php foreach ($estadosValidos as $es): ?>
                <option value="<?= $es ?>" <?= $fEstado === $es ? 'selected' : '' ?>><?= ucfirst($es) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-secondary">Filtrar</button>
    </form>
    <button class="btn btn-oscuro btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrear">+ Nuevo usuario</button>
</div>

<div class="table-responsive">
<table class="table table-sm table-bordered bg-white align-middle">
    <thead><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Registro</th><th class="text-end">Acciones</th></tr></thead>
    <tbody>
    <?php if (!$usuarios): ?><tr><td colspan="7" class="text-center text-muted py-4">No hay usuarios para mostrar.</td></tr><?php endif; ?>
    <?php foreach ($usuarios as $x): $esYo = (int)$x['id_usuario'] === (int)$yo['id']; ?>
        <tr>
            <td>#<?= (int)$x['id_usuario'] ?></td>
            <td><?= e($x['nombre_completo']) ?><?= $esYo ? ' <span class="badge bg-info text-dark">tú</span>' : '' ?></td>
            <td><?= e($x['correo']) ?></td>
            <td><?= e($x['rol']) ?></td>
            <td><?= badge_estado($x['estado']) ?></td>
            <td><?= e(substr((string)$x['fecha_registro'], 0, 10)) ?></td>
            <td class="text-end text-nowrap">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditar"
                        data-id="<?= (int)$x['id_usuario'] ?>" data-nombre="<?= e($x['nombre_completo']) ?>" data-correo="<?= e($x['correo']) ?>"
                        data-rol="<?= (int)$x['id_rol'] ?>" data-estado="<?= e($x['estado']) ?>">Editar</button>
                <?php if (!$esYo): ?>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_usuario" value="<?= (int)$x['id_usuario'] ?>">
                        <?php if ($x['estado'] === 'activo'): ?>
                            <button name="accion" value="desactivar" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Desactivar esta cuenta?')">Desactivar</button>
                        <?php else: ?>
                            <button name="accion" value="activar" class="btn btn-sm btn-success"><?= $x['estado'] === 'pendiente' ? 'Aprobar' : 'Activar' ?></button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= paginacion_html($pagina, $paginas) ?>

<!-- Modal crear -->
<div class="modal fade" id="modalCrear" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="accion" value="crear">
    <div class="modal-header"><h5 class="modal-title">Nuevo usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body vstack gap-3">
        <div><label class="form-label">Nombre completo</label><input name="nombre_completo" class="form-control" maxlength="100" required></div>
        <div><label class="form-label">Correo</label><input type="email" name="correo" class="form-control" maxlength="120" required></div>
        <div><label class="form-label">Contraseña (mín. 8)</label><input type="password" name="contrasena" class="form-control" minlength="8" required></div>
        <div><label class="form-label">Rol</label><?= selector_roles($roles, 'crRol') ?></div>
        <div><label class="form-label">Estado</label>
            <select name="estado" class="form-select"><option value="activo">Activo</option><option value="pendiente">Pendiente</option><option value="inactivo">Inactivo</option></select>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ll">Crear usuario</button></div>
</form></div></div>

<!-- Modal editar -->
<div class="modal fade" id="modalEditar" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id_usuario" id="edId">
    <div class="modal-header"><h5 class="modal-title">Editar usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body vstack gap-3">
        <div><label class="form-label">Nombre completo</label><input name="nombre_completo" id="edNombre" class="form-control" maxlength="100" required></div>
        <div><label class="form-label">Correo</label><input type="email" name="correo" id="edCorreo" class="form-control" maxlength="120" required></div>
        <div><label class="form-label">Rol</label><?= selector_roles($roles, 'edRol') ?></div>
        <div><label class="form-label">Estado</label>
            <select name="estado" id="edEstado" class="form-select"><option value="activo">Activo</option><option value="pendiente">Pendiente</option><option value="inactivo">Inactivo</option></select>
        </div>
        <div><label class="form-label">Nueva contraseña <small class="text-muted">(vacío = no cambiar)</small></label><input type="password" name="contrasena" class="form-control" minlength="8"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-ll">Guardar cambios</button></div>
</form></div></div>
<script>
document.getElementById('modalEditar').addEventListener('show.bs.modal', function (ev) {
    var d = ev.relatedTarget.dataset;
    document.getElementById('edId').value = d.id;
    document.getElementById('edNombre').value = d.nombre;
    document.getElementById('edCorreo').value = d.correo;
    document.getElementById('edRol').value = d.rol;
    document.getElementById('edEstado').value = d.estado;
});
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
