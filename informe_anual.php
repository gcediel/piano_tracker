<?php
// ACTIVAR ERRORES PARA DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'config/database.php';
$pageTitle = 'Informe Anual - Piano Tracker';
$db = getDB();

// ============================================
// FUNCIONES AUXILIARES
// ============================================

// Función para obtener nombre del mes en español (abreviado)
function getNombreMesCorto($numeroMes) {
    $meses = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
    ];
    return $meses[(int)$numeroMes] ?? '?';
}

// Función para formatear tiempo breve
function formatearTiempoBreve($segundos) {
    if ($segundos == 0) return '-';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    if ($horas > 0) {
        return sprintf("%d:%02d", $horas, $minutos);
    }
    return sprintf("%d'", $minutos);
}

// Función para obtener color según media de fallos
function getColorFallos($media) {
    if ($media === null || $media === 0) return '#2E5F8A';
    if ($media < 0.5) return '#2E5F8A';
    if ($media < 1.5) return '#4A7BA7';
    if ($media < 2.5) return '#A3C1DA';
    if ($media < 3.5) return '#D4E89E';
    if ($media <= 5) return '#9B9B9B';
    return '#E57373';
}

// Función para obtener color de texto según media de fallos
function getColorTextoFallos($media) {
    if ($media === null || $media === 0) return 'white';
    if ($media < 0.5) return 'white';
    if ($media < 1.5) return 'white';
    if ($media < 2.5) return 'black';
    if ($media < 3.5) return 'black';
    if ($media <= 5) return 'white';
    return 'white';
}

// ============================================
// PROCESAMIENTO DE PARÁMETROS
// ============================================

$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
if ($anio < 2000 || $anio > 2100) $anio = (int)date('Y');

$fechaInicio = "$anio-01-01";
$fechaFin = "$anio-12-31";
$todosMeses = range(1, 12);

// ============================================
// CONSULTAS DE DATOS
// ============================================

