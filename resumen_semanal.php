<?php
require_once 'config/database.php';
$pageTitle = 'Resumen semanal - Piano Tracker';
$db = getDB();

// Asegurar que las evaluaciones de progresión mensual están al día antes de
// leer los avisos pendientes (idempotente, ver includes/funciones.php).
evaluarProgresionMensual($db);

// Al confirmar desde aquí, se marca la semana como mostrada y se pasa a
// planificar/continuar la sesión. Visitar esta página sin confirmar (p. ej.
// desde el enlace "Resumen semanal" del dashboard) no marca nada: así se
// puede reabrir voluntariamente sin afectar al aviso automático.
if (isset($_GET['continuar'])) {
    marcarResumenSemanalMostrado($db);
    header('Location: sesion.php');
    exit;
}

$resumen = obtenerResumenSemanal($db);

function compararTexto($actual, $previo) {
    if ($previo == 0) {
        return $actual > 0 ? ' <small style="opacity:0.7;">(la semana anterior no hubo práctica)</small>' : '';
    }
    $pct = round((($actual - $previo) / $previo) * 100);
    if ($pct == 0) {
        return ' <small style="opacity:0.7;">(igual que la semana anterior)</small>';
    }
    $signo = $pct > 0 ? '+' : '';
    return ' <small style="opacity:0.7;">(' . $signo . $pct . '% vs. semana anterior)</small>';
}

include 'includes/header.php';
?>

<div class="card">
    <h2>📅 Resumen de la semana del <?php echo date('d/m', strtotime($resumen['inicio'])) . ' al ' . date('d/m', strtotime($resumen['fin'] . ' -1 day')); ?></h2>

    <?php if ($resumen['tiempo']['dias'] === 0): ?>
        <p>No hubo ninguna sesión registrada la semana pasada. ¡Esta es una buena semana para retomarlo!</p>
    <?php else: ?>
    <div class="stats-grid">
        <div class="stat-box">
            <h3><?php echo formatearTiempo($resumen['tiempo']['segundos']); ?></h3>
            <p>Tiempo practicado</p>
            <?php echo compararTexto($resumen['tiempo']['segundos'], $resumen['tiempo_previo']['segundos']); ?>
        </div>
        <div class="stat-box">
            <h3><?php echo $resumen['tiempo']['dias']; ?>/7 días</h3>
            <p>Días practicados</p>
            <?php echo compararTexto($resumen['tiempo']['dias'], $resumen['tiempo_previo']['dias']); ?>
        </div>
        <div class="stat-box">
            <h3>🏅 <?php echo number_format($resumen['puntuacion']['total'], 1); ?></h3>
            <p><?php echo $resumen['puntuacion']['piezas']; ?> piezas puntuadas</p>
            <?php
            $diff = $resumen['puntuacion_diff'];
            if ($resumen['puntuacion_previa']['piezas'] === 0) {
                echo ' <small style="opacity:0.7;">(sin datos suficientes la semana pasada)</small>';
            } elseif (abs($diff) < 0.05) {
                echo ' <small style="opacity:0.7;">(igual que la semana anterior)</small>';
            } else {
                $signo = $diff > 0 ? '+' : '';
                echo ' <small style="opacity:0.7;">(' . $signo . number_format($diff, 1) . ' vs. semana anterior)</small>';
            }
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($resumen['semana_floja']): ?>
<div class="alert alert-info">
    📉 Esta semana bajaste el ritmo respecto a la anterior. No pasa nada, pero si puedes, intenta recuperarlo esta semana.
</div>
<?php endif; ?>

<?php if ($resumen['logro_pieza']): ?>
<?php $l = $resumen['logro_pieza']; ?>
<div class="alert alert-success">
    🏆 <strong>Logro de la semana:</strong>
    <?php echo htmlspecialchars($l['pieza']['compositor'] . ' - ' . $l['pieza']['titulo']); ?>
    bajó su media de fallos de <strong><?php echo number_format($l['media_anterior'], 2); ?></strong>
    a <strong><?php echo number_format($l['media_actual'], 2); ?></strong> por pase.
</div>
<?php endif; ?>

<?php if (count($resumen['mejoras']) > 1): ?>
<div class="card">
    <h2>🎹 Piezas con mejora esta semana</h2>
    <table>
        <thead>
            <tr>
                <th>Pieza</th>
                <th>Media de fallos (semana anterior)</th>
                <th>Media de fallos (esta semana)</th>
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
            <?php echo compararTexto($resumen['tecnica']['bpm_medio'], $resumen['tecnica']['bpm_medio_previo']); ?>
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<div class="card" style="text-align: center;">
    <a href="resumen_semanal.php?continuar=1" class="btn btn-success">Empezar a practicar →</a>
</div>

<?php include 'includes/footer.php'; ?>
