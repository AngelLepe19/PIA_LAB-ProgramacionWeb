<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

$u = usuario_actual();
if ($u === null) {
    redirect('login.php');
}
$destinos = ['Administrador' => 'reportes.php', 'Trabajador' => 'resenas.php', 'Autor' => 'libros.php'];
redirect($destinos[$u['rol']] ?? 'login.php');
