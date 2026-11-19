<?php
declare(strict_types=1);
/**
 * CRUD 1 — Libros (Crear y Actualizar).
 *  - Administrador: crea libros ya aprobados, elige autor y estado.
 *  - Autor: sube sus propios libros; quedan "pendiente" hasta que un Trabajador/Administrador los apruebe.
 *    Si un autor edita un libro aprobado, vuelve a "pendiente" para revisión.
 */
require_once __DIR__ . '/inc/bootstrap.php';
// Permite el acceso únicamente a Administradores y Autores
$u = require_rol(['Administrador', 'Autor']);
$pdo = db();
$esAdmin = es_rol($u, 'Administrador');

/* Autor logueado: obtener (o crear) su perfil en la tabla autores */
$miAutor = null;
if (!$esAdmin) {
    $st = $pdo->prepare('SELECT id_autor FROM autores WHERE id_usuario = ?');
    $st->execute([$u['id']]);
    $miAutor = $st->fetchColumn();
    // Si el usuario tiene rol Autor pero aún no tiene registro en la tabla autores, se genera automáticamente
    if ($miAutor === false) {
        $pdo->prepare('INSERT INTO autores (nombre_artistico, id_usuario) VALUES (?, ?)')
            ->execute([$u['nombre'], $u['id']]);
        $miAutor = (int)$pdo->lastInsertId();
    }
    $miAutor = (int)$miAutor;
}

/* Libro a editar (si viene ?id=) */
$id = (int)($_GET['id'] ?? $_POST['id_libro'] ?? 0);
$libro = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM libros WHERE id_libro = ?');
    $st->execute([$id]);
    $libro = $st->fetch();
    // Verifica que el libro exista y que un autor no pueda editar obras de otros autores
    if (!$libro || (!$esAdmin && (int)$libro['id_autor'] !== $miAutor)) {
        flash('danger', 'No tienes acceso a ese libro o no existe.');
        redirect('libros.php');
    }
}
$editando = $libro !== null;

/* Catálogos para los selects */
$categorias  = $pdo->query('SELECT id_categoria, nombre FROM categorias ORDER BY nombre')->fetchAll();
$editoriales = $pdo->query('SELECT id_editorial, nombre FROM editoriales ORDER BY nombre')->fetchAll();
$autores     = $esAdmin ? $pdo->query('SELECT id_autor, nombre_artistico FROM autores ORDER BY nombre_artistico')->fetchAll() : [];

