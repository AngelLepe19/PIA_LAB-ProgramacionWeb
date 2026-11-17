<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../index.php?error=inicia_sesion");
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];
$id_pedido = (int)($_GET['pedido'] ?? 0);
$id_libro = (int)($_GET['libro'] ?? 0);

// 1. El libro tiene que estar en un pedido de este usuario
$stmt = $conn->prepare("
    SELECT l.titulo, l.archivo_pdf, a.nombre_artistico AS autor
    FROM detalle_pedido dp
    INNER JOIN pedidos p ON dp.id_pedido = p.id_pedido
    INNER JOIN libros l ON dp.id_libro = l.id_libro
    INNER JOIN autores a ON l.id_autor = a.id_autor
    WHERE dp.id_pedido = ? AND dp.id_libro = ? AND p.id_usuario = ? AND p.estado <> 'cancelado'
");
$stmt->bind_param("iii", $id_pedido, $id_libro, $id_usuario);
$stmt->execute();
$libro = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$libro) {
    header("Location: ../index.php?error=sin_permiso");
    exit;
}

// 2. Registrar la descarga (una fila por cada descarga, para los reportes por mes)
$stmt = $conn->prepare("INSERT INTO descargas (id_pedido, id_libro) VALUES (?, ?)");
$stmt->bind_param("ii", $id_pedido, $id_libro);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("UPDATE libros SET lecturas_acumuladas = lecturas_acumuladas + 1 WHERE id_libro = ?");
$stmt->bind_param("i", $id_libro);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("UPDATE pedidos SET estado = 'descargado' WHERE id_pedido = ?");
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$stmt->close();

// 3. Mandar el archivo: si el PDF existe en el proyecto se manda ese,
//    si no, se simula generando un PDF de una página con los datos del libro
$raiz = realpath(__DIR__ . '/..');
$ruta = realpath($raiz . '/' . ltrim((string)$libro['archivo_pdf'], '/'));

if ($ruta !== false && strpos($ruta, $raiz . DIRECTORY_SEPARATOR) === 0 && is_file($ruta)) {
    $contenido = file_get_contents($ruta);
} else {
    $contenido = pdf_simulado($libro['titulo'], $libro['autor']);
}

// Nombre del archivo: "cien-anos-de-soledad.pdf"
$nombre = strtr($libro['titulo'], ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                                    'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
$nombre = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $nombre), '-'));

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . ($nombre !== '' ? $nombre : 'libro') . '.pdf"');
header('Content-Length: ' . strlen($contenido));
echo $contenido;
exit;

// Arma un PDF básico de una página sin usar librerías
function pdf_simulado($titulo, $autor)
{
    $limpiar = function ($texto) {
        $texto = iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    };

    $lineas = [
        [24, 750, $limpiar($titulo)],
        [14, 720, $limpiar('Autor: ' . $autor)],
        [11, 680, $limpiar('Descarga simulada de LecturaLibre - ' . date('d/m/Y H:i'))],
        [11, 662, $limpiar('Este archivo representa el PDF que subió el autor.')],
    ];
    $texto = '';
    foreach ($lineas as $l) {
        $texto .= "BT /F1 {$l[0]} Tf 72 {$l[1]} Td ({$l[2]}) Tj ET\n";
    }

    $objetos = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        "<< /Length " . strlen($texto) . " >>\nstream\n" . $texto . "endstream",
    ];

    $pdf = "%PDF-1.4\n";
    $posiciones = [];
    foreach ($objetos as $i => $obj) {
        $posiciones[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
    }
    $inicio_xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
    foreach ($posiciones as $p) {
        $pdf .= sprintf("%010d 00000 n \n", $p);
    }
    $pdf .= "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R >>\nstartxref\n" . $inicio_xref . "\n%%EOF";
    return $pdf;
}
