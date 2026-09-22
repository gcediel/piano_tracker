<?php
require_once 'config/database.php';
$pageTitle = 'Repertorio - Piano Tracker';
$db = getDB();

$mensaje = '';
$error = '';

$gmInstrumentos = [
    0=>'Acoustic Grand Piano',1=>'Bright Acoustic Piano',2=>'Electric Grand Piano',3=>'Honky-tonk Piano',
    4=>'Electric Piano 1',5=>'Electric Piano 2',6=>'Harpsichord',7=>'Clavinet',
    8=>'Celesta',9=>'Glockenspiel',10=>'Music Box',11=>'Vibraphone',
    12=>'Marimba',13=>'Xylophone',14=>'Tubular Bells',15=>'Dulcimer',
    16=>'Drawbar Organ',17=>'Percussive Organ',18=>'Rock Organ',19=>'Church Organ',
    20=>'Reed Organ',21=>'Accordion',22=>'Harmonica',23=>'Tango Accordion',
    24=>'Nylon Guitar',25=>'Steel Guitar',26=>'Jazz Guitar',27=>'Clean Guitar',
    28=>'Muted Guitar',29=>'Overdriven Guitar',30=>'Distortion Guitar',31=>'Guitar Harmonics',
    32=>'Acoustic Bass',33=>'Finger Bass',34=>'Pick Bass',35=>'Fretless Bass',
    36=>'Slap Bass 1',37=>'Slap Bass 2',38=>'Synth Bass 1',39=>'Synth Bass 2',
    40=>'Violin',41=>'Viola',42=>'Cello',43=>'Contrabass',
    44=>'Tremolo Strings',45=>'Pizzicato Strings',46=>'Orchestral Harp',47=>'Timpani',
    48=>'String Ensemble 1',49=>'String Ensemble 2',50=>'Synth Strings 1',51=>'Synth Strings 2',
    52=>'Choir Aahs',53=>'Voice Oohs',54=>'Synth Choir',55=>'Orchestra Hit',
    56=>'Trumpet',57=>'Trombone',58=>'Tuba',59=>'Muted Trumpet',
    60=>'French Horn',61=>'Brass Section',62=>'Synth Brass 1',63=>'Synth Brass 2',
    64=>'Soprano Sax',65=>'Alto Sax',66=>'Tenor Sax',67=>'Baritone Sax',
    68=>'Oboe',69=>'English Horn',70=>'Bassoon',71=>'Clarinet',
    72=>'Piccolo',73=>'Flute',74=>'Recorder',75=>'Pan Flute',
    76=>'Blown Bottle',77=>'Shakuhachi',78=>'Whistle',79=>'Ocarina',
    80=>'Lead 1 (square)',81=>'Lead 2 (sawtooth)',82=>'Lead 3 (calliope)',83=>'Lead 4 (chiff)',
    84=>'Lead 5 (charang)',85=>'Lead 6 (voice)',86=>'Lead 7 (fifths)',87=>'Lead 8 (bass+lead)',
    88=>'Pad 1 (new age)',89=>'Pad 2 (warm)',90=>'Pad 3 (polysynth)',91=>'Pad 4 (choir)',
    92=>'Pad 5 (bowed)',93=>'Pad 6 (metallic)',94=>'Pad 7 (halo)',95=>'Pad 8 (sweep)',
    96=>'FX 1 (rain)',97=>'FX 2 (soundtrack)',98=>'FX 3 (crystal)',99=>'FX 4 (atmosphere)',
    100=>'FX 5 (brightness)',101=>'FX 6 (goblins)',102=>'FX 7 (echoes)',103=>'FX 8 (sci-fi)',
    104=>'Sitar',105=>'Banjo',106=>'Shamisen',107=>'Koto',
    108=>'Kalimba',109=>'Bag pipe',110=>'Fiddle',111=>'Shanai',
    112=>'Tinkle Bell',113=>'Agogo',114=>'Steel Drums',115=>'Woodblock',
    116=>'Taiko Drum',117=>'Melodic Tom',118=>'Synth Drum',119=>'Reverse Cymbal',
    120=>'Guitar Fret Noise',121=>'Breath Noise',122=>'Seashore',123=>'Bird Tweet',
    124=>'Telephone Ring',125=>'Helicopter',126=>'Applause',127=>'Gunshot',
];