// Obtener actividades por tipo y mes
$stmt = $db->prepare("
    SELECT 
        a.tipo,
        MONTH(s.fecha) as mes,
        SUM(a.tiempo_segundos) as tiempo_total,
        COUNT(DISTINCT DATE(s.fecha)) as dias_practicados
    FROM actividades a
    JOIN sesiones s ON a.sesion_id = s.id
    WHERE s.fecha BETWEEN :fecha_inicio AND :fecha_fin
    GROUP BY a.tipo, MONTH(s.fecha)
");
$stmt->execute([':fecha_inicio' => $fechaInicio, ':fecha_fin' => $fechaFin]);
$datosActividades = $stmt->fetchAll();

// Organizar datos de actividades
$tiposActividad = ['calentamiento', 'tecnica', 'practica', 'repertorio', 'improvisacion', 'composicion'];
$actividades = [];

foreach ($tiposActividad as $tipo) {
    $tiempos_por_mes = array_fill(1, 12, 0);
    $dias_por_mes = array_fill(1, 12, 0);
    $actividades[$tipo] = [
        'tipo' => $tipo,
        'nombre' => getNombreActividad($tipo),
        'tiempos_por_mes' => $tiempos_por_mes,
        'dias_por_mes' => $dias_por_mes,
        'total_tiempo' => 0,
        'total_dias' => 0
    ];
}

foreach ($datosActividades as $dato) {
    $tipo = $dato['tipo'];
    $mes = (int)$dato['mes'];
    if (isset($actividades[$tipo])) {
        $actividades[$tipo]['tiempos_por_mes'][$mes] = (int)$dato['tiempo_total'];
        $actividades[$tipo]['dias_por_mes'][$mes] = (int)$dato['dias_practicados'];
        $actividades[$tipo]['total_tiempo'] += (int)$dato['tiempo_total'];
        $actividades[$tipo]['total_dias'] += (int)$dato['dias_practicados'];
    }
}

// Calcular totales
$tiempoTotalAnio = array_sum(array_column($actividades, 'total_tiempo'));

$stmt = $db->prepare("SELECT COUNT(DISTINCT DATE(s.fecha)) as total_dias FROM sesiones s WHERE s.fecha BETWEEN :fi AND :ff");
$stmt->execute([':fi' => $fechaInicio, ':ff' => $fechaFin]);
$diasTotalAnio = (int)$stmt->fetch()['total_dias'];

// Obtener piezas practicadas con media de fallos por mes
$stmt = $db->prepare("
    SELECT 
        p.id, p.compositor, p.titulo, p.libro, p.grado, p.instrumento, p.tempo, p.ponderacion,
        MONTH(s.fecha) as mes,
        SUM(f.cantidad) as total_fallos,
        COUNT(DISTINCT DATE(f.fecha_registro)) as dias_practicados
    FROM piezas p
    JOIN fallos f ON p.id = f.pieza_id
    JOIN actividades a ON f.actividad_id = a.id
    JOIN sesiones s ON a.sesion_id = s.id
    WHERE s.fecha BETWEEN :fi AND :ff AND a.tipo = 'repertorio'
    GROUP BY p.id, MONTH(s.fecha)
    ORDER BY p.libro, p.grado, p.compositor, p.titulo
");
$stmt->execute([':fi' => $fechaInicio, ':ff' => $fechaFin]);
$datosPiezas = $stmt->fetchAll();

// Organizar datos de piezas
$piezas = [];
foreach ($datosPiezas as $dato) {
    if (!isset($piezas[$dato['id']])) {
        $piezas[$dato['id']] = [
            'compositor' => $dato['compositor'],
            'titulo' => $dato['titulo'],
            'libro' => $dato['libro'],
            'grado' => $dato['grado'],
            'instrumento' => $dato['instrumento'],
            'tempo' => $dato['tempo'],
            'ponderacion' => $dato['ponderacion'],
            'medias_por_mes' => array_fill(1, 12, null),
            'dias_practicados_anio' => 0,
            'total_fallos_anio' => 0
        ];
    }
    
    $mes = (int)$dato['mes'];
    $totalFallos = (int)$dato['total_fallos'];
    $diasPracticados = (int)$dato['dias_practicados'];
    
    $piezas[$dato['id']]['medias_por_mes'][$mes] = $diasPracticados > 0 ? $totalFallos / $diasPracticados : 0;
    $piezas[$dato['id']]['dias_practicados_anio'] += $diasPracticados;
    $piezas[$dato['id']]['total_fallos_anio'] += $totalFallos;
}

// Calcular media anual
foreach ($piezas as &$pieza) {
    $pieza['media_fallos_anio'] = $pieza['dias_practicados_anio'] > 0 ? 
        $pieza['total_fallos_anio'] / $pieza['dias_practicados_anio'] : 0;
}
unset($pieza);

// Calcular distribución por categorías
$categorias = [
    'excelente' => ['count' => 0, 'color' => '#2E5F8A', 'label' => 'Excelente (< 0.5)'],
    'muy_bien' => ['count' => 0, 'color' => '#4A7BA7', 'label' => 'Muy bien (0.5-1.5)'],
    'bien' => ['count' => 0, 'color' => '#A3C1DA', 'label' => 'Bien (1.5-2.5)'],
    'aceptable' => ['count' => 0, 'color' => '#D4E89E', 'label' => 'Aceptable (2.5-3.5)'],
    'mejorable' => ['count' => 0, 'color' => '#9B9B9B', 'label' => 'Mejorable (3.5-5)'],
    'atencion' => ['count' => 0, 'color' => '#E57373', 'label' => 'Atención (> 5)']
];

foreach ($piezas as $pieza) {
    $media = $pieza['media_fallos_anio'];
    if ($media < 0.5) $categorias['excelente']['count']++;
    elseif ($media < 1.5) $categorias['muy_bien']['count']++;
    elseif ($media < 2.5) $categorias['bien']['count']++;
    elseif ($media < 3.5) $categorias['aceptable']['count']++;
    elseif ($media <= 5) $categorias['mejorable']['count']++;
    else $categorias['atencion']['count']++;
}

$totalPiezas = count($piezas);

include 'includes/header.php';
?>

<style>
.container { max-width: none !important; width: 100% !important; padding: 0 10px !important; }
.card { max-width: none !important; }
* { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }

@media print {
    @page { size: landscape; margin: 1cm; }
    body { font-size: 9pt; background: white; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    table, tr, td, th { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    header, footer, .nav-menu, .btn, .no-print { display: none !important; }
    .card { box-shadow: none; page-break-inside: avoid; margin-bottom: 1rem; }
    table { page-break-inside: auto; font-size: 8pt; }
    .titulo-informe { text-align: center; margin-bottom: 15px; }
}

.tabla-horizontal { width: 100%; overflow-x: auto; margin-top: 1rem; }
.tabla-horizontal table { border-collapse: collapse; min-width: 100%; font-size: 0.85rem; }
.tabla-horizontal th { background: var(--primary); color: white; padding: 0.4rem 0.3rem; text-align: center; 
                       font-size: 0.75rem; white-space: nowrap; position: sticky; top: 0; z-index: 10; }
.tabla-horizontal td { padding: 0.3rem 0.2rem; text-align: center; border: 1px solid #ddd; font-size: 0.75rem; }
.col-fija-header { background: var(--primary) !important; min-width: 60px; }
.col-fija { background: #f8f9fa; font-weight: 500; }
.col-estadistica { background: var(--primary) !important; font-weight: bold; min-width: 50px; }
.celda-fallo-excelente { background: #2E5F8A; color: white; font-weight: bold; }
.celda-fallo-muy-bien { background: #4A7BA7; color: white; font-weight: bold; }
.celda-fallo-bien { background: #A3C1DA; color: black; font-weight: bold; }
.celda-fallo-aceptable { background: #D4E89E; color: black; font-weight: bold; }
.celda-fallo-mejorable { background: #9B9B9B; color: white; font-weight: bold; }
.celda-fallo-atencion { background: #E57373; color: white; font-weight: bold; }
.celda-vacia { background: #f0f0f0; color: #999; }
</style>

<div style="display: flex; gap: 1rem; margin-bottom: 1rem;" class="no-print">
    <button onclick="window.print()" class="btn btn-primary btn-imprimir">🖨️ Imprimir / Guardar PDF</button>
    <a href="informes.php" class="btn btn-secondary">← Volver a Informes</a>
</div>

<div class="card no-print">
    <h2>Seleccionar año para informe</h2>
    <form method="GET" class="form-inline">
        <div class="form-group">
            <label for="anio">Año</label>
            <select name="anio" id="anio">
                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $anio ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Generar informe</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="titulo-informe">
        <h1>📊 Informe de Práctica de Piano</h1>
        <h2>Año <?php echo $anio; ?></h2>
        <p style="color: #666;">Tiempo total del año: <strong><?php echo formatearTiempoBreve($tiempoTotalAnio); ?></strong></p>
    </div>
</div>

<div class="titulo-informe" style="display: none;">
    <h1>Piano Tracker - Informe Anual <?php echo $anio; ?></h1>
    <p>Generado el <?php echo date('d/m/Y H:i'); ?></p>
</div>

<div class="card">
    <h3>Tiempo de práctica por tipo de actividad</h3>
    <div class="tabla-horizontal">
        <table>
            <thead>
                <tr>
                    <th class="col-fija-header">Actividad</th>
                    <?php foreach ($todosMeses as $mes): ?>
                    <th><?php echo getNombreMesCorto($mes); ?></th>
                    <?php endforeach; ?>
                    <th class="col-estadistica">Días</th>
                    <th class="col-estadistica">Total</th>
                    <th class="col-estadistica">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($actividades as $act): ?>
                <tr>
                    <td class="col-fija" style="text-align: left; padding-left: 0.5rem;">
                        <?php echo htmlspecialchars($act['nombre']); ?>
                    </td>
                    <?php foreach ($todosMeses as $mes): 
                        $tiempo = $act['tiempos_por_mes'][$mes];
                        $dias = $act['dias_por_mes'][$mes];
                    ?>
                    <td class="<?php echo $tiempo > 0 ? '' : 'celda-vacia'; ?>">
                        <?php echo $tiempo > 0 ? formatearTiempoBreve($tiempo) : '-'; ?>
                        <?php if ($dias > 0): ?>
                        <br><small style="font-size: 0.65rem; opacity: 0.7;">(<?php echo $dias; ?>d)</small>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                    <td style="background: #e8f5e9; font-weight: bold;"><?php echo $act['total_dias']; ?></td>
                    <td style="background: #e3f2fd; font-weight: bold;"><?php echo formatearTiempoBreve($act['total_tiempo']); ?></td>
                    <td style="background: #fff3e0; font-weight: bold;">
                        <?php echo $tiempoTotalAnio > 0 ? round(($act['total_tiempo'] / $tiempoTotalAnio) * 100, 1) : 0; ?>%
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr style="background: #f5f5f5; font-weight: bold; border-top: 3px solid var(--primary);">
                    <td class="col-fija" style="text-align: left; padding-left: 0.5rem;">TOTAL</td>
                    <?php foreach ($todosMeses as $mes): 
                        $tiempoMes = 0;
                        foreach ($actividades as $act) $tiempoMes += $act['tiempos_por_mes'][$mes];
                    ?>
                    <td><?php echo $tiempoMes > 0 ? formatearTiempoBreve($tiempoMes) : '-'; ?></td>
                    <?php endforeach; ?>
                    <td style="background: #c8e6c9;"><?php echo $diasTotalAnio; ?></td>
                    <td style="background: #bbdefb;"><?php echo formatearTiempoBreve($tiempoTotalAnio); ?></td>
                    <td style="background: #ffe0b2;">100%</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Gráfico de tarta: Distribución de tiempo por actividad -->
    <div style="margin-top: 2rem; padding: 1.5rem; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h4 style="margin-top: 0; margin-bottom: 1rem; text-align: center;">📊 Distribución de Tiempo por Tipo de Actividad</h4>
        
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 2rem;">
            <!-- Gráfico de tarta -->
            <div style="position: relative; width: 300px; height: 300px;">
                <canvas id="chartActividades" width="300" height="300"></canvas>
            </div>
            
            <!-- Leyenda del gráfico -->
            <div style="flex: 1; min-width: 250px;">
                <div style="display: grid; gap: 0.5rem;">
                    <?php 
                    $coloresActividades = [
                        'calentamiento' => '#FF6B6B',
                        'tecnica' => '#4ECDC4',
                        'practica' => '#45B7D1',
                        'repertorio' => '#FFA07A',
                        'improvisacion' => '#98D8C8',
                        'composicion' => '#C7CEEA'
                    ];
                    
                    foreach ($actividades as $act):
                        if ($act['total_tiempo'] == 0) continue;
                        $porcentaje = $tiempoTotalAnio > 0 ? round(($act['total_tiempo'] / $tiempoTotalAnio) * 100, 1) : 0;
                    ?>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 20px; height: 20px; background: <?php echo $coloresActividades[$act['tipo']]; ?>; border: 1px solid #999; border-radius: 3px;"></div>
                        <span style="flex: 1;">
                            <strong><?php echo htmlspecialchars($act['nombre']); ?>:</strong> 
                            <?php echo formatearTiempoBreve($act['total_tiempo']); ?> (<?php echo $porcentaje; ?>%)
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 2px solid #ddd;">
                        <strong>Total: <?php echo formatearTiempoBreve($tiempoTotalAnio); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Crear gráfico de tarta de actividades
    (function() {
        const canvas = document.getElementById('chartActividades');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const centerX = 150;
        const centerY = 150;
        const radius = 120;
        
        // Datos del gráfico
        const data = [
            <?php 
            $dataPointsAct = [];
            foreach ($actividades as $act) {
                if ($act['total_tiempo'] > 0) {
                    $color = $coloresActividades[$act['tipo']];
                    $nombre = htmlspecialchars($act['nombre']);
                    $dataPointsAct[] = "{value: {$act['total_tiempo']}, color: '$color', label: '$nombre'}";
                }
            }
            echo implode(",\n            ", $dataPointsAct);
            ?>
        ];
        
        const total = data.reduce((sum, item) => sum + item.value, 0);
        
        // Dibujar sectores
        let currentAngle = -Math.PI / 2; // Empezar desde arriba
        
        data.forEach(item => {
            const sliceAngle = (item.value / total) * 2 * Math.PI;
            
            // Dibujar sector
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
            ctx.closePath();
            ctx.fillStyle = item.color;
            ctx.fill();
            
            // Borde blanco entre sectores
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();
            
            currentAngle += sliceAngle;
        });
        
        // Círculo blanco en el centro para efecto "donut"
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius * 0.5, 0, 2 * Math.PI);
        ctx.fillStyle = '#fff';
        ctx.fill();
        
        // Texto central - convertir segundos totales a horas
        const horas = Math.floor(total / 3600);
        const minutos = Math.floor((total % 3600) / 60);
        
        ctx.fillStyle = '#333';
        ctx.font = 'bold 24px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        
        if (horas > 0) {
            ctx.fillText(horas + 'h ' + minutos + 'm', centerX, centerY - 5);
        } else {
            ctx.fillText(minutos + ' min', centerX, centerY - 5);
        }
        
        ctx.font = '12px Arial';
        ctx.fillText('total', centerX, centerY + 15);
    })();
    </script>
</div>

<?php if (!empty($piezas)): ?>
<div class="card">
    <h3>Piezas de Repertorio</h3>
    <div class="tabla-horizontal">
        <table>
            <thead>
                <tr>
                    <th class="col-fija-header">Libro</th>
                    <th class="col-fija-header">Gr</th>
                    <th class="col-fija-header">Compositor</th>
                    <th class="col-fija-header">Nombre</th>
                    <th class="col-fija-header">Tempo</th>
                    <th class="col-fija-header">Instr</th>
                    <th class="col-fija-header">Pond</th>
                    <?php foreach ($todosMeses as $mes): ?>
                    <th><?php echo getNombreMesCorto($mes); ?></th>
                    <?php endforeach; ?>
                    <th class="col-estadistica">Días</th>
                    <th class="col-estadistica">Media</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($piezas as $pieza): ?>
                <tr style="background: <?php echo getColorFallos($pieza['media_fallos_anio']); ?>; 
                           color: <?php echo getColorTextoFallos($pieza['media_fallos_anio']); ?>">
                    <td class="col-fija" style="font-size: 0.75rem; white-space: normal; color: black;">
                        <?php echo htmlspecialchars($pieza['libro'] ?? '-'); ?>
                    </td>
                    <td class="col-fija" style="text-align: center; white-space: normal; color: black;">
                        <?php echo $pieza['grado'] ?? '-'; ?>
                    </td>
                    <td class="col-fija" style="white-space: normal; color: black;">
                        <?php echo htmlspecialchars($pieza['compositor']); ?>
                    </td>
                    <td class="col-fija" style="white-space: normal; color: black;">
                        <strong><?php echo htmlspecialchars($pieza['titulo']); ?></strong>
                    </td>
                    <td class="col-fija" style="text-align: center; font-size: 0.75rem; white-space: normal; color: black;">
                        <?php echo $pieza['tempo'] ? '♩=' . $pieza['tempo'] : '-'; ?>
                    </td>
                    <td class="col-fija" style="font-size: 0.75rem; white-space: normal; color: black;">
                        <?php echo htmlspecialchars($pieza['instrumento'] ?? 'Piano'); ?>
                    </td>
                    <td class="col-fija" style="text-align: center; font-size: 0.75rem; white-space: normal; color: black;">
                        <?php echo number_format($pieza['ponderacion'], 2); ?>
                    </td>
                    <?php foreach ($todosMeses as $mes): 
                        $media = $pieza['medias_por_mes'][$mes];
                        if ($media !== null) {
                            if ($media < 0.5) $claseColor = 'celda-fallo-excelente';
                            elseif ($media < 1.5) $claseColor = 'celda-fallo-muy-bien';
                            elseif ($media < 2.5) $claseColor = 'celda-fallo-bien';
                            elseif ($media < 3.5) $claseColor = 'celda-fallo-aceptable';
                            elseif ($media <= 5) $claseColor = 'celda-fallo-mejorable';
                            else $claseColor = 'celda-fallo-atencion';
                        } else {
                            $claseColor = 'celda-vacia';
                        }
                    ?>
                    <td class="<?php echo $claseColor; ?>">
                        <?php echo $media !== null ? number_format($media, 2) : '-'; ?>
                    </td>
                    <?php endforeach; ?>
                    <td style="background: #e8f5e9; font-weight: bold; color: black;">
                        <?php echo $pieza['dias_practicados_anio']; ?>
                    </td>
                    <td style="font-weight: bold;">
                        <?php echo number_format($pieza['media_fallos_anio'], 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 2rem; padding: 1.5rem; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h4 style="margin-top: 0; margin-bottom: 1rem; text-align: center;">📊 Distribución de Piezas por Rendimiento Anual</h4>
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 2rem;">
            <div style="position: relative; width: 300px; height: 300px;">
                <canvas id="chartPiezas" width="300" height="300"></canvas>
            </div>
            <div style="flex: 1; min-width: 250px;">
                <div style="display: grid; gap: 0.5rem;">
                    <?php 
                    foreach ($categorias as $cat):
                        $porcentaje = $totalPiezas > 0 ? round(($cat['count'] / $totalPiezas) * 100, 1) : 0;
                        if ($cat['count'] > 0):
                    ?>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 20px; height: 20px; background: <?php echo $cat['color']; ?>; border: 1px solid #999; border-radius: 3px;"></div>
                        <span style="flex: 1;"><strong><?php echo $cat['label']; ?>:</strong> 
                            <?php echo $cat['count']; ?> pieza<?php echo $cat['count'] != 1 ? 's' : ''; ?> (<?php echo $porcentaje; ?>%)</span>
                    </div>
                    <?php endif; endforeach; ?>
                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 2px solid #ddd;">
                        <strong>Total: <?php echo $totalPiezas; ?> pieza<?php echo $totalPiezas != 1 ? 's' : ''; ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const canvas = document.getElementById('chartPiezas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const centerX = 150, centerY = 150, radius = 120;
        const data = [<?php 
            $dataPoints = [];
            foreach ($categorias as $cat) {
                if ($cat['count'] > 0) {
                    $dataPoints[] = "{value: {$cat['count']}, color: '{$cat['color']}'}";
                }
            }
            echo implode(",", $dataPoints);
        ?>];
        const total = data.reduce((sum, item) => sum + item.value, 0);
        let currentAngle = -Math.PI / 2;
        data.forEach(item => {
            const sliceAngle = (item.value / total) * 2 * Math.PI;
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
            ctx.closePath();
            ctx.fillStyle = item.color;
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();
            currentAngle += sliceAngle;
        });
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius * 0.5, 0, 2 * Math.PI);
        ctx.fillStyle = '#fff';
        ctx.fill();
        ctx.fillStyle = '#333';
        ctx.font = 'bold 24px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(total, centerX, centerY - 10);
        ctx.font = '14px Arial';
        ctx.fillText('piezas', centerX, centerY + 15);
    })();
    </script>
    
    <div style="margin-top: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 4px; font-size: 0.85rem;">
        <strong>📊 Leyenda de colores:</strong>
        <div style="margin-top: 0.75rem;">
            <strong>Filas según media anual:</strong>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-top: 0.5rem;">
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #2E5F8A; border: 1px solid #999;"></span> &lt; 0.5 - Excelente</div>
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #4A7BA7; border: 1px solid #999;"></span> 0.5-1.5 - Muy bien</div>
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #A3C1DA; border: 1px solid #999;"></span> 1.5-2.5 - Bien</div>
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #D4E89E; border: 1px solid #999;"></span> 2.5-3.5 - Aceptable</div>
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #9B9B9B; border: 1px solid #999;"></span> 3.5-5 - Mejorable</div>
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #E57373; border: 1px solid #999;"></span> &gt; 5 - Atención</div>
            </div>
        </div>
        <div style="margin-top: 0.75rem;">
            <strong>Celdas mensuales:</strong> Media de fallos/día en ese mes.
        </div>
    </div>
</div>
<?php else: ?>
<div class="card">
    <p style="text-align: center; color: #666; padding: 2rem;">No se practicaron piezas de repertorio este año.</p>
</div>
<?php endif; ?>

<div class="card no-print" style="text-align: center; margin-top: 2rem;">
    <p><strong>💡 Formato apaisado.</strong> Para PDF: Ctrl+P → Orientación: Apaisado → ✅ Gráficos de fondo</p>
</div>

<?php include 'includes/footer.php'; ?>
