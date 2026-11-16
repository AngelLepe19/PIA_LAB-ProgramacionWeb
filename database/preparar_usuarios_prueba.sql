-- Contraseñas de PRUEBA para los usuarios de ejemplo (los hashes originales eran de relleno y no permiten iniciar sesión).
-- Ejecutar una sola vez, DESPUÉS de importar la base de datos. Cambiar o borrar estas cuentas antes de publicar el sitio.
--   admin@lecturalibre.com       -> Admin2026!
--   trabajador@lecturalibre.com  -> Trabajador2026!
--   autor@lecturalibre.com       -> Autor2026!
--   carlos@correo.com / lector@lecturalibre.com -> Lector2026!

USE lecturalibre;

UPDATE usuarios SET contrasena_hash = '$2y$10$9qA6apgdMZTTs741soScaeJtkGGs4SKbb4IqvMaar1SAwtXvW1ruG', estado = 'activo' WHERE correo = 'admin@lecturalibre.com';
UPDATE usuarios SET contrasena_hash = '$2y$10$.gyD1QiVNlqDqXMUO91pO.xWGmuCawa7r.8E9DbIqaIuwHeecLkyC', estado = 'activo' WHERE correo = 'trabajador@lecturalibre.com';
UPDATE usuarios SET contrasena_hash = '$2y$10$/gCPlnvKsoE.bnM14J36WuGcMIbc8.pawNiv096d5Oaxlz5BBdOvG', estado = 'activo' WHERE correo = 'autor@lecturalibre.com';
UPDATE usuarios SET contrasena_hash = '$2y$10$MMlgqda/Vx1wy6TBCaHGWeM0Y8PvqaJAqCjZYXcjpwKOpw7JwROuy', estado = 'activo' WHERE correo = 'carlos@correo.com';
UPDATE usuarios SET contrasena_hash = '$2y$10$5EugRlJWaTD9RROvCJCeH.Ao2oPoOd2tP9tnwJu/TXPsA1N6kSAwS', estado = 'activo' WHERE correo = 'lector@lecturalibre.com';
