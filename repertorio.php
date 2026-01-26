<?php
require_once 'config/database.php';
$pageTitle = 'Repertorio - Piano Tracker';
$db = getDB();

$mensaje = '';
$error = '';

// Procesar acciones CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        try {
            $stmt = $db->prepare("INSERT INTO piezas (compositor, titulo, libro, grado, tempo, ponderacion, instrumento) 
                                  VALUES (:compositor, :titulo, :libro, :grado, :tempo, :ponderacion, :instrumento)");
            $stmt->execute([
                ':compositor' => $_POST['compositor'],
                ':titulo' => $_POST['titulo'],
                ':libro' => $_POST['libro'] ?: null,
                ':grado' => $_POST['grado'] ?: null,
                ':tempo' => $_POST['tempo'] ?: null,
                ':ponderacion' => $_POST['ponderacion'] ?: 1.00,
                ':instrumento' => $_POST['instrumento'] ?: 'Piano'
            ]);
            $mensaje = 'Pieza añadida correctamente';
        } catch (PDOException $e) {
            $error = 'Error al añadir pieza: ' . $e->getMessage();
        }
    }
    
    if ($accion === 'editar') {
        try {
            $stmt = $db->prepare("UPDATE piezas SET compositor = :compositor, titulo = :titulo, 
                                  libro = :libro, grado = :grado, tempo = :tempo, ponderacion = :ponderacion, 
                                  instrumento = :instrumento
                                  WHERE id = :id");
            $stmt->execute([
                ':id' => $_POST['id'],
                ':compositor' => $_POST['compositor'],
                ':titulo' => $_POST['titulo'],
                ':libro' => $_POST['libro'] ?: null,
                ':grado' => $_POST['grado'] ?: null,
                ':tempo' => $_POST['tempo'] ?: null,
                ':ponderacion' => $_POST['ponderacion'] ?: 1.00,
                ':instrumento' => $_POST['instrumento'] ?: 'Piano'
            ]);
            $mensaje = 'Pieza actualizada correctamente';
        } catch (PDOException $e) {
            $error = 'Error al actualizar pieza: ' . $e->getMessage();
        }
    }
    
    if ($accion === 'desactivar') {
        try {
            $stmt = $db->prepare("UPDATE piezas SET activa = 0 WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            $mensaje = 'Pieza desactivada correctamente';
        } catch (PDOException $e) {
            $error = 'Error al desactivar pieza: ' . $e->getMessage();
        }
    }
    
    if ($accion === 'activar') {
        try {
            $stmt = $db->prepare("UPDATE piezas SET activa = 1 WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            $mensaje = 'Pieza activada correctamente';
        } catch (PDOException $e) {
            $error = 'Error al activar pieza: ' . $e->getMessage();
        }
    }
    
    if ($accion === 'eliminar') {
        try {
            // Verificar si la pieza tiene registros de práctica
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM fallos WHERE pieza_id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            $tieneRegistros = $stmt->fetch()['count'] > 0;
            
            if ($tieneRegistros) {
                $error = 'No se puede eliminar esta pieza porque tiene registros de práctica asociados. Puedes desactivarla en su lugar.';
            } else {
                $stmt = $db->prepare("DELETE FROM piezas WHERE id = :id");
                $stmt->execute([':id' => $_POST['id']]);
                $mensaje = 'Pieza eliminada correctamente';
            }
        } catch (PDOException $e) {
            $error = 'Error al eliminar pieza: ' . $e->getMessage();
        }
    }
}

// Obtener pieza para editar
$piezaEditar = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM piezas WHERE id = :id");
    $stmt->execute([':id' => $_GET['editar']]);
    $piezaEditar = $stmt->fetch();
}

// Obtener todas las piezas con estadísticas de los últimos 30 días
$fechaLimite = date('Y-m-d', strtotime('-30 days'));

$stmt = $db->prepare("
    SELECT 
        p.*,
        COALESCE(f_stats.total_fallos_30d, 0) as total_fallos_30d,
        COALESCE(f_stats.dias_practicados_30d, 0) as dias_practicados_30d,
        f_stats.media_fallos_dia
    FROM piezas p
    LEFT JOIN (
        SELECT 
            f.pieza_id,
            COUNT(DISTINCT DATE(f.fecha_registro)) as dias_practicados_30d,
            SUM(f.cantidad) as total_fallos_30d,
            -- Media: total fallos / días PRACTICADOS (no 30)
            ROUND(
                SUM(f.cantidad) / NULLIF(COUNT(DISTINCT DATE(f.fecha_registro)), 0),
            2) as media_fallos_dia
        FROM fallos f
        WHERE f.fecha_registro >= :fecha_limite
        GROUP BY f.pieza_id
    ) as f_stats ON p.id = f_stats.pieza_id
    ORDER BY p.compositor, p.titulo
");
$stmt->execute([':fecha_limite' => $fechaLimite]);
$piezas = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- CSS de DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">

<style>
.dataTables_wrapper {
    padding: 20px 0;
}
.dataTables_filter input {
    margin-left: 0.5em;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 0.5rem;
}
.dataTables_length select {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 0.5rem;
    margin: 0 0.5rem;
}
/* Optimizar tabla para que sea más compacta */
#tablaPiezas {
    font-size: 0.9rem;
}
#tablaPiezas th,
#tablaPiezas td {
    padding: 0.5rem 0.3rem !important;
}
#tablaPiezas .btn-small {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
    margin: 0.1rem;
}
/* Estilo para botones deshabilitados */
.btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
/* Reducir ancho de la tabla en el contenedor */
.table-wrapper {
    overflow-x: auto;
    max-width: 100%;
}
#tablaPiezas {
    width: 100% !important;
    table-layout: fixed;
}
</style>

