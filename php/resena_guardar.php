<?php
session_start();
require_once '../config/conexion.php';

// Solo usuarios con sesión pueden reseñar
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php?error=inicia_sesion');
    exit;
}

// Solo se aceptan datos enviados por el formulario (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../catalogo.php');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];
$id_libro   = (int)($_POST['id_libro'] ?? 0);
$puntuacion = (int)($_POST['puntuacion'] ?? 0);
$comentario = trim($_POST['comentario'] ?? '');

// Validar: calificación de 1 a 5 (igual que el CHECK de la tabla) y comentario de máximo 500
if ($puntuacion < 1 || $puntuacion > 5 || $comentario === '' || mb_strlen($comentario) > 500) {
    header("Location: ../detalle.php?id=$id_libro&error=resena_invalida#resenas");
    exit;
}

// El libro debe existir y estar aprobado
$stmt = $conn->prepare("SELECT 1 FROM libros WHERE id_libro = ? AND estado = 'aprobado'");
$stmt->bind_param("i", $id_libro);
$stmt->execute();
$existe_libro = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$existe_libro) {
    header('Location: ../catalogo.php');
    exit;
}

// La llave primaria (id_libro, id_usuario) solo permite una reseña por libro
$stmt = $conn->prepare("SELECT 1 FROM resenas WHERE id_libro = ? AND id_usuario = ?");
$stmt->bind_param("ii", $id_libro, $id_usuario);
$stmt->execute();
$ya_reseno = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($ya_reseno) {
    header("Location: ../detalle.php?id=$id_libro&error=resena_duplicada#resenas");
    exit;
}

// Guardar con estado 'pendiente' para que el trabajador/admin la modere
$stmt = $conn->prepare("INSERT INTO resenas (id_libro, id_usuario, puntuacion, comentario, estado) VALUES (?, ?, ?, ?, 'pendiente')");
$stmt->bind_param("iiis", $id_libro, $id_usuario, $puntuacion, $comentario);
$stmt->execute();
$stmt->close();

header("Location: ../detalle.php?id=$id_libro&resena=enviada#resenas");
exit;