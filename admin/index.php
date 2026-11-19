<?php
declare(strict_types=1);
/**
 * Punto de entrada de la carpeta admin/.
 * Comprueba si hay un usuario autenticado y lo redirige automáticamente
 * a la sección principal que le corresponde según su rol.
 */
require_once __DIR__ . '/inc/bootstrap.php';

$u = usuario_actual();
// Si no hay sesión activa, envía al formulario de inicio de sesión del panel
if ($u === null) {
    redirect('login.php');
}
// Mapa de vistas iniciales por rol
$destinos = ['Administrador' => 'reportes.php', 'Trabajador' => 'resenas.php', 'Autor' => 'libros.php'];
redirect($destinos[$u['rol']] ?? 'login.php');