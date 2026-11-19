<?php
declare(strict_types=1);
/**
 * Ventas por mes con gráfica.
 *  - Autor: solo ve las ventas de sus libros.
 *  - Administrador: ve las de toda la plataforma.
 * Se cuentan los pedidos que no están cancelados (completado o descargado).
 */
require_once __DIR__ . '/inc/bootstrap.php';
$u = require_rol(['Administrador', 'Autor']);
$pdo = db();

// Si es autor, se filtra por su id_autor (0 si aún no tiene perfil = sin ventas)
$filtroAutor = '';
$params = [];
if (es_rol($u, 'Autor')) {
    $st = $pdo->prepare('SELECT id_autor FROM autores WHERE id_usuario = ?');
    $st->execute([$u['id']]);
    $filtroAutor = 'AND l.id_autor = ?';
    $params[] = (int)($st->fetchColumn() ?: 0);
}

// Periodo: últimos 6 o 12 meses
$meses = (int)($_GET['meses'] ?? 12) === 6 ? 6 : 12;
$desde = date('Y-m-01', strtotime('-' . ($meses - 1) . ' months'));

/* Ventas agrupadas por mes */
$st = $pdo->prepare("
    SELECT DATE_FORMAT(p.fecha_pedido, '%Y-%m') AS mes,
           COUNT(*) AS copias,
           COALESCE(SUM(dp.precio_unitario), 0) AS ingresos
      FROM detalle_pedido dp
      JOIN pedidos p ON p.id_pedido = dp.id_pedido
      JOIN libros l  ON l.id_libro = dp.id_libro
     WHERE p.estado <> 'cancelado' AND p.fecha_pedido >= ? $filtroAutor
     GROUP BY mes
");
$st->execute(array_merge([$desde], $params));
$porMes = [];
foreach ($st->fetchAll() as $fila) {
    $porMes[$fila['mes']] = $fila;
}

// Se llenan los meses sin ventas con 0 para que la gráfica no tenga huecos
$nombresMes = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$etiquetas = $copias = $ingresos = [];
for ($i = $meses - 1; $i >= 0; $i--) {
    $t = strtotime(date('Y-m-01') . " -$i months");
    $clave = date('Y-m', $t);
    $etiquetas[] = $nombresMes[(int)date('n', $t) - 1] . ' ' . date('y', $t);
    $copias[]    = (int)($porMes[$clave]['copias'] ?? 0);
    $ingresos[]  = (float)($porMes[$clave]['ingresos'] ?? 0);
}

/* Ventas por libro en el mismo periodo */
$st = $pdo->prepare("
    SELECT l.titulo, l.precio, l.estado,
           COUNT(dp.id_libro) AS copias,
           COALESCE(SUM(dp.precio_unitario), 0) AS ingresos,
           (SELECT COUNT(*) FROM descargas d WHERE d.id_libro = l.id_libro) AS descargas
      FROM libros l
      LEFT JOIN detalle_pedido dp ON dp.id_libro = l.id_libro
           AND dp.id_pedido IN (SELECT id_pedido FROM pedidos WHERE estado <> 'cancelado' AND fecha_pedido >= ?)
     WHERE l.estado <> 'baja' $filtroAutor
     GROUP BY l.id_libro
     ORDER BY ingresos DESC, copias DESC, l.titulo
");
$st->execute(array_merge([$desde], $params));
$porLibro = $st->fetchAll();

// Indicadores del periodo
$totalCopias   = array_sum($copias);
$totalIngresos = array_sum($ingresos);
$copiasMes     = end($copias);
$ingresosMes   = end($ingresos);

$titulo = 'Ventas';
$tab = 'ventas';
require __DIR__ . '/inc/header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h5 fw-bold mb-0"><?= es_rol($u, 'Autor') ? 'Ventas de mis libros' : 'Ventas de la plataforma' ?></h2>
        <small class="text-muted">Pedidos completados o descargados. Los libros gratuitos cuentan como copia con $0.</small>
    </div>
    <form method="get" class="no-print">
        <select name="meses" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="12" <?= $meses === 12 ? 'selected' : '' ?>>Últimos 12 meses</option>
            <option value="6" <?= $meses === 6 ? 'selected' : '' ?>>Últimos 6 meses</option>
        </select>
    </form>
</div>

<!-- Indicadores -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="kpi"><div class="num"><?= number_format($totalCopias) ?></div><div class="small text-muted">Copias en el periodo</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><div class="num">$<?= number_format($totalIngresos, 2) ?></div><div class="small text-muted">Ingresos en el periodo</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><div class="num"><?= number_format($copiasMes) ?></div><div class="small text-muted">Copias este mes</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><div class="num">$<?= number_format($ingresosMes, 2) ?></div><div class="small text-muted">Ingresos este mes</div></div></div>
</div>

<!-- Gráfica: barras = copias, línea = ingresos -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h3 class="h6 fw-bold">Ventas por mes</h3>
        <?php if ($totalCopias === 0): ?>
            <p class="text-muted small mb-2">Todavía no hay ventas en este periodo.</p>
        <?php endif; ?>
        <div style="position: relative; height: 320px;">
            <canvas id="graficaVentas" aria-label="Gráfica de ventas por mes" role="img"></canvas>
        </div>
    </div>
</div>

<!-- Detalle por libro -->
<h3 class="h6 fw-bold">Ventas por libro</h3>
<div class="table-responsive">
<table class="table table-sm table-bordered bg-white align-middle">
    <thead><tr><th>Libro</th><th>Precio</th><th>Estado</th><th>Copias</th><th>Ingresos</th><th>Descargas</th></tr></thead>
    <tbody>
    <?php if (!$porLibro): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No hay libros para mostrar.</td></tr>
    <?php endif; ?>
    <?php foreach ($porLibro as $l): ?>
        <tr>
            <td><?= e($l['titulo']) ?></td>
            <td><?= (float)$l['precio'] > 0 ? '$' . number_format((float)$l['precio'], 2) : 'Gratis' ?></td>
            <td><?= badge_estado($l['estado']) ?></td>
            <td><?= (int)$l['copias'] ?></td>
            <td>$<?= number_format((float)$l['ingresos'], 2) ?></td>
            <td><?= (int)$l['descargas'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Datos que manda PHP a la gráfica
new Chart(document.getElementById('graficaVentas'), {
    data: {
        labels: <?= json_encode($etiquetas) ?>,
        datasets: [
            { type: 'bar', label: 'Copias', data: <?= json_encode($copias) ?>,
              backgroundColor: '#e67e22', yAxisID: 'yCopias' },
            { type: 'line', label: 'Ingresos ($)', data: <?= json_encode($ingresos) ?>,
              borderColor: '#2c3e50', backgroundColor: '#2c3e50', tension: 0.3, yAxisID: 'yIngresos' }
        ]
    },
    options: {
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
            yCopias:   { position: 'left',  beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Copias' } },
            yIngresos: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Ingresos ($)' } }
        }
    }
});
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
