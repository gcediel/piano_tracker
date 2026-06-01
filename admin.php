<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Requerir autenticación
requerirAuth();

// Inicializar conexión a BD
$db = getDB();

$pageTitle = 'Administración - Piano Tracker';
$mensaje = '';
$error = '';

// Exportar sesiones a CSV
if (isset($_GET['exportar_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="piano_tracker_sesiones_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, ['Fecha', 'Tipo Actividad', 'Tiempo (min)', 'Notas', 'Pieza', 'Compositor', 'Fallos']);
    
    // Datos
    $stmt = $db->query("
        SELECT s.fecha, a.tipo, 
               ROUND(a.tiempo_segundos / 60) as minutos,
               a.notas,
               p.titulo, p.compositor,
               COALESCE(f.cantidad, 0) as fallos
        FROM sesiones s
        JOIN actividades a ON s.id = a.sesion_id
        LEFT JOIN piezas p ON a.pieza_id = p.id
        LEFT JOIN fallos f ON f.actividad_id = a.id
        ORDER BY s.fecha DESC, a.id
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Exportar backup SQL
if (isset($_GET['exportar_sql'])) {
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="piano_tracker_backup_' . date('Y-m-d_H-i-s') . '.sql"');
    
    echo "-- Piano Tracker - Backup completo\n";
    echo "-- Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    // Tablas a exportar
    $tablas = ['configuracion', 'piezas', 'sesiones', 'actividades', 'fallos'];
    
    foreach ($tablas as $tabla) {
        echo "-- Tabla: $tabla\n";
        echo "TRUNCATE TABLE `$tabla`;\n";
        
        $stmt = $db->query("SELECT * FROM $tabla");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $columnas = array_keys($row);
                $valores = array_values($row);
                
                // Escapar valores
                $valores = array_map(function($val) use ($db) {
                    if ($val === null) return 'NULL';
                    return $db->quote($val);
                }, $valores);
                
                echo "INSERT INTO `$tabla` (`" . implode('`, `', $columnas) . "`) VALUES (" . implode(', ', $valores) . ");\n";
            }
        }
        echo "\n";
    }
    
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    echo "-- Fin del backup\n";
    exit;
}

// Importar backup SQL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    try {
        $archivo = $_FILES['sql_file'];
        
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo');
        }
        
        $contenido = file_get_contents($archivo['tmp_name']);
        
        if ($contenido === false) {
            throw new Exception('No se pudo leer el archivo');
        }
        
        // Ejecutar SQL
        $db->exec($contenido);
        $mensaje = 'Backup importado correctamente. Se han restaurado todos los datos.';
        
    } catch (Exception $e) {
        $error = 'Error al importar backup: ' . $e->getMessage();
    }
}

// Borrar todos los datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar_datos'])) {
    try {
        $db->beginTransaction();
        
        // Borrar en orden inverso por foreign keys
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $db->exec("TRUNCATE TABLE fallos");
        $db->exec("TRUNCATE TABLE actividades");
        $db->exec("TRUNCATE TABLE sesiones");
        $db->exec("TRUNCATE TABLE piezas");
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
        
        $db->commit();
        $mensaje = '✓ Todos los datos han sido eliminados correctamente. La configuración se ha mantenido.';
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error al borrar datos: ' . $e->getMessage();
    }
}

// Guardar configuración del metrónomo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_config_metro'])) {
    $bpmTecnica  = intval($_POST['metro_bpm_tecnica']  ?? 144);
    $bpmPractica = intval($_POST['metro_bpm_practica'] ?? 92);

    if ($bpmTecnica < 20 || $bpmTecnica > 300 || $bpmPractica < 20 || $bpmPractica > 300) {
        $error = 'Los valores de BPM deben estar entre 20 y 300.';
    } else {
        $stmt = $db->prepare("INSERT INTO configuracion (clave, valor, descripcion) VALUES (?, ?, ?)
                              ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['metro_bpm_tecnica',  $bpmTecnica,  'BPM por defecto del metrónomo para Técnica',  $bpmTecnica]);
        $stmt->execute(['metro_bpm_practica', $bpmPractica, 'BPM por defecto del metrónomo para Práctica', $bpmPractica]);
        $mensaje = '✓ Configuración del metrónomo guardada correctamente.';
    }
}

// Leer configuración del metrónomo
$configMetro = ['metro_bpm_tecnica' => 144, 'metro_bpm_practica' => 92];
$stmt = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('metro_bpm_tecnica', 'metro_bpm_practica')");
foreach ($stmt->fetchAll() as $row) {
    $configMetro[$row['clave']] = intval($row['valor']);
}

// Cambiar contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $passwordActual = $_POST['password_actual'] ?? '';
    $passwordNueva = $_POST['password_nueva'] ?? '';
    $passwordConfirmar = $_POST['password_confirmar'] ?? '';
    
    if (!verificarPassword($passwordActual)) {
        $error = 'La contraseña actual es incorrecta';
    } elseif (strlen($passwordNueva) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres';
    } elseif ($passwordNueva !== $passwordConfirmar) {
        $error = 'Las contraseñas nuevas no coinciden';
    } else {
        if (cambiarPassword($passwordNueva)) {
            $mensaje = 'Contraseña cambiada correctamente';
        } else {
            $error = 'Error al cambiar la contraseña';
        }
    }
}

