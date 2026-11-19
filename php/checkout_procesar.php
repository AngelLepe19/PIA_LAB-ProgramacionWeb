<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];

// Transacción: se guarda todo el pedido o no se guarda nada
$conn->begin_transaction();
try {
    // 1. Libros del carrito con su precio actual (se saltan los que ya compró antes)
    $stmt = $conn->prepare("
        SELECT c.id_libro, l.precio
        FROM carrito c
        INNER JOIN libros l ON c.id_libro = l.id_libro
        WHERE c.id_usuario = ? AND l.estado = 'aprobado'
          AND NOT EXISTS (
              SELECT 1 FROM detalle_pedido dp
              INNER JOIN pedidos p ON p.id_pedido = dp.id_pedido
              WHERE p.id_usuario = ? AND dp.id_libro = c.id_libro AND p.estado <> 'cancelado'
          )
    ");
    $stmt->bind_param("ii", $id_usuario, $id_usuario);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($items) === 0) {
        // Puede que solo quedaran libros ya comprados: se limpian del carrito
        $stmt_del = $conn->prepare("DELETE FROM carrito WHERE id_usuario = ?");
        $stmt_del->bind_param("i", $id_usuario);
        $stmt_del->execute();
        $stmt_del->close();
        $conn->commit();
        header("Location: ../carrito.php?error=ya_comprado");
        exit;
    }

    $total = 0.00;
    foreach ($items as $item) {
        $total += (float)$item['precio'];
    }

    // 2. Crear el pedido (queda 'completado' hasta que se descargue)
    $stmt_ped = $conn->prepare("INSERT INTO pedidos (id_usuario, total, estado) VALUES (?, ?, 'completado')");
    $stmt_ped->bind_param("id", $id_usuario, $total);
    $stmt_ped->execute();
    $id_pedido = $stmt_ped->insert_id;
    $stmt_ped->close();

    // 3. Un renglón de detalle por libro con el precio del momento
    $stmt_det = $conn->prepare("INSERT INTO detalle_pedido (id_pedido, id_libro, precio_unitario) VALUES (?, ?, ?)");
    foreach ($items as $item) {
        $id_lib = (int)$item['id_libro'];
        $precio_u = (float)$item['precio'];
        $stmt_det->bind_param("iid", $id_pedido, $id_lib, $precio_u);
        $stmt_det->execute();
    }
    $stmt_det->close();

    // 4. Vaciar el carrito
    $stmt_del = $conn->prepare("DELETE FROM carrito WHERE id_usuario = ?");
    $stmt_del->bind_param("i", $id_usuario);
    $stmt_del->execute();
    $stmt_del->close();

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    header("Location: ../carrito.php?error=pedido");
    exit;
}

header("Location: ../confirmacion.php?pedido=" . $id_pedido);
exit;
