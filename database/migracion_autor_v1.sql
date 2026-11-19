-- =====================================================================
-- LecturaLibre - Migración "Mejoras del Autor" v1
-- Agrega a la tabla `libros` el motivo de rechazo, para que el autor
-- sepa qué corregir cuando un Trabajador o Admin rechaza su libro.
--
-- CÓMO EJECUTARLO (phpMyAdmin):
--   1) Abre http://localhost/phpmyadmin
--   2) Haz clic en la base lecturalibre
--   3) Pestaña "Importar" -> elige este archivo -> botón "Importar"
--      (o pestaña "SQL" -> pega el contenido -> "Continuar")
--
-- Se puede ejecutar más de una vez sin que marque error.
-- =====================================================================

ALTER TABLE libros
  ADD COLUMN IF NOT EXISTS motivo_rechazo VARCHAR(255) NULL AFTER fecha_revision;
