<?php
// Funciones auxiliares y algoritmos de la app. Sin credenciales: se sube a git.
// Requiere que config/database.php (con getDB()) ya esté cargado.

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
        'calentamiento'      => 'Calentamiento',
        'tecnica'            => 'Técnica',
        'tecnica_ejercicios' => 'Técnica',
        'practica_tecnica'   => 'Práctica de técnica',
        'practica'           => 'Práctica de repertorio',
        'repertorio'         => 'Repertorio',
        'improvisacion'      => 'Improvisación',
        'composicion'        => 'Composición',
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

        // Calcular score según fórmula de la hoja de cálculo, con decaimiento
        // exponencial (semivida 15 días) en vez de un corte binario a 30 días,
        // para no igualar "nunca evaluada" con "dominada y sin fallos recientes".
        // Solo cuentan los fallos de la pasada "con metrónomo" (la que refleja
        // la interpretación real); el pase libre previo no entra en el cálculo.
        //
        // Inversión de fallos:
        //   0 fallos → 10 puntos
        //   1 fallo  → 9 puntos
        //   ...
        //   10+ fallos → 0 puntos
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as num_fallos,
                SUM(
                    GREATEST(0, 10 - f.cantidad) * POW(0.5, DATEDIFF(CURDATE(), DATE(f.fecha_registro)) / 15)
                ) as suma_ponderada
            FROM fallos f
            WHERE f.pieza_id = :pieza_id
              AND f.tipo_pasada = 'metronomo'
              AND f.fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
        ");
        $stmt->execute([
            ':pieza_id' => $pieza['id']
        ]);
        $resultado = $stmt->fetch();

        $numFallos = (int)($resultado['num_fallos'] ?? 0);

        if ($numFallos === 0) {
            // Nunca evaluada con metrónomo: prioridad máxima, distinta de una
            // pieza ya dominada cuyo score decae hacia (pero no llega a) 0.
            $score = -1;
        } else {
            $sumaPonderada = $resultado['suma_ponderada'] ?? 0;
            // Score = suma_ponderada × (1 / ponderación)
            // MENOR score = MAYOR prioridad
            $score = $sumaPonderada * (1.0 / max($pieza['ponderacion'], 0.1));
        }

        // Las piezas en mantenimiento no compiten en igualdad con las piezas
        // en aprendizaje activo: solo se seleccionan cuando ya no queda
        // ninguna pieza en aprendizaje disponible para esta actividad.
        if ($pieza['estado'] === 'mantenimiento') {
            $score += 1000000;
        }

        $scores[$pieza['id']] = [
            'pieza' => $pieza,
            'score' => $score
        ];
    }

    if (empty($scores)) {
        return null;
    }

    // Ordenar por score ASCENDENTE (menor primero = mayor prioridad)
    uasort($scores, function($a, $b) {
        return $a['score'] <=> $b['score'];
    });

    return reset($scores)['pieza'];
}

// Constantes de la progresión automática de tempo (ver auditoria_pedagogica.md)
define('INCREMENTO_TEMPO_PROGRESION', 5);  // BPM sugeridos al subir tempo (rango acordado: 4-6)
define('MESES_PARA_GRADUACION', 3);        // meses consecutivos cumpliendo el umbral de graduación para pasar a mantenimiento
define('TEMPO_DEMOCION_MARGEN', 8);        // BPM por debajo del tempo objetivo al volver de mantenimiento a aprendizaje
define('UMBRAL_FALLOS_GRADUACION', 0.25);  // media máxima de fallos/día para que un mes cuente hacia la graduación (no exige 0)
define('DIAS_MINIMOS_GRADUACION', 8);      // días mínimos practicados con metrónomo ese mes para que cuente (evita graduar por 1-2 sesiones sueltas)

// Media y días practicados "con metrónomo" de una pieza en el mes natural que empieza en $primerDiaMes.
// Devuelve null si no hay ningún día practicado ese mes (aún no evaluable).
function mediaFallosMetronomo($db, $piezaId, $primerDiaMes) {
    $fin = date('Y-m-d', strtotime($primerDiaMes . ' +1 month'));
    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT DATE(f.fecha_registro)) as dias_practicados,
            SUM(f.cantidad) as total_fallos
        FROM fallos f
        WHERE f.pieza_id = :pieza_id
          AND f.tipo_pasada = 'metronomo'
          AND f.fecha_registro >= :inicio
          AND f.fecha_registro < :fin
    ");
    $stmt->execute([':pieza_id' => $piezaId, ':inicio' => $primerDiaMes, ':fin' => $fin]);
    $r = $stmt->fetch();
    $dias = (int)($r['dias_practicados'] ?? 0);
    if ($dias === 0) {
        return null;
    }
    return ['media' => ($r['total_fallos'] ?? 0) / $dias, 'dias' => $dias];
}

