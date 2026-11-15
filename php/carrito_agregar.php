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
    $stmt = $conn->prepare("INSERT IGNORE INTO carrito (id_usuario, id_libro) VALUES (?, ?)");
    $stmt->bind_param("ii", $id_usuario, $id_libro);
    $stmt->execute();
    $stmt->close();
}

header("Location: " . $redirect);
exit;