<?php if ($mensaje): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <h2><?php echo $piezaEditar ? 'Editar pieza' : 'Añadir nueva pieza'; ?></h2>
    
    <form method="POST" action="">
        <input type="hidden" name="accion" value="<?php echo $piezaEditar ? 'editar' : 'crear'; ?>">
        <?php if ($piezaEditar): ?>
        <input type="hidden" name="id" value="<?php echo $piezaEditar['id']; ?>">
        <?php endif; ?>
        
        <div class="form-inline">
            <div class="form-group">
                <label for="compositor">Compositor *</label>
                <input type="text" id="compositor" name="compositor" required 
                       value="<?php echo htmlspecialchars($piezaEditar['compositor'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="titulo">Título *</label>
                <input type="text" id="titulo" name="titulo" required 
                       value="<?php echo htmlspecialchars($piezaEditar['titulo'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-inline">
            <div class="form-group">
                <label for="libro">Libro</label>
                <input type="text" id="libro" name="libro" 
                       value="<?php echo htmlspecialchars($piezaEditar['libro'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="grado">Grado</label>
                <input type="number" id="grado" name="grado" min="1" max="10" 
                       value="<?php echo htmlspecialchars($piezaEditar['grado'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="tempo">Tempo</label>
                <input type="number" id="tempo" name="tempo" min="1" max="300" 
                       value="<?php echo htmlspecialchars($piezaEditar['tempo'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-inline">
            <div class="form-group">
                <label for="ponderacion">Ponderación</label>
                <input type="number" id="ponderacion" name="ponderacion" step="0.01" min="0.01" max="10" 
                       value="<?php echo htmlspecialchars($piezaEditar['ponderacion'] ?? '1.00'); ?>">
            </div>
            
            <div class="form-group">
                <label for="instrumento">Instrumento</label>
                <input type="text" id="instrumento" name="instrumento" 
                       value="<?php echo htmlspecialchars($piezaEditar['instrumento'] ?? 'Piano'); ?>"
                       placeholder="Piano o 0">
                <small style="color: #666; font-size: 0.85rem;">Piano o 0 para piano. Número para otro instrumento.</small>
            </div>
        </div>
        
        <button type="submit" class="btn btn-success">
            <?php echo $piezaEditar ? 'Actualizar pieza' : 'Añadir pieza'; ?>
        </button>
        <?php if ($piezaEditar): ?>
        <a href="repertorio.php" class="btn btn-primary">Cancelar</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>Listado de piezas</h2>
    
    <div class="alert alert-info" style="margin-bottom: 1rem;">
        <strong>ℹ️ Para eliminar una pieza:</strong> Primero debes <strong>desactivarla</strong> usando el botón amarillo "Desactivar". Una vez desactivada, aparecerá el botón rojo "Eliminar". Solo se pueden eliminar piezas que no tengan registros de práctica asociados.
    </div>
    
    <?php if (empty($piezas)): ?>
        <p>No hay piezas en el repertorio. Añade tu primera pieza usando el formulario anterior.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table id="tablaPiezas" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>Compositor</th>
                        <th>Título</th>
                        <th>Libro</th>
                        <th>Gr.</th>
                        <th>Tempo</th>
                        <th>Pond.</th>
                        <th>Instr.</th>
                        <th title="Días practicados últimos 30 días">Días</th>
                        <th title="Media de fallos por día (últimos 30 días)">M.Fallos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($piezas as $pieza): 
                    // Cálculo de color y estado según nueva paleta (adaptada para daltonismo)
                    $colorFondo = '#999';
                    $colorTexto = 'white';
                    $estadoTexto = 'Sin datos';
                    
                    if ($pieza['media_fallos_dia'] !== null) {
                        $media = $pieza['media_fallos_dia'];
                        if ($media < 0.5) {
                            $colorFondo = '#2E5F8A';  // Azul oscuro
                            $colorTexto = 'white';
                            $estadoTexto = 'Excelente';
                        } elseif ($media < 1.5) {
                            $colorFondo = '#4A7BA7';  // Azul medio
                            $colorTexto = 'white';
                            $estadoTexto = 'Muy bien';
                        } elseif ($media < 2.5) {
                            $colorFondo = '#A3C1DA';  // Azul claro
                            $colorTexto = 'black';
                            $estadoTexto = 'Bien';
                        } elseif ($media < 3.5) {
                            $colorFondo = '#D4E89E';  // Verde
                            $colorTexto = 'black';
                            $estadoTexto = 'Aceptable';
                        } elseif ($media <= 5) {
                            $colorFondo = '#9B9B9B';  // Gris
                            $colorTexto = 'white';
                            $estadoTexto = 'Mejorable';
                        } else {
                            $colorFondo = '#E57373';  // Rojo
                            $colorTexto = 'white';
                            $estadoTexto = 'Atención';
                        }
                    }
                    
                    $instrumentoDisplay = $pieza['instrumento'];
                    if ($instrumentoDisplay === '0' || strtolower($instrumentoDisplay) === 'piano') {
                        $instrumentoDisplay = 'Piano';
                    }
                    ?>
                    <tr style="<?php echo !$pieza['activa'] ? 'opacity: 0.5;' : ''; ?>">
                        <td><?php echo htmlspecialchars($pieza['compositor']); ?></td>
                        <td><?php echo htmlspecialchars($pieza['titulo']); ?></td>
                        <td><?php echo htmlspecialchars($pieza['libro'] ?? '-'); ?></td>
                        <td><?php echo $pieza['grado'] ?? '-'; ?></td>
                        <td><?php echo $pieza['tempo'] ?? '-'; ?></td>
                        <td><?php echo number_format($pieza['ponderacion'], 2); ?></td>
                        <td><?php echo htmlspecialchars($instrumentoDisplay); ?></td>
                        <td style="text-align: center;">
                            <?php echo $pieza['dias_practicados_30d'] > 0 ? $pieza['dias_practicados_30d'] : '-'; ?>
                        </td>
                        <td style="text-align: center;" data-order="<?php echo $pieza['media_fallos_dia'] ?? 999; ?>">
                            <?php if ($pieza['media_fallos_dia'] !== null): ?>
                                <div style="background: <?php echo $colorFondo; ?>; color: <?php echo $colorTexto; ?>; padding: 0.5rem; border-radius: 4px; font-weight: bold;">
                                    <?php echo number_format($pieza['media_fallos_dia'], 2); ?>
                                    <small style="display: block; font-size: 0.75em; opacity: 0.9;">
                                        (<?php echo $estadoTexto; ?>)
                                    </small>
                                </div>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $pieza['activa'] ? 
                                '<span style="color: var(--success)">✓ Activa</span>' : 
                                '<span style="color: var(--danger)">✗ Inactiva</span>'; ?>
                        </td>
                        <td style="padding: 0.3rem;">
                            <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                <a href="?editar=<?php echo $pieza['id']; ?>" class="btn btn-primary" style="padding: 0.2rem 0.4rem; font-size: 0.75rem; text-align: center;">Editar</a>
                                <form method="POST" onsubmit="return confirm('¿<?php echo $pieza['activa'] ? 'Desactivar' : 'Activar'; ?> esta pieza?');">
                                    <input type="hidden" name="id" value="<?php echo $pieza['id']; ?>">
                                    <input type="hidden" name="accion" value="<?php echo $pieza['activa'] ? 'desactivar' : 'activar'; ?>">
                                    <button type="submit" class="btn <?php echo $pieza['activa'] ? 'btn-warning' : 'btn-success'; ?>" style="padding: 0.2rem 0.4rem; font-size: 0.75rem; width: 100%;">
                                        <?php echo $pieza['activa'] ? 'Desactivar' : 'Activar'; ?>
                                    </button>
                                </form>
                                <?php if ($pieza['activa']): ?>
                                <button type="button" class="btn btn-danger" disabled title="Primero debes desactivar la pieza" style="padding: 0.2rem 0.4rem; font-size: 0.75rem;">
                                    Eliminar
                                </button>
                                <?php else: ?>
                                <form method="POST" onsubmit="return confirm('⚠️ ¿ELIMINAR permanentemente esta pieza?\n\nEsta acción NO se puede deshacer.\n\nSolo se puede eliminar si no tiene registros de práctica.');">
                                    <input type="hidden" name="id" value="<?php echo $pieza['id']; ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <button type="submit" class="btn btn-danger" style="padding: 0.2rem 0.4rem; font-size: 0.75rem; width: 100%;">Eliminar</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 4px; font-size: 0.9rem;">
            <strong>📊 Explicación de columnas estadísticas:</strong>
            <ul style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                <li><strong>Días:</strong> Número de días DISTINTOS en que se interpretó la pieza en los últimos 30 días naturales (calendario).</li>
                <li><strong>M.Fallos:</strong> Media de fallos/día en los últimos 30 días naturales. Se calcula: (Total de fallos últimos 30 días) / 30.</li>
            </ul>
            
            <strong>Leyenda de Media de fallos/día (últimos 30 días) - Paleta adaptada para daltonismo:</strong>
            <ul style="margin-top: 0.5rem; margin-bottom: 0;">
                <li><span style="background: #2E5F8A; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">🔵 Azul oscuro (&lt; 0.5 fallos/día):</span> Excelente - Dominio total de la pieza</li>
                <li><span style="background: #4A7BA7; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">🔵 Azul medio (0.5-1.5 fallos/día):</span> Muy bien - Pieza muy bien trabajada</li>
                <li><span style="background: #A3C1DA; color: black; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">🔵 Azul claro (1.5-2.5 fallos/día):</span> Bien - Buen nivel de ejecución</li>
                <li><span style="background: #D4E89E; color: black; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">🟢 Verde (2.5-3.5 fallos/día):</span> Aceptable - Progreso adecuado</li>
                <li><span style="background: #9B9B9B; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">⚪ Gris (3.5-5 fallos/día):</span> Mejorable - Necesita más práctica</li>
                <li><span style="background: #E57373; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-weight: bold;">🔴 Rojo (&gt; 5 fallos/día):</span> Atención - Requiere trabajo intensivo</li>
                <li><span style="color: #999; font-weight: bold;">⚫ Sin color (-):</span> Sin datos - No practicada en los últimos 30 días</li>
            </ul>
            <p style="margin-top: 0.5rem; margin-bottom: 0; color: #666;">
                <em><strong>Importante:</strong> La ponderación NO afecta la media de fallos mostrada. Solo se usa en el algoritmo de selección de piezas durante la práctica.</em>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- JS de DataTables -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaPiezas').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todas"]],
        "order": [[0, "asc"]],
        "columnDefs": [
            { "orderable": false, "targets": -1 }, // Desactivar ordenamiento en columna Acciones
            { "width": "13%", "targets": 0 },  // Compositor
            { "width": "16%", "targets": 1 },  // Título
            { "width": "13%", "targets": 2 },  // Libro
            { "width": "5%", "targets": 3 },   // Grado
            { "width": "6%", "targets": 4 },   // Tempo
            { "width": "6%", "targets": 5 },   // Ponderación
            { "width": "7%", "targets": 6 },   // Instrumento
            { "width": "5%", "targets": 7 },   // Días
            { "width": "9%", "targets": 8 },   // Media
            { "width": "6%", "targets": 9 },   // Estado
            { "width": "14%", "targets": 10 }  // Acciones
        ],
        "autoWidth": false,
        "scrollX": false
    });
});
</script>

<?php include 'includes/footer.php'; ?>
