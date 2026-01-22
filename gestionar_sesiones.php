<?php
require_once 'config/database.php';
$pageTitle = 'Gestión de Sesiones - Piano Tracker';

$db = getDB();
$mensaje = '';
$error = '';
$sesionEditar = null;

// Obtener lista de piezas activas para el select
$stmtPiezas = $db->query("SELECT id, compositor, titulo FROM piezas WHERE activa = 1 ORDER BY compositor, titulo");
$piezas = $stmtPiezas->fetchAll();

// Acciones CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    // CREAR SESIÓN MANUAL
    if ($accion === 'crear') {
        try {
            $db->beginTransaction();
            
            // 1. Crear sesión
            $fecha = $_POST['fecha'];
            $stmt = $db->prepare("INSERT INTO sesiones (fecha, estado) VALUES (:fecha, 'finalizada')");
            $stmt->execute([':fecha' => $fecha]);
            $sesionId = $db->lastInsertId();
            
            // 2. Crear actividades
            $actividades = $_POST['actividades'] ?? [];
            $orden = 1;
            foreach ($actividades as $act) {
                if (empty($act['tipo'])) continue;
                
                $tiempoSegundos = intval($act['minutos']) * 60;
                
                $stmtAct = $db->prepare("INSERT INTO actividades 
                    (sesion_id, tipo, tiempo_segundos, notas, orden, estado) 
                    VALUES (:sesion_id, :tipo, :tiempo, :notas, :orden, 'completada')");
                
                $stmtAct->execute([
                    ':sesion_id' => $sesionId,
                    ':tipo' => $act['tipo'],
                    ':tiempo' => $tiempoSegundos,
                    ':notas' => $act['descripcion'] ?? null,
                    ':orden' => $orden++
                ]);
                
                $actividadId = $db->lastInsertId();
                
                // 3. Si es Repertorio, registrar piezas y fallos
                if ($act['tipo'] === 'repertorio' && !empty($act['piezas'])) {
                    $stmtFallos = $db->prepare("INSERT INTO fallos 
                        (actividad_id, pieza_id, cantidad, fecha_registro) 
                        VALUES (:act_id, :pieza_id, :cantidad, :fecha_registro)");
                    
                    foreach ($act['piezas'] as $pieza) {
                        if (empty($pieza['pieza_id'])) continue;
                        
                        // Usar la fecha de la sesión para el registro de fallos
                        $fechaRegistro = $fecha . ' ' . date('H:i:s');
                        
                        $stmtFallos->execute([
                            ':act_id' => $actividadId,
                            ':pieza_id' => $pieza['pieza_id'],
                            ':cantidad' => intval($pieza['fallos'] ?? 0),
                            ':fecha_registro' => $fechaRegistro
                        ]);
                    }
                }
            }
            
            $db->commit();
            $mensaje = "Sesión creada correctamente";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error al crear sesión: " . $e->getMessage();
        }
    }
    
    // ACTUALIZAR SESIÓN EXISTENTE
    if ($accion === 'actualizar') {
        try {
            $db->beginTransaction();
            
            $sesionId = $_POST['sesion_id'];
            $fecha = $_POST['fecha'];
            
            // 1. Actualizar fecha de sesión y estado
            $stmt = $db->prepare("UPDATE sesiones SET fecha = :fecha, estado = 'finalizada' WHERE id = :id");
            $stmt->execute([':fecha' => $fecha, ':id' => $sesionId]);
            
            // 2. Eliminar actividades y fallos existentes
            $stmt = $db->prepare("SELECT id FROM actividades WHERE sesion_id = :id");
            $stmt->execute([':id' => $sesionId]);
            $actividadIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($actividadIds)) {
                $placeholders = implode(',', array_fill(0, count($actividadIds), '?'));
                $stmt = $db->prepare("DELETE FROM fallos WHERE actividad_id IN ($placeholders)");
                $stmt->execute($actividadIds);
            }
            
            $stmt = $db->prepare("DELETE FROM actividades WHERE sesion_id = :id");
            $stmt->execute([':id' => $sesionId]);
            
            // 3. Crear actividades actualizadas
            $actividades = $_POST['actividades'] ?? [];
            $orden = 1;
            foreach ($actividades as $act) {
                if (empty($act['tipo'])) continue;
                
                $tiempoSegundos = intval($act['minutos']) * 60;
                
                $stmtAct = $db->prepare("INSERT INTO actividades 
                    (sesion_id, tipo, tiempo_segundos, notas, orden, estado) 
                    VALUES (:sesion_id, :tipo, :tiempo, :notas, :orden, 'completada')");
                
                $stmtAct->execute([
                    ':sesion_id' => $sesionId,
                    ':tipo' => $act['tipo'],
                    ':tiempo' => $tiempoSegundos,
                    ':notas' => $act['descripcion'] ?? null,
                    ':orden' => $orden++
                ]);
                
                $actividadId = $db->lastInsertId();
                
                // Si es Repertorio, registrar piezas y fallos
                if ($act['tipo'] === 'repertorio' && !empty($act['piezas'])) {
                    $stmtFallos = $db->prepare("INSERT INTO fallos 
                        (actividad_id, pieza_id, cantidad, fecha_registro) 
                        VALUES (:act_id, :pieza_id, :cantidad, :fecha_registro)");
                    
                    foreach ($act['piezas'] as $pieza) {
                        if (empty($pieza['pieza_id'])) continue;
                        
                        // Usar la fecha de la sesión para el registro de fallos
                        $fechaRegistro = $fecha . ' ' . date('H:i:s');
                        
                        $stmtFallos->execute([
                            ':act_id' => $actividadId,
                            ':pieza_id' => $pieza['pieza_id'],
                            ':cantidad' => intval($pieza['fallos'] ?? 0),
                            ':fecha_registro' => $fechaRegistro
                        ]);
                    }
                }
            }
            
            $db->commit();
            $mensaje = "Sesión actualizada correctamente";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error al actualizar sesión: " . $e->getMessage();
        }
    }
    
    // ELIMINAR SESIÓN
    if ($accion === 'eliminar') {
        try {
            $db->beginTransaction();
            
            $sesionId = $_POST['id'];
            
            // Obtener IDs de actividades
            $stmt = $db->prepare("SELECT id FROM actividades WHERE sesion_id = :id");
            $stmt->execute([':id' => $sesionId]);
            $actividadIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Eliminar fallos de esas actividades
            if (!empty($actividadIds)) {
                $placeholders = implode(',', array_fill(0, count($actividadIds), '?'));
                $stmt = $db->prepare("DELETE FROM fallos WHERE actividad_id IN ($placeholders)");
                $stmt->execute($actividadIds);
            }
            
            // Eliminar actividades
            $stmt = $db->prepare("DELETE FROM actividades WHERE sesion_id = :id");
            $stmt->execute([':id' => $sesionId]);
            
            // Eliminar sesión
            $stmt = $db->prepare("DELETE FROM sesiones WHERE id = :id");
            $stmt->execute([':id' => $sesionId]);
            
            $db->commit();
            $mensaje = "Sesión eliminada correctamente";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error al eliminar sesión: " . $e->getMessage();
        }
    }
}

// Cargar sesión para editar
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM sesiones WHERE id = :id");
    $stmt->execute([':id' => $_GET['editar']]);
    $sesionEditar = $stmt->fetch();
    
    if ($sesionEditar) {
        // Cargar actividades de esta sesión
        $stmt = $db->prepare("
            SELECT a.id, a.tipo, a.tiempo_segundos, a.notas, a.pieza_id
            FROM actividades a
            WHERE a.sesion_id = :id
            ORDER BY a.orden
        ");
        $stmt->execute([':id' => $sesionEditar['id']]);
        $actividades = $stmt->fetchAll();
        
        // Para cada actividad, convertir tiempo a minutos y cargar fallos
        foreach ($actividades as &$act) {
            $act['minutos'] = round($act['tiempo_segundos'] / 60);
            $act['descripcion'] = $act['notas']; // Mapear notas a descripcion para el formulario
            
            $stmt = $db->prepare("
                SELECT f.pieza_id, f.cantidad, p.compositor, p.titulo
                FROM fallos f
                LEFT JOIN piezas p ON f.pieza_id = p.id
                WHERE f.actividad_id = :act_id
                ORDER BY f.id
            ");
            $stmt->execute([':act_id' => $act['id']]);
            $act['fallos_piezas'] = $stmt->fetchAll();
            
            // Para compatibilidad con el formulario existente, si solo hay una pieza, usar formato antiguo
            if (count($act['fallos_piezas']) == 1) {
                $act['pieza_id'] = $act['fallos_piezas'][0]['pieza_id'];
                $act['fallos'] = $act['fallos_piezas'][0]['cantidad'];
                $act['compositor'] = $act['fallos_piezas'][0]['compositor'];
                $act['titulo'] = $act['fallos_piezas'][0]['titulo'];
            } elseif (empty($act['fallos_piezas'])) {
                $act['fallos'] = 0;
            }
        }
        unset($act);
        
        $sesionEditar['actividades'] = $actividades;
    }
}

// Obtener todas las sesiones
$stmt = $db->query("
    SELECT s.id, s.fecha,
           COUNT(a.id) as num_actividades,
           SUM(a.tiempo_segundos) as tiempo_total
    FROM sesiones s
    LEFT JOIN actividades a ON s.id = a.sesion_id
    GROUP BY s.id
    ORDER BY s.fecha DESC
    LIMIT 50
");
$sesiones = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- CSS de DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">

<style>
.actividad-row {
    background: #f8f9fa;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-radius: 4px;
    border-left: 3px solid var(--primary);
}
.actividad-row .form-inline {
    display: grid;
    grid-template-columns: 150px 80px 1fr;
    gap: 0.5rem;
    align-items: start;
}
.btn-add-actividad {
    margin-top: 1rem;
}
.piezas-repertorio {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: #e8f5e9;
    border-left: 3px solid #4caf50;
    border-radius: 4px;
}
.pieza-entry {
    display: grid;
    grid-template-columns: 1fr 120px 50px;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    align-items: center;
}
.pieza-entry:last-child {
    margin-bottom: 0;
}
</style>

<?php if ($mensaje): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<h2><?php echo $sesionEditar ? 'Editar Sesión' : 'Añadir Sesión Manual'; ?></h2>

<div class="card">
    <form method="POST" action="" id="formSesion">
        <input type="hidden" name="accion" value="<?php echo $sesionEditar ? 'actualizar' : 'crear'; ?>">
        <?php if ($sesionEditar): ?>
        <input type="hidden" name="sesion_id" value="<?php echo $sesionEditar['id']; ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label for="fecha">Fecha de la sesión *</label>
            <input type="date" id="fecha" name="fecha" required 
                   value="<?php echo $sesionEditar['fecha'] ?? date('Y-m-d'); ?>">
        </div>
        
        <h3>Actividades</h3>
        <div id="actividades-container">
            <?php if ($sesionEditar && !empty($sesionEditar['actividades'])): ?>
                <?php foreach ($sesionEditar['actividades'] as $index => $act): ?>
                <div class="actividad-row">
                    <div class="form-inline">
                        <div class="form-group">
                            <label>Tipo *</label>
                            <select name="actividades[<?php echo $index; ?>][tipo]" class="tipo-actividad" required>
                                <option value="">Seleccionar...</option>
                                <option value="calentamiento" <?php echo $act['tipo'] == 'calentamiento' ? 'selected' : ''; ?>>Calentamiento</option>
                                <option value="practica" <?php echo $act['tipo'] == 'practica' ? 'selected' : ''; ?>>Práctica</option>
                                <option value="tecnica" <?php echo $act['tipo'] == 'tecnica' ? 'selected' : ''; ?>>Técnica</option>
                                <option value="repertorio" <?php echo $act['tipo'] == 'repertorio' ? 'selected' : ''; ?>>Repertorio</option>
                                <option value="improvisacion" <?php echo $act['tipo'] == 'improvisacion' ? 'selected' : ''; ?>>Improvisación</option>
                                <option value="composicion" <?php echo $act['tipo'] == 'composicion' ? 'selected' : ''; ?>>Composición</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Minutos *</label>
                            <input type="number" name="actividades[<?php echo $index; ?>][minutos]" 
                                   min="1" required value="<?php echo round($act['tiempo_segundos'] / 60); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Descripción</label>
                            <input type="text" name="actividades[<?php echo $index; ?>][descripcion]" 
                                   value="<?php echo htmlspecialchars($act['descripcion'] ?? ''); ?>">
                        </div>
                        
                        <button type="button" class="btn btn-danger btn-small" onclick="eliminarActividad(this)">✕</button>
                    </div>
                    
                    <!-- Sección de piezas para Repertorio -->
                    <div class="piezas-repertorio" style="<?php echo $act['tipo'] !== 'repertorio' ? 'display:none;' : ''; ?>">
                        <strong>🎹 Piezas practicadas:</strong>
                        <div class="piezas-container">
                            <?php if (!empty($act['fallos_piezas'])): ?>
                                <?php foreach ($act['fallos_piezas'] as $fp_idx => $fp): ?>
                                <div class="pieza-entry">
                                    <select name="actividades[<?php echo $index; ?>][piezas][<?php echo $fp_idx; ?>][pieza_id]" required>
                                        <option value="">Seleccionar pieza...</option>
                                        <?php foreach ($piezas as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" <?php echo ($fp['pieza_id'] == $p['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['compositor'] . ' - ' . $p['titulo']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="actividades[<?php echo $index; ?>][piezas][<?php echo $fp_idx; ?>][fallos]" 
                                           min="0" value="<?php echo $fp['cantidad']; ?>" placeholder="Fallos" style="width: 100px;">
                                    <button type="button" class="btn btn-danger btn-small" onclick="eliminarPieza(this)">✕</button>
                                </div>
                                <?php endforeach; ?>
                            <?php elseif ($act['pieza_id']): ?>
                                <div class="pieza-entry">
                                    <select name="actividades[<?php echo $index; ?>][piezas][0][pieza_id]" required>
                                        <option value="">Seleccionar pieza...</option>
                                        <?php foreach ($piezas as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" <?php echo ($act['pieza_id'] == $p['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['compositor'] . ' - ' . $p['titulo']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="actividades[<?php echo $index; ?>][piezas][0][fallos]" 
                                           min="0" value="<?php echo $act['fallos'] ?? 0; ?>" placeholder="Fallos" style="width: 100px;">
                                    <button type="button" class="btn btn-danger btn-small" onclick="eliminarPieza(this)">✕</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-secondary btn-small" onclick="añadirPieza(this)" style="margin-top: 0.5rem;">+ Añadir pieza</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Primera actividad por defecto -->
                <div class="actividad-row">
                    <div class="form-inline">
                        <div class="form-group">
                            <label>Tipo *</label>
                            <select name="actividades[0][tipo]" class="tipo-actividad" required>
                                <option value="">Seleccionar...</option>
                                <option value="calentamiento">Calentamiento</option>
                                <option value="practica">Práctica</option>
                                <option value="tecnica">Técnica</option>
                                <option value="repertorio" selected>Repertorio</option>
                                <option value="improvisacion">Improvisación</option>
                                <option value="composicion">Composición</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Minutos *</label>
                            <input type="number" name="actividades[0][minutos]" min="1" value="20" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Descripción</label>
                            <input type="text" name="actividades[0][descripcion]">
                        </div>
                        
                        <button type="button" class="btn btn-danger btn-small" onclick="eliminarActividad(this)">✕</button>
                    </div>
                    
                    <!-- Sección de piezas para Repertorio -->
                    <div class="piezas-repertorio" style="display:block;">
                        <strong>🎹 Piezas practicadas:</strong>
                        <div class="piezas-container">
                            <div class="pieza-entry">
                                <select name="actividades[0][piezas][0][pieza_id]" required>
                                    <option value="">Seleccionar pieza...</option>
                                    <?php foreach ($piezas as $p): ?>
                                    <option value="<?php echo $p['id']; ?>">
                                        <?php echo htmlspecialchars($p['compositor'] . ' - ' . $p['titulo']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="actividades[0][piezas][0][fallos]" 
                                       min="0" value="0" placeholder="Fallos" style="width: 100px;">
                                <button type="button" class="btn btn-danger btn-small" onclick="eliminarPieza(this)">✕</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-small" onclick="añadirPieza(this)" style="margin-top: 0.5rem;">+ Añadir pieza</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <button type="button" class="btn btn-secondary btn-add-actividad" onclick="añadirActividad()">+ Añadir actividad</button>
        
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-success"><?php echo $sesionEditar ? 'Actualizar sesión' : 'Guardar sesión'; ?></button>
            <a href="gestionar_sesiones.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<h2 style="margin-top: 3rem;">Sesiones registradas</h2>

<div class="card">
    <div class="table-wrapper">
        <table id="tablaSesiones" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Actividades</th>
                    <th>Tiempo total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sesiones as $sesion): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($sesion['fecha'])); ?></td>
                    <td style="text-align: center;"><?php echo $sesion['num_actividades']; ?></td>
                    <td><?php echo formatearTiempo($sesion['tiempo_total']); ?></td>
                    <td style="white-space: nowrap;">
                        <a href="?editar=<?php echo $sesion['id']; ?>" class="btn btn-warning btn-small">✏️ Editar</a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta sesión? Esta acción no se puede deshacer.');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?php echo $sesion['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-small">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let actividadIndex = <?php echo $sesionEditar ? count($sesionEditar['actividades']) : 1; ?>;
let piezaIndices = {};

function añadirActividad() {
    const container = document.getElementById('actividades-container');
    const div = document.createElement('div');
    div.className = 'actividad-row';
    
    piezaIndices[actividadIndex] = 0;
    
    div.innerHTML = `
        <div class="form-inline">
            <div class="form-group">
                <label>Tipo *</label>
                <select name="actividades[${actividadIndex}][tipo]" class="tipo-actividad" required>
                    <option value="">Seleccionar...</option>
                    <option value="calentamiento">Calentamiento</option>
                    <option value="practica">Práctica</option>
                    <option value="tecnica">Técnica</option>
                    <option value="repertorio">Repertorio</option>
                    <option value="improvisacion">Improvisación</option>
                    <option value="composicion">Composición</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Minutos *</label>
                <input type="number" name="actividades[${actividadIndex}][minutos]" min="1" value="10" required>
            </div>
            
            <div class="form-group">
                <label>Descripción</label>
                <input type="text" name="actividades[${actividadIndex}][descripcion]">
            </div>
            
            <button type="button" class="btn btn-danger btn-small" onclick="eliminarActividad(this)">✕</button>
        </div>
        
        <div class="piezas-repertorio" style="display:none;">
            <strong>🎹 Piezas practicadas:</strong>
            <div class="piezas-container">
                <div class="pieza-entry">
                    <select name="actividades[${actividadIndex}][piezas][0][pieza_id]" required>
                        <option value="">Seleccionar pieza...</option>
                        <?php foreach ($piezas as $p): ?>
                        <option value="<?php echo $p['id']; ?>">
                            <?php echo htmlspecialchars($p['compositor'] . ' - ' . $p['titulo']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="actividades[${actividadIndex}][piezas][0][fallos]" 
                           min="0" value="0" placeholder="Fallos" style="width: 100px;">
                    <button type="button" class="btn btn-danger btn-small" onclick="eliminarPieza(this)">✕</button>
                </div>
            </div>
            <button type="button" class="btn btn-secondary btn-small" onclick="añadirPieza(this)" style="margin-top: 0.5rem;">+ Añadir pieza</button>
        </div>
    `;
    
    container.appendChild(div);
    
    // Añadir listener para mostrar/ocultar sección de piezas
    const select = div.querySelector('.tipo-actividad');
    select.addEventListener('change', function() {
        const piezasSection = this.closest('.actividad-row').querySelector('.piezas-repertorio');
        const piezasSelects = piezasSection.querySelectorAll('select');
        
        if (this.value === 'repertorio') {
            piezasSection.style.display = 'block';
            piezasSelects.forEach(s => s.required = true);
        } else {
            piezasSection.style.display = 'none';
            piezasSelects.forEach(s => s.required = false);
        }
    });
    
    actividadIndex++;
}

function eliminarActividad(btn) {
    const container = document.getElementById('actividades-container');
    if (container.children.length > 1) {
        btn.closest('.actividad-row').remove();
    } else {
        alert('Debe haber al menos una actividad');
    }
}

function añadirPieza(btn) {
    const container = btn.previousElementSibling; // .piezas-container
    const actRow = btn.closest('.actividad-row');
    const actIndex = Array.from(actRow.parentNode.children).indexOf(actRow);
    
    if (!piezaIndices[actIndex]) {
        piezaIndices[actIndex] = container.children.length;
    }
    
    const piezaIndex = piezaIndices[actIndex]++;
    
    const div = document.createElement('div');
    div.className = 'pieza-entry';
    div.innerHTML = `
        <select name="actividades[${actIndex}][piezas][${piezaIndex}][pieza_id]" required>
            <option value="">Seleccionar pieza...</option>
            <?php foreach ($piezas as $p): ?>
            <option value="<?php echo $p['id']; ?>">
                <?php echo htmlspecialchars($p['compositor'] . ' - ' . $p['titulo']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="actividades[${actIndex}][piezas][${piezaIndex}][fallos]" 
               min="0" value="0" placeholder="Fallos" style="width: 100px;">
        <button type="button" class="btn btn-danger btn-small" onclick="eliminarPieza(this)">✕</button>
    `;
    
    container.appendChild(div);
}

function eliminarPieza(btn) {
    const container = btn.closest('.piezas-container');
    if (container.children.length > 1) {
        btn.closest('.pieza-entry').remove();
    } else {
        alert('Debe haber al menos una pieza en Repertorio');
    }
}

// Listeners para actividades existentes
document.querySelectorAll('.tipo-actividad').forEach(select => {
    select.addEventListener('change', function() {
        const piezasSection = this.closest('.actividad-row').querySelector('.piezas-repertorio');
        const piezasSelects = piezasSection.querySelectorAll('select');
        
        if (this.value === 'repertorio') {
            piezasSection.style.display = 'block';
            piezasSelects.forEach(s => s.required = true);
        } else {
            piezasSection.style.display = 'none';
            piezasSelects.forEach(s => s.required = false);
        }
    });
    
    // Trigger inicial para ajustar el required según el valor actual
    const piezasSection = select.closest('.actividad-row').querySelector('.piezas-repertorio');
    const piezasSelects = piezasSection.querySelectorAll('select');
    if (select.value !== 'repertorio') {
        piezasSelects.forEach(s => s.required = false);
    }
});
</script>

<!-- JS de DataTables -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaSesiones').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todas"]],
        "order": [[0, "desc"]],  // Ordenar por fecha descendente (más reciente primero)
        "columnDefs": [
            { "orderable": false, "targets": -1 }  // Desactivar ordenamiento en columna Acciones
        ]
    });
});
</script>

<?php include 'includes/footer.php'; ?>