// Si estamos editando, recupera las categorías actualmente asignadas al libro
$catsSel = [];
if ($editando) {
    $st = $pdo->prepare('SELECT id_categoria FROM libro_categoria WHERE id_libro = ?');
    $st->execute([$id]);
    $catsSel = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/* Valores del formulario */
$f = [
    'titulo'             => $libro['titulo'] ?? '',
    'isbn'               => $libro['isbn'] ?? '',
    'sinopsis'           => $libro['sinopsis'] ?? '',
    'fecha_publicacion'  => $libro['fecha_publicacion'] ?? '',
    'precio'             => $libro['precio'] ?? '0.00',
    'copias_disponibles' => $libro['copias_disponibles'] ?? 100,
    'id_editorial'       => $libro['id_editorial'] ?? '',
    'id_autor'           => $libro['id_autor'] ?? '',
    'estado'             => $libro['estado'] ?? 'aprobado',
    'num_paginas'        => $libro['num_paginas'] ?? '',
    'idioma'             => $libro['idioma'] ?? 'Español',
    'clasificacion_edad' => $libro['clasificacion_edad'] ?? 'adultos',
];
$errores = [];

// Procesa el guardado cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Captura y limpieza de todos los campos enviados
    $f['titulo']             = trim((string)($_POST['titulo'] ?? ''));
    $f['isbn']               = trim((string)($_POST['isbn'] ?? ''));
    $f['sinopsis']           = trim((string)($_POST['sinopsis'] ?? ''));
    $f['fecha_publicacion']  = trim((string)($_POST['fecha_publicacion'] ?? ''));
    $f['precio']             = trim((string)($_POST['precio'] ?? '0'));
    $f['copias_disponibles'] = trim((string)($_POST['copias_disponibles'] ?? '0'));
    $f['id_editorial']       = (string)($_POST['id_editorial'] ?? '');
    $f['num_paginas']        = trim((string)($_POST['num_paginas'] ?? ''));
    $f['idioma']             = trim((string)($_POST['idioma'] ?? ''));
    $f['clasificacion_edad'] = (string)($_POST['clasificacion_edad'] ?? 'adultos');
    $catsSel = array_values(array_unique(array_map('intval', (array)($_POST['categorias'] ?? []))));

    // --- Validaciones ---
    if ($f['titulo'] === '' || mb_strlen($f['titulo']) > 200) {
        $errores[] = 'El título es obligatorio (máximo 200 caracteres).';
    }
    if (mb_strlen($f['isbn']) > 20) {
        $errores[] = 'El ISBN no puede pasar de 20 caracteres.';
    }
    if ($f['fecha_publicacion'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $f['fecha_publicacion']);
        if (!$d || $d->format('Y-m-d') !== $f['fecha_publicacion']) {
            $errores[] = 'La fecha de publicación no es válida.';
        }
    }
    if (!is_numeric($f['precio']) || (float)$f['precio'] < 0 || (float)$f['precio'] > 99999999) {
        $errores[] = 'El precio debe ser un número mayor o igual a 0 (usa 0 para libros gratuitos).';
    }
    if (!ctype_digit($f['copias_disponibles'])) {
        $errores[] = 'Las copias disponibles deben ser un número entero mayor o igual a 0.';
    }
    if ($f['num_paginas'] !== '' && (!ctype_digit($f['num_paginas']) || (int)$f['num_paginas'] < 1 || (int)$f['num_paginas'] > 100000)) {
        $errores[] = 'El número de páginas debe ser un número entero mayor a 0.';
    }
    if ($f['idioma'] === '' || mb_strlen($f['idioma']) > 30) {
        $errores[] = 'El idioma es obligatorio (máximo 30 caracteres).';
    }
    if (!in_array($f['clasificacion_edad'], ['ninos', 'jovenes', 'adultos'], true)) {
        $errores[] = 'Selecciona una clasificación por edad válida.';
    }
    // Filtra los IDs de categoría para asegurarse de que existan en la base de datos
    $idsCatValidos = array_map(fn($c) => (int)$c['id_categoria'], $categorias);
    $catsSel = array_values(array_intersect($catsSel, $idsCatValidos));
    if (!$catsSel) {
        $errores[] = 'Selecciona al menos una categoría.';
    }

    $idEditorial = $f['id_editorial'] === '' ? null : (int)$f['id_editorial'];
    if ($idEditorial !== null && !in_array($idEditorial, array_map(fn($x) => (int)$x['id_editorial'], $editoriales), true)) {
        $errores[] = 'La editorial seleccionada no existe.';
    }

    // El autor debe declarar que la obra es suya (ver terminos.php)
    if (!$esAdmin && empty($_POST['declaro_derechos'])) {
        $errores[] = 'Debes declarar que tienes los derechos de la obra para publicarla.';
    }

    // Autor y estado según el rol
    if ($esAdmin) {
        $idAutor = (int)($_POST['id_autor'] ?? 0);
        if (!in_array($idAutor, array_map(fn($a) => (int)$a['id_autor'], $autores), true)) {
            $errores[] = 'Selecciona el autor del libro.';
        }
        $estado = (string)($_POST['estado'] ?? 'aprobado');
        if (!in_array($estado, ['pendiente', 'aprobado', 'rechazado', 'baja'], true)) {
            $estado = 'aprobado';
        }
    } else {
        $idAutor = $miAutor;
        if (!$editando) {
            $estado = 'pendiente';
        } else {
            // Si un autor edita un libro ya aprobado, pasa nuevamente a revisión
            $estado = $libro['estado'] === 'aprobado' ? 'pendiente' : $libro['estado'];
        }
    }
    $f['id_autor'] = $idAutor;
    $f['estado'] = $estado;

    // Archivos (solo si no hay errores previos, para no dejar archivos huérfanos)
    $rutaPdf = $rutaPortada = null;
    if (!$errores) {
        try {
            $rutaPdf = guardar_subida('archivo_pdf', ['application/pdf' => 'pdf'], MAX_PDF_BYTES, 'pdf');
            $rutaPortada = guardar_subida('portada', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], MAX_PORTADA_BYTES, 'portadas');
            if (!$editando && $rutaPdf === null) {
                $errores[] = 'Debes subir el archivo PDF del libro.';
            }
        } catch (RuntimeException $ex) {
            $errores[] = $ex->getMessage();
        }
    }

    if (!$errores) {
        $isbn = $f['isbn'] === '' ? null : $f['isbn'];
        $fecha = $f['fecha_publicacion'] === '' ? null : $f['fecha_publicacion'];
        $numPaginas = $f['num_paginas'] === '' ? null : (int)$f['num_paginas'];
        // Tamaño del archivo en MB: se calcula solo cuando se sube un PDF nuevo
        $tamanoMb = null;
        if ($rutaPdf !== null && is_file(__DIR__ . '/../' . $rutaPdf)) {
            $tamanoMb = round(filesize(__DIR__ . '/../' . $rutaPdf) / 1048576, 2);
        }
        try {
            // Inicia transacción para garantizar consistencia entre la tabla libros y libro_categoria
            $pdo->beginTransaction();
            if ($editando) {
                // COALESCE conserva los archivos y tamaño previos si no se subieron reemplazos
                $pdo->prepare(
                    'UPDATE libros SET titulo=?, isbn=?, sinopsis=?, fecha_publicacion=?, precio=?, copias_disponibles=?,
                            id_autor=?, id_editorial=?, estado=?,
                            num_paginas=?, idioma=?, clasificacion_edad=?,
                            archivo_pdf = COALESCE(?, archivo_pdf), portada = COALESCE(?, portada),
                            tamano_archivo_mb = COALESCE(?, tamano_archivo_mb)
                      WHERE id_libro=?'
                )->execute([$f['titulo'], $isbn, $f['sinopsis'] ?: null, $fecha, $f['precio'], (int)$f['copias_disponibles'],
                            $idAutor, $idEditorial, $estado,
                            $numPaginas, $f['idioma'], $f['clasificacion_edad'],
                            $rutaPdf, $rutaPortada, $tamanoMb, $id]);
                $idLibro = $id;
            } else {
                $pdo->prepare(
                    'INSERT INTO libros (titulo, isbn, sinopsis, fecha_publicacion, portada, archivo_pdf, precio,
                                         copias_disponibles, lecturas_acumuladas, estado, id_autor, id_editorial,
                                         num_paginas, idioma, tamano_archivo_mb, clasificacion_edad, fecha_alta)
                     VALUES (?,?,?,?,?,?,?,?,0,?,?,?,?,?,?,?,NOW())'
                )->execute([$f['titulo'], $isbn, $f['sinopsis'] ?: null, $fecha, $rutaPortada, $rutaPdf, $f['precio'],
                            (int)$f['copias_disponibles'], $estado, $idAutor, $idEditorial,
                            $numPaginas, $f['idioma'], $tamanoMb, $f['clasificacion_edad']]);
                $idLibro = (int)$pdo->lastInsertId();
            }

            // Categorías (relación n-n): se reemplazan por las seleccionadas
            $pdo->prepare('DELETE FROM libro_categoria WHERE id_libro = ?')->execute([$idLibro]);
            $ins = $pdo->prepare('INSERT INTO libro_categoria (id_libro, id_categoria) VALUES (?, ?)');
            foreach ($catsSel as $idCat) {
                $ins->execute([$idLibro, $idCat]);
            }
            $pdo->commit();

            // Si se reemplazaron archivos, borrar los anteriores
            if ($editando) {
                if ($rutaPdf)     borrar_subida($libro['archivo_pdf']);
                if ($rutaPortada) borrar_subida($libro['portada']);
            }
            flash('success', $editando ? 'Libro actualizado correctamente.' : (
                $estado === 'pendiente' ? 'Libro enviado a revisión. Aparecerá en el catálogo cuando sea aprobado.' : 'Libro creado correctamente.'));
            redirect('libros.php');
        } catch (PDOException $ex) {
            // Si falla la base de datos, revierte cambios y limpia los archivos recién subidos
            if ($pdo->inTransaction()) $pdo->rollBack();
            borrar_subida($rutaPdf);
            borrar_subida($rutaPortada);
            $errores[] = es_duplicado($ex) ? 'Ya existe un libro con ese ISBN.' : 'No se pudo guardar el libro. Inténtalo de nuevo.';
            if (!es_duplicado($ex)) error_log($ex->getMessage());
        }
    }
}

