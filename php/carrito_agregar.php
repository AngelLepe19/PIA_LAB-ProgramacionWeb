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

// Si ya lo compró no se agrega otra vez; se manda al detalle para descargarlo
if ($id_libro > 0) {
    $stmt = $conn->prepare("
        SELECT 1 FROM detalle_pedido dp
        INNER JOIN pedidos p ON p.id_pedido = dp.id_pedido
        WHERE p.id_usuario = ? AND dp.id_libro = ? AND p.estado <> 'cancelado'
    ");
    $stmt->bind_param("ii", $id_usuario, $id_libro);
    $stmt->execute();
    $ya_comprado = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($ya_comprado) {
        header("Location: ../detalle.php?id=" . $id_libro . "&compra=ya_tienes");
        exit;
    }
}

if ($id_libro > 0) {
    $stmt = $conn->prepare("INSERT IGNORE INTO carrito (id_usuario, id_libro) VALUES (?, ?)");
    $stmt->bind_param("ii", $id_usuario, $id_libro);
    $stmt->execute();
    $stmt->close();
}

header("Location: " . $redirect);
exit;