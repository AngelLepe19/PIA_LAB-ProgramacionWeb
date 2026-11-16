<?php
session_start();
require_once '../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$token = trim($_POST['token'] ?? '');
$nueva_password = $_POST['nueva_password'] ?? '';
$confirmar_password = $_POST['confirmar_password'] ?? '';

if ($token === '' || $nueva_password === '' || $confirmar_password === '') {
    header("Location: ../restablecer.php?token=" . urlencode($token) . "&error=campos_vacios");
    exit;
}

if ($nueva_password !== $confirmar_password) {
    header("Location: ../restablecer.php?token=" . urlencode($token) . "&error=no_coinciden");
    exit;
}

$token_hash = hash('sha256', $token);

$sql = "SELECT id_token, id_usuario
        FROM tokens_recuperacion
        WHERE token_hash = ? AND usado = 0 AND fecha_expiracion > NOW()
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$registro_token = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$registro_token) {
    header("Location: ../index.php?error=token_invalido");
    exit;
}

$id_token = (int)$registro_token['id_token'];
$id_usuario = (int)$registro_token['id_usuario'];
$nuevo_hash = password_hash($nueva_password, PASSWORD_DEFAULT);

// 1. Actualizar contraseña del usuario
$stmt_upd = $conn->prepare("UPDATE usuarios SET contrasena_hash = ? WHERE id_usuario = ?");
$stmt_upd->bind_param("si", $nuevo_hash, $id_usuario);
$stmt_upd->execute();
$stmt_upd->close();

// 2. Marcar token como usado para que no pueda reutilizarse
$stmt_tok = $conn->prepare("UPDATE tokens_recuperacion SET usado = 1 WHERE id_token = ?");
$stmt_tok->bind_param("i", $id_token);
$stmt_tok->execute();
$stmt_tok->close();

header("Location: ../index.php?restablecido=exitoso");
exit;