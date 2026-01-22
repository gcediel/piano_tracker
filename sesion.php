<?php
require_once 'config/database.php';
$pageTitle = 'Sesión de práctica - Piano Tracker';
$db = getDB();

$mensaje = '';
$error = '';

// Ver detalles de una sesión
if (isset($_GET['ver'])) {
    $stmt = $db->prepare("SELECT * FROM sesiones WHERE id = :id");
    $stmt->execute([':id' => $_GET['ver']]);
    $sesionVer = $stmt->fetch();
    
    if ($sesionVer) {
        $stmt = $db->prepare("SELECT a.*, p.compositor, p.titulo 
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
        
        // IMPORTANTE: Eliminar sesiones programadas pendientes antes de crear una nueva
        // Esto evita que se acumulen sesiones planificadas
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

// Cargar sesión activa
$sesion = null;
$actividades = [];
$actividadActual = null;

if (isset($_GET['sesion'])) {
    $stmt = $db->prepare("SELECT * FROM sesiones WHERE id = :id");
    $stmt->execute([':id' => $_GET['sesion']]);
    $sesion = $stmt->fetch();
    
    if ($sesion) {
        $stmt = $db->prepare("SELECT a.*, p.compositor, p.titulo 
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
                            <strong>🎹 <?php echo htmlspecialchars($act['compositor'] . ' - ' . $act['titulo']); ?></strong>
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
                    <br><small><?php echo htmlspecialchars($actividadActual['compositor'] . ' - ' . $actividadActual['titulo']); ?></small>
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
            </div>
            <h2 id="timerTime">00:00:00</h2>
            
            <!-- Botones para actividades normales -->
            <div class="timer-controls" id="controlesNormales" style="<?php echo $actividadActual['tipo'] === 'repertorio' ? 'display:none;' : ''; ?>">
                <button id="btnIniciar" class="btn btn-success" onclick="iniciarTimer()">Iniciar</button>
                <button id="btnPausar" class="btn btn-warning" onclick="pausarTimer()" style="display:none;">Pausar</button>
                <button id="btnSiguiente" class="btn btn-primary" onclick="siguienteActividad()">Siguiente actividad</button>
                <button id="btnFinalizar" class="btn btn-danger" onclick="finalizarSesion()">Finalizar sesión</button>
            </div>
            
            <!-- Botones específicos para Repertorio -->
            <div class="timer-controls" id="controlesRepertorio" style="<?php echo $actividadActual['tipo'] !== 'repertorio' ? 'display:none;' : ''; ?>">
                <button id="btnIniciarRep" class="btn btn-success" onclick="iniciarTimer()">Iniciar</button>
                <button id="btnPausarRep" class="btn btn-warning" onclick="pausarTimer()" style="display:none;">Pausar</button>
                <button id="btnCompletarPieza" class="btn btn-primary" onclick="completarPieza()">✓ Pieza completada - Siguiente</button>
                <button id="btnTerminarRepertorio" class="btn btn-warning" onclick="terminarRepertorio()">Terminar Repertorio</button>
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
        </div>
        
        <input type="hidden" id="sesionId" value="<?php echo $sesion['id']; ?>">
        <input type="hidden" id="actividadId" value="<?php echo $actividadActual['id']; ?>">
        <input type="hidden" id="piezaId" value="<?php echo $actividadActual['pieza_id'] ?? ''; ?>">
        <input type="hidden" id="tiempoInicial" value="<?php echo $actividadActual['tiempo_segundos']; ?>">
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
                    <small><?php echo htmlspecialchars($act['compositor'] . ' - ' . $act['titulo']); ?></small>
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
        
        <form method="POST" id="formPlanificacion">
            <input type="hidden" name="crear_sesion" value="1">
            
            <div id="actividadesContainer"></div>
            
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
                    <label for="notasActividad">Notas (opcional)</label>
                    <input type="text" id="notasActividad" placeholder="Ej: Escalas en Do Mayor">
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
let actividadesCount = 0;

function agregarActividad() {
    const tipo = document.getElementById('tipoActividad').value;
    const notas = document.getElementById('notasActividad').value;
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
    
    document.getElementById('notasActividad').value = '';
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

function iniciarTimer() {
    if (timerActivo) return;
    
    timerActivo = true;
    const btnIniciar = document.getElementById('btnIniciar') || document.getElementById('btnIniciarRep');
    const btnPausar = document.getElementById('btnPausar') || document.getElementById('btnPausarRep');
    if (btnIniciar) btnIniciar.style.display = 'none';
    if (btnPausar) btnPausar.style.display = 'inline-block';
    
    // Marcar actividad como en curso
    fetch('/piano/ajax/timer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
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
    fetch('/piano/ajax/timer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
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
    
    fetch('/piano/ajax/timer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            accion: 'guardar_notas',
            actividad_id: actividadId,
            notas: notas
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar display de notas actuales
            const notasDisplay = document.getElementById('notasActuales');
            if (notasDisplay) {
                notasDisplay.textContent = notas;
            } else if (notas) {
                // Crear elemento de display si no existía
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
    
    // Guardar tiempo actual
    guardarTiempo();
    
    fetch('/piano/ajax/timer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
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
            // Actualizar interfaz con la nueva pieza
            document.getElementById('piezaId').value = data.siguiente_pieza.id;
            document.getElementById('piezaActualInfo').innerHTML = 
                '<br><small>' + data.siguiente_pieza.compositor + ' - ' + data.siguiente_pieza.titulo + '</small>';
            document.getElementById('fallos').value = 0;
            
            // Actualizar contador
            piezasCompletadas++;
            document.getElementById('piezasTocadas').textContent = 'Piezas completadas: ' + piezasCompletadas;
            
            // Notificar al usuario
            const info = document.getElementById('infoActividad');
            const originalBg = info.parentElement.style.background;
            info.parentElement.style.background = 'linear-gradient(135deg, #27ae60, #229954)';
            setTimeout(() => {
                info.parentElement.style.background = originalBg;
            }, 1000);
            
        } else if (data.success && !data.siguiente_pieza) {
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
    
    // Pausar timer si está activo
    if (timerActivo) {
        pausarTimer();
    }
    
    fetch('/piano/ajax/timer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
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
            location.reload();
        } else {
            alert('Error al finalizar repertorio: ' + (data.error || 'Desconocido'));
        }
    });
}

function siguienteActividad() {
    if (timerActivo) {
        pausarTimer();
    }
    
    const piezaId = document.getElementById('piezaId').value;
    const fallos = piezaId ? (document.getElementById('fallos')?.value || 0) : 0;
    
    fetch('/piano/ajax/timer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
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
            location.reload();
        } else {
            alert('Error al avanzar: ' + (data.error || 'Desconocido'));
        }
    });
}

function finalizarSesion() {
    if (!confirm('¿Finalizar la sesión? Esto guardará todo el progreso.')) {
        return;
    }
    
    if (timerActivo) {
        pausarTimer();
    }
    
    const piezaId = document.getElementById('piezaId').value;
    const fallos = piezaId ? (document.getElementById('fallos')?.value || 0) : 0;
    
    fetch('/piano/ajax/timer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
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
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
