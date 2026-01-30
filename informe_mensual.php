<?php
// ACTIVAR ERRORES PARA DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'config/database.php';
$pageTitle = 'Informe Mensual - Piano Tracker';
$db = getDB();

// ============================================
// FUNCIONES AUXILIARES
// ============================================

// Función para obtener nombre del mes en español
function getNombreMes($numeroMes) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[(int)$numeroMes] ?? 'Desconocido';
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
    if ($media === null || $media === 0) return '#2E5F8A';  // Azul oscuro
    if ($media < 0.5) return '#2E5F8A';   // Azul oscuro
    if ($media < 1.5) return '#4A7BA7';   // Azul medio
    if ($media < 2.5) return '#A3C1DA';   // Azul claro
    if ($media < 3.5) return '#D4E89E';   // Verde
    if ($media <= 5) return '#9B9B9B';    // Gris
    return '#E57373';                      // Rojo
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
// OBTENER DATOS
// ============================================

// Obtener mes y año (por defecto: mes actual)
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

// Calcular fechas del mes
$fechaInicio = "$anio-$mes-01";
$fechaFin = date('Y-m-t', strtotime($fechaInicio));
$diasDelMes = (int)date('t', strtotime($fechaInicio));

// Crear array con TODOS los días del mes
$todosDias = range(1, $diasDelMes);

