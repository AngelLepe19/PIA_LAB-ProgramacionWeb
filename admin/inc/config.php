<?php
/**
 * Configuración de la conexión a la base de datos.
 * Valores por defecto de XAMPP: usuario root sin contraseña.
 * Si tu MySQL usa otro puerto (por ejemplo 3307), cámbialo aquí.
 */
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'lecturalibre');
define('DB_USER', 'root');
define('DB_PASS', '');

// Carpeta física donde se guardan los PDF y las portadas (raíz del proyecto/uploads)
define('UPLOAD_DIR', dirname(__DIR__, 2) . '/uploads');

// Límites de subida de archivos
define('MAX_PDF_BYTES', 20 * 1024 * 1024);     // 20 MB
define('MAX_PORTADA_BYTES', 3 * 1024 * 1024);  // 3 MB

// Registros por página en las tablas
define('POR_PAGINA', 15);
