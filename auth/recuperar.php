<?php
session_start();
require_once '../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$correo = trim($_POST['correo'] ?? '');

if ($correo === '') {
    header("Location: ../index.php?error=campos_vacios");
    exit;
}

$stmt = $conn->prepare("SELECT id_usuario, estado FROM usuarios WHERE correo = ?");
$stmt->bind_param("s", $correo);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($usuario && $usuario['estado'] === 'activo') {
    $id_usuario = (int)$usuario['id_usuario'];

    // Invalidar tokens anteriores no usados de este usuario
    $stmt_inv = $conn->prepare("UPDATE tokens_recuperacion SET usado = 1 WHERE id_usuario = ? AND usado = 0");
    $stmt_inv->bind_param("i", $id_usuario);
    $stmt_inv->execute();
    $stmt_inv->close();

    // Generar token aleatorio y guardar su hash SHA-256 con 1 hora de expiración
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);

    $stmt_ins = $conn->prepare("INSERT INTO tokens_recuperacion (id_usuario, token_hash, fecha_expiracion, usado) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)");
    $stmt_ins->bind_param("is", $id_usuario, $token_hash);
    $stmt_ins->execute();
    $stmt_ins->close();

    // Guardar enlace de prueba en sesión para mostrarlo en el aviso local
    $_SESSION['link_recuperacion_demo'] = "restablecer.php?token=" . $token;
}

header("Location: ../index.php?recuperar=enviado");
exit;