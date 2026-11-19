-- =====================================================================
-- LecturaLibre - Migración "Funciones del Lector" v1
-- Agrega a la tabla `libros`: número de páginas, idioma, tamaño del
-- archivo y clasificación por edad.
--
-- CÓMO EJECUTARLO (phpMyAdmin):
--   1) Abre http://localhost/phpmyadmin
--   2) Haz clic en TU base de datos (la que importaste con lecturalibre_bd.sql)
--   3) Pestaña "Importar" -> elige este archivo -> botón "Importar"
--      (o pestaña "SQL" -> pega el contenido -> "Continuar")
--
-- Es compatible con MariaDB (la que trae XAMPP) y se puede ejecutar
-- más de una vez sin que marque error.
-- =====================================================================

ALTER TABLE libros
  ADD COLUMN IF NOT EXISTS num_paginas INT UNSIGNED NULL AFTER precio,
  ADD COLUMN IF NOT EXISTS idioma VARCHAR(30) NOT NULL DEFAULT 'Español' AFTER num_paginas,
  ADD COLUMN IF NOT EXISTS tamano_archivo_mb DECIMAL(6,2) NULL AFTER idioma,
  ADD COLUMN IF NOT EXISTS clasificacion_edad ENUM('ninos','jovenes','adultos') NOT NULL DEFAULT 'adultos' AFTER tamano_archivo_mb;

-- Datos de ejemplo para que los libros que ya existen no se vean vacíos.
-- (Después cada autor/admin puede editarlos desde el panel.)
UPDATE libros SET num_paginas = 120 WHERE num_paginas IS NULL;
UPDATE libros SET tamano_archivo_mb = 2.50 WHERE tamano_archivo_mb IS NULL;

-- Ejemplo para cambiar un libro en específico (cambia el id_libro):
-- UPDATE libros
--    SET num_paginas = 320, idioma = 'Inglés', tamano_archivo_mb = 4.80, clasificacion_edad = 'jovenes'
--  WHERE id_libro = 1;

-- Las reseñas con "Compra verificada" NO necesitan ninguna columna nueva:
-- se calcula con las tablas pedidos y detalle_pedido que ya existen.
