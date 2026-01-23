<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? '';

$db = getDB();

try {
    switch ($accion) {
        case 'iniciar':
            // Marcar actividad como en curso
            $stmt = $db->prepare("UPDATE actividades SET estado = 'en_curso', fecha_inicio = NOW() WHERE id = :id");
            $stmt->execute([':id' => $input['actividad_id']]);
            
            // Actualizar sesión a en_curso
            $stmt = $db->prepare("UPDATE sesiones s 
                                  JOIN actividades a ON s.id = a.sesion_id 
                                  SET s.estado = 'en_curso' 
                                  WHERE a.id = :id");
            $stmt->execute([':id' => $input['actividad_id']]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'guardar':
            // Guardar tiempo actual
            $stmt = $db->prepare("UPDATE actividades SET tiempo_segundos = :tiempo WHERE id = :id");
            $stmt->execute([
                ':id' => $input['actividad_id'],
                ':tiempo' => $input['tiempo']
            ]);
            
            echo json_encode(['success' => true]);
            break;
        
        case 'guardar_notas':
            // Guardar o actualizar notas de la actividad
            $stmt = $db->prepare("UPDATE actividades SET notas = :notas WHERE id = :id");
            $stmt->execute([
                ':id' => $input['actividad_id'],
                ':notas' => $input['notas'] ?? ''
            ]);
            
            echo json_encode(['success' => true]);
            break;
        
        case 'completar_pieza':
            $db->beginTransaction();
            
            // Registrar fallos de la pieza actual
            if (!empty($input['pieza_id']) && isset($input['fallos'])) {
                $stmt = $db->prepare("INSERT INTO fallos (actividad_id, pieza_id, cantidad) VALUES (:actividad_id, :pieza_id, :cantidad)");
                $stmt->execute([
                    ':actividad_id' => $input['actividad_id'],
                    ':pieza_id' => $input['pieza_id'],
                    ':cantidad' => $input['fallos']
                ]);
            }
            
            // Guardar tiempo acumulado
            $stmt = $db->prepare("UPDATE actividades SET tiempo_segundos = :tiempo WHERE id = :id");
            $stmt->execute([
                ':id' => $input['actividad_id'],
                ':tiempo' => $input['tiempo']
            ]);
            
            // Obtener piezas ya tocadas en esta actividad
            $stmt = $db->prepare("SELECT DISTINCT pieza_id FROM fallos WHERE actividad_id = :actividad_id");
            $stmt->execute([':actividad_id' => $input['actividad_id']]);
            $piezasTocadas = array_column($stmt->fetchAll(), 'pieza_id');
            
            // Obtener siguiente pieza sugerida
            $siguientePieza = obtenerPiezaSugerida($db, $piezasTocadas);
            
            $db->commit();
            
            if ($siguientePieza) {
                echo json_encode([
                    'success' => true,
                    'siguiente_pieza' => $siguientePieza
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'siguiente_pieza' => null,
                    'mensaje' => 'No hay más piezas disponibles'
                ]);
            }
            break;
        
        case 'terminar_repertorio':
            $db->beginTransaction();
            
            // Registrar fallos de la última pieza si corresponde
            if (!empty($input['pieza_id']) && isset($input['fallos']) && $input['fallos'] > 0) {
                // Verificar si ya se registraron fallos para esta pieza en esta actividad
                $stmt = $db->prepare("SELECT COUNT(*) as existe FROM fallos WHERE actividad_id = :actividad_id AND pieza_id = :pieza_id");
                $stmt->execute([
                    ':actividad_id' => $input['actividad_id'],
                    ':pieza_id' => $input['pieza_id']
                ]);
                
                if ($stmt->fetch()['existe'] == 0) {
                    $stmt = $db->prepare("INSERT INTO fallos (actividad_id, pieza_id, cantidad) VALUES (:actividad_id, :pieza_id, :cantidad)");
                    $stmt->execute([
                        ':actividad_id' => $input['actividad_id'],
                        ':pieza_id' => $input['pieza_id'],
                        ':cantidad' => $input['fallos']
                    ]);
                }
            }
            
            // Guardar tiempo y marcar como completada
            $stmt = $db->prepare("UPDATE actividades SET tiempo_segundos = :tiempo, estado = 'completada', fecha_fin = NOW() WHERE id = :id");
            $stmt->execute([
                ':id' => $input['actividad_id'],
                ':tiempo' => $input['tiempo']
            ]);
            
            // Buscar siguiente actividad pendiente
            $stmt = $db->prepare("SELECT a.sesion_id FROM actividades a WHERE a.id = :id");
            $stmt->execute([':id' => $input['actividad_id']]);
            $sesionId = $stmt->fetch()['sesion_id'];
            
            $stmt = $db->prepare("SELECT id FROM actividades WHERE sesion_id = :sesion_id AND estado = 'pendiente' ORDER BY orden LIMIT 1");
            $stmt->execute([':sesion_id' => $sesionId]);
            $siguiente = $stmt->fetch();
            
            if ($siguiente) {
                // Marcar siguiente como en curso
                $stmt = $db->prepare("UPDATE actividades SET estado = 'en_curso', fecha_inicio = NOW() WHERE id = :id");
                $stmt->execute([':id' => $siguiente['id']]);
            }
            
            $db->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'siguiente':
            $db->beginTransaction();
            
            // Guardar tiempo y marcar como completada
            $stmt = $db->prepare("UPDATE actividades SET tiempo_segundos = :tiempo, estado = 'completada', fecha_fin = NOW() WHERE id = :id");
            $stmt->execute([
                ':id' => $input['actividad_id'],
                ':tiempo' => $input['tiempo']
            ]);
            
            // Registrar fallos si es una actividad de repertorio
            if (!empty($input['pieza_id']) && isset($input['fallos']) && $input['fallos'] > 0) {
                $stmt = $db->prepare("INSERT INTO fallos (actividad_id, pieza_id, cantidad) VALUES (:actividad_id, :pieza_id, :cantidad)");
                $stmt->execute([
                    ':actividad_id' => $input['actividad_id'],
                    ':pieza_id' => $input['pieza_id'],
                    ':cantidad' => $input['fallos']
                ]);
            }
            
            // Buscar siguiente actividad pendiente
            $stmt = $db->prepare("SELECT a.sesion_id FROM actividades a WHERE a.id = :id");
            $stmt->execute([':id' => $input['actividad_id']]);
            $sesionId = $stmt->fetch()['sesion_id'];
            
            $stmt = $db->prepare("SELECT id FROM actividades WHERE sesion_id = :sesion_id AND estado = 'pendiente' ORDER BY orden LIMIT 1");
            $stmt->execute([':sesion_id' => $sesionId]);
            $siguiente = $stmt->fetch();
            
            if ($siguiente) {
                // Marcar siguiente como en curso
                $stmt = $db->prepare("UPDATE actividades SET estado = 'en_curso', fecha_inicio = NOW() WHERE id = :id");
                $stmt->execute([':id' => $siguiente['id']]);
            }
            
            $db->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'finalizar':
            $db->beginTransaction();
            
            // Guardar última actividad
            $stmt = $db->prepare("UPDATE actividades SET tiempo_segundos = :tiempo, estado = 'completada', fecha_fin = NOW() WHERE id = :id");
            $stmt->execute([
                ':id' => $input['actividad_id'],
                ':tiempo' => $input['tiempo']
            ]);
            
            // Registrar fallos si corresponde
            if (!empty($input['pieza_id']) && isset($input['fallos']) && $input['fallos'] > 0) {
                // Verificar si ya se registraron fallos para esta pieza en esta actividad
                $stmt = $db->prepare("SELECT COUNT(*) as existe FROM fallos WHERE actividad_id = :actividad_id AND pieza_id = :pieza_id");
                $stmt->execute([
                    ':actividad_id' => $input['actividad_id'],
                    ':pieza_id' => $input['pieza_id']
                ]);
                
                if ($stmt->fetch()['existe'] == 0) {
                    $stmt = $db->prepare("INSERT INTO fallos (actividad_id, pieza_id, cantidad) VALUES (:actividad_id, :pieza_id, :cantidad)");
                    $stmt->execute([
                        ':actividad_id' => $input['actividad_id'],
                        ':pieza_id' => $input['pieza_id'],
                        ':cantidad' => $input['fallos']
                    ]);
                }
            }
            
            // Marcar todas las actividades pendientes como completadas
            $stmt = $db->prepare("UPDATE actividades SET estado = 'completada' WHERE sesion_id = :sesion_id AND estado = 'pendiente'");
            $stmt->execute([':sesion_id' => $input['sesion_id']]);
            
            // Finalizar sesión
            $stmt = $db->prepare("UPDATE sesiones SET estado = 'finalizada', hora_fin = NOW() WHERE id = :id");
            $stmt->execute([':id' => $input['sesion_id']]);
            
            $db->commit();
            echo json_encode(['success' => true]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
