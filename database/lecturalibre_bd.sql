CREATE DATABASE lecturalibre CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lecturalibre;

-- Catálogo de roles del sistema
CREATE TABLE roles (
  id_rol INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(30) NOT NULL,
  descripcion VARCHAR(150),
  PRIMARY KEY (id_rol),
  UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB;

-- Cuentas de todos los roles
CREATE TABLE usuarios (
  id_usuario INT NOT NULL AUTO_INCREMENT,
  nombre_completo VARCHAR(100) NOT NULL,
  correo VARCHAR(120) NOT NULL,
  contrasena_hash VARCHAR(255) NOT NULL,
  telefono VARCHAR(20),
  foto_perfil VARCHAR(255),
  descripcion VARCHAR(300),
  id_rol INT NOT NULL,
  estado ENUM('pendiente','activo','inactivo') NOT NULL DEFAULT 'pendiente',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuarios_correo (correo),
  CONSTRAINT fk_usuarios_id_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Perfil público de quien firma las obras
CREATE TABLE autores (
  id_autor INT NOT NULL AUTO_INCREMENT,
  nombre_artistico VARCHAR(100) NOT NULL,
  biografia TEXT,
  id_usuario INT,
  PRIMARY KEY (id_autor),
  UNIQUE KEY uq_autores_id_usuario (id_usuario),
  CONSTRAINT fk_autores_id_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Casas editoriales (CRUD de categorías, géneros y editoriales)
CREATE TABLE editoriales (
  id_editorial INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_editorial),
  UNIQUE KEY uq_editoriales_nombre (nombre)
) ENGINE=InnoDB;

-- Géneros literarios
CREATE TABLE categorias (
  id_categoria INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(50) NOT NULL,
  descripcion VARCHAR(200),
  PRIMARY KEY (id_categoria),
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB;

-- Catálogo de libros digitales
CREATE TABLE libros (
  id_libro INT NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(200) NOT NULL,
  isbn VARCHAR(20),
  sinopsis TEXT,
  fecha_publicacion DATE,
  portada VARCHAR(255),
  archivo_pdf VARCHAR(255) NOT NULL,
  precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  copias_disponibles INT NOT NULL DEFAULT 0,
  lecturas_acumuladas INT NOT NULL DEFAULT 0,
  estado ENUM('pendiente','aprobado','rechazado','baja') NOT NULL DEFAULT 'pendiente',
  id_autor INT NOT NULL,
  id_editorial INT,
  id_revisor INT,
  fecha_revision DATETIME,
  fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_libro),
  UNIQUE KEY uq_libros_isbn (isbn),
  CONSTRAINT fk_libros_id_autor FOREIGN KEY (id_autor) REFERENCES autores(id_autor) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_libros_id_editorial FOREIGN KEY (id_editorial) REFERENCES editoriales(id_editorial) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_libros_id_revisor FOREIGN KEY (id_revisor) REFERENCES usuarios(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Tabla intermedia libros ↔ categorías (n-n)
CREATE TABLE libro_categoria (
  id_libro INT NOT NULL,
  id_categoria INT NOT NULL,
  PRIMARY KEY (id_libro, id_categoria),
  CONSTRAINT fk_libro_categoria_id_libro FOREIGN KEY (id_libro) REFERENCES libros(id_libro) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_libro_categoria_id_categoria FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Reseñas y valoraciones con moderación (PK compuesta: una reseña por usuario y libro)
CREATE TABLE resenas (
  id_libro INT NOT NULL,
  id_usuario INT NOT NULL,
  puntuacion TINYINT NOT NULL,
  comentario VARCHAR(500) NOT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  id_moderador INT,
  fecha_moderacion DATETIME,
  PRIMARY KEY (id_libro, id_usuario),
  CONSTRAINT ck_resena_puntuacion CHECK (puntuacion BETWEEN 1 AND 5),
  CONSTRAINT fk_resenas_id_libro FOREIGN KEY (id_libro) REFERENCES libros(id_libro) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_resenas_id_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_resenas_id_moderador FOREIGN KEY (id_moderador) REFERENCES usuarios(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Lista de deseos / favoritos (n-n)
CREATE TABLE favoritos (
  id_usuario INT NOT NULL,
  id_libro INT NOT NULL,
  fecha_agregado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario, id_libro),
  CONSTRAINT fk_favoritos_id_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_favoritos_id_libro FOREIGN KEY (id_libro) REFERENCES libros(id_libro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Carrito de selección (n-n)
CREATE TABLE carrito (
  id_usuario INT NOT NULL,
  id_libro INT NOT NULL,
  fecha_agregado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario, id_libro),
  CONSTRAINT fk_carrito_id_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_carrito_id_libro FOREIGN KEY (id_libro) REFERENCES libros(id_libro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Órdenes generadas al confirmar el carrito
CREATE TABLE pedidos (
  id_pedido INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  fecha_pedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  estado ENUM('completado','descargado','cancelado') NOT NULL DEFAULT 'completado',
  PRIMARY KEY (id_pedido),
  CONSTRAINT fk_pedidos_id_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Libros incluidos en cada pedido
CREATE TABLE detalle_pedido (
  id_pedido INT NOT NULL,
  id_libro INT NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id_pedido, id_libro),
  CONSTRAINT fk_detalle_pedido_id_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_detalle_pedido_id_libro FOREIGN KEY (id_libro) REFERENCES libros(id_libro) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Bitácora de descargas: una fila por cada descarga realizada
CREATE TABLE descargas (
  id_descarga INT NOT NULL AUTO_INCREMENT,
  id_pedido INT NOT NULL,
  id_libro INT NOT NULL,
  fecha_descarga DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_descarga),
  KEY idx_descargas_fecha (fecha_descarga),
  CONSTRAINT fk_descargas_detalle FOREIGN KEY (id_pedido, id_libro) REFERENCES detalle_pedido(id_pedido, id_libro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Mensajes del formulario de contacto
CREATE TABLE mensajes_contacto (
  id_mensaje INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  correo VARCHAR(120) NOT NULL,
  asunto VARCHAR(150) NOT NULL,
  mensaje TEXT NOT NULL,
  fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('pendiente','atendido') NOT NULL DEFAULT 'pendiente',
  id_trabajador INT,
  fecha_atencion DATETIME,
  PRIMARY KEY (id_mensaje),
  CONSTRAINT fk_mensajes_contacto_id_trabajador FOREIGN KEY (id_trabajador) REFERENCES usuarios(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Enlaces de recuperación de contraseña
CREATE TABLE tokens_recuperacion (
  id_token INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  fecha_expiracion DATETIME NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id_token),
  CONSTRAINT fk_tokens_recuperacion_id_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  Datos de ejemplo (para las capturas del reporte y pruebas)
--  NOTA: los hashes son de relleno; en el sistema real se generan con bcrypt.
-- =====================================================================
INSERT INTO roles (nombre, descripcion) VALUES
 ('Lector','Usuario registrado: descarga libros, reseña y guarda favoritos'),
 ('Autor','Publica libros (pasan por revisión) y consulta sus estadísticas'),
 ('Trabajador','Atiende mensajes, modera reseñas y revisa publicaciones'),
 ('Administrador','Gestiona usuarios, roles, catálogo, categorías y reportes');

INSERT INTO usuarios (nombre_completo, correo, contrasena_hash, id_rol, estado) VALUES
 ('Administrador LecturaLibre','admin@lecturalibre.com','$2b$10$hashDeEjemploAdmin',4,'activo'),
 ('Trabajador Demo','trabajador@lecturalibre.com','$2b$10$hashDeEjemploTrab',3,'activo'),
 ('Autor Demo','autor@lecturalibre.com','$2b$10$hashDeEjemploAutor',2,'activo'),
 ('Carlos Lector','carlos@correo.com','$2b$10$hashDeEjemploLector',1,'activo'),
 ('Lector Demo','lector@lecturalibre.com','$2b$10$hashDeEjemploLector2',1,'activo');

INSERT INTO autores (nombre_artistico, biografia, id_usuario) VALUES
 ('Gabriel García Márquez','Autor de ejemplo para el catálogo demo',NULL),
 ('Stephen Hawking','Autor de ejemplo para el catálogo demo',NULL),
 ('Yuval Noah Harari','Autor de ejemplo para el catálogo demo',NULL),
 ('Autor Demo','Autor registrado en la plataforma',3);

INSERT INTO editoriales (nombre) VALUES ('Independiente'),('Editorial Demo');

INSERT INTO categorias (nombre, descripcion) VALUES
 ('Ficción','Narrativa de ficción'),('Ciencia','Divulgación científica'),
 ('Historia','Historia y humanidades'),('Tecnología','Computación y tecnología'),('Novela','Novelas');

INSERT INTO libros (titulo, isbn, sinopsis, fecha_publicacion, archivo_pdf, precio, copias_disponibles, lecturas_acumuladas, estado, id_autor, id_editorial, id_revisor, fecha_revision) VALUES
 ('Cien Años de Soledad','978-0000000001','Obra del realismo mágico ambientada en el pueblo ficticio de Macondo.','1967-05-30','/pdf/cien-anos.pdf',0.00,50,142,'aprobado',1,1,2,NOW()),
 ('Breves Respuestas a las Grandes Preguntas','978-0000000002','Respuestas breves a grandes preguntas de la ciencia.','2018-10-16','/pdf/breves.pdf',0.00,50,89,'aprobado',2,1,2,NOW()),
 ('Sapiens: De animales a dioses','978-0000000003','Breve historia de la humanidad.','2011-01-01','/pdf/sapiens.pdf',0.00,50,210,'aprobado',3,2,2,NOW());

INSERT INTO libro_categoria (id_libro, id_categoria) VALUES (1,1),(1,5),(2,2),(3,3);

INSERT INTO resenas (id_libro, id_usuario, puntuacion, comentario, estado, id_moderador, fecha_moderacion) VALUES
 (1,4,5,'Excelente lectura, imprescindible para amantes de la literatura.','aprobada',2,NOW()),
 (2,5,4,'Muy claro y fácil de leer.','pendiente',NULL,NULL);

INSERT INTO favoritos (id_usuario, id_libro) VALUES (4,2),(5,1);
INSERT INTO carrito (id_usuario, id_libro) VALUES (5,3);

INSERT INTO pedidos (id_usuario, total, estado) VALUES (4,0.00,'descargado');
INSERT INTO detalle_pedido (id_pedido, id_libro, precio_unitario) VALUES (1,1,0.00);

-- Cada descarga es una fila nueva: así los reportes se filtran por fecha/mes sin acumular meses anteriores
INSERT INTO descargas (id_pedido, id_libro, fecha_descarga) VALUES
 (1,1,'2026-09-13 10:15:00'),
 (1,1,'2026-10-05 18:40:00');

INSERT INTO mensajes_contacto (nombre, correo, asunto, mensaje, estado, id_trabajador, fecha_atencion) VALUES
 ('Juan Pérez','juan@gmail.com','Consulta de stock','Hola, ¿cuándo estará disponible el libro Sapiens?','atendido',2,NOW());

-- =====================================================================
--  Vistas para la pantalla Panel Admin > Reportes
-- =====================================================================
CREATE VIEW v_descargas_por_categoria AS
SELECT c.nombre AS categoria,
       COUNT(DISTINCT lc.id_libro) AS total_libros,
       COUNT(d.id_descarga) AS total_descargas
FROM categorias c
LEFT JOIN libro_categoria lc ON lc.id_categoria = c.id_categoria
LEFT JOIN descargas d        ON d.id_libro = lc.id_libro
GROUP BY c.id_categoria, c.nombre;

CREATE VIEW v_libros_mas_descargados AS
SELECT l.id_libro, l.titulo, a.nombre_artistico AS autor,
       COUNT(d.id_descarga) AS descargas,
       l.lecturas_acumuladas AS lecturas,
       ROUND((SELECT AVG(r.puntuacion) FROM resenas r
              WHERE r.id_libro = l.id_libro AND r.estado='aprobada'),1) AS valoracion
FROM libros l
JOIN autores a ON a.id_autor = l.id_autor
LEFT JOIN descargas d ON d.id_libro = l.id_libro
WHERE l.estado = 'aprobado'
GROUP BY l.id_libro, l.titulo, a.nombre_artistico, l.lecturas_acumuladas
ORDER BY descargas DESC;

-- Ejemplo: reporte de descargas de un mes (fecha límite incluida) sin sumar otros meses
-- SELECT l.titulo, COUNT(*) AS descargas
-- FROM descargas d JOIN libros l ON l.id_libro = d.id_libro
-- WHERE d.fecha_descarga >= '2026-10-01' AND d.fecha_descarga < '2026-11-01'
-- GROUP BY l.id_libro, l.titulo ORDER BY descargas DESC;
