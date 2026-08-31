<?php
require_once 'config/database.php';
$pageTitle = 'Técnica - Piano Tracker';
$db = getDB();

$mensaje = '';
$error = '';

// Procesar acciones CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        try {
            $bpm = intval($_POST['bpm'] ?: 120);
            if ($bpm < 20 || $bpm > 300) throw new Exception('El BPM debe estar entre 20 y 300.');

            $stmt = $db->prepare("INSERT INTO ejercicios_tecnica (nombre, bpm, comentarios)
                                  VALUES (:nombre, :bpm, :comentarios)");
            $stmt->execute([
                ':nombre'      => trim($_POST['nombre']),
                ':bpm'         => $bpm,
                ':comentarios' => trim($_POST['comentarios']) ?: null,
            ]);
            $mensaje = 'Ejercicio añadido correctamente.';
        } catch (PDOException $e) {
            $error = 'Error al añadir ejercicio: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($accion === 'editar') {
        try {
            $bpm = intval($_POST['bpm'] ?: 120);
            if ($bpm < 20 || $bpm > 300) throw new Exception('El BPM debe estar entre 20 y 300.');

            $stmt = $db->prepare("UPDATE ejercicios_tecnica
                                  SET nombre = :nombre, bpm = :bpm, comentarios = :comentarios
                                  WHERE id = :id");
            $stmt->execute([
                ':id'          => intval($_POST['id']),
                ':nombre'      => trim($_POST['nombre']),
                ':bpm'         => $bpm,
                ':comentarios' => trim($_POST['comentarios']) ?: null,
            ]);
            $mensaje = 'Ejercicio actualizado correctamente.';
        } catch (PDOException $e) {
            $error = 'Error al actualizar ejercicio: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($accion === 'activar' || $accion === 'desactivar') {
        try {
            $activo = $accion === 'activar' ? 1 : 0;
            $stmt = $db->prepare("UPDATE ejercicios_tecnica SET activo = :activo WHERE id = :id");
            $stmt->execute([':activo' => $activo, ':id' => intval($_POST['id'])]);
            $mensaje = $activo ? 'Ejercicio activado.' : 'Ejercicio desactivado.';
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }

    if ($accion === 'resetear_bpm') {
        try {
            $id = intval($_POST['id']);
            $db->prepare("UPDATE ejercicios_tecnica SET bpm = 120 WHERE id = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM sesion_tecnica_ejercicios WHERE ejercicio_id = :id")->execute([':id' => $id]);
            $mensaje = 'BPM reiniciado a 120 y prácticas borradas.';
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }

    if ($accion === 'eliminar') {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM sesion_tecnica_ejercicios WHERE ejercicio_id = :id");
            $stmt->execute([':id' => intval($_POST['id'])]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'No se puede eliminar: el ejercicio tiene registros de práctica. Puedes desactivarlo.';
            } else {
                $stmt = $db->prepare("DELETE FROM ejercicios_tecnica WHERE id = :id");
                $stmt->execute([':id' => intval($_POST['id'])]);
                $mensaje = 'Ejercicio eliminado.';
            }
        } catch (PDOException $e) {
            $error = 'Error al eliminar: ' . $e->getMessage();
        }
    }

    // Redirigir para evitar reenvío de formulario
    if ($mensaje) {
        header("Location: tecnica.php?ok=" . urlencode($mensaje));
        exit;
    }
}

if (isset($_GET['ok'])) {
    $mensaje = htmlspecialchars($_GET['ok']);
}

// Ejercicio a editar
$ejercicioEditar = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM ejercicios_tecnica WHERE id = :id");
    $stmt->execute([':id' => $_GET['editar']]);
    $ejercicioEditar = $stmt->fetch();
}

// Cargar todos los ejercicios con contador de reproducciones
$stmt = $db->query("
    SELECT e.*, COUNT(se.id) AS num_reproducciones
    FROM ejercicios_tecnica e
    LEFT JOIN sesion_tecnica_ejercicios se ON se.ejercicio_id = e.id
    GROUP BY e.id
    ORDER BY e.bpm ASC, e.nombre ASC
");
$ejercicios = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- CSS de DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">

<?php if ($mensaje): ?>
<div class="alert alert-success"><?php echo $mensaje; ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <h2><?php echo $ejercicioEditar ? 'Editar ejercicio' : 'Añadir ejercicio de técnica'; ?></h2>

    <form method="POST" action="tecnica.php">
        <input type="hidden" name="accion" value="<?php echo $ejercicioEditar ? 'editar' : 'crear'; ?>">
        <?php if ($ejercicioEditar): ?>
        <input type="hidden" name="id" value="<?php echo $ejercicioEditar['id']; ?>">
        <?php endif; ?>

        <div class="form-inline">
            <div class="form-group">
                <label for="nombre">Nombre del ejercicio *</label>
                <input type="text" id="nombre" name="nombre" required style="width:100%; max-width:500px;"
                       value="<?php echo htmlspecialchars($ejercicioEditar['nombre'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="bpm">BPM</label>
                <input type="number" id="bpm" name="bpm" min="20" max="300" style="width:80px;"
                       value="<?php echo htmlspecialchars($ejercicioEditar['bpm'] ?? 120); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="comentarios">Comentarios</label>
            <textarea id="comentarios" name="comentarios" rows="2" style="width:100%; max-width:600px;"><?php echo htmlspecialchars($ejercicioEditar['comentarios'] ?? ''); ?></textarea>
        </div>

        <div class="mt-1">
            <button type="submit" class="btn btn-success"><?php echo $ejercicioEditar ? '💾 Guardar cambios' : '➕ Añadir ejercicio'; ?></button>
            <?php if ($ejercicioEditar): ?>
            <a href="tecnica.php" class="btn btn-primary">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (empty($ejercicios)): ?>
<div class="card">
    <p style="text-align:center; color:#666;">No hay ejercicios definidos todavía. Añade el primero con el formulario anterior.</p>
</div>
<?php else: ?>

<div class="card">
    <h2>Ejercicios de técnica</h2>
    <div style="overflow-x:auto;">
    <table id="tablaEjercicios" class="display" style="width:100%">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>BPM</th>
                <th>Prácticas</th>
                <th>Comentarios</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ejercicios as $ej): ?>
            <tr style="opacity: <?php echo $ej['activo'] ? '1' : '0.5'; ?>;">
                <td><?php echo htmlspecialchars($ej['nombre']); ?></td>
                <td data-order="<?php echo $ej['bpm']; ?>" style="text-align:center;"><strong>♩ = <?php echo $ej['bpm']; ?></strong></td>
                <td style="text-align:center;"><?php echo $ej['num_reproducciones']; ?></td>
                <td style="font-size:0.85rem; color:#666;"><?php echo htmlspecialchars($ej['comentarios'] ?? ''); ?></td>
                <td style="text-align:center;">
                    <?php echo $ej['activo'] ? '<span style="color:#27ae60;">Activo</span>' : '<span style="color:#999;">Inactivo</span>'; ?>
                </td>
                <td style="white-space:nowrap;">
                    <a href="tecnica.php?editar=<?php echo $ej['id']; ?>" class="btn btn-small btn-primary">Editar</a>
                    <form method="POST" action="tecnica.php" style="display:inline;"
                          data-confirm="¿Resetear BPM a 120 y borrar todas las prácticas?">
                        <input type="hidden" name="id" value="<?php echo $ej['id']; ?>">
                        <input type="hidden" name="accion" value="resetear_bpm">
                        <button type="submit" class="btn btn-small btn-primary">↺ 120</button>
                    </form>
                    <form method="POST" action="tecnica.php" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $ej['id']; ?>">
                        <?php if ($ej['activo']): ?>
                        <input type="hidden" name="accion" value="desactivar">
                        <button type="submit" class="btn btn-small btn-warning">Desactivar</button>
                        <?php else: ?>
                        <input type="hidden" name="accion" value="activar">
                        <button type="submit" class="btn btn-small btn-success">Activar</button>
                        <?php endif; ?>
                    </form>
                    <form method="POST" action="tecnica.php" style="display:inline;"
                          data-confirm="¿Eliminar este ejercicio?">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?php echo $ej['id']; ?>">
                        <button type="submit" class="btn btn-small btn-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php endif; ?>

<!-- JS de DataTables -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#tablaEjercicios').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "pageLength": 25,
        "order": [[1, "asc"]],
        "columnDefs": [
            { "orderable": false, "targets": -1 }
        ]
    });
});
</script>

<?php include 'includes/footer.php'; ?>
