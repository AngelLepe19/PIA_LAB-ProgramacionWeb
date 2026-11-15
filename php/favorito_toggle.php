<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../index.php?error=inicia_sesion");
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];
$id_libro = (int)($_POST['id_libro'] ?? $_GET['id_libro'] ?? 0);
$redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../index.php';

if ($id_libro > 0) {
    $check = $conn->prepare("SELECT 1 FROM favoritos WHERE id_usuario = ? AND id_libro = ?");
    $check->bind_param("ii", $id_usuario, $id_libro);
    $check->execute();
    $existe = $check->get_result()->num_rows > 0;
    $check->close();

    if ($existe) {
        $stmt = $conn->prepare("DELETE FROM favoritos WHERE id_usuario = ? AND id_libro = ?");
    } else {
        $stmt = $conn->prepare("INSERT INTO favoritos (id_usuario, id_libro) VALUES (?, ?)");
    }
    $stmt->bind_param("ii", $id_usuario, $id_libro);
    $stmt->execute();
    $stmt->close();
}

header("Location: " . $redirect);
exit;