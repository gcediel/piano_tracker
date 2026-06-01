<?php
require_once 'config/database.php';
$pageTitle = 'Sesión de práctica - Piano Tracker';
$db = getDB();

// ============================================
// PROCESAR PETICIONES AJAX
// ============================================
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $accion = $input['accion'] ?? '';
    
    try {
        switch ($accion) {
            case 'iniciar':
                $actividadId = $input['actividad_id'];
                $stmt = $db->prepare("UPDATE actividades SET estado = 'en_curso', fecha_inicio = NOW() WHERE id = :id");
                $stmt->execute([':id' => $actividadId]);
                
                // Actualizar sesión a 'en_curso' si estaba planificada
                $stmt = $db->prepare("UPDATE sesiones s 
                                      JOIN actividades a ON s.id = a.sesion_id 
                                      SET s.estado = 'en_curso' 
                                      WHERE a.id = :id AND s.estado = 'planificada'");
                $stmt->execute([':id' => $actividadId]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'guardar':
                $actividadId = $input['actividad_id'];
                $tiempo = $input['tiempo'];
                $stmt = $db->prepare("UPDATE actividades SET tiempo_segundos = :tiempo WHERE id = :id");
                $stmt->execute([':tiempo' => $tiempo, ':id' => $actividadId]);
                echo json_encode(['success' => true]);
                break;
                
            case 'guardar_notas':
                $actividadId = $input['actividad_id'];
                $notas = $input['notas'];
                $stmt = $db->prepare("UPDATE actividades SET notas = :notas WHERE id = :id");
                $stmt->execute([':notas' => $notas, ':id' => $actividadId]);
                echo json_encode(['success' => true]);
                break;

            case 'guardar_tempo':
                $piezaId = intval($input['pieza_id']);
                $tempo   = intval($input['tempo']);
                if ($piezaId < 1 || $tempo < 20 || $tempo > 300) {
                    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
                    break;
                }
                $stmt = $db->prepare("UPDATE piezas SET tempo = :tempo WHERE id = :id");
                $stmt->execute([':tempo' => $tempo, ':id' => $piezaId]);
                echo json_encode(['success' => true]);
                break;
                
            case 'completar_pieza':
                $actividadId = $input['actividad_id'];
                $piezaId = $input['pieza_id'];
                $fallos = $input['fallos'];
                $tiempo = $input['tiempo'];
                
                // Guardar tiempo actual
                $stmt = $db->prepare("UPDATE actividades SET tiempo_segundos = :tiempo WHERE id = :id");
                $stmt->execute([':tiempo' => $tiempo, ':id' => $actividadId]);
                
                // Registrar fallos
                $stmt = $db->prepare("INSERT INTO fallos (actividad_id, pieza_id, cantidad, fecha_registro) 
                                      VALUES (:act_id, :pieza_id, :cantidad, NOW())");
                $stmt->execute([
                    ':act_id' => $actividadId,
                    ':pieza_id' => $piezaId,
                    ':cantidad' => $fallos
                ]);
                
                // Obtener piezas ya practicadas en esta actividad
                $stmt = $db->prepare("SELECT pieza_id FROM fallos WHERE actividad_id = :id");
                $stmt->execute([':id' => $actividadId]);
                $piezasYaSeleccionadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Obtener siguiente pieza
                $siguientePieza = obtenerPiezaSugerida($db, $piezasYaSeleccionadas);
                
                if ($siguientePieza) {
                    echo json_encode([
                        'success' => true,
                        'siguiente_pieza' => [
                            'id' => $siguientePieza['id'],
                            'compositor' => $siguientePieza['compositor'],
                            'titulo' => $siguientePieza['titulo'],
                            'tempo' => $siguientePieza['tempo']
                        ]
                    ]);
                } else {
                    // No hay más piezas, devolver éxito pero sin siguiente
                    // Esto permitirá al frontend limpiar el piezaId
                    echo json_encode(['success' => true, 'siguiente_pieza' => null]);
                }
                break;
                
            case 'terminar_repertorio':
            case 'siguiente':
                $actividadId = $input['actividad_id'];
                $tiempo = $input['tiempo'];
                $piezaId = $input['pieza_id'] ?? null;
                $fallos = $input['fallos'] ?? 0;
                
                // Guardar tiempo
                $stmt = $db->prepare("UPDATE actividades SET tiempo_segundos = :tiempo WHERE id = :id");
                $stmt->execute([':tiempo' => $tiempo, ':id' => $actividadId]);
                
                // Si hay pieza pendiente, registrar fallos
                if ($piezaId) {
                    $stmt = $db->prepare("INSERT INTO fallos (actividad_id, pieza_id, cantidad, fecha_registro) 
                                          VALUES (:act_id, :pieza_id, :cantidad, NOW())");
                    $stmt->execute([
                        ':act_id' => $actividadId,
                        ':pieza_id' => $piezaId,
                        ':cantidad' => $fallos
                    ]);
                }
                
                // Marcar actividad como completada
                $stmt = $db->prepare("UPDATE actividades SET estado = 'completada', fecha_fin = NOW() WHERE id = :id");
                $stmt->execute([':id' => $actividadId]);
                
                // Verificar si hay siguiente actividad
                $stmt = $db->prepare("
                    SELECT a.sesion_id 
                    FROM actividades a 
                    WHERE a.id = :id
                ");
                $stmt->execute([':id' => $actividadId]);
                $sesionId = $stmt->fetch()['sesion_id'];
                
                $stmt = $db->prepare("
                    SELECT COUNT(*) as pendientes 
                    FROM actividades 
                    WHERE sesion_id = :sesion_id 
                    AND estado IN ('pendiente', 'en_curso')
                ");
                $stmt->execute([':sesion_id' => $sesionId]);
                $hayPendientes = $stmt->fetch()['pendientes'] > 0;
                
                echo json_encode([
                    'success' => true,
                    'hay_siguiente' => $hayPendientes
                ]);
                break;
                
            case 'finalizar':
                $sesionId = $input['sesion_id'];
                $actividadId = $input['actividad_id'];
                $tiempo = $input['tiempo'];
                $piezaId = $input['pieza_id'] ?? null;
                $fallos = $input['fallos'] ?? 0;
                
                // Guardar tiempo de actividad actual
                $stmt = $db->prepare("UPDATE actividades SET tiempo_segundos = :tiempo WHERE id = :id");
                $stmt->execute([':tiempo' => $tiempo, ':id' => $actividadId]);
                
                // Si hay pieza pendiente, registrar fallos
                if ($piezaId) {
                    $stmt = $db->prepare("INSERT INTO fallos (actividad_id, pieza_id, cantidad, fecha_registro) 
                                          VALUES (:act_id, :pieza_id, :cantidad, NOW())");
                    $stmt->execute([
                        ':act_id' => $actividadId,
                        ':pieza_id' => $piezaId,
                        ':cantidad' => $fallos
                    ]);
                }
                
                // Marcar actividad actual como completada
                $stmt = $db->prepare("UPDATE actividades SET estado = 'completada', fecha_fin = NOW() WHERE id = :id");
                $stmt->execute([':id' => $actividadId]);
                
                // Marcar todas las actividades pendientes como completadas
                $stmt = $db->prepare("UPDATE actividades SET estado = 'completada' WHERE sesion_id = :id AND estado = 'pendiente'");
                $stmt->execute([':id' => $sesionId]);
                
                // Marcar sesión como finalizada
                $stmt = $db->prepare("UPDATE sesiones SET estado = 'finalizada' WHERE id = :id");
                $stmt->execute([':id' => $sesionId]);
                
                echo json_encode(['success' => true]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================
// LÓGICA NORMAL DE LA PÁGINA
// ============================================

$mensaje = '';
$error = '';

// Ver detalles de una sesión
if (isset($_GET['ver'])) {
    $stmt = $db->prepare("SELECT * FROM sesiones WHERE id = :id");
    $stmt->execute([':id' => $_GET['ver']]);
    $sesionVer = $stmt->fetch();
    
    if ($sesionVer) {
        $stmt = $db->prepare("SELECT a.*, p.compositor, p.titulo, p.tempo 
                              FROM actividades a 
                              LEFT JOIN piezas p ON a.pieza_id = p.id
                              WHERE a.sesion_id = :id 
                              ORDER BY a.orden");
        $stmt->execute([':id' => $_GET['ver']]);
        $actividadesVer = $stmt->fetchAll();
    }
}

// Continuar sesión existente
if (isset($_GET['continuar'])) {
    $sesionId = $_GET['continuar'];
    header("Location: sesion.php?sesion=" . $sesionId);
    exit;
}

// Procesar creación de sesión y planificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_sesion'])) {
    try {
        $db->beginTransaction();
        
        // Eliminar sesiones programadas pendientes antes de crear una nueva
        $stmt = $db->prepare("
            DELETE FROM sesiones 
            WHERE estado = 'planificada' 
            AND fecha = CURDATE()
        ");
        $stmt->execute();
        
        $iniciarAhora = isset($_POST['iniciar_ahora']);
        
        // Crear nueva sesión
        $stmt = $db->prepare("INSERT INTO sesiones (fecha, estado) VALUES (CURDATE(), 'planificada')");
        $stmt->execute();
        $sesionId = $db->lastInsertId();
        
        // Procesar actividades planificadas
        $actividades = $_POST['actividades'] ?? [];
        $piezasSeleccionadas = [];
        $orden = 1;
        
        foreach ($actividades as $act) {
            $tipo = $act['tipo'];
            $notas = $act['notas'] ?? '';
            $piezaId = null;
            
            // Si es repertorio, obtener pieza sugerida
            if ($tipo === 'repertorio') {
                $piezaSugerida = obtenerPiezaSugerida($db, $piezasSeleccionadas);
                if ($piezaSugerida) {
                    $piezaId = $piezaSugerida['id'];
                    $piezasSeleccionadas[] = $piezaId;
                }
            }
            
            $stmt = $db->prepare("INSERT INTO actividades (sesion_id, orden, tipo, pieza_id, notas, estado) 
                                  VALUES (:sesion_id, :orden, :tipo, :pieza_id, :notas, 'pendiente')");
            $stmt->execute([
                ':sesion_id' => $sesionId,
                ':orden' => $orden++,
                ':tipo' => $tipo,
                ':pieza_id' => $piezaId,
                ':notas' => $notas
            ]);
        }
        
        $db->commit();
        
        if ($iniciarAhora) {
            header("Location: sesion.php?sesion=" . $sesionId);
            exit;
        } else {
            $mensaje = '✓ Sesión preparada correctamente. Puedes iniciarla cuando quieras desde el Dashboard.';
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error al crear sesión: ' . $e->getMessage();
    }
}

// Cargar última sesión para precargar formulario
$ultimaSesion = null;
$ultimasActividades = [];

if (!isset($_GET['sesion']) && !isset($_GET['ver'])) {
    $stmt = $db->query("SELECT * FROM sesiones ORDER BY fecha DESC, id DESC LIMIT 1");
    $ultimaSesion = $stmt->fetch();
    
    if ($ultimaSesion) {
        $stmt = $db->prepare("SELECT tipo, notas FROM actividades WHERE sesion_id = :id ORDER BY orden");
        $stmt->execute([':id' => $ultimaSesion['id']]);
        $ultimasActividades = $stmt->fetchAll();
    }
}

// Cargar sesión activa
$sesion = null;
$actividades = [];
$actividadActual = null;

if (isset($_GET['sesion'])) {
    $stmt = $db->prepare("SELECT * FROM sesiones WHERE id = :id");
    $stmt->execute([':id' => $_GET['sesion']]);
    $sesion = $stmt->fetch();
    
    if ($sesion) {
        $stmt = $db->prepare("SELECT a.*, p.compositor, p.titulo, p.tempo 
                              FROM actividades a 
                              LEFT JOIN piezas p ON a.pieza_id = p.id
                              WHERE a.sesion_id = :id 
                              ORDER BY a.orden");
        $stmt->execute([':id' => $sesion['id']]);
        $actividades = $stmt->fetchAll();
        
        // Buscar actividad actual
        foreach ($actividades as $act) {
            if ($act['estado'] === 'en_curso') {
                $actividadActual = $act;
                break;
            }
        }
        
        // Si no hay actividad en curso pero hay pendientes, tomar la primera
        if (!$actividadActual) {
            foreach ($actividades as $act) {
                if ($act['estado'] === 'pendiente') {
                    $actividadActual = $act;
                    break;
                }
            }
        }
        
        // Obtener piezas ya practicadas en esta sesión
        $stmt = $db->prepare("
            SELECT p.compositor, p.titulo, f.cantidad as fallos, f.fecha_registro
            FROM fallos f
            JOIN piezas p ON f.pieza_id = p.id
            JOIN actividades a ON f.actividad_id = a.id
            WHERE a.sesion_id = :sesion_id
            ORDER BY f.fecha_registro DESC
        ");
        $stmt->execute([':sesion_id' => $sesion['id']]);
        $piezasPracticadas = $stmt->fetchAll();
    }
}

// Calcular BPM por defecto del metrónomo para la actividad actual
$defaultBpm = 92;
if ($actividadActual) {
    $bpmTecnica  = 144;
    $bpmPractica = 92;
    $stmtCfg = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('metro_bpm_tecnica', 'metro_bpm_practica')");
    foreach ($stmtCfg->fetchAll() as $cfgRow) {
        if ($cfgRow['clave'] === 'metro_bpm_tecnica')  $bpmTecnica  = intval($cfgRow['valor']);
        if ($cfgRow['clave'] === 'metro_bpm_practica') $bpmPractica = intval($cfgRow['valor']);
    }
    if ($actividadActual['tipo'] === 'tecnica') {
        $defaultBpm = $bpmTecnica;
    } elseif ($actividadActual['tipo'] === 'repertorio') {
        $defaultBpm = $actividadActual['tempo'] ?: $bpmPractica;
    } else {
        $defaultBpm = $bpmPractica;
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

<?php if (isset($sesionVer)): ?>
    <!-- Vista de detalles de sesión -->
    <div class="card">
        <h2>Detalles de sesión - <?php echo date('d/m/Y', strtotime($sesionVer['fecha'])); ?></h2>
        <p><strong>Estado:</strong> <?php echo ucfirst($sesionVer['estado']); ?></p>
        
        <?php if (!empty($actividadesVer)): ?>
        <table>
            <thead>
                <tr>
                    <th>Actividad</th>
                    <th>Descripción</th>
                    <th>Tiempo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($actividadesVer as $act): ?>
                <tr>
                    <td><?php echo getNombreActividad($act['tipo']); ?></td>
                    <td>
                        <?php if ($act['tipo'] === 'repertorio' && $act['compositor']): ?>
                            <strong>🎹 <?php echo htmlspecialchars($act['compositor'] . ' - ' . $act['titulo']); ?>
                            <?php if ($act['tempo']): ?>
                            (♩ = <?php echo $act['tempo']; ?>)
                            <?php endif; ?>
                            </strong>
                            <?php if ($act['notas']): ?>
                                <br><small><?php echo nl2br(htmlspecialchars($act['notas'])); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo $act['notas'] ? nl2br(htmlspecialchars($act['notas'])) : '-'; ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo formatearTiempo($act['tiempo_segundos']); ?></td>
                    <td><?php echo ucfirst($act['estado']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <a href="sesion.php" class="btn btn-primary mt-1">Volver</a>
    </div>
<?php elseif ($sesion): ?>
    <!-- Vista de sesión activa con timer -->
    <div class="card">
        <h2>Sesión en curso - <?php echo date('d/m/Y', strtotime($sesion['fecha'])); ?></h2>
        
        <?php if ($actividadActual): ?>
        <div class="timer-display" id="timerDisplay">
            <div class="actividad-actual" id="infoActividad">
                <strong><?php echo getNombreActividad($actividadActual['tipo']); ?></strong>
                <span id="piezaActualInfo">
                    <?php if ($actividadActual['compositor']): ?>
                    <br><small><?php echo htmlspecialchars($actividadActual['compositor'] . ' - ' . $actividadActual['titulo']); ?>
                    <?php if ($actividadActual['tempo']): ?>
                     (♩ = <?php echo $actividadActual['tempo']; ?>)
                    <?php endif; ?>
                    </small>
                    <?php endif; ?>
                </span>
                <?php if ($actividadActual['notas']): ?>
                <br><small id="notasActuales"><?php echo nl2br(htmlspecialchars($actividadActual['notas'])); ?></small>
                <?php endif; ?>
                <br>
                <div style="margin-top: 0.5rem;">
                    <input type="text" id="notasActividad"
                           placeholder="Añadir/editar notas de esta actividad..."
                           value="<?php echo htmlspecialchars($actividadActual['notas'] ?? ''); ?>"
                           style="width: 100%; max-width: 500px; padding: 0.4rem; border-radius: 4px;">
                    <button onclick="guardarNotas()" class="btn btn-small btn-primary" style="margin-left: 0.5rem;">
                        💾 Guardar notas
                    </button>
                </div>
                <?php if ($actividadActual['tipo'] === 'repertorio'): ?>
                <div style="margin-top: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; flex-wrap: wrap;">
                    <span style="color: rgba(255,255,255,0.85); font-size: 0.95rem;">Tempo:</span>
                    <input type="number" id="tempoEditar"
                           value="<?php echo $actividadActual['tempo'] ?: $defaultBpm; ?>"
                           min="20" max="300"
                           oninput="tempoInputChanged(this.value)"
                           style="width: 80px; text-align: center; padding: 0.4rem; border-radius: 4px; border: none; font-size: 1rem; touch-action: manipulation;">
                    <button onclick="guardarTempoPieza()" class="btn btn-small btn-primary" style="touch-action: manipulation;">
                        💾 Guardar tempo
                    </button>
                    <span id="tempoGuardadoMsg" style="font-size: 0.85rem; color: #2ecc71; display: none;">✓ Guardado</span>
                </div>
                <?php endif; ?>
            </div>
            <h2 id="timerTime">00:00:00</h2>
            
            <?php
            // Determinar si es la última actividad
            $esUltimaActividad = true;
            $hayActividadesCompletadas = false;
            foreach ($actividades as $act) {
                if ($act['estado'] === 'completada') {
                    $hayActividadesCompletadas = true;
                }
                if ($act['id'] != $actividadActual['id'] && $act['estado'] === 'pendiente') {
                    $esUltimaActividad = false;
                    break;
                }
            }
            ?>
            
            <!-- Botones para actividades normales -->
            <div class="timer-controls" id="controlesNormales" style="<?php echo $actividadActual['tipo'] === 'repertorio' ? 'display:none;' : ''; ?>">
                <?php if (!$hayActividadesCompletadas): ?>
                <button id="btnIniciar" class="btn btn-success" onclick="iniciarTimer()">Iniciar</button>
                <?php endif; ?>
                <button id="btnPausar" class="btn btn-warning" onclick="pausarTimer()" style="display:none;">Pausar</button>
                <?php if (!$esUltimaActividad): ?>
                <button id="btnSiguiente" class="btn btn-primary" onclick="siguienteActividad()">Siguiente actividad</button>
                <?php endif; ?>
                <button id="btnFinalizar" class="btn btn-danger" onclick="finalizarSesion()">Finalizar sesión</button>
            </div>
            
            <!-- Botones específicos para Repertorio -->
            <div class="timer-controls" id="controlesRepertorio" style="<?php echo $actividadActual['tipo'] !== 'repertorio' ? 'display:none;' : ''; ?>">
                <?php if (!$hayActividadesCompletadas): ?>
                <button id="btnIniciarRep" class="btn btn-success" onclick="iniciarTimer()">Iniciar</button>
                <?php endif; ?>
                <button id="btnPausarRep" class="btn btn-warning" onclick="pausarTimer()" style="display:none;">Pausar</button>
                <button id="btnCompletarPieza" class="btn btn-primary" onclick="completarPieza()">✓ Pieza completada - Siguiente</button>
                <?php if (!$esUltimaActividad): ?>
                <button id="btnTerminarRepertorio" class="btn btn-warning" onclick="terminarRepertorio()">Terminar Repertorio</button>
                <?php endif; ?>
                <button id="btnFinalizarRep" class="btn btn-danger" onclick="finalizarSesion()">Finalizar sesión</button>
            </div>
            
            <?php if ($actividadActual['tipo'] === 'repertorio'): ?>
            <div class="mt-1">
                <label for="fallos" style="color: white; font-size: 1.1rem;">Fallos en esta pieza:</label>
                <input type="number" id="fallos" min="0" value="0" style="width: 120px; text-align: center; font-size: 1.5rem; padding: 0.5rem;">
                <div style="margin-top: 0.5rem; font-size: 0.9rem; opacity: 0.8;">
                    <span id="piezasTocadas">Piezas completadas: 0</span>
                </div>
            </div>
            <?php endif; ?>


        <!-- Metrónomo -->
        <div class="metronome-widget">
            <h3>♩ Metrónomo</h3>
            <div class="metro-controls-row">
                <div class="metro-control-group">
                    <div class="metro-control-label">BPM</div>
                    <div class="metro-btn-row">
                        <button class="btn-metro" onclick="metronomeChangeBpm(-5)">-5</button>
                        <button class="btn-metro" onclick="metronomeChangeBpm(-1)">-1</button>
                        <span id="metronomeBpmDisplay" class="metro-value"><?php echo $defaultBpm; ?></span>
                        <button class="btn-metro" onclick="metronomeChangeBpm(+1)">+1</button>
                        <button class="btn-metro" onclick="metronomeChangeBpm(+5)">+5</button>
                    </div>
                </div>
                <div class="metro-control-group">
                    <div class="metro-control-label">Pulsos/compás</div>
                    <div class="metro-btn-row">
                        <button class="btn-metro" onclick="metronomeChangeBeats(-1)">−</button>
                        <span id="metronomeBeatsDisplay" class="metro-beats-value">4</span>
                        <button class="btn-metro" onclick="metronomeChangeBeats(+1)">+</button>
                    </div>
                </div>
            </div>
            <div class="metro-beats-visual" id="metronomeBeatsVisual"></div>
            <div style="display:flex; align-items:center; justify-content:center; margin-bottom:0.6rem;">
                <button id="metronomeAccentBtn" class="btn-metro" onclick="metronomeToggleAccent()"
                        style="padding:0.3rem 0.9rem; font-size:0.85rem;">
                    Acento 1er pulso: <strong id="metronomeAccentLabel">ON</strong>
                </button>
            </div>
            <div style="display:flex; align-items:center; justify-content:center; gap:0.6rem; margin-bottom:0.75rem;">
                <span style="font-size:0.95rem;">🔇</span>
                <input type="range" id="metronomeVolSlider" min="0" max="100" value="70"
                       oninput="metronomeSetVolume(this.value)"
                       style="width:130px; accent-color:#f39c12; cursor:pointer;">
                <span style="font-size:0.95rem;">🔊</span>
                <span id="metronomeVolDisplay" style="color:white; font-size:0.85rem; min-width:2.5rem;">70%</span>
            </div>
            <button id="metronomeBtnToggle" class="btn btn-success" onclick="metronomeToggle()"
                    style="touch-action:manipulation;">▶ Iniciar metrónomo</button>
        </div>
        </div>

        <input type="hidden" id="sesionId" value="<?php echo $sesion['id']; ?>">
        <input type="hidden" id="actividadId" value="<?php echo $actividadActual['id']; ?>">
        <input type="hidden" id="piezaId" value="<?php echo $actividadActual['pieza_id'] ?? ''; ?>">
        <input type="hidden" id="tiempoInicial" value="<?php echo $actividadActual['tiempo_segundos']; ?>">
        <input type="hidden" id="esUltimaActividad" value="<?php echo $esUltimaActividad ? '1' : '0'; ?>">
        <?php else: ?>
        <div class="alert alert-success">
            <strong>¡Sesión completada!</strong> Todas las actividades han sido finalizadas.
            <br><a href="sesion.php" class="btn btn-primary btn-small mt-1">Nueva sesión</a>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2>Actividades de la sesión</h2>
        <ul class="actividades-lista">
            <?php foreach ($actividades as $act): ?>
            <li class="actividad-item <?php echo $act['estado'] === 'en_curso' ? 'activa' : ($act['estado'] === 'completada' ? 'completada' : ''); ?>">
                <div class="actividad-info">
                    <strong><?php echo getNombreActividad($act['tipo']); ?></strong>
                    <?php if ($act['compositor']): ?>
                    <small><?php echo htmlspecialchars($act['compositor'] . ' - ' . $act['titulo']); ?>
                    <?php if ($act['tempo']): ?>
                    (♩ = <?php echo $act['tempo']; ?>)
                    <?php endif; ?>
                    </small>
                    <?php endif; ?>
                    <?php if ($act['notas']): ?>
                    <small><?php echo nl2br(htmlspecialchars($act['notas'])); ?></small>
                    <?php endif; ?>
                </div>
                <div class="actividad-tiempo"><?php echo formatearTiempo($act['tiempo_segundos']); ?></div>
                <div><?php echo ucfirst($act['estado']); ?></div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <?php if (!empty($piezasPracticadas)): ?>
    <div class="card">
        <h2>🎹 Piezas practicadas en esta sesión</h2>
        <table>
            <thead>
                <tr>
                    <th>Pieza</th>
                    <th>Fallos</th>
                    <th>Hora</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($piezasPracticadas as $pp): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($pp['compositor'] . ' - ' . $pp['titulo']); ?></strong></td>
                    <td style="text-align: center;">
                        <span style="color: <?php echo $pp['fallos'] < 5 ? '#27ae60' : ($pp['fallos'] < 10 ? '#f39c12' : '#e74c3c'); ?>; font-weight: bold;">
                            <?php echo $pp['fallos']; ?>
                        </span>
                    </td>
                    <td><?php echo date('H:i', strtotime($pp['fecha_registro'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
<?php else: ?>
    <!-- Vista de planificación de nueva sesión -->
    <div class="card">
        <h2>Planificar nueva sesión</h2>
        
        <?php if ($ultimaSesion): ?>
        <div class="alert alert-info" style="margin-bottom: 1rem;">
            ℹ️ <strong>Configuración precargada</strong> de la última sesión (<?php echo date('d/m/Y', strtotime($ultimaSesion['fecha'])); ?>). 
            Puedes modificar, añadir o eliminar actividades antes de iniciar.
        </div>
        <?php endif; ?>
        
        <form method="POST" id="formPlanificacion">
            <input type="hidden" name="crear_sesion" value="1">
            
            <div id="actividadesContainer">
                <?php if (!empty($ultimasActividades)): ?>
                    <?php foreach ($ultimasActividades as $index => $act): ?>
                    <div class="actividad-item">
                        <div class="actividad-info">
                            <strong><?php echo getNombreActividad($act['tipo']); ?></strong>
                            <?php if ($act['notas']): ?>
                            <small><?php echo htmlspecialchars($act['notas']); ?></small>
                            <?php endif; ?>
                            <input type="hidden" name="actividades[<?php echo $index; ?>][tipo]" value="<?php echo $act['tipo']; ?>">
                            <input type="hidden" name="actividades[<?php echo $index; ?>][notas]" value="<?php echo htmlspecialchars($act['notas']); ?>">
                        </div>
                        <button type="button" class="btn btn-danger btn-small" onclick="this.parentElement.remove()">Eliminar</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="form-inline">
                <div class="form-group">
                    <label for="tipoActividad">Tipo de actividad</label>
                    <select id="tipoActividad">
                        <option value="calentamiento">Calentamiento</option>
                        <option value="tecnica">Técnica</option>
                        <option value="practica">Práctica</option>
                        <option value="repertorio">Repertorio</option>
                        <option value="improvisacion">Improvisación</option>
                        <option value="composicion">Composición</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notasActividadNueva">Notas (opcional)</label>
                    <input type="text" id="notasActividadNueva" placeholder="Ej: Escalas en Do Mayor">
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-primary" onclick="agregarActividad()">Añadir</button>
                </div>
            </div>
            
            <div class="mt-2">
                <button type="submit" name="iniciar_ahora" class="btn btn-success">▶️ Comenzar sesión ahora</button>
                <button type="submit" class="btn btn-primary">📅 Preparar para después</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<script>
let actividadesCount = <?php echo !empty($ultimasActividades) ? count($ultimasActividades) : 0; ?>;

function agregarActividad() {
    const tipo = document.getElementById('tipoActividad').value;
    const notas = document.getElementById('notasActividadNueva').value;
    const tipoNombre = document.getElementById('tipoActividad').options[document.getElementById('tipoActividad').selectedIndex].text;
    
    const container = document.getElementById('actividadesContainer');
    const div = document.createElement('div');
    div.className = 'actividad-item';
    div.innerHTML = `
        <div class="actividad-info">
            <strong>${tipoNombre}</strong>
            ${notas ? '<small>' + notas + '</small>' : ''}
            <input type="hidden" name="actividades[${actividadesCount}][tipo]" value="${tipo}">
            <input type="hidden" name="actividades[${actividadesCount}][notas]" value="${notas}">
        </div>
        <button type="button" class="btn btn-danger btn-small" onclick="this.parentElement.remove()">Eliminar</button>
    `;
    
    container.appendChild(div);
    actividadesCount++;
    
    document.getElementById('notasActividadNueva').value = '';
}

// Timer JavaScript
let timerInterval = null;
let tiempoActual = 0;
let timerActivo = false;
let piezasCompletadas = 0;

<?php if ($sesion && $actividadActual): ?>
// Inicializar tiempo actual
tiempoActual = parseInt(document.getElementById('tiempoInicial').value) || 0;
actualizarDisplay();

// Contar piezas ya completadas en esta actividad
<?php
$stmt = $db->prepare("SELECT COUNT(DISTINCT pieza_id) as total FROM fallos WHERE actividad_id = :id");
$stmt->execute([':id' => $actividadActual['id']]);
$piezasYaCompletadas = $stmt->fetch()['total'] ?? 0;
?>
piezasCompletadas = <?php echo $piezasYaCompletadas; ?>;
if (document.getElementById('piezasTocadas')) {
    document.getElementById('piezasTocadas').textContent = 'Piezas completadas: ' + piezasCompletadas;
}

// AUTO-INICIO: Si hay actividades completadas, iniciar automáticamente esta actividad
<?php if ($hayActividadesCompletadas): ?>
setTimeout(function() {
    iniciarTimer();
}, 500);
<?php endif; ?>

function iniciarTimer() {
    if (timerActivo) return;
    
    timerActivo = true;
    const btnIniciar = document.getElementById('btnIniciar') || document.getElementById('btnIniciarRep');
    const btnPausar = document.getElementById('btnPausar') || document.getElementById('btnPausarRep');
    if (btnIniciar) btnIniciar.style.display = 'none';
    if (btnPausar) btnPausar.style.display = 'inline-block';
    
    // Marcar actividad como en curso
    fetch('sesion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            accion: 'iniciar',
            actividad_id: document.getElementById('actividadId').value
        })
    });
    
    timerInterval = setInterval(() => {
        tiempoActual++;
        actualizarDisplay();
        
        // Guardar cada 5 segundos
        if (tiempoActual % 5 === 0) {
            guardarTiempo();
        }
    }, 1000);
}

function pausarTimer() {
    if (!timerActivo) return;
    
    timerActivo = false;
    const btnIniciar = document.getElementById('btnIniciar') || document.getElementById('btnIniciarRep');
    const btnPausar = document.getElementById('btnPausar') || document.getElementById('btnPausarRep');
    if (btnIniciar) btnIniciar.style.display = 'inline-block';
    if (btnPausar) btnPausar.style.display = 'none';
    
    clearInterval(timerInterval);
    guardarTiempo();
}

function guardarTiempo() {
    fetch('sesion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            accion: 'guardar',
            actividad_id: document.getElementById('actividadId').value,
            tiempo: tiempoActual
        })
    });
}

function guardarNotas() {
    const notas = document.getElementById('notasActividad').value;
    const actividadId = document.getElementById('actividadId').value;
    
    fetch('sesion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            accion: 'guardar_notas',
            actividad_id: actividadId,
            notas: notas
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notasDisplay = document.getElementById('notasActuales');
            if (notasDisplay) {
                notasDisplay.textContent = notas;
            } else if (notas) {
                const infoActividad = document.getElementById('infoActividad');
                const br = document.createElement('br');
                const small = document.createElement('small');
                small.id = 'notasActuales';
                small.textContent = notas;
                infoActividad.appendChild(br);
                infoActividad.appendChild(small);
            }
            alert('✓ Notas guardadas correctamente');
        } else {
            alert('Error al guardar notas');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar notas');
    });
}

function completarPieza() {
    const fallos = document.getElementById('fallos')?.value || 0;
    const piezaId = document.getElementById('piezaId').value;
    
    if (!piezaId) {
        alert('No hay pieza seleccionada');
        return;
    }
    
    if (!confirm('¿Marcar esta pieza como completada y cargar la siguiente?')) {
        return;
    }
    
    guardarTiempo();
    
    fetch('sesion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            accion: 'completar_pieza',
            actividad_id: document.getElementById('actividadId').value,
            pieza_id: piezaId,
            fallos: parseInt(fallos),
            tiempo: tiempoActual
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.siguiente_pieza) {
            document.getElementById('piezaId').value = data.siguiente_pieza.id;
            
            // Construir el texto con tempo si existe
            let textoInfo = '<br><small>' + data.siguiente_pieza.compositor + ' - ' + data.siguiente_pieza.titulo;
            if (data.siguiente_pieza.tempo) {
                textoInfo += ' (♩ = ' + data.siguiente_pieza.tempo + ')';
            }
            textoInfo += '</small>';
            
            document.getElementById('piezaActualInfo').innerHTML = textoInfo;
            document.getElementById('fallos').value = 0;
            const tempoInput = document.getElementById('tempoEditar');
            if (tempoInput) {
                tempoInput.value = data.siguiente_pieza.tempo || '';
            }
            if (data.siguiente_pieza.tempo) {
                metronomeSetBpm(data.siguiente_pieza.tempo);
            }

            piezasCompletadas++;
            document.getElementById('piezasTocadas').textContent = 'Piezas completadas: ' + piezasCompletadas;
            
            const info = document.getElementById('infoActividad');
            const originalBg = info.parentElement.style.background;
            info.parentElement.style.background = 'linear-gradient(135deg, #27ae60, #229954)';
            setTimeout(() => {
                info.parentElement.style.background = originalBg;
            }, 1000);
            
        } else if (data.success && !data.siguiente_pieza) {
            // No hay más piezas - limpiar piezaId para evitar duplicación
            document.getElementById('piezaId').value = '';
            document.getElementById('fallos').value = 0;
            
            alert('¡Felicidades! Has completado todas las piezas disponibles en tu repertorio para esta sesión.');
            terminarRepertorio();
        } else {
            alert('Error: ' + (data.error || 'No se pudo cargar la siguiente pieza'));
        }
    })
    .catch(error => {
        alert('Error de conexión: ' + error);
    });
}

function terminarRepertorio() {
    if (!confirm('¿Finalizar la actividad de Repertorio y pasar a la siguiente actividad?')) {
        return;
    }
    
    const piezaId = document.getElementById('piezaId').value;
    const fallos = piezaId ? (document.getElementById('fallos')?.value || 0) : 0;
    
    if (timerActivo) {
        pausarTimer();
    }
    
    fetch('sesion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            accion: 'terminar_repertorio',
            actividad_id: document.getElementById('actividadId').value,
            tiempo: tiempoActual,
            pieza_id: piezaId,
            fallos: parseInt(fallos)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.hay_siguiente) {
                location.reload();
            } else {
                // Era la última actividad, limpiar campos y finalizar sesión automáticamente
                // IMPORTANTE: Limpiar piezaId y fallos ANTES de finalizar para evitar duplicación
                // La pieza ya fue registrada por terminar_repertorio, no debe volver a registrarse
                document.getElementById('piezaId').value = '';
                document.getElementById('fallos').value = 0;
                finalizarSesionInterno(true);
            }
        } else {
            alert('Error al finalizar repertorio: ' + (data.error || 'Desconocido'));
        }
    });
}

function siguienteActividad() {
    if (!confirm('¿Pasar a la siguiente actividad? Se guardará el progreso actual.')) {
        return;
    }
    
    if (timerActivo) {
        pausarTimer();
    }
    
    const piezaId = document.getElementById('piezaId').value;
    const fallos = piezaId ? (document.getElementById('fallos')?.value || 0) : 0;
    
    fetch('sesion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            accion: 'siguiente',
            actividad_id: document.getElementById('actividadId').value,
            tiempo: tiempoActual,
            pieza_id: piezaId,
            fallos: fallos
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.hay_siguiente) {
                location.reload();
            } else {
                // Era la última actividad, limpiar campos y finalizar sesión automáticamente
                // IMPORTANTE: La pieza ya fue registrada por 'siguiente', limpiar para evitar duplicación
                document.getElementById('piezaId').value = '';
                if (document.getElementById('fallos')) {
                    document.getElementById('fallos').value = 0;
                }
                finalizarSesionInterno(true);
            }
        } else {
            alert('Error al avanzar: ' + (data.error || 'Desconocido'));
        }
    });
}

function finalizarSesion() {
    if (!confirm('¿Finalizar la sesión? Esto guardará todo el progreso.')) {
        return;
    }
    finalizarSesionInterno(false);
}

function finalizarSesionInterno(autoFinalizado) {
    if (timerActivo) {
        pausarTimer();
    }
    
    const piezaId = document.getElementById('piezaId').value;
    const fallos = piezaId ? (document.getElementById('fallos')?.value || 0) : 0;
    
    fetch('sesion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            accion: 'finalizar',
            sesion_id: document.getElementById('sesionId').value,
            actividad_id: document.getElementById('actividadId').value,
            tiempo: tiempoActual,
            pieza_id: piezaId,
            fallos: fallos
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (autoFinalizado) {
                alert('✓ ¡Sesión completada automáticamente! Has terminado todas las actividades.');
            }
            location.reload();
        } else {
            alert('Error al finalizar: ' + (data.error || 'Desconocido'));
        }
    });
}

function actualizarDisplay() {
    const horas = Math.floor(tiempoActual / 3600);
    const minutos = Math.floor((tiempoActual % 3600) / 60);
    const segundos = tiempoActual % 60;

    document.getElementById('timerTime').textContent =
        String(horas).padStart(2, '0') + ':' +
        String(minutos).padStart(2, '0') + ':' +
        String(segundos).padStart(2, '0');
}

// === METRÓNOMO ===
const METRO_LOOKAHEAD = 0.1;
const METRO_INTERVAL = 25;

let metronomeBpm = <?php echo $defaultBpm; ?>;
let metronomeBeats = 4;
let metronomeRunning = false;
let metronomeCurBeat = 0;
let metronomeNextTime = 0;
let metronomeTimer = null;
let metronomeAudioCtx = null;
let metronomeVolume = parseFloat(localStorage.getItem('metro_volumen') || '0.7');
let metronomeAccent = localStorage.getItem('metro_acento') !== 'false';

function metronomeGetCtx() {
    if (!metronomeAudioCtx) {
        metronomeAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (metronomeAudioCtx.state === 'suspended') metronomeAudioCtx.resume();
    return metronomeAudioCtx;
}

function metronomePlayClick(time, isFirst) {
    const ctx = metronomeGetCtx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = (isFirst && metronomeAccent) ? 880 : 440;
    gain.gain.setValueAtTime(metronomeVolume, time);
    gain.gain.exponentialRampToValueAtTime(0.001, time + 0.06);
    osc.start(time);
    osc.stop(time + 0.06);
}

function metronomeSchedule() {
    const ctx = metronomeGetCtx();
    while (metronomeNextTime < ctx.currentTime + METRO_LOOKAHEAD) {
        const beat = metronomeCurBeat;
        const t = metronomeNextTime;
        metronomePlayClick(t, beat === 0);
        const delay = Math.max(0, (t - ctx.currentTime) * 1000);
        setTimeout(() => metronomeSetActiveBeat(beat), delay);
        metronomeCurBeat = (metronomeCurBeat + 1) % metronomeBeats;
        metronomeNextTime += 60.0 / metronomeBpm;
    }
    metronomeTimer = setTimeout(metronomeSchedule, METRO_INTERVAL);
}

function metronomeStart() {
    const ctx = metronomeGetCtx();
    metronomeCurBeat = 0;
    metronomeNextTime = ctx.currentTime + 0.05;
    metronomeSchedule();
    metronomeRunning = true;
    const btn = document.getElementById('metronomeBtnToggle');
    btn.textContent = '⏹ Parar metrónomo';
    btn.classList.remove('btn-success');
    btn.classList.add('btn-warning');
}

function metronomeStop() {
    clearTimeout(metronomeTimer);
    metronomeRunning = false;
    metronomeCurBeat = 0;
    const btn = document.getElementById('metronomeBtnToggle');
    btn.textContent = '▶ Iniciar metrónomo';
    btn.classList.remove('btn-warning');
    btn.classList.add('btn-success');
    metronomeSetActiveBeat(-1);
}

function metronomeToggle() {
    if (metronomeRunning) metronomeStop();
    else metronomeStart();
}

function metronomeChangeBpm(delta) {
    metronomeBpm = Math.max(20, Math.min(300, metronomeBpm + delta));
    document.getElementById('metronomeBpmDisplay').textContent = metronomeBpm;
    if (metronomeRunning) { metronomeStop(); metronomeStart(); }
}

function metronomeSetBpm(bpm) {
    metronomeBpm = Math.max(20, Math.min(300, parseInt(bpm) || 92));
    document.getElementById('metronomeBpmDisplay').textContent = metronomeBpm;
    if (metronomeRunning) { metronomeStop(); metronomeStart(); }
}

function metronomeChangeBeats(delta) {
    metronomeBeats = Math.max(1, Math.min(12, metronomeBeats + delta));
    document.getElementById('metronomeBeatsDisplay').textContent = metronomeBeats;
    metronomeBuildDots();
    if (metronomeRunning) { metronomeStop(); metronomeStart(); }
}

function metronomeSetActiveBeat(active) {
    document.querySelectorAll('.metro-beat-dot').forEach((dot, i) => {
        dot.className = 'metro-beat-dot';
        if (i === 0) dot.classList.add('first-idle');
        if (i === active) {
            dot.classList.remove('first-idle');
            dot.classList.add(i === 0 ? 'active-first' : 'active');
        }
    });
}

function metronomeBuildDots() {
    const container = document.getElementById('metronomeBeatsVisual');
    container.innerHTML = '';
    for (let i = 0; i < metronomeBeats; i++) {
        const dot = document.createElement('span');
        dot.className = 'metro-beat-dot' + (i === 0 ? ' first-idle' : '');
        container.appendChild(dot);
    }
}

metronomeBuildDots();

// Inicializar slider de volumen y botón de acento con valores guardados
(function() {
    const pct = Math.round(metronomeVolume * 100);
    document.getElementById('metronomeVolSlider').value = pct;
    document.getElementById('metronomeVolDisplay').textContent = pct + '%';

    document.getElementById('metronomeAccentLabel').textContent = metronomeAccent ? 'ON' : 'OFF';
    document.getElementById('metronomeAccentBtn').style.opacity = metronomeAccent ? '1' : '0.5';
})();

function metronomeSetVolume(val) {
    metronomeVolume = parseInt(val) / 100;
    localStorage.setItem('metro_volumen', metronomeVolume.toString());
    document.getElementById('metronomeVolDisplay').textContent = val + '%';
}

function metronomeToggleAccent() {
    metronomeAccent = !metronomeAccent;
    localStorage.setItem('metro_acento', metronomeAccent.toString());
    document.getElementById('metronomeAccentLabel').textContent = metronomeAccent ? 'ON' : 'OFF';
    document.getElementById('metronomeAccentBtn').style.opacity = metronomeAccent ? '1' : '0.5';
}

function tempoInputChanged(val) {
    const bpm = parseInt(val);
    if (bpm >= 20 && bpm <= 300) metronomeSetBpm(bpm);
}

function guardarTempoPieza() {
    const tempoInput = document.getElementById('tempoEditar');
    const piezaId = document.getElementById('piezaId').value;
    if (!tempoInput || !piezaId) return;

    const tempo = parseInt(tempoInput.value);
    if (!tempo || tempo < 20 || tempo > 300) return;

    fetch('sesion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ accion: 'guardar_tempo', pieza_id: piezaId, tempo: tempo })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const msg = document.getElementById('tempoGuardadoMsg');
            if (msg) {
                msg.style.display = 'inline';
                setTimeout(() => { msg.style.display = 'none'; }, 2000);
            }
        } else {
            alert('Error al guardar el tempo: ' + (data.error || 'Desconocido'));
        }
    })
    .catch(() => alert('Error de conexión al guardar el tempo.'));
}
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
