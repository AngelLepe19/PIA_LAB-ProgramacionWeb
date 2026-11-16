<?php
session_start();
require_once '../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    $sql = "SELECT u.id_usuario, u.nombre_completo, u.correo, u.contrasena_hash, u.id_rol, u.estado, r.nombre AS rol_nombre
            FROM usuarios u
            INNER JOIN roles r ON u.id_rol = r.id_rol
            WHERE u.correo = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($usuario = $resultado->fetch_assoc()) {
        if ($usuario['estado'] !== 'activo') {
            header("Location: ../index.php?error=cuenta_inactiva");
            exit;
        }

        if (password_verify($password, $usuario['contrasena_hash'])) {
            session_regenerate_id(true);

            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
            $_SESSION['correo'] = $usuario['correo'];
            $_SESSION['id_rol'] = (int)$usuario['id_rol'];
            $_SESSION['rol_nombre'] = $usuario['rol_nombre'];

            if ($_SESSION['id_rol'] === 4) {
                header("Location: ../paneladmin.php");
            } elseif ($_SESSION['id_rol'] === 3) {
                header("Location: ../paneltrabajador.php");
            } else {
                header("Location: ../index.php");
            }
            exit;
        }
    }

    header("Location: ../index.php?error=credenciales_invalidas");
    exit;
}

header("Location: ../index.php");
exit;