// Obtener tiempo por día y tipo de actividad
$stmt = $db->prepare("
    SELECT 
        DAY(s.fecha) as dia,
        a.tipo,
        SUM(a.tiempo_segundos) as tiempo_total
    FROM sesiones s
    LEFT JOIN actividades a ON s.id = a.sesion_id
    WHERE s.fecha BETWEEN :fecha_inicio AND :fecha_fin
    AND s.estado = 'finalizada'
    GROUP BY DAY(s.fecha), a.tipo
");
$stmt->execute([
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin
]);
$datosActividades = $stmt->fetchAll();

// Organizar datos en matriz [tipo][dia] = tiempo
$matrizActividades = [];
$tipos = ['calentamiento', 'tecnica', 'practica', 'repertorio', 'improvisacion', 'composicion'];
foreach ($tipos as $tipo) {
    $matrizActividades[$tipo] = [];
    for ($dia = 1; $dia <= $diasDelMes; $dia++) {
        $matrizActividades[$tipo][$dia] = 0;
    }
}

foreach ($datosActividades as $dato) {
    if ($dato['tipo'] && $dato['dia'] && isset($matrizActividades[$dato['tipo']])) {
        $matrizActividades[$dato['tipo']][(int)$dato['dia']] = (int)$dato['tiempo_total'];
    }
}

// Calcular totales por tipo
$totalesPorTipo = [];
foreach ($matrizActividades as $tipo => $dias) {
    $totalesPorTipo[$tipo] = array_sum($dias);
}

// Calcular totales por día
$totalesPorDia = [];
for ($dia = 1; $dia <= $diasDelMes; $dia++) {
    $total = 0;
    foreach ($matrizActividades as $dias) {
        if (isset($dias[$dia])) {
            $total += $dias[$dia];
        }
    }
    $totalesPorDia[$dia] = $total;
}

$tiempoTotalMes = array_sum($totalesPorTipo);

// Calcular porcentajes
$porcentajes = [];
if ($tiempoTotalMes > 0) {
    foreach ($totalesPorTipo as $tipo => $tiempo) {
        $porcentajes[$tipo] = ($tiempo / $tiempoTotalMes) * 100;
    }
}

// Crear array $actividades para el gráfico de tarta
$nombresActividades = [
    'calentamiento' => 'Calentamiento',
    'tecnica' => 'Técnica',
    'practica' => 'Práctica',
    'repertorio' => 'Repertorio',
    'improvisacion' => 'Improvisación',
    'composicion' => 'Composición'
];
$actividades = [];
foreach ($tipos as $tipo) {
    $actividades[] = [
        'tipo' => $tipo,
        'nombre' => $nombresActividades[$tipo],
        'tiempo_total' => $totalesPorTipo[$tipo]
    ];
}

// Calcular días practicados por actividad
$diasPracticadosPorTipo = [];
foreach ($tipos as $tipo) {
    $diasCount = 0;
    foreach ($matrizActividades[$tipo] as $dia => $tiempo) {
        if ($tiempo > 0) {
            $diasCount++;
        }
    }
    $diasPracticadosPorTipo[$tipo] = $diasCount;
}

// Calcular total de días practicados en el mes (días únicos con práctica)
$diasPracticadosTotales = 0;
foreach ($todosDias as $dia) {
    if (isset($totalesPorDia[$dia]) && $totalesPorDia[$dia] > 0) {
        $diasPracticadosTotales++;
    }
}

// Obtener piezas practicadas con fallos por día
$stmt = $db->prepare("
    SELECT 
        p.id,
        p.compositor,
        p.titulo,
        p.libro,
        p.grado,
        p.instrumento,
        p.tempo,
        p.ponderacion,
        DAY(s.fecha) as dia,
        f.cantidad as fallos
    FROM piezas p
    JOIN fallos f ON p.id = f.pieza_id
    JOIN actividades a ON f.actividad_id = a.id
    JOIN sesiones s ON a.sesion_id = s.id
    WHERE s.fecha BETWEEN :fecha_inicio AND :fecha_fin
    AND a.tipo = 'repertorio'
    ORDER BY p.libro, p.grado, p.compositor, p.titulo
");
$stmt->execute([
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin
]);
$datosPiezas = $stmt->fetchAll();

// Organizar datos de piezas
$piezas = [];
foreach ($datosPiezas as $dato) {
    if (!isset($piezas[$dato['id']])) {
        $fallos_por_dia = [];
        for ($d = 1; $d <= $diasDelMes; $d++) {
            $fallos_por_dia[$d] = null;
        }
        
        $piezas[$dato['id']] = [
            'compositor' => $dato['compositor'],
            'titulo' => $dato['titulo'],
            'libro' => $dato['libro'],
            'grado' => $dato['grado'],
            'instrumento' => $dato['instrumento'],
            'tempo' => $dato['tempo'],
            'ponderacion' => $dato['ponderacion'],
            'fallos_por_dia' => $fallos_por_dia
        ];
    }
    $piezas[$dato['id']]['fallos_por_dia'][(int)$dato['dia']] = (int)$dato['fallos'];
}

// Calcular estadísticas por pieza
foreach ($piezas as $id => &$pieza) {
    $diasPracticados = 0;
    $totalFallos = 0;
    
    foreach ($pieza['fallos_por_dia'] as $fallos) {
        if ($fallos !== null) {
            $diasPracticados++;
            $totalFallos += $fallos;
        }
    }
    
    $pieza['dias_practicados'] = $diasPracticados;
    $pieza['media_fallos'] = $diasPracticados > 0 ? $totalFallos / $diasPracticados : 0;
}
unset($pieza);

// Calcular distribución de piezas por categoría de fallos
$categorias = [
    'excelente' => ['count' => 0, 'color' => '#2E5F8A', 'label' => 'Excelente (< 0.5)'],
    'muy_bien' => ['count' => 0, 'color' => '#4A7BA7', 'label' => 'Muy bien (0.5-1.5)'],
    'bien' => ['count' => 0, 'color' => '#A3C1DA', 'label' => 'Bien (1.5-2.5)'],
    'aceptable' => ['count' => 0, 'color' => '#D4E89E', 'label' => 'Aceptable (2.5-3.5)'],
    'mejorable' => ['count' => 0, 'color' => '#9B9B9B', 'label' => 'Mejorable (3.5-5)'],
    'atencion' => ['count' => 0, 'color' => '#E57373', 'label' => 'Atención (> 5)']
];

foreach ($piezas as $pieza) {
    $media = $pieza['media_fallos'];
    if ($media < 0.5) {
        $categorias['excelente']['count']++;
    } elseif ($media < 1.5) {
        $categorias['muy_bien']['count']++;
    } elseif ($media < 2.5) {
        $categorias['bien']['count']++;
    } elseif ($media < 3.5) {
        $categorias['aceptable']['count']++;
    } elseif ($media <= 5) {
        $categorias['mejorable']['count']++;
    } else {
        $categorias['atencion']['count']++;
    }
}

$totalPiezas = count($piezas);

include 'includes/header.php';
?>

<style>
/* Eliminar limitación de ancho en esta página para que la tabla use todo el espacio */
.container {
    max-width: none !important;
    width: 100% !important;
    padding: 0 10px !important;
}

/* Asegurar que las cards también usen todo el ancho */
.card {
    max-width: none !important;
}

/* FORZAR IMPRESIÓN DE COLORES - Crítico para mantener los colores en PDF */
* {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    color-adjust: exact !important;
}

@media print {
    @page {
        size: landscape;
        margin: 1cm;
    }
    
    /* Forzar colores de fondo en impresión */
    body { 
        font-size: 9pt; 
        background: white;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    /* Mantener colores en todas las celdas y filas */
    table, tr, td, th {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    header, footer, .nav-menu, .btn, .no-print { display: none !important; }
    .card { box-shadow: none; page-break-inside: avoid; margin-bottom: 1rem; }
    table { page-break-inside: auto; font-size: 8pt; }
    .titulo-informe { text-align: center; margin-bottom: 15px; }
}

.tabla-horizontal {
    width: 100%;
    overflow-x: auto;
    margin-top: 1rem;
}

.tabla-horizontal table {
    border-collapse: collapse;
    min-width: 100%;
    font-size: 0.85rem;
}

.tabla-horizontal th {
    background: var(--primary);
    color: white;
    padding: 0.4rem 0.3rem;
    text-align: center;
    font-size: 0.75rem;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}

.tabla-horizontal td {
    padding: 0.3rem 0.2rem;
    text-align: center;
    border: 1px solid #ddd;
    font-size: 0.8rem;
}

.tabla-horizontal .fila-tipo {
    background: #f8f9fa;
    font-weight: bold;
    text-align: left;
    padding-left: 0.5rem;
}

.tabla-horizontal .fila-total {
    background: #e9ecef;
    font-weight: bold;
}

.tabla-horizontal .col-fija {
    position: sticky;
    left: 0;
    background: #f8f9fa;
    z-index: 5;
    font-weight: bold;
    text-align: left;
    padding-left: 0.5rem;
    border-right: 2px solid #999;
}

.tabla-horizontal .col-fija-header {
    position: sticky;
    left: 0;
    background: var(--primary);
    z-index: 15;
    border-right: 2px solid white;
}

.tabla-horizontal .col-estadistica {
    background: #fff3cd;
    font-weight: bold;
    border-left: 2px solid #999;
}

/* Headers de columnas estadísticas con mismo color que otros headers */
.tabla-horizontal th.col-estadistica {
    background: var(--primary);
    color: white;
    border-left: 2px solid white;
}

.tabla-horizontal .col-dia-vacio {
    background: #f0f0f0;
    color: #999;
}

.btn-imprimir {
    position: fixed;
    top: 100px;
    right: 20px;
    z-index: 1000;
}

/* Colores adaptados para daltonismo */
.celda-fallo { font-weight: bold; }

/* Celdas de fallos diarios individuales */
.celda-fallo-0 { 
    background: #2E5F8A; 
    color: white; 
}
.celda-fallo-1 { 
    background: #4A7BA7; 
    color: white; 
}
.celda-fallo-2 { 
    background: #A3C1DA; 
    color: black; 
}
.celda-fallo-3 { 
    background: #D4E89E; 
    color: black; 
}
.celda-fallo-4 { 
    background: #9B9B9B; 
    color: white; 
}
.celda-fallo-5plus { 
    background: #E57373; 
    color: white; 
}
</style>

<div style="display: flex; gap: 1rem; margin-bottom: 1rem;" class="no-print">
    <button onclick="window.print()" class="btn btn-primary btn-imprimir">🖨️ Imprimir / Guardar PDF</button>
    <a href="informes.php" class="btn btn-secondary">← Volver a Informes</a>
</div>

<div class="card no-print">
    <h2>Seleccionar mes para informe</h2>
    <form method="GET" class="form-inline">
        <div class="form-group">
            <label for="mes">Mes</label>
            <select name="mes" id="mes">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>" 
                        <?php echo $mes == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                    <?php echo getNombreMes($i); ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="form-group">
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
</div>

<div class="card">
    <div class="titulo-informe">
        <h1>📊 Informe de Práctica de Piano</h1>
        <h2><?php echo getNombreMes((int)$mes) . ' ' . $anio; ?></h2>
        <p style="color: #666;">Tiempo total del mes: <strong><?php echo formatearTiempo($tiempoTotalMes); ?></strong></p>
    </div>
    
    <h3>Tiempo de práctica por tipo de actividad</h3>
    
    <div class="tabla-horizontal">
        <table>
            <thead>
                <tr>
                    <th class="col-fija-header">Actividad</th>
                    <?php foreach ($todosDias as $dia): ?>
                    <th><?php echo $dia; ?></th>
                    <?php endforeach; ?>
                    <th class="col-estadistica">Días</th>
                    <th class="col-estadistica">Total</th>
                    <th class="col-estadistica">%</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tiposNombres = [
                    'calentamiento' => 'Calentamiento',
                    'tecnica' => 'Técnica',
                    'practica' => 'Práctica',
                    'repertorio' => 'Repertorio',
                    'improvisacion' => 'Improvisación',
                    'composicion' => 'Composición'
                ];
                
                foreach ($matrizActividades as $tipo => $dias):
                ?>
                <tr>
                    <td class="col-fija fila-tipo"><?php echo $tiposNombres[$tipo]; ?></td>
                    <?php foreach ($todosDias as $dia): 
                        $tiempo = isset($dias[$dia]) ? $dias[$dia] : 0;
                    ?>
                    <td class="<?php echo $tiempo == 0 ? 'col-dia-vacio' : ''; ?>">
                        <?php echo formatearTiempoBreve($tiempo); ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="col-estadistica"><?php echo $diasPracticadosPorTipo[$tipo]; ?></td>
                    <td class="col-estadistica"><?php echo formatearTiempo($totalesPorTipo[$tipo] ?? 0); ?></td>
                    <td class="col-estadistica"><?php echo number_format($porcentajes[$tipo] ?? 0, 1); ?>%</td>
                </tr>
                <?php endforeach; ?>
                
                <tr class="fila-total">
                    <td class="col-fija">TOTAL</td>
                    <?php foreach ($todosDias as $dia): ?>
                    <td><?php echo formatearTiempoBreve($totalesPorDia[$dia] ?? 0); ?></td>
                    <?php endforeach; ?>
                    <td class="col-estadistica"><?php echo $diasPracticadosTotales; ?></td>
                    <td class="col-estadistica"><?php echo formatearTiempo($tiempoTotalMes); ?></td>
                    <td class="col-estadistica">100%</td>
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
                        if ($act['tiempo_total'] == 0) continue;
                        $porcentaje = $tiempoTotalMes > 0 ? round(($act['tiempo_total'] / $tiempoTotalMes) * 100, 1) : 0;
                    ?>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 20px; height: 20px; background: <?php echo $coloresActividades[$act['tipo']]; ?>; border: 1px solid #999; border-radius: 3px;"></div>
                        <span style="flex: 1;">
                            <strong><?php echo htmlspecialchars($act['nombre']); ?>:</strong> 
                            <?php echo formatearTiempo($act['tiempo_total']); ?> (<?php echo $porcentaje; ?>%)
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 2px solid #ddd;">
                        <strong>Total: <?php echo formatearTiempo($tiempoTotalMes); ?></strong>
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
                if ($act['tiempo_total'] > 0) {
                    $color = $coloresActividades[$act['tipo']];
                    $nombre = htmlspecialchars($act['nombre']);
                    $dataPointsAct[] = "{value: {$act['tiempo_total']}, color: '$color', label: '$nombre'}";
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
<div class="card" style="page-break-before: always;">
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
                    <?php foreach ($todosDias as $dia): ?>
                    <th><?php echo $dia; ?></th>
                    <?php endforeach; ?>
                    <th class="col-estadistica">Días</th>
                    <th class="col-estadistica">Media</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($piezas as $pieza): ?>
                <tr style="background: <?php echo getColorFallos($pieza['media_fallos']); ?>; color: <?php echo getColorTextoFallos($pieza['media_fallos']); ?>">
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
                    <?php foreach ($todosDias as $dia): 
                        $fallos = isset($pieza['fallos_por_dia'][$dia]) ? $pieza['fallos_por_dia'][$dia] : null;
                        $claseColor = '';
                        if ($fallos !== null) {
                            // Asignar clase según número exacto de fallos
                            if ($fallos == 0) $claseColor = 'celda-fallo-0';
                            elseif ($fallos == 1) $claseColor = 'celda-fallo-1';
                            elseif ($fallos == 2) $claseColor = 'celda-fallo-2';
                            elseif ($fallos == 3) $claseColor = 'celda-fallo-3';
                            elseif ($fallos == 4) $claseColor = 'celda-fallo-4';
                            else $claseColor = 'celda-fallo-5plus';
                        }
                    ?>
                    <td class="<?php echo $fallos === null ? 'col-dia-vacio' : 'celda-fallo ' . $claseColor; ?>">
                        <?php echo $fallos !== null ? $fallos : '-'; ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="col-estadistica" style="background: #f0f0f0; color: black;"><?php echo $pieza['dias_practicados']; ?></td>
                    <td class="col-estadistica" style="background: <?php echo getColorFallos($pieza['media_fallos']); ?>; color: <?php echo getColorTextoFallos($pieza['media_fallos']); ?>; font-weight: bold;">
                        <?php echo number_format($pieza['media_fallos'], 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Gráfico de tarta: Distribución de piezas por categoría -->
    <div style="margin-top: 2rem; padding: 1.5rem; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h4 style="margin-top: 0; margin-bottom: 1rem; text-align: center;">📊 Distribución de Piezas por Rendimiento</h4>
        
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 2rem;">
            <!-- Gráfico de tarta -->
            <div style="position: relative; width: 300px; height: 300px;">
                <canvas id="chartPiezas" width="300" height="300"></canvas>
            </div>
            
            <!-- Leyenda del gráfico -->
            <div style="flex: 1; min-width: 250px;">
                <div style="display: grid; gap: 0.5rem;">
                    <?php 
                    foreach ($categorias as $key => $cat):
                        $porcentaje = $totalPiezas > 0 ? round(($cat['count'] / $totalPiezas) * 100, 1) : 0;
                        if ($cat['count'] > 0):
                    ?>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 20px; height: 20px; background: <?php echo $cat['color']; ?>; border: 1px solid #999; border-radius: 3px;"></div>
                        <span style="flex: 1;"><strong><?php echo $cat['label']; ?>:</strong> <?php echo $cat['count']; ?> pieza<?php echo $cat['count'] != 1 ? 's' : ''; ?> (<?php echo $porcentaje; ?>%)</span>
                    </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 2px solid #ddd;">
                        <strong>Total: <?php echo $totalPiezas; ?> pieza<?php echo $totalPiezas != 1 ? 's' : ''; ?> practicadas</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Crear gráfico de tarta
    (function() {
        const canvas = document.getElementById('chartPiezas');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const centerX = 150;
        const centerY = 150;
        const radius = 120;
        
        // Datos del gráfico
        const data = [
            <?php 
            $dataPoints = [];
            foreach ($categorias as $cat) {
                if ($cat['count'] > 0) {
                    $dataPoints[] = "{value: {$cat['count']}, color: '{$cat['color']}', label: '{$cat['label']}'}";
                }
            }
            echo implode(",\n            ", $dataPoints);
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
        
        // Texto central
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
        <strong>📊 Leyenda de colores - Paleta adaptada para daltonismo:</strong>
        
        <div style="margin-top: 0.75rem;">
            <strong>Filas según media de fallos:</strong>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-top: 0.5rem;">
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #2E5F8A; border: 1px solid #999;"></span> &lt; 0.5 - Excelente (azul oscuro)</div>
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #4A7BA7; border: 1px solid #999;"></span> 0.5-1.5 - Muy bien (azul medio)</div>
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #A3C1DA; border: 1px solid #999;"></span> 1.5-2.5 - Bien (azul claro)</div>
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #D4E89E; border: 1px solid #999;"></span> 2.5-3.5 - Aceptable (verde)</div>
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #9B9B9B; border: 1px solid #999;"></span> 3.5-5 - Mejorable (gris)</div>
                <div><span style="display: inline-block; width: 30px; height: 20px; background: #E57373; border: 1px solid #999;"></span> &gt; 5 - Atención (rojo)</div>
            </div>
        </div>
        
        <div style="margin-top: 0.75rem;">
            <strong>Celdas de fallos diarios (0-5+):</strong>
            <div style="display: inline-flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem;">
                <span style="background: #2E5F8A; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">0</span>
                <span style="background: #4A7BA7; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">1</span>
                <span style="background: #A3C1DA; color: black; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">2</span>
                <span style="background: #D4E89E; color: black; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">3</span>
                <span style="background: #9B9B9B; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">4</span>
                <span style="background: #E57373; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">5+</span>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card">
    <p style="text-align: center; color: #666; padding: 2rem;">No se practicaron piezas de repertorio en este mes.</p>
</div>
<?php endif; ?>

<div class="card no-print" style="text-align: center; margin-top: 2rem;">
    <p><strong>💡 Consejo:</strong> Este informe está diseñado en <strong>formato apaisado</strong> para mejor visualización.</p>
    <p>Para guardar como PDF:</p>
    <ol style="text-align: left; max-width: 600px; margin: 1rem auto;">
        <li>Haz clic en el botón "🖨️ Imprimir / Guardar PDF" o presiona <kbd>Ctrl + P</kbd></li>
        <li>En el diálogo de impresión:
            <ul>
                <li>Destino: "Guardar como PDF" o "Microsoft Print to PDF"</li>
                <li>Orientación: <strong>Apaisado / Horizontal</strong> (se configura automáticamente)</li>
                <li>✅ Activar "Gráficos de fondo" para ver los colores</li>
            </ul>
        </li>
        <li>Haz clic en "Guardar"</li>
    </ol>
</div>

<?php include 'includes/footer.php'; ?>