// Evalúa el mes natural anterior para cada pieza en aprendizaje con tempo objetivo
// que aún no haya sido evaluada ese mes, y deja marcada una sugerencia pendiente
// de subida de tempo o de graduación a mantenimiento (la confirma el usuario).
// Pensada para llamarse una vez por carga del dashboard: es idempotente y barata
// una vez que el mes ya quedó marcado como evaluado.
function evaluarProgresionMensual($db) {
    $primerDiaMesAnterior = date('Y-m-01', strtotime('first day of last month'));

    $stmt = $db->prepare("
        SELECT * FROM piezas
        WHERE activa = 1 AND estado = 'aprendizaje' AND tempo_objetivo IS NOT NULL
          AND (mes_evaluado IS NULL OR mes_evaluado < :mes)
    ");
    $stmt->execute([':mes' => $primerDiaMesAnterior]);
    $piezas = $stmt->fetchAll();

    foreach ($piezas as $pieza) {
        $datosMes = mediaFallosMetronomo($db, $pieza['id'], $primerDiaMesAnterior);
        if ($datosMes === null) {
            continue; // sin datos ese mes: se reintenta en la próxima carga
        }
        $media = $datosMes['media'];
        $dias  = $datosMes['dias'];

        if ($media <= 1 && $pieza['tempo'] < $pieza['tempo_objetivo']) {
            $nuevoTempo = min($pieza['tempo_objetivo'], $pieza['tempo'] + INCREMENTO_TEMPO_PROGRESION);
            $stmt2 = $db->prepare("
                UPDATE piezas SET mes_evaluado = :mes, sugerencia_tempo_pendiente = :nuevo
                WHERE id = :id
            ");
            $stmt2->execute([':mes' => $primerDiaMesAnterior, ':nuevo' => $nuevoTempo, ':id' => $pieza['id']]);
        } elseif ($pieza['tempo'] >= $pieza['tempo_objetivo']) {
            // Graduación a mantenimiento: umbral más exigente que el de subir tempo
            // (no hace falta 0 fallos, pero sí un mínimo de días practicados para
            // que el mes cuente y no baste una sesión suelta con suerte).
            $cuentaMes = $media <= UMBRAL_FALLOS_GRADUACION && $dias >= DIAS_MINIMOS_GRADUACION;
            $meses = $cuentaMes ? $pieza['meses_objetivo_consecutivos'] + 1 : 0;
            $graduar = $meses >= MESES_PARA_GRADUACION;
            $stmt2 = $db->prepare("
                UPDATE piezas SET mes_evaluado = :mes, meses_objetivo_consecutivos = :meses,
                                   sugerencia_graduacion_pendiente = :grad
                WHERE id = :id
            ");
            $stmt2->execute([
                ':mes' => $primerDiaMesAnterior, ':meses' => $meses,
                ':grad' => $graduar ? 1 : 0, ':id' => $pieza['id']
            ]);
        } else {
            $stmt2 = $db->prepare("
                UPDATE piezas SET mes_evaluado = :mes, meses_objetivo_consecutivos = 0
                WHERE id = :id
            ");
            $stmt2->execute([':mes' => $primerDiaMesAnterior, ':id' => $pieza['id']]);
        }
    }
}

// Comprueba si una pieza en mantenimiento debe volver a aprendizaje: sus dos
// últimos registros de fallos (de cualquier tipo de pasada) tienen cantidad > 0.
// Si se dispara la démotion, baja el tempo por debajo del objetivo en vez de
// reiniciar la escalera desde cero. Devuelve true si demovió la pieza.
function revisarDemocionMantenimiento($db, $piezaId) {
    $stmt = $db->prepare("
        SELECT cantidad FROM fallos WHERE pieza_id = :id ORDER BY fecha_registro DESC LIMIT 2
    ");
    $stmt->execute([':id' => $piezaId]);
    $ultimos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($ultimos) < 2 || $ultimos[0] <= 0 || $ultimos[1] <= 0) {
        return false;
    }

    $stmt2 = $db->prepare("SELECT tempo, tempo_objetivo FROM piezas WHERE id = :id");
    $stmt2->execute([':id' => $piezaId]);
    $pieza = $stmt2->fetch();

    $sql = "UPDATE piezas SET estado = 'aprendizaje', meses_objetivo_consecutivos = 0,
                               mes_evaluado = NULL, sugerencia_tempo_pendiente = NULL,
                               sugerencia_graduacion_pendiente = 0";
    $params = [':id' => $piezaId];
    if ($pieza && $pieza['tempo_objetivo']) {
        $sql .= ", tempo = :tempo";
        $params[':tempo'] = max(20, $pieza['tempo_objetivo'] - TEMPO_DEMOCION_MARGEN);
    }
    $sql .= " WHERE id = :id";
    $db->prepare($sql)->execute($params);

    return true;
}

// Nivel de "media de fallos/día" (últimos 30 días) según la leyenda de
// repertorio.php. Devuelve null si $media es null. rango: 0 (peor) a 5 (mejor).
function nivelFallos($media) {
    if ($media === null) {
        return null;
    }
    if ($media < 0.5) {
        return ['rango' => 5, 'texto' => 'Excelente', 'color' => '#2E5F8A'];
    } elseif ($media < 1.5) {
        return ['rango' => 4, 'texto' => 'Muy bien', 'color' => '#4A7BA7'];
    } elseif ($media < 2.5) {
        return ['rango' => 3, 'texto' => 'Bien', 'color' => '#A3C1DA'];
    } elseif ($media < 3.5) {
        return ['rango' => 2, 'texto' => 'Aceptable', 'color' => '#D4E89E'];
    } elseif ($media <= 5) {
        return ['rango' => 1, 'texto' => 'Mejorable', 'color' => '#9B9B9B'];
    }
    return ['rango' => 0, 'texto' => 'Atención', 'color' => '#E57373'];
}

// Media de fallos/día de una pieza en los últimos 30 días (todo tipo de pasada),
// igual que el listado de repertorio.php. Devuelve null si no hay días practicados.
function mediaFallosDia30($db, $piezaId) {
    $fechaLimite = date('Y-m-d', strtotime('-30 days'));
    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT DATE(f.fecha_registro)) as dias,
            SUM(f.cantidad) as total
        FROM fallos f
        WHERE f.pieza_id = :pieza_id AND f.fecha_registro >= :fecha_limite
    ");
    $stmt->execute([':pieza_id' => $piezaId, ':fecha_limite' => $fechaLimite]);
    $r = $stmt->fetch();
    $dias = (int)($r['dias'] ?? 0);
    if ($dias === 0) {
        return null;
    }
    return round(($r['total'] ?? 0) / $dias, 2);
}

// Inserta un registro de fallos para una pieza y, si está en mantenimiento,
// comprueba si debe volver a aprendizaje (ver revisarDemocionMantenimiento).
// Devuelve datos de cambio de nivel (para celebrar/avisar en el frontend) si el
// nivel de "media de fallos/día (30 días)" de la pieza cambia con este registro,
// o null si no hay datos previos que comparar o el nivel no cambia.
function registrarFallo($db, $actividadId, $piezaId, $cantidad, $tipoPasada) {
    $nivelAntes = nivelFallos(mediaFallosDia30($db, $piezaId));

    $stmt = $db->prepare("
        INSERT INTO fallos (actividad_id, pieza_id, cantidad, tipo_pasada, fecha_registro)
        VALUES (:act_id, :pieza_id, :cantidad, :tipo_pasada, NOW())
    ");
    $stmt->execute([
        ':act_id' => $actividadId,
        ':pieza_id' => $piezaId,
        ':cantidad' => $cantidad,
        ':tipo_pasada' => $tipoPasada
    ]);

    $stmt2 = $db->prepare("SELECT estado FROM piezas WHERE id = :id");
    $stmt2->execute([':id' => $piezaId]);
    if ($stmt2->fetchColumn() === 'mantenimiento') {
        revisarDemocionMantenimiento($db, $piezaId);
    }

    $nivelDespues = nivelFallos(mediaFallosDia30($db, $piezaId));

    if ($nivelAntes === null || $nivelDespues === null || $nivelDespues['rango'] === $nivelAntes['rango']) {
        return null;
    }

    $stmt3 = $db->prepare("SELECT compositor, titulo FROM piezas WHERE id = :id");
    $stmt3->execute([':id' => $piezaId]);
    $pieza = $stmt3->fetch();

    return [
        'pieza' => ['compositor' => $pieza['compositor'], 'titulo' => $pieza['titulo']],
        'direccion' => $nivelDespues['rango'] > $nivelAntes['rango'] ? 'sube' : 'baja',
        'anterior' => $nivelAntes,
        'nuevo' => $nivelDespues,
    ];
}
