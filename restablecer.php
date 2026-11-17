<?php
session_start();
require_once 'config/conexion.php';

$token = trim($_GET['token'] ?? '');
$token_valido = false;
$correo_usuario = '';

if ($token !== '') {
    $token_hash = hash('sha256', $token);
    $sql = "SELECT t.id_token, u.correo
            FROM tokens_recuperacion t
            INNER JOIN usuarios u ON t.id_usuario = u.id_usuario
            WHERE t.token_hash = ? AND t.usado = 0 AND t.fecha_expiracion > NOW()
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res) {
        $token_valido = true;
        $correo_usuario = $res['correo'];
    }
}

$error_msg = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'no_coinciden') {
        $error_msg = 'Las contraseñas no coinciden.';
    } elseif ($_GET['error'] === 'campos_vacios') {
        $error_msg = 'Completa ambos campos de contraseña.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - LecturaLibre</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-book-open"></i><span>LecturaLibre</span>
            </a>
        </div>
    </header>

    <main class="container" style="max-width: 480px; margin-top: 40px;">
        <div style="background: white; padding: 25px; border: 1px solid var(--wf-border); border-radius: var(--radius);">
            <h2 style="margin-bottom: 15px;">Restablecer Contraseña</h2>

            <?php if (!$token_valido): ?>
                <p style="color: #721c24; margin-bottom: 15px;">
                    El enlace de recuperación no es válido, ya fue utilizado o ha expirado.
                </p>
                <a href="index.php" class="btn btn-primary">Volver al Inicio</a>
            <?php else: ?>
                <p style="font-size: 0.9rem; color: var(--wf-gray); margin-bottom: 15px;">
                    Cuenta: <strong><?= htmlspecialchars($correo_usuario) ?></strong>
                </p>

                <?php if ($error_msg !== ''): ?>
                    <div style="padding: 10px 14px; border-radius: 6px; margin-bottom: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                        <?= htmlspecialchars($error_msg) ?>
                    </div>
                <?php endif; ?>

                <form action="auth/procesar_restablecimiento.php" method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="form-group">
                        <label for="nueva_password">Nueva Contraseña</label>
                        <input type="password" id="nueva_password" name="nueva_password" autocomplete="new-password" placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label for="confirmar_password">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirmar_password" name="confirmar_password" autocomplete="new-password" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Guardar Nueva Contraseña</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>