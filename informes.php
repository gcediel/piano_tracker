<?php
require_once 'config/database.php';
$pageTitle = 'Informes - Piano Tracker';
$db = getDB();

// Filtros
$periodo = $_GET['periodo'] ?? 'mes';
$anio = $_GET['anio'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');

// Nombres de meses en castellano
$mesesCastellano = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];

// Calcular fechas según periodo
switch ($periodo) {
    case 'dia':
        $fechaInicio = date('Y-m-d');
        $fechaFin = date('Y-m-d');
        break;
    case 'semana':
        $fechaInicio = date('Y-m-d', strtotime('monday this week'));
        $fechaFin = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mes':
        $fechaInicio = "$anio-$mes-01";
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));
        break;
    case 'anio':
        $fechaInicio = "$anio-01-01";
        $fechaFin = "$anio-12-31";
        break;
    default:
        $fechaInicio = date('Y-m-01');
        $fechaFin = date('Y-m-t');
}

// Obtener tiempo por actividad
$stmt = $db->prepare("
    SELECT 
        a.tipo,
        SUM(a.tiempo_segundos) as tiempo_total,
        COUNT(DISTINCT a.id) as num_actividades
    FROM actividades a
    JOIN sesiones s ON a.sesion_id = s.id
    WHERE s.fecha BETWEEN :fecha_inicio AND :fecha_fin
    GROUP BY a.tipo
    ORDER BY tiempo_total DESC
");
$stmt->execute([
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin
]);
$tiempoActividades = $stmt->fetchAll();

// Para repertorio, obtener el número de piezas practicadas por separado
$stmt = $db->prepare("
    SELECT COUNT(*) as num_piezas
    FROM fallos f
    JOIN actividades a ON f.actividad_id = a.id
    JOIN sesiones s ON a.sesion_id = s.id
    WHERE a.tipo = 'repertorio'
    AND s.fecha BETWEEN :fecha_inicio AND :fecha_fin
");
$stmt->execute([
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin
]);
$numPiezasRepertorio = $stmt->fetch()['num_piezas'] ?? 0;

// Procesar para mostrar el conteo correcto según el tipo
$tiempoPorActividad = [];
foreach ($tiempoActividades as $act) {
    $tiempoPorActividad[] = [
        'tipo' => $act['tipo'],
        'tiempo_total' => $act['tiempo_total'],
        'num_veces' => $act['tipo'] === 'repertorio' ? $numPiezasRepertorio : $act['num_actividades']
    ];
}

// Obtener fallos por pieza con media simple: total fallos / días practicados
$stmt = $db->prepare("
    SELECT 
        p.id,
        p.compositor,
        p.titulo,
        p.ponderacion,
        SUM(f.cantidad) as total_fallos,
        COUNT(DISTINCT DATE(f.fecha_registro)) as dias_practicados,
        ROUND(
            SUM(f.cantidad) / NULLIF(COUNT(DISTINCT DATE(f.fecha_registro)), 0),
        2) as media_fallos_dia
    FROM piezas p
    JOIN fallos f ON p.id = f.pieza_id
    JOIN actividades a ON f.actividad_id = a.id
    JOIN sesiones s ON a.sesion_id = s.id
    WHERE s.fecha BETWEEN :fecha_inicio AND :fecha_fin
    GROUP BY p.id
    ORDER BY total_fallos DESC
");
$stmt->execute([
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin
]);
$fallosPorPieza = $stmt->fetchAll();

// Obtener tiempo total del periodo
$stmt = $db->prepare("
    SELECT SUM(tiempo_segundos) as total
    FROM actividades a
    JOIN sesiones s ON a.sesion_id = s.id
    WHERE s.fecha BETWEEN :fecha_inicio AND :fecha_fin
");
$stmt->execute([
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin
]);
$tiempoTotal = $stmt->fetch()['total'] ?? 0;

// Obtener número de sesiones
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM sesiones
    WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin AND estado = 'finalizada'
");
$stmt->execute([
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin
]);
$numSesiones = $stmt->fetch()['total'] ?? 0;

// Tiempo por día (para gráfico de tendencia)
// Obtener tiempo por día y por tipo de actividad
$stmt = $db->prepare("
    SELECT 
        s.fecha,
        s.id as sesion_id,
        SUM(a.tiempo_segundos) as tiempo_total,
        SUM(CASE WHEN a.tipo = 'calentamiento' THEN a.tiempo_segundos ELSE 0 END) as tiempo_calentamiento,
        SUM(CASE WHEN a.tipo = 'tecnica' THEN a.tiempo_segundos ELSE 0 END) as tiempo_tecnica,
        SUM(CASE WHEN a.tipo = 'practica' THEN a.tiempo_segundos ELSE 0 END) as tiempo_practica,
        SUM(CASE WHEN a.tipo = 'repertorio' THEN a.tiempo_segundos ELSE 0 END) as tiempo_repertorio,
        SUM(CASE WHEN a.tipo = 'improvisacion' THEN a.tiempo_segundos ELSE 0 END) as tiempo_improvisacion,
        SUM(CASE WHEN a.tipo = 'composicion' THEN a.tiempo_segundos ELSE 0 END) as tiempo_composicion,
        (SELECT ROUND(AVG(f.cantidad), 2)
         FROM fallos f 
         JOIN actividades a2 ON f.actividad_id = a2.id 
         WHERE a2.sesion_id = s.id 
         AND a2.tipo = 'repertorio') as media_fallos_repertorio
    FROM sesiones s
    LEFT JOIN actividades a ON s.id = a.sesion_id
    WHERE s.fecha BETWEEN :fecha_inicio AND :fecha_fin
    GROUP BY s.fecha, s.id
    ORDER BY s.fecha
");
$stmt->execute([
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin
]);
$tiempoPorDia = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- CSS de DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">

<div class="card">
    <h2>Filtros de informe</h2>
    
    <form method="GET" class="form-inline">
        <div class="form-group">
            <label for="periodo">Periodo</label>
            <select name="periodo" id="periodo" onchange="toggleFiltros()">
                <option value="dia" <?php echo $periodo === 'dia' ? 'selected' : ''; ?>>Hoy</option>
                <option value="semana" <?php echo $periodo === 'semana' ? 'selected' : ''; ?>>Esta semana</option>
                <option value="mes" <?php echo $periodo === 'mes' ? 'selected' : ''; ?>>Mes específico</option>
                <option value="anio" <?php echo $periodo === 'anio' ? 'selected' : ''; ?>>Año específico</option>
            </select>
        </div>
        
        <div class="form-group" id="filtroMes" style="<?php echo $periodo !== 'mes' ? 'display:none;' : ''; ?>">
            <label for="mes">Mes</label>
            <select name="mes" id="mes">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>" 
                        <?php echo $mes == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                    <?php echo $mesesCastellano[$i]; ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="form-group" id="filtroAnio" style="<?php echo !in_array($periodo, ['mes', 'anio']) ? 'display:none;' : ''; ?>">
            <label for="anio">Año</label>
            <select name="anio" id="anio">
                <?php for ($i = date('Y'); $i >= 2020; $i--): ?>
                <option value="<?php echo $i; ?>" <?php echo $anio == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Generar informe</button>
        </div>
    </form>
    
    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #e9ecef;">
        <a href="informe_mensual.php" class="btn btn-success" style="font-size: 1.05rem; padding: 0.7rem 1.5rem;">
            Informes mensuales detallados
        </a>
    </div>
</div>

<div class="card">
    <h2>Resumen del periodo: <?php echo date('d/m/Y', strtotime($fechaInicio)); ?> - <?php echo date('d/m/Y', strtotime($fechaFin)); ?></h2>
    
    <div class="stats-grid">
        <div class="stat-box">
            <h3><?php echo formatearTiempo($tiempoTotal); ?></h3>
            <p>Tiempo total practicado</p>
        </div>
        <div class="stat-box">
            <h3><?php echo $numSesiones; ?></h3>
            <p>Sesiones completadas</p>
        </div>
        <div class="stat-box">
            <h3><?php echo $numSesiones > 0 ? formatearTiempo($tiempoTotal / $numSesiones) : '00:00:00'; ?></h3>
            <p>Promedio por sesión</p>
        </div>
    </div>
</div>

<div class="card">
    <h2>Tiempo por actividad</h2>
    
    <?php if (empty($tiempoPorActividad)): ?>
        <p>No hay datos de actividades en este periodo.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Actividad</th>
                    <th>Tiempo total</th>
                    <th>Veces practicada</th>
                    <th>% del total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tiempoPorActividad as $act): ?>
                <tr>
                    <td><?php echo getNombreActividad($act['tipo']); ?></td>
                    <td><?php echo formatearTiempo($act['tiempo_total']); ?></td>
                    <td><?php echo $act['num_veces']; ?></td>
                    <td><?php echo $tiempoTotal > 0 ? number_format(($act['tiempo_total'] / $tiempoTotal) * 100, 1) : 0; ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Práctica de piezas del repertorio</h2>
    
    <?php if (empty($fallosPorPieza)): ?>
        <p>No hay datos de fallos registrados en este periodo.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table id="tablaPracticaPiezas" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>Compositor</th>
                        <th>Título</th>
                        <th>Días practicados</th>
                        <th>Media fallos/día</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fallosPorPieza as $pieza): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pieza['compositor']); ?></td>
                        <td><?php echo htmlspecialchars($pieza['titulo']); ?></td>
                        <td style="text-align: center;"><?php echo $pieza['dias_practicados']; ?></td>
                        <td style="text-align: center;"><?php echo number_format($pieza['media_fallos_dia'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Práctica diaria</h2>
    
    <?php if (empty($tiempoPorDia)): ?>
        <p>No hay datos de práctica en este periodo.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table id="tablaPracticaDiaria" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Calentamiento</th>
                        <th>Técnica</th>
                        <th>Práctica</th>
                        <th>Repertorio</th>
                        <th>Improvisación</th>
                        <th>Composición</th>
                        <th>Media fallos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tiempoPorDia as $dia): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($dia['fecha'])); ?></td>
                        <td><?php echo formatearTiempo($dia['tiempo_total']); ?></td>
                        <td><?php echo $dia['tiempo_calentamiento'] > 0 ? formatearTiempo($dia['tiempo_calentamiento']) : '-'; ?></td>
                        <td><?php echo $dia['tiempo_tecnica'] > 0 ? formatearTiempo($dia['tiempo_tecnica']) : '-'; ?></td>
                        <td><?php echo $dia['tiempo_practica'] > 0 ? formatearTiempo($dia['tiempo_practica']) : '-'; ?></td>
                        <td><?php echo $dia['tiempo_repertorio'] > 0 ? formatearTiempo($dia['tiempo_repertorio']) : '-'; ?></td>
                        <td><?php echo $dia['tiempo_improvisacion'] > 0 ? formatearTiempo($dia['tiempo_improvisacion']) : '-'; ?></td>
                        <td><?php echo $dia['tiempo_composicion'] > 0 ? formatearTiempo($dia['tiempo_composicion']) : '-'; ?></td>
                        <td style="text-align: center;">
                            <?php 
                            if ($dia['media_fallos_repertorio'] !== null) {
                                echo number_format($dia['media_fallos_repertorio'], 2);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleFiltros() {
    const periodo = document.getElementById('periodo').value;
    const filtroMes = document.getElementById('filtroMes');
    const filtroAnio = document.getElementById('filtroAnio');
    
    filtroMes.style.display = periodo === 'mes' ? 'block' : 'none';
    filtroAnio.style.display = (periodo === 'mes' || periodo === 'anio') ? 'block' : 'none';
}
</script>

<!-- JS de DataTables -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    // Inicializar tabla de Práctica de piezas
    $('#tablaPracticaPiezas').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todas"]],
        "order": [[3, "desc"]]  // Ordenar por media de fallos descendente
    });
    
    // Inicializar tabla de Práctica diaria
    $('#tablaPracticaDiaria').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todas"]],
        "order": [[0, "desc"]]  // Ordenar por fecha descendente (más reciente primero)
    });
});
</script>

<?php include 'includes/footer.php'; ?>
