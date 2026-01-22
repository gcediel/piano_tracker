<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'piano_tracker');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Crear conexión PDO
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Función para formatear segundos a HH:MM:SS
function formatearTiempo($segundos) {
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $segs = $segundos % 60;
    return sprintf("%02d:%02d:%02d", $horas, $minutos, $segs);
}

// Función para obtener el nombre legible del tipo de actividad
function getNombreActividad($tipo) {
    $nombres = [
        'calentamiento' => 'Calentamiento',
        'tecnica' => 'Técnica',
        'practica' => 'Práctica',
        'repertorio' => 'Repertorio',
        'improvisacion' => 'Improvisación',
        'composicion' => 'Composición'
    ];
    return $nombres[$tipo] ?? $tipo;
}

// Función para obtener la pieza sugerida según el algoritmo
function obtenerPiezaSugerida($db, $piezasYaSeleccionadas = []) {
    // Obtener todas las piezas activas
    $stmt = $db->query("SELECT * FROM piezas WHERE activa = 1");
    $piezas = $stmt->fetchAll();
    
    if (empty($piezas)) {
        return null;
    }
    
    $scores = [];
    
    foreach ($piezas as $pieza) {
        // Si ya fue seleccionada en esta sesión, saltarla
        if (in_array($pieza['id'], $piezasYaSeleccionadas)) {
            continue;
        }
        
        // Calcular score según fórmula de la hoja de cálculo:
        // Score = SUM((10 - fallos_día_i) × peso_día_i) × (1 / ponderación)
        // 
        // Inversión de fallos:
        //   0 fallos → 10 puntos
        //   1 fallo  → 9 puntos
        //   ...
        //   10+ fallos → 0 puntos
        //
        // Peso temporal:
        //   Hace 30 días → peso 1
        //   Hace 29 días → peso 2
        //   ...
        //   Hace 1 día (ayer) → peso 30
        
        $stmt = $db->prepare("
            SELECT 
                SUM(
                    GREATEST(0, 10 - f.cantidad) * (31 - DATEDIFF(CURDATE(), DATE(f.fecha_registro)))
                ) as suma_ponderada
            FROM fallos f
            WHERE f.pieza_id = :pieza_id 
              AND f.fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND DATEDIFF(CURDATE(), DATE(f.fecha_registro)) < 30
        ");
        $stmt->execute([
            ':pieza_id' => $pieza['id']
        ]);
        $resultado = $stmt->fetch();
        
        $sumaPonderada = $resultado['suma_ponderada'] ?? 0;
        
        // Score = suma_ponderada × (1 / ponderación)
        // MENOR score = MAYOR prioridad
        $score = $sumaPonderada * (1.0 / max($pieza['ponderacion'], 0.1));
        
        $scores[$pieza['id']] = [
            'pieza' => $pieza,
            'score' => $score,
            'suma_ponderada' => $sumaPonderada
        ];
    }
    
    if (empty($scores)) {
        return null;
    }
    
    // Ordenar por score ASCENDENTE (menor primero = mayor prioridad)
    // Piezas con muchos fallos recientes tendrán score bajo → alta prioridad
    // Piezas importantes (alta ponderación) tendrán score más bajo → más prioridad
    uasort($scores, function($a, $b) {
        return $a['score'] <=> $b['score'];
    });
    
    return reset($scores)['pieza'];
}
