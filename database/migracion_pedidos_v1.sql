-- =====================================================================
-- LecturaLibre - Migración "Método de pago" v1
-- Agrega a la tabla `pedidos` el método de pago que se elige en el
-- checkout. perfil.php y confirmacion.php lo muestran; sin esta columna
-- esas páginas marcan error.
--
-- CÓMO EJECUTARLO (phpMyAdmin):
--   1) Abre http://localhost/phpmyadmin
--   2) Haz clic en la base lecturalibre
--   3) Pestaña "Importar" -> elige este archivo -> botón "Importar"
--
-- Se puede ejecutar más de una vez sin que marque error.
-- =====================================================================

ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS metodo_pago VARCHAR(20) NOT NULL DEFAULT 'gratuito' AFTER total;
