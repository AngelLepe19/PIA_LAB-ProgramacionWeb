<?php
declare(strict_types=1);
/**
 * Cierra completamente la sesión del panel administrativo, invalida la cookie
 * de sesión en el navegador y redirige a la pantalla de login.
 */
require_once __DIR__ . '/inc/bootstrap.php';

// Limpia todas las variables almacenadas en $_SESSION
$_SESSION = [];
// Caduca la cookie de sesión en el navegador si está habilitada
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
// Destruye la sesión en el servidor y vuelve al inicio de sesión
session_destroy();
redirect('login.php');