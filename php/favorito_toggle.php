<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../index.php?error=inicia_sesion");
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];
$id_libro = (int)($_POST['id_libro'] ?? $_GET['id_libro'] ?? 0);

// Regresar a la página anterior, pero solo si es una página .php de nuestro sitio
$redirect = '../catalogo.php';
$ref = $_SERVER['HTTP_REFERER'] ?? '';
$pagina = basename((string)parse_url($ref, PHP_URL_PATH));
if (preg_match('/^[a-z_]+\.php$/', $pagina)) {
    $query = parse_url($ref, PHP_URL_QUERY);
    $redirect = '../' . $pagina . ($query ? '?' . $query : '');
}

// Solo se pueden marcar libros que existan y estén aprobados
$stmt_lib = $conn->prepare("SELECT 1 FROM libros WHERE id_libro = ? AND estado = 'aprobado'");
$stmt_lib->bind_param("i", $id_libro);
$stmt_lib->execute();
$libro_valido = $stmt_lib->get_result()->num_rows > 0;
$stmt_lib->close();

if ($libro_valido) {
    $check = $conn->prepare("SELECT 1 FROM favoritos WHERE id_usuario = ? AND id_libro = ?");
    $check->bind_param("ii", $id_usuario, $id_libro);
    $check->execute();
    $existe = $check->get_result()->num_rows > 0;
    $check->close();

    // Si ya era favorito se quita, si no se agrega
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