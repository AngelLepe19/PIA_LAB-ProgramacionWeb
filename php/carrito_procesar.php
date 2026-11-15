<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];

// Obtener libros actuales en el carrito del usuario
$sql = "SELECT c.id_libro, l.precio
        FROM carrito c
        INNER JOIN libros l ON c.id_libro = l.id_libro
        WHERE c.id_usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (count($items) === 0) {
    header("Location: ../carrito.php");
    exit;
}

$total = 0.00;
foreach ($items as $item) {
    $total += (float)$item['precio'];
}

// 1. Crear el pedido
$estado_pedido = 'descargado';
$stmt_ped = $conn->prepare("INSERT INTO pedidos (id_usuario, total, estado) VALUES (?, ?, ?)");
$stmt_ped->bind_param("ids", $id_usuario, $total, $estado_pedido);
$stmt_ped->execute();
$id_pedido = $stmt_ped->insert_id;
$stmt_ped->close();

// 2. Guardar detalle, registrar descarga y sumar lecturas
$stmt_det = $conn->prepare("INSERT INTO detalle_pedido (id_pedido, id_libro, precio_unitario) VALUES (?, ?, ?)");
$stmt_desc = $conn->prepare("INSERT INTO descargas (id_pedido, id_libro) VALUES (?, ?)");
$stmt_upd = $conn->prepare("UPDATE libros SET lecturas_acumuladas = lecturas_acumuladas + 1 WHERE id_libro = ?");

foreach ($items as $item) {
    $id_lib = (int)$item['id_libro'];
    $precio_u = (float)$item['precio'];

    $stmt_det->bind_param("iid", $id_pedido, $id_lib, $precio_u);
    $stmt_det->execute();

    $stmt_desc->bind_param("ii", $id_pedido, $id_lib);
    $stmt_desc->execute();

    $stmt_upd->bind_param("i", $id_lib);
    $stmt_upd->execute();
}

$stmt_det->close();
$stmt_desc->close();
$stmt_upd->close();

// 3. Vaciar el carrito
$stmt_del = $conn->prepare("DELETE FROM carrito WHERE id_usuario = ?");
$stmt_del->bind_param("i", $id_usuario);
$stmt_del->execute();
$stmt_del->close();

header("Location: ../carrito.php?pedido=exitoso");
exit;