include 'includes/header.php';
?>

<?php if ($mensaje): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <h2>🎵 Configuración del metrónomo</h2>
    <p>BPM que se asignará por defecto al metrónomo según el tipo de actividad.</p>

    <form method="POST" action="">
        <input type="hidden" name="guardar_config_metro" value="1">

        <div class="form-inline">
            <div class="form-group">
                <label for="metro_bpm_tecnica">BPM por defecto — Técnica</label>
                <input type="number" id="metro_bpm_tecnica" name="metro_bpm_tecnica"
                       min="20" max="300"
                       value="<?php echo $configMetro['metro_bpm_tecnica']; ?>" required>
            </div>
            <div class="form-group">
                <label for="metro_bpm_practica">BPM por defecto — Práctica</label>
                <input type="number" id="metro_bpm_practica" name="metro_bpm_practica"
                       min="20" max="300"
                       value="<?php echo $configMetro['metro_bpm_practica']; ?>" required>
            </div>
        </div>
        <small style="color: #666;">El Repertorio usa siempre el tempo de la pieza (o el BPM de Práctica si no tiene tempo asignado).</small>

        <div class="mt-1">
            <button type="submit" class="btn btn-primary">Guardar configuración</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Cambiar contraseña</h2>
    
    <form method="POST" action="">
        <input type="hidden" name="cambiar_password" value="1">
        
        <div class="form-group">
            <label for="password_actual">Contraseña actual</label>
            <input type="password" id="password_actual" name="password_actual" required>
        </div>
        
        <div class="form-group">
            <label for="password_nueva">Nueva contraseña (mínimo 6 caracteres)</label>
            <input type="password" id="password_nueva" name="password_nueva" required minlength="6">
        </div>
        
        <div class="form-group">
            <label for="password_confirmar">Confirmar nueva contraseña</label>
            <input type="password" id="password_confirmar" name="password_confirmar" required minlength="6">
        </div>
        
        <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
    </form>
</div>

<div class="card">
    <h2>⚙️ Gestión de sesiones</h2>
    <p>Añadir, editar o eliminar sesiones registradas manualmente.</p>
    <a href="gestionar_sesiones.php" class="btn btn-primary">Gestionar sesiones</a>
</div>

<div class="card">
    <h2>📊 Exportar datos</h2>
    
    <h3>Exportar sesiones a CSV</h3>
    <p>Descarga todas las sesiones y actividades en formato CSV para análisis externo.</p>
    <a href="?exportar_csv" class="btn btn-success">📄 Descargar sesiones.csv</a>
    
    <h3 style="margin-top: 1.5rem;">Exportar base de datos completa</h3>
    <p>Crea un backup completo de la base de datos en formato SQL.</p>
    <a href="?exportar_sql" class="btn btn-success">💾 Descargar piano_tracker_backup.sql</a>
</div>

<div class="card">
    <h2>📥 Importar base de datos</h2>
    <p><strong>⚠️ ADVERTENCIA:</strong> Esta acción reemplazará TODOS los datos actuales. Haz un backup primero.</p>
    
    <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('¿SEGURO que quieres importar este backup? Se perderán todos los datos actuales.');">
        <div class="form-group">
            <label for="sql_file">Selecciona archivo SQL de backup</label>
            <input type="file" id="sql_file" name="sql_file" accept=".sql" required>
        </div>
        
        <button type="submit" class="btn btn-danger">⚠️ Importar y reemplazar datos</button>
    </form>
</div>

<div class="card" style="border: 2px solid var(--danger);">
    <h2 style="color: var(--danger);">🗑️ Borrar todos los datos</h2>
    <p><strong>⚠️ PELIGRO MÁXIMO:</strong> Esta acción eliminará <strong>PERMANENTEMENTE</strong> todos los datos de la aplicación:</p>
    <ul style="color: var(--danger); font-weight: bold;">
        <li>Todas las sesiones de práctica</li>
        <li>Todas las actividades y tiempos registrados</li>
        <li>Todos los registros de fallos</li>
        <li>Todo el repertorio de piezas</li>
    </ul>
    <p><strong>SE MANTENDRÁ:</strong> La configuración de la aplicación (contraseña).</p>
    <p style="background: #fff3cd; padding: 1rem; border-left: 4px solid var(--warning); margin: 1rem 0;">
        💡 <strong>Recomendación:</strong> Exporta un backup SQL antes de borrar (ver sección anterior).
    </p>
    
    <form method="POST" onsubmit="return confirm('⚠️⚠️⚠️ CONFIRMA QUE QUIERES BORRAR TODOS LOS DATOS ⚠️⚠️⚠️\n\n¿Estás ABSOLUTAMENTE SEGURO?\n\nEsta acción NO se puede deshacer.\n\nSe perderán:\n• Todas las sesiones\n• Todas las actividades\n• Todos los registros de fallos\n• Todo el repertorio\n\n¿Continuar con el borrado?');">
        <input type="hidden" name="borrar_datos" value="1">
        <button type="submit" class="btn btn-danger" style="font-size: 1.1rem; padding: 0.8rem 1.5rem;">
            ☠️ SÍ, BORRAR TODOS LOS DATOS
        </button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
