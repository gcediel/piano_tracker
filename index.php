<?php
require_once 'config/database.php';
$pageTitle = 'Inicio - Piano Tracker';

// Obtener estadísticas rápidas
$db = getDB();

// Auto-corrección: marcar como finalizadas las sesiones que tienen todas sus actividades completadas
$db->exec("
    UPDATE sesiones s 
    SET s.estado = 'finalizada' 
    WHERE s.estado IN ('planificada', 'en_curso')
    AND NOT EXISTS (
        SELECT 1 FROM actividades a 
        WHERE a.sesion_id = s.id 
        AND a.estado IN ('pendiente', 'en_curso')
    )
    AND EXISTS (
        SELECT 1 FROM actividades a 
        WHERE a.sesion_id = s.id
    )
");

// Sesión activa hoy (solo si tiene actividades pendientes o en curso)
$stmt = $db->prepare("
    SELECT s.* FROM sesiones s
    WHERE s.fecha = CURDATE() 
    AND s.estado != 'finalizada'
    AND EXISTS (
        SELECT 1 FROM actividades a 
        WHERE a.sesion_id = s.id 
        AND a.estado IN ('pendiente', 'en_curso')
    )
    ORDER BY s.id DESC 
    LIMIT 1
");
$stmt->execute();
$sesionActiva = $stmt->fetch();

// Tiempo total practicado hoy
$stmt = $db->prepare("SELECT SUM(tiempo_segundos) as total FROM actividades a 
                      JOIN sesiones s ON a.sesion_id = s.id 
                      WHERE s.fecha = CURDATE()");
$stmt->execute();
$tiempoHoy = $stmt->fetch()['total'] ?? 0;

// Tiempo total este mes
$stmt = $db->prepare("SELECT SUM(tiempo_segundos) as total FROM actividades a 
                      JOIN sesiones s ON a.sesion_id = s.id 
                      WHERE YEAR(s.fecha) = YEAR(CURDATE()) AND MONTH(s.fecha) = MONTH(CURDATE())");
$stmt->execute();
$tiempoMes = $stmt->fetch()['total'] ?? 0;

// Número de piezas activas
$stmt = $db->prepare("SELECT COUNT(*) as total FROM piezas WHERE activa = 1");
$stmt->execute();
$numPiezas = $stmt->fetch()['total'] ?? 0;

// Calcular racha actual de práctica
$stmt = $db->query("SELECT DISTINCT fecha FROM sesiones ORDER BY fecha DESC");
$fechasSesiones = $stmt->fetchAll(PDO::FETCH_COLUMN);

$rachaActual = 0;
$rachaMasLarga = 0;
$rachaTemp = 0;

if (!empty($fechasSesiones)) {
    $hoy = new DateTime();
    $hoy->setTime(0, 0, 0);
    
    // Calcular racha actual (desde hoy hacia atrás)
    $fechaCheck = clone $hoy;
    foreach ($fechasSesiones as $fecha) {
        $fechaSesion = new DateTime($fecha);
        $fechaSesion->setTime(0, 0, 0);
        
        if ($fechaSesion == $fechaCheck) {
            $rachaActual++;
            $fechaCheck->modify('-1 day');
        } else {
            break;
        }
    }
    
    // Calcular racha más larga
    $fechaAnterior = null;
    foreach ($fechasSesiones as $fecha) {
        $fechaSesion = new DateTime($fecha);
        
        if ($fechaAnterior === null) {
            $rachaTemp = 1;
        } else {
            $diff = $fechaAnterior->diff($fechaSesion);
            if ($diff->days == 1) {
                $rachaTemp++;
            } else {
                $rachaMasLarga = max($rachaMasLarga, $rachaTemp);
                $rachaTemp = 1;
            }
        }
        
        $fechaAnterior = $fechaSesion;
    }
    $rachaMasLarga = max($rachaMasLarga, $rachaTemp);
}

// Porcentaje de días practicados esta semana
$stmt = $db->query("
    SELECT COUNT(DISTINCT fecha) as dias 
    FROM sesiones 
    WHERE YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1)
");
$diasEstaSemana = $stmt->fetch()['dias'] ?? 0;
$diasTranscurridosSemana = (int)date('N'); // 1=Lunes, 7=Domingo
$porcentajeSemana = $diasTranscurridosSemana > 0 ? round(($diasEstaSemana / $diasTranscurridosSemana) * 100) : 0;

// Porcentaje de días practicados este mes
$stmt = $db->query("
    SELECT COUNT(DISTINCT fecha) as dias 
    FROM sesiones 
    WHERE YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) = MONTH(CURDATE())
");
$diasEsteMes = $stmt->fetch()['dias'] ?? 0;
$diasTranscurridosMes = (int)date('j'); // Día del mes
$porcentajeMes = $diasTranscurridosMes > 0 ? round(($diasEsteMes / $diasTranscurridosMes) * 100) : 0;

// Porcentaje de días practicados este año
$stmt = $db->query("
    SELECT COUNT(DISTINCT fecha) as dias 
    FROM sesiones 
    WHERE YEAR(fecha) = YEAR(CURDATE())
");
$diasEsteAno = $stmt->fetch()['dias'] ?? 0;
$diasTranscurridosAno = (int)date('z') + 1; // Día del año (0-indexed)
$porcentajeAno = $diasTranscurridosAno > 0 ? round(($diasEsteAno / $diasTranscurridosAno) * 100) : 0;

// Últimas 5 sesiones
$stmt = $db->prepare("
    SELECT s.*, 
        (SELECT SUM(tiempo_segundos) FROM actividades WHERE sesion_id = s.id) as tiempo_total,
        (SELECT ROUND(AVG(f.cantidad), 2)
         FROM fallos f 
         JOIN actividades a ON f.actividad_id = a.id 
         WHERE a.sesion_id = s.id 
         AND a.tipo = 'repertorio') as media_fallos_repertorio
    FROM sesiones s 
    ORDER BY fecha DESC, id DESC 
    LIMIT 5
");
$stmt->execute();
$ultimasSesiones = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="card">
    <h2>Dashboard</h2>
    
    <div class="stats-grid">
        <div class="stat-box">
            <h3><?php echo formatearTiempo($tiempoHoy); ?></h3>
            <p>Tiempo practicado hoy</p>
        </div>
        <div class="stat-box">
            <h3><?php echo formatearTiempo($tiempoMes); ?></h3>
            <p>Tiempo este mes</p>
        </div>
        <div class="stat-box">
            <h3><?php echo $numPiezas; ?></h3>
            <p>Piezas en repertorio</p>
        </div>
    </div>
</div>

<?php if ($sesionActiva): ?>
<div class="alert alert-info">
    <strong>Sesión en curso</strong> - 
    <a href="sesion.php?continuar=<?php echo $sesionActiva['id']; ?>" class="btn btn-primary btn-small">Continuar sesión</a>
</div>
<?php endif; ?>

<div class="card">
    <h2>📊 Estadísticas de práctica</h2>
    
    <div class="stats-grid">
        <div class="stat-box">
            <h3 style="color: var(--secondary);"><?php echo $rachaActual; ?> días</h3>
            <p>Racha actual</p>
            <?php if ($rachaActual > 0): ?>
            <small style="opacity: 0.7;">🔥 ¡Sigue así!</small>
            <?php endif; ?>
        </div>
        <div class="stat-box">
            <h3><?php echo $rachaMasLarga; ?> días</h3>
            <p>Racha más larga</p>
            <?php if ($rachaMasLarga > 7): ?>
            <small style="opacity: 0.7;">🏆 ¡Increíble!</small>
            <?php endif; ?>
        </div>
        <div class="stat-box">
            <h3><?php echo $porcentajeSemana; ?>%</h3>
            <p>Esta semana (<?php echo $diasEstaSemana; ?>/<?php echo $diasTranscurridosSemana; ?> días)</p>
        </div>
        <div class="stat-box">
            <h3><?php echo $porcentajeMes; ?>%</h3>
            <p>Este mes (<?php echo $diasEsteMes; ?>/<?php echo $diasTranscurridosMes; ?> días)</p>
        </div>
    </div>
</div>

<div class="card">
    <h2>Acciones rápidas</h2>
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <a href="sesion.php" class="btn btn-success">Nueva sesión</a>
        <a href="repertorio.php" class="btn btn-primary">Gestionar repertorio</a>
        <a href="informes.php" class="btn btn-warning">Ver informes</a>
    </div>
</div>

<div class="card">
    <h2>Últimas sesiones</h2>
    <?php if (empty($ultimasSesiones)): ?>
        <p>No hay sesiones registradas aún. <a href="sesion.php">Comienza tu primera sesión</a></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Tiempo total</th>
                    <th>Media fallos repertorio</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ultimasSesiones as $sesion): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($sesion['fecha'])); ?></td>
                    <td>
                        <?php 
                        $badges = [
                            'planificada' => '<span style="color: var(--warning)">Planificada</span>',
                            'en_curso' => '<span style="color: var(--secondary)">En curso</span>',
                            'finalizada' => '<span style="color: var(--success)">Finalizada</span>'
                        ];
                        echo $badges[$sesion['estado']];
                        ?>
                    </td>
                    <td><?php echo formatearTiempo($sesion['tiempo_total'] ?? 0); ?></td>
                    <td style="text-align: center;">
                        <?php 
                        if ($sesion['media_fallos_repertorio'] !== null) {
                            echo number_format($sesion['media_fallos_repertorio'], 2);
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($sesion['estado'] === 'planificada'): ?>
                            <a href="sesion.php?sesion=<?php echo $sesion['id']; ?>" class="btn btn-success btn-small">▶️ Iniciar sesión</a>
                        <?php endif; ?>
                        <a href="sesion.php?ver=<?php echo $sesion['id']; ?>" class="btn btn-primary btn-small">Ver detalles</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