$titulo = $editando ? 'Editar libro' : 'Nuevo libro';
$tab = 'libros';
require __DIR__ . '/inc/header.php';
?>
<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 fw-bold mb-3"><?= $editando ? 'Editar libro #' . (int)$id : (!$esAdmin ? 'Publicar nueva obra' : 'Agregar libro') ?></h2>

        <!-- Listado de errores de validación si los hubo -->
        <?php if ($errores): ?>
            <div class="alert alert-danger"><ul class="mb-0">
                <?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <!-- Formulario multipart para permitir la carga del PDF y la portada -->
        <form method="post" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <?php if ($editando): ?><input type="hidden" name="id_libro" value="<?= (int)$id ?>"><?php endif; ?>

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Título del libro *</label>
                    <input name="titulo" class="form-control" maxlength="200" required value="<?= e($f['titulo']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">ISBN</label>
                    <input name="isbn" class="form-control" maxlength="20" value="<?= e($f['isbn']) ?>">
                </div>

                <!-- Campos exclusivos del Administrador para asignar autor y estado inicial -->
                <?php if ($esAdmin): ?>
                    <div class="col-md-6">
                        <label class="form-label">Autor *</label>
                        <select name="id_autor" class="form-select" required>
                            <option value="">— Selecciona —</option>
                            <?php foreach ($autores as $a): ?>
                                <option value="<?= (int)$a['id_autor'] ?>" <?= (int)$f['id_autor'] === (int)$a['id_autor'] ? 'selected' : '' ?>><?= e($a['nombre_artistico']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <?php foreach (['aprobado', 'pendiente', 'rechazado', 'baja'] as $es): ?>
                                <option value="<?= $es ?>" <?= $f['estado'] === $es ? 'selected' : '' ?>><?= ucfirst($es) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Selección múltiple de categorías literarias -->
                <div class="col-md-6">
                    <label class="form-label">Categorías * <small class="text-muted">(una o varias)</small></label>
                    <div class="border rounded p-2 bg-white" style="max-height: 150px; overflow:auto;">
                        <?php foreach ($categorias as $c): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="categorias[]" id="cat<?= (int)$c['id_categoria'] ?>"
                                       value="<?= (int)$c['id_categoria'] ?>" <?= in_array((int)$c['id_categoria'], $catsSel, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="cat<?= (int)$c['id_categoria'] ?>"><?= e($c['nombre']) ?></label>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$categorias): ?><span class="text-muted small">Primero crea categorías en la pestaña Categorías.</span><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Editorial</label>
                    <select name="id_editorial" class="form-select">
                        <option value="">— Sin editorial —</option>
                        <?php foreach ($editoriales as $ed): ?>
                            <option value="<?= (int)$ed['id_editorial'] ?>" <?= (string)$f['id_editorial'] === (string)$ed['id_editorial'] ? 'selected' : '' ?>><?= e($ed['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Metadatos comerciales y de inventario -->
                <div class="col-md-4">
                    <label class="form-label">Fecha de publicación</label>
                    <input type="date" name="fecha_publicacion" class="form-control" value="<?= e($f['fecha_publicacion']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Precio ($) <small class="text-muted">0 = gratis</small></label>
                    <input type="number" step="0.01" min="0" name="precio" class="form-control" value="<?= e($f['precio']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Copias disponibles</label>
                    <input type="number" min="0" name="copias_disponibles" class="form-control" value="<?= e($f['copias_disponibles']) ?>">
                </div>

                <!-- Ficha técnica de la obra -->
                <div class="col-md-4">
                    <label class="form-label">Número de páginas</label>
                    <input type="number" min="1" name="num_paginas" class="form-control" value="<?= e((string)$f['num_paginas']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Idioma *</label>
                    <input name="idioma" class="form-control" maxlength="30" value="<?= e((string)$f['idioma']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Clasificación por edad</label>
                    <select name="clasificacion_edad" class="form-select">
                        <?php foreach (['ninos' => 'Niños', 'jovenes' => 'Jóvenes', 'adultos' => 'Adultos'] as $val => $txt): ?>
                            <option value="<?= $val ?>" <?= $f['clasificacion_edad'] === $val ? 'selected' : '' ?>><?= $txt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Sinopsis / resumen</label>
                    <textarea name="sinopsis" rows="4" class="form-control"><?= e($f['sinopsis']) ?></textarea>
                </div>

                <!-- Carga de archivos digitales y vista previa de los actuales en modo edición -->
                <div class="col-md-6">
                    <label class="form-label">Archivo PDF <?= $editando ? '<small class="text-muted">(déjalo vacío para conservar el actual)</small>' : '*' ?></label>
                    <input type="file" name="archivo_pdf" class="form-control" accept="application/pdf">
                    <?php if ($editando && $libro['archivo_pdf']): ?>
                        <div class="form-text">Actual: <a href="../<?= e($libro['archivo_pdf']) ?>" target="_blank"><?= e(basename($libro['archivo_pdf'])) ?></a></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Portada <small class="text-muted">(JPG, PNG o WEBP)</small></label>
                    <input type="file" name="portada" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <?php if ($editando && $libro['portada']): ?>
                        <div class="form-text">Actual: <img src="../<?= e($libro['portada']) ?>" alt="" style="height: 60px; vertical-align: middle;"></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Declaración obligatoria de derechos de autor para publicaciones de autores -->
            <?php if (!$esAdmin): ?>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="declaro_derechos" value="1" id="declaro_derechos" required>
                    <label class="form-check-label small" for="declaro_derechos">
                        Declaro que soy el autor de esta obra o que tengo los derechos para publicarla, y acepto los
                        <a href="../terminos.php" target="_blank">Términos de uso</a>.
                    </label>
                </div>
                <p class="small text-muted mt-2 mb-0">Tu libro será revisado por un Trabajador o Administrador antes de aparecer en el catálogo.</p>
            <?php endif; ?>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-ll">Guardar<?= !$esAdmin ? ' y publicar' : '' ?></button>
                <a class="btn btn-outline-secondary" href="libros.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>