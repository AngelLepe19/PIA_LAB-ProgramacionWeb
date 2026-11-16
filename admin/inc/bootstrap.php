<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ---------- Conexión (PDO + consultas preparadas) ---------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

set_exception_handler(function (Throwable $ex) {
    error_log('[LecturaLibre admin] ' . $ex->getMessage());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;padding:2rem">'
       . '<h3>Ocurrió un error</h3><p>No se pudo completar la operación. '
       . 'Revisa que XAMPP (Apache y MySQL) esté encendido y que los datos de <code>admin/inc/config.php</code> sean correctos.</p>'
       . '</body>';
});

/* ---------- Utilidades ---------- */
function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = [$tipo, $mensaje];
}

function pull_flash(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

function es_duplicado(PDOException $ex): bool
{
    return ($ex->errorInfo[1] ?? 0) === 1062;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Llamar al inicio de cualquier bloque que procese un POST. */
function csrf_check(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals($_SESSION['csrf'] ?? '', $enviado)) {
        http_response_code(419);
        exit('Sesión expirada o formulario inválido. Regresa e inténtalo de nuevo.');
    }
}

/* ---------- Sesión y roles ----------
 * CONTRATO CON EL LOGIN DEL EQUIPO: al iniciar sesión se debe guardar
 *   $_SESSION['usuario'] = ['id' => 1, 'nombre' => 'Ana', 'rol' => 'Administrador'];
 * donde 'rol' es el nombre del rol en la tabla roles:
 * Lector | Autor | Trabajador | Administrador
 * Si el login de tus compañeros guarda otras llaves, solo hay que adaptar usuario_actual().
 */
function usuario_actual(): ?array
{
    $u = $_SESSION['usuario'] ?? null;
    return (is_array($u) && isset($u['id'], $u['rol'])) ? $u : null;
}

function require_rol(array $rolesPermitidos): array
{
    $u = usuario_actual();
    if ($u === null) {
        redirect('login.php');
    }
    if (!in_array($u['rol'], $rolesPermitidos, true)) {
        http_response_code(403);
        $titulo = 'Acceso denegado';
        $tab = '';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-danger mt-4">Tu rol (<strong>' . e($u['rol']) . '</strong>) no tiene permiso para esta sección.</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $u;
}

function es_rol(array $u, string ...$roles): bool
{
    return in_array($u['rol'], $roles, true);
}

/* ---------- Subida de archivos ----------
 * Devuelve la ruta relativa (ej. "uploads/pdf/ab12.pdf") o null si no se envió archivo.
 * Lanza RuntimeException con un mensaje legible si el archivo no es válido.
 */
function guardar_subida(string $campo, array $mimes, int $maxBytes, string $subcarpeta): ?string
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$campo];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('El archivo es demasiado grande.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new RuntimeException('No se pudo recibir el archivo. Inténtalo de nuevo.');
    }
    if ($f['size'] > $maxBytes) {
        throw new RuntimeException('El archivo excede el límite de ' . round($maxBytes / 1048576) . ' MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($mimes[$mime])) {
        throw new RuntimeException('Tipo de archivo no permitido (' . implode(', ', array_values($mimes)) . ').');
    }
    $carpeta = UPLOAD_DIR . '/' . $subcarpeta;
    if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de subidas.');
    }
    $nombre = bin2hex(random_bytes(12)) . '.' . $mimes[$mime];
    if (!move_uploaded_file($f['tmp_name'], $carpeta . '/' . $nombre)) {
        throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
    }
    return 'uploads/' . $subcarpeta . '/' . $nombre;
}

/** Borra un archivo subido solo si realmente está dentro de /uploads. */
function borrar_subida(?string $rutaRelativa): void
{
    if (!$rutaRelativa || strpos($rutaRelativa, 'uploads/') !== 0 || strpos($rutaRelativa, '..') !== false) {
        return;
    }
    $ruta = dirname(UPLOAD_DIR) . '/' . $rutaRelativa;
    if (is_file($ruta)) {
        @unlink($ruta);
    }
}

/* ---------- Presentación ---------- */
function badge_estado(string $estado): string
{
    $clases = [
        'aprobado' => 'success', 'aprobada' => 'success', 'activo' => 'success',
        'pendiente' => 'warning text-dark',
        'rechazado' => 'danger', 'rechazada' => 'danger',
        'baja' => 'secondary', 'inactivo' => 'secondary',
    ];
    $c = $clases[$estado] ?? 'light text-dark';
    return '<span class="badge bg-' . $c . '">' . e(ucfirst($estado)) . '</span>';
}

function estrellas(int $n): string
{
    $n = max(0, min(5, $n));
    return '<span class="text-warning" title="' . $n . ' de 5">' . str_repeat('★', $n) . str_repeat('☆', 5 - $n) . '</span>';
}

function paginar(int $total, int $pagina): array
{
    $paginas = max(1, (int)ceil($total / POR_PAGINA));
    $pagina = max(1, min($pagina, $paginas));
    return [$pagina, $paginas, ($pagina - 1) * POR_PAGINA];
}

function paginacion_html(int $pagina, int $paginas): string
{
    if ($paginas <= 1) {
        return '';
    }
    $params = $_GET;
    $html = '<nav><ul class="pagination pagination-sm justify-content-center mt-3">';
    for ($i = 1; $i <= $paginas; $i++) {
        $params['p'] = $i;
        $html .= '<li class="page-item' . ($i === $pagina ? ' active' : '') . '">'
               . '<a class="page-link" href="?' . e(http_build_query($params)) . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}
