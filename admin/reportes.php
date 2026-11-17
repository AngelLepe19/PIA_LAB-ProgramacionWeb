<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_rol(['Administrador']);

$pdo = db();

/* Indicadores generales */
$kpi = [
    'Descargas totales'    => (int)$pdo->query('SELECT COUNT(*) FROM descargas')->fetchColumn(),
    'Libros registrados'   => (int)$pdo->query("SELECT COUNT(*) FROM libros WHERE estado <> 'baja'")->fetchColumn(),
    'Categorías activas'   => (int)$pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn(),
    'Usuarios registrados' => (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(),
];

/* Reportes: se leen directamente de las vistas SQL */
$porCategoria = $pdo->query('SELECT * FROM v_descargas_por_categoria')->fetchAll();
$masDescargados = $pdo->query('SELECT * FROM v_libros_mas_descargados')->fetchAll();

/** Dibuja una tabla con las columnas que traiga la vista (los nombres de columna se formatean solos). */
function tabla_desde_vista(array $filas, string $vacio): void
{
    if (!$filas) {
        echo '<p class="text-muted">' . e($vacio) . '</p>';
        return;
    }
    echo '<div class="table-responsive"><table class="table table-sm table-bordered bg-white align-middle"><thead><tr>';
    foreach (array_keys($filas[0]) as $col) {
        echo '<th>' . e(ucfirst(str_replace('_', ' ', $col))) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($filas as $fila) {
        echo '<tr>';
        foreach ($fila as $valor) {
            echo '<td>' . ($valor === null ? '<span class="text-muted">—</span>' : e((string)$valor)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

$titulo = 'Reportes';
$tab = 'reportes';
require __DIR__ . '/inc/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h5 fw-bold mb-0">Reporte de Descargas y Plataforma</h2>
        <small class="text-muted">Fecha de corte y de generación: <?= date('d/m/Y H:i') ?></small>
    </div>
    <button class="btn btn-oscuro btn-sm no-print" onclick="window.print()">🖨 Imprimir reporte</button>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($kpi as $etiqueta => $valor): ?>
        <div class="col-6 col-md-3">
            <div class="kpi"><div class="num"><?= number_format($valor) ?></div><div class="small text-muted"><?= e($etiqueta) ?></div></div>
        </div>
    <?php endforeach; ?>
</div>

<h3 class="h6 fw-bold">Descargas totales por categoría</h3>
<?php tabla_desde_vista($porCategoria, 'Aún no hay categorías con información.'); ?>

<h3 class="h6 fw-bold mt-4">Libros más descargados / leídos</h3>
<?php tabla_desde_vista($masDescargados, 'Aún no hay libros con información.'); ?>

<?php require __DIR__ . '/inc/footer.php'; ?>