<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../index.php");
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];
$id_libro = (int)($_GET['id_libro'] ?? 0);

if ($id_libro > 0) {
    $stmt = $conn->prepare("DELETE FROM carrito WHERE id_usuario = ? AND id_libro = ?");
    $stmt->bind_param("ii", $id_usuario, $id_libro);
    $stmt->execute();
    $stmt->close();
}

header("Location: ../carrito.php");
exit;