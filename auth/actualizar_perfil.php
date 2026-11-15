<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];
$nombre_completo = trim($_POST['nombre_completo'] ?? '');
$nueva_password = $_POST['nueva_password'] ?? '';

if ($nombre_completo === '') {
    header("Location: ../perfil.php?error=campos_vacios");
    exit;
}

if ($nueva_password !== '') {
    // Actualizar nombre y nueva contraseña encriptada
    $contrasena_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE usuarios SET nombre_completo = ?, contrasena_hash = ? WHERE id_usuario = ?");
    $stmt->bind_param("ssi", $nombre_completo, $contrasena_hash, $id_usuario);
} else {
    // Actualizar únicamente el nombre
    $stmt = $conn->prepare("UPDATE usuarios SET nombre_completo = ? WHERE id_usuario = ?");
    $stmt->bind_param("si", $nombre_completo, $id_usuario);
}

if ($stmt->execute()) {
    $_SESSION['nombre_completo'] = $nombre_completo;
    $stmt->close();
    header("Location: ../perfil.php?perfil=actualizado");
} else {
    $stmt->close();
    header("Location: ../perfil.php?error=error_actualizacion");
}
exit;