<?php
declare(strict_types=1);
/**
 * LOGIN TEMPORAL DEL PANEL.
 * Sirve para probar el módulo del Integrante 4. Cuando el login oficial del equipo esté listo,
 * basta con que ese login guarde $_SESSION['usuario'] = ['id'=>..,'nombre'=>..,'rol'=>..]
 * (ver admin/inc/bootstrap.php) y este archivo se puede dejar o quitar.
 */
require_once __DIR__ . '/inc/bootstrap.php';

// Rutas iniciales permitidas dentro del panel según el rol
$destinos = [
    'Administrador' => 'reportes.php',
    'Trabajador'    => 'resenas.php',
    'Autor'         => 'libros.php',
];

// Si ya existe una sesión válida con acceso al panel, redirige directamente a su módulo
if (usuario_actual() !== null && isset($destinos[usuario_actual()['rol']])) {
    redirect($destinos[usuario_actual()['rol']]);
}

$error = '';
// Validación de credenciales cuando se envía el formulario de acceso
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $correo = trim((string)($_POST['correo'] ?? ''));
    $clave  = (string)($_POST['contrasena'] ?? '');

    // Busca el usuario por correo junto con el nombre de su rol
    $st = db()->prepare(
        'SELECT u.id_usuario, u.nombre_completo, u.contrasena_hash, u.estado, r.nombre AS rol
           FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
          WHERE u.correo = ?'
    );
    $st->execute([$correo]);
    $fila = $st->fetch();

    if (!$fila || !password_verify($clave, $fila['contrasena_hash'])) {
        $error = 'Correo o contraseña incorrectos.';
    } elseif ($fila['estado'] !== 'activo') {
        $error = 'Tu cuenta aún no está activa. Un administrador debe aprobarla.';
    } elseif (!isset($destinos[$fila['rol']])) {
        // Bloquea el acceso al panel a usuarios con rol Lector
        $error = 'Tu rol (' . $fila['rol'] . ') no tiene acceso al panel administrativo.';
    } else {
        // Regenera el ID de sesión para prevenir fijación de sesión y guarda los datos del usuario
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id'     => (int)$fila['id_usuario'],
            'nombre' => $fila['nombre_completo'],
            'rol'    => $fila['rol'],
        ];
        redirect($destinos[$fila['rol']]);
    }
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · LecturaLibre</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/admin.css" rel="stylesheet">
</head>
<body>
<!-- Tarjeta centrada con el formulario de inicio de sesión -->
<div class="container" style="max-width: 420px;">
    <div class="card shadow-sm mt-5">
        <div class="card-body p-4">
            <h1 class="h5 fw-bold mb-1"><span class="brand-mark">▮▮</span> LecturaLibre</h1>
            <p class="text-muted small">Acceso al panel administrativo</p>
            <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" name="correo" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="contrasena" class="form-control" required>
                </div>
                <button class="btn btn-ll w-100">Ingresar</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>