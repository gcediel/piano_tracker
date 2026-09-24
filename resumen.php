<?php
require_once 'config/database.php';
$db = getDB();

// Periodo del resumen: semana (por defecto), mes o año natural anterior al actual.
$periodo = $_GET['periodo'] ?? 'semana';
if (!in_array($periodo, ['semana', 'mes', 'anio'], true)) {
    $periodo = 'semana';
}

// Asegurar que las evaluaciones de progresión mensual están al día antes de
// leer los avisos pendientes (idempotente, ver includes/funciones.php).
evaluarProgresionMensual($db);

// Al confirmar desde aquí, se marca el periodo como mostrado y se pasa a
// planificar/continuar la sesión. Visitar esta página sin confirmar (p. ej.
// desde el enlace "Resumen semanal" del dashboard) no marca nada: así se
// puede reabrir voluntariamente sin afectar al aviso automático.
if (isset($_GET['continuar'])) {
    marcarResumenMostrado($db, $periodo);
    header('Location: sesion.php');
    exit;
}

$resumen = obtenerResumenPeriodo($db, $periodo);

$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
          'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$inicioTs = strtotime($resumen['inicio']);

// Textos que cambian según el periodo (género y número incluidos).
$textos = [
    'semana' => [
        'titulo' => 'Resumen de la semana del ' . date('d/m', $inicioTs) . ' al ' . date('d/m', strtotime($resumen['fin'] . ' -1 day')),
        'anterior' => 'la semana anterior',
        'vs' => 'semana anterior',
        'pasado' => 'la semana pasada',
        'sin_practica' => 'No hubo ninguna sesión registrada la semana pasada. ¡Esta es una buena semana para retomarlo!',
        'flojo' => 'Esta semana bajaste el ritmo respecto a la anterior. No pasa nada, pero si puedes, intenta recuperarlo esta semana.',
        'logro' => 'Logro de la semana',
        'este' => 'esta semana',
    ],
    'mes' => [
        'titulo' => 'Resumen de ' . $meses[(int)date('n', $inicioTs)] . ' de ' . date('Y', $inicioTs),
        'anterior' => 'el mes anterior',
        'vs' => 'mes anterior',
        'pasado' => 'el mes pasado',
        'sin_practica' => 'No hubo ninguna sesión registrada el mes pasado. ¡Este es un buen mes para retomarlo!',
        'flojo' => 'Este mes bajaste el ritmo respecto al anterior. No pasa nada, pero si puedes, intenta recuperarlo este mes.',
        'logro' => 'Logro del mes',
        'este' => 'este mes',
        'informe_url' => 'informe_mensual.php?mes=' . date('m', $inicioTs) . '&anio=' . date('Y', $inicioTs),
        'informe_texto' => '📊 Ver informe mensual',
    ],
    'anio' => [
        'titulo' => 'Resumen del año ' . date('Y', $inicioTs),
        'anterior' => 'el año anterior',
        'vs' => 'año anterior',
        'pasado' => 'el año pasado',
        'sin_practica' => 'No hubo ninguna sesión registrada el año pasado. ¡Este es un buen año para retomarlo!',
        'flojo' => 'Este año bajaste el ritmo respecto al anterior. No pasa nada, pero si puedes, intenta recuperarlo este año.',
        'logro' => 'Logro del año',
        'este' => 'este año',
        'informe_url' => 'informe_anual.php?anio=' . date('Y', $inicioTs),
        'informe_texto' => '📊 Ver informe anual',
    ],
][$periodo];

$pageTitle = $textos['titulo'] . ' - Piano Tracker';

function compararTexto($actual, $previo, $textos) {
    if ($previo == 0) {
        return $actual > 0 ? ' <small style="opacity:0.7;">(' . $textos['anterior'] . ' no hubo práctica)</small>' : '';
    }
    $pct = round((($actual - $previo) / $previo) * 100);
    if ($pct == 0) {
        return ' <small style="opacity:0.7;">(igual que ' . $textos['anterior'] . ')</small>';
    }
    $signo = $pct > 0 ? '+' : '';
    return ' <small style="opacity:0.7;">(' . $signo . $pct . '% vs. ' . $textos['vs'] . ')</small>';
}

include 'includes/header.php';
?>

