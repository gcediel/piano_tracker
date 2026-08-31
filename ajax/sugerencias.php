<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? '';
$piezaId = intval($input['pieza_id'] ?? 0);

$db = getDB();

try {
    switch ($accion) {
        case 'aplicar_tempo':
            $stmt = $db->prepare("
                UPDATE piezas SET tempo = sugerencia_tempo_pendiente, sugerencia_tempo_pendiente = NULL
                WHERE id = :id AND sugerencia_tempo_pendiente IS NOT NULL
            ");
            $stmt->execute([':id' => $piezaId]);
            echo json_encode(['success' => true]);
            break;

        case 'descartar_tempo':
            $stmt = $db->prepare("UPDATE piezas SET sugerencia_tempo_pendiente = NULL WHERE id = :id");
            $stmt->execute([':id' => $piezaId]);
            echo json_encode(['success' => true]);
            break;

        case 'aplicar_graduacion':
            $stmt = $db->prepare("
                UPDATE piezas SET estado = 'mantenimiento', sugerencia_graduacion_pendiente = 0,
                                   meses_objetivo_consecutivos = 0
                WHERE id = :id
            ");
            $stmt->execute([':id' => $piezaId]);
            echo json_encode(['success' => true]);
            break;

        case 'descartar_graduacion':
            $stmt = $db->prepare("UPDATE piezas SET sugerencia_graduacion_pendiente = 0 WHERE id = :id");
            $stmt->execute([':id' => $piezaId]);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
