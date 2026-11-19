<?php
session_start();
require_once '../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_completo'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    // Solo se puede elegir Lector o Autor; Trabajador y Admin los asigna el administrador
    $es_autor = ($_POST['tipo_cuenta'] ?? '') === 'autor';

    if ($nombre === '' || $correo === '' || $password === '') {
        header("Location: ../index.php?error=campos_vacios");
        exit;
    }

    if (empty($_POST['acepto_terminos'])) {
        header("Location: ../index.php?error=terminos");
        exit;
    }

    // Verificar si el correo ya está registrado
    $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        header("Location: ../index.php?error=correo_existente");
        exit;
    }
    $stmt->close();

    // Encriptar contraseña e insertar como Lector (1) o Autor (2) activo
    $contrasena_hash = password_hash($password, PASSWORD_DEFAULT);
    $id_rol = $es_autor ? 2 : 1;
    $estado = 'activo';

    $stmt = $conn->prepare("INSERT INTO usuarios (nombre_completo, correo, contrasena_hash, id_rol, estado) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $nombre, $correo, $contrasena_hash, $id_rol, $estado);

    if ($stmt->execute()) {
        $_SESSION['id_usuario'] = $stmt->insert_id;
        $_SESSION['nombre_completo'] = $nombre;
        $_SESSION['correo'] = $correo;
        $_SESSION['id_rol'] = $id_rol;
        $_SESSION['rol_nombre'] = $es_autor ? 'Autor' : 'Lector';

        header("Location: ../index.php?registro=" . ($es_autor ? 'autor' : 'exitoso'));
    } else {
        header("Location: ../index.php?error=error_registro");
    }
    $stmt->close();
    exit;
}