// Procesar acciones CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        try {
            $stmt = $db->prepare("INSERT INTO piezas (compositor, titulo, libro, grado, tempo, tempo_objetivo, ponderacion, programa_midi)
                                  VALUES (:compositor, :titulo, :libro, :grado, :tempo, :tempo_objetivo, :ponderacion, :programa_midi)");
            $stmt->execute([
                ':compositor' => $_POST['compositor'],
                ':titulo' => $_POST['titulo'],
                ':libro' => $_POST['libro'] ?: null,
                ':grado' => $_POST['grado'] ?: null,
                ':tempo' => $_POST['tempo'] ?: null,
                ':tempo_objetivo' => $_POST['tempo_objetivo'] ?: null,
                ':ponderacion' => $_POST['ponderacion'] ?: 1.00,
                ':programa_midi' => intval($_POST['programa_midi'] ?? 0),
            ]);
            $mensaje = 'Pieza añadida correctamente';
        } catch (PDOException $e) {
            $error = 'Error al añadir pieza: ' . $e->getMessage();
        }
    }

    if ($accion === 'editar') {
        try {
            $stmt = $db->prepare("UPDATE piezas SET compositor = :compositor, titulo = :titulo,
                                  libro = :libro, grado = :grado, tempo = :tempo, tempo_objetivo = :tempo_objetivo,
                                  ponderacion = :ponderacion, programa_midi = :programa_midi
                                  WHERE id = :id");
            $stmt->execute([
                ':id' => $_POST['id'],
                ':compositor' => $_POST['compositor'],
                ':titulo' => $_POST['titulo'],
                ':libro' => $_POST['libro'] ?: null,
                ':grado' => $_POST['grado'] ?: null,
                ':tempo' => $_POST['tempo'] ?: null,
                ':tempo_objetivo' => $_POST['tempo_objetivo'] ?: null,
                ':ponderacion' => $_POST['ponderacion'] ?: 1.00,
                ':programa_midi' => intval($_POST['programa_midi'] ?? 0),
            ]);
            $mensaje = 'Pieza actualizada correctamente';
        } catch (PDOException $e) {
            $error = 'Error al actualizar pieza: ' . $e->getMessage();
        }
    }

    if ($accion === 'marcar_mantenimiento') {
        try {
            $stmt = $db->prepare("UPDATE piezas SET estado = 'mantenimiento', meses_objetivo_consecutivos = 0,
                                  aviso_graduacion_pendiente = 0 WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            $mensaje = 'Pieza marcada como mantenimiento';
        } catch (PDOException $e) {
            $error = 'Error al cambiar el estado: ' . $e->getMessage();
        }
    }

    if ($accion === 'marcar_aprendizaje') {
        try {
            $stmt = $db->prepare("UPDATE piezas SET estado = 'aprendizaje' WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            $mensaje = 'Pieza devuelta a aprendizaje';
        } catch (PDOException $e) {
            $error = 'Error al cambiar el estado: ' . $e->getMessage();
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

            <div class="form-group">
                <label for="tempo_objetivo" title="Al alcanzarlo y mantenerlo, la app sugerirá pasar la pieza a mantenimiento">Tempo objetivo</label>
                <input type="number" id="tempo_objetivo" name="tempo_objetivo" min="1" max="300"
                       value="<?php echo htmlspecialchars($piezaEditar['tempo_objetivo'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-inline">
            <div class="form-group">
                <label for="programa_midi">Tono MIDI (GM)</label>
                <select id="programa_midi" name="programa_midi">
                    <?php foreach ($gmInstrumentos as $num => $nombre): ?>
                    <option value="<?php echo $num; ?>"
                        <?php if (($piezaEditar['programa_midi'] ?? 0) == $num): ?>selected<?php endif; ?>>
                        <?php echo $num; ?> – <?php echo htmlspecialchars($nombre); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-inline">
            <div class="form-group">
                <label for="ponderacion">Ponderación</label>
                <input type="number" id="ponderacion" name="ponderacion" step="0.01" min="0.01" max="10"
                       value="<?php echo htmlspecialchars($piezaEditar['ponderacion'] ?? '1.00'); ?>">
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

    <div style="margin-bottom: 1rem;">
        <label style="cursor: pointer;">
            <input type="checkbox" id="chkIncluirInactivas" onchange="toggleInactivas()"> Incluir piezas desactivadas
        </label>
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
                        <th>Tono GM</th>
                        <th title="Tempo al que se sugiere pasar la pieza a mantenimiento">Objetivo</th>
                        <th title="Aprendizaje: compite en la selección automática. Mantenimiento: solo se propone cuando no quedan piezas en aprendizaje">Categoría</th>
                        <th>Pond.</th>
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
                    
                    ?>
                    <tr data-activa="<?php echo $pieza['activa'] ? '1' : '0'; ?>" style="<?php echo !$pieza['activa'] ? 'opacity: 0.5;' : ''; ?>">
                        <td><?php echo htmlspecialchars($pieza['compositor']); ?></td>
                        <td><?php echo htmlspecialchars($pieza['titulo']); ?></td>
                        <td><?php echo htmlspecialchars($pieza['libro'] ?? '-'); ?></td>
                        <td><?php echo $pieza['grado'] ?? '-'; ?></td>
                        <td><?php echo $pieza['tempo'] ?? '-'; ?></td>
                        <td><?php echo $pieza['programa_midi'] ?? 0; ?></td>
                        <td><?php echo $pieza['tempo_objetivo'] ?? '-'; ?></td>
                        <td>
                            <?php if ($pieza['estado'] === 'mantenimiento'): ?>
                                <span style="color: var(--secondary)">🛠 Mantenimiento</span>
                            <?php else: ?>
                                <span>📈 Aprendizaje</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($pieza['ponderacion'], 2); ?></td>
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
                                <form method="POST" data-confirm="<?php echo $pieza['estado'] === 'mantenimiento' ? 'Volver esta pieza a aprendizaje' : 'Marcar esta pieza como mantenimiento'; ?>?">
                                    <input type="hidden" name="id" value="<?php echo $pieza['id']; ?>">
                                    <input type="hidden" name="accion" value="<?php echo $pieza['estado'] === 'mantenimiento' ? 'marcar_aprendizaje' : 'marcar_mantenimiento'; ?>">
                                    <button type="submit" class="btn" style="padding: 0.2rem 0.4rem; font-size: 0.75rem; width: 100%; background: var(--secondary); color: white;">
                                        <?php echo $pieza['estado'] === 'mantenimiento' ? '📈 A aprendizaje' : '🛠 A mantenimiento'; ?>
                                    </button>
                                </form>
                                <form method="POST" data-confirm="<?php echo $pieza['activa'] ? 'Desactivar' : 'Activar'; ?> esta pieza?">
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
                                <form method="POST" data-confirm="⚠️ ¿ELIMINAR permanentemente esta pieza? Esta acción NO se puede deshacer. Solo se puede eliminar si no tiene registros de práctica.">
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
let incluirInactivas = false;

$.fn.dataTable.ext.search.push(function(settings, data, dataIndex, rowData, counter) {
    if (settings.nTable.id !== 'tablaPiezas') return true;
    if (incluirInactivas) return true;
    return $(tablaPiezasDT.row(dataIndex).node()).attr('data-activa') === '1';
});

function toggleInactivas() {
    incluirInactivas = document.getElementById('chkIncluirInactivas').checked;
    tablaPiezasDT.draw();
}

let tablaPiezasDT;

$(document).ready(function() {
    tablaPiezasDT = $('#tablaPiezas').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todas"]],
        "order": [[0, "asc"]],
        "columnDefs": [
            { "orderable": false, "targets": -1 }, // Desactivar ordenamiento en columna Acciones
            { "width": "12%", "targets": 0 },  // Compositor
            { "width": "16%", "targets": 1 },  // Título
            { "width": "10%", "targets": 2 },  // Libro
            { "width": "4%",  "targets": 3 },  // Grado
            { "width": "5%",  "targets": 4 },  // Tempo
            { "width": "6%",  "targets": 5 },  // Tono GM
            { "width": "5%",  "targets": 6 },  // Objetivo
            { "width": "7%",  "targets": 7 },  // Categoría
            { "width": "5%",  "targets": 8 },  // Ponderación
            { "width": "4%",  "targets": 9 },  // Días
            { "width": "8%",  "targets": 10 }, // Media
            { "width": "5%",  "targets": 11 }, // Estado
            { "width": "13%", "targets": 12 }  // Acciones

        ],
        "autoWidth": false,
        "scrollX": false
    });
});
</script>

<?php include 'includes/footer.php'; ?>