<div class="card">
    <h2>📅 <?php echo $textos['titulo']; ?></h2>

    <?php if ($resumen['tiempo']['dias'] === 0): ?>
        <p><?php echo $textos['sin_practica']; ?></p>
    <?php else: ?>
    <div class="stats-grid">
        <div class="stat-box">
            <h3><?php echo formatearTiempo($resumen['tiempo']['segundos']); ?></h3>
            <p>Tiempo practicado</p>
            <?php echo compararTexto($resumen['tiempo']['segundos'], $resumen['tiempo_previo']['segundos'], $textos); ?>
        </div>
        <div class="stat-box">
            <h3><?php echo $resumen['tiempo']['dias'] . '/' . $resumen['dias_periodo']; ?> días</h3>
            <p>Días practicados</p>
            <?php echo compararTexto($resumen['tiempo']['dias'], $resumen['tiempo_previo']['dias'], $textos); ?>
        </div>
        <div class="stat-box">
            <h3>🏅 <?php echo number_format($resumen['puntuacion']['total'], 1); ?></h3>
            <p><?php echo $resumen['puntuacion']['piezas']; ?> piezas puntuadas</p>
            <?php
            $diff = $resumen['puntuacion_diff'];
            if ($resumen['puntuacion_previa']['piezas'] === 0) {
                echo ' <small style="opacity:0.7;">(sin datos suficientes ' . $textos['pasado'] . ')</small>';
            } elseif (abs($diff) < 0.05) {
                echo ' <small style="opacity:0.7;">(igual que ' . $textos['anterior'] . ')</small>';
            } else {
                $signo = $diff > 0 ? '+' : '';
                echo ' <small style="opacity:0.7;">(' . $signo . number_format($diff, 1) . ' vs. ' . $textos['vs'] . ')</small>';
            }
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($resumen['periodo_flojo']): ?>
<div class="alert alert-info">
    📉 <?php echo $textos['flojo']; ?>
</div>
<?php endif; ?>

<?php if ($resumen['logro_pieza']): ?>
<?php $l = $resumen['logro_pieza']; ?>
<div class="alert alert-success">
    🏆 <strong><?php echo $textos['logro']; ?>:</strong>
    <?php echo htmlspecialchars($l['pieza']['compositor'] . ' - ' . $l['pieza']['titulo']); ?>
    bajó su media de fallos de <strong><?php echo number_format($l['media_anterior'], 2); ?></strong>
    a <strong><?php echo number_format($l['media_actual'], 2); ?></strong> por pase.
</div>
<?php endif; ?>

<?php if (count($resumen['mejoras']) > 1): ?>
<div class="card">
    <h2>🎹 Piezas con mejora <?php echo $textos['este']; ?></h2>
    <table>
        <thead>
            <tr>
                <th>Pieza</th>
                <th>Media de fallos (<?php echo $textos['vs']; ?>)</th>
                <th>Media de fallos (<?php echo $textos['este']; ?>)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resumen['mejoras'] as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars($m['pieza']['compositor'] . ' - ' . $m['pieza']['titulo']); ?></td>
                <td><?php echo number_format($m['media_anterior'], 2); ?></td>
                <td><strong><?php echo number_format($m['media_actual'], 2); ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($resumen['piezas_nuevas'])): ?>
<div class="card">
    <h2>✨ Piezas nuevas en el repertorio</h2>
    <ul>
        <?php foreach ($resumen['piezas_nuevas'] as $p): ?>
        <li><?php echo htmlspecialchars($p['compositor'] . ' - ' . $p['titulo']); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($resumen['avisos_progresion'])): ?>
<div class="card">
    <h2>📈 Progresión</h2>
    <ul>
        <?php foreach ($resumen['avisos_progresion'] as $a): ?>
            <?php if ($a['sugerencia_tempo_pendiente'] !== null): ?>
            <li>🎹 <?php echo htmlspecialchars($a['compositor'] . ' - ' . $a['titulo']); ?>:
                sugerencia de subir tempo de <?php echo $a['tempo']; ?> a <strong><?php echo $a['sugerencia_tempo_pendiente']; ?> BPM</strong>
                pendiente de confirmar en el dashboard.</li>
            <?php endif; ?>
            <?php if ($a['aviso_graduacion_pendiente']): ?>
            <li>🛠 <?php echo htmlspecialchars($a['compositor'] . ' - ' . $a['titulo']); ?>:
                pasó a repertorio de mantenimiento.</li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($resumen['tecnica']['total'] > 0): ?>
<div class="card">
    <h2>🛠 Técnica</h2>
    <p>
        <?php echo $resumen['tecnica']['ejercicios_distintos']; ?> ejercicios distintos trabajados
        (<?php echo $resumen['tecnica']['bien']; ?> con resultado "bien" de <?php echo $resumen['tecnica']['total']; ?> valoraciones).
        BPM medio practicado: <strong><?php echo number_format($resumen['tecnica']['bpm_medio'], 0); ?></strong>
        <?php if ($resumen['tecnica']['bpm_medio_previo'] !== null): ?>
            <?php echo compararTexto($resumen['tecnica']['bpm_medio'], $resumen['tecnica']['bpm_medio_previo'], $textos); ?>
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<div class="card" style="text-align: center; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
    <?php if (isset($textos['informe_url'])): ?>
    <a href="<?php echo htmlspecialchars($textos['informe_url']); ?>" class="btn btn-primary"><?php echo $textos['informe_texto']; ?></a>
    <?php endif; ?>
    <a href="resumen.php?periodo=<?php echo $periodo; ?>&amp;continuar=1" class="btn btn-success">Empezar a practicar →</a>
</div>

<?php include 'includes/footer.php'; ?>
