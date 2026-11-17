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

            $_SESSION['id_usuario'] = (int)$usuario['id_usuario'];
            $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
            $_SESSION['correo'] = $usuario['correo'];
            $_SESSION['id_rol'] = (int)$usuario['id_rol'];
            $_SESSION['rol_nombre'] = $usuario['rol_nombre'];
            $_SESSION['usuario'] = [
                'id'     => (int)$usuario['id_usuario'],
                'nombre' => $usuario['nombre_completo'],
                'rol'    => $usuario['rol_nombre'],
            ];

            switch ($_SESSION['id_rol']) {
                case 4:
                    header("Location: ../admin/reportes.php");
                    break;
                case 3:
                    header("Location: ../admin/resenas.php");
                    break;
                case 2:
                    header("Location: ../admin/libros.php");
                    break;
                default:
                    header("Location: ../index.php");
                    break;
            }
            exit;
        }
    }

    header("Location: ../index.php?error=credenciales_invalidas");
    exit;
}

header("Location: ../index.php");
exit;