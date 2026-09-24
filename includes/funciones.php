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

            if ($meses >= MESES_PARA_GRADUACION) {
                // Alcanzado el umbral: se aplica directamente el paso a mantenimiento
                // (mismo cambio que el botón manual "A mantenimiento" de repertorio.php);
                // el aviso solo informa de que ya ha ocurrido, no pide confirmación.
                $stmt2 = $db->prepare("
                    UPDATE piezas SET mes_evaluado = :mes, estado = 'mantenimiento',
                                       meses_objetivo_consecutivos = 0, aviso_graduacion_pendiente = 1
                    WHERE id = :id
                ");
                $stmt2->execute([':mes' => $primerDiaMesAnterior, ':id' => $pieza['id']]);
            } else {
                $stmt2 = $db->prepare("
                    UPDATE piezas SET mes_evaluado = :mes, meses_objetivo_consecutivos = :meses
                    WHERE id = :id
                ");
                $stmt2->execute([':mes' => $primerDiaMesAnterior, ':meses' => $meses, ':id' => $pieza['id']]);
            }
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
                               aviso_graduacion_pendiente = 0";
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

// Calcula la racha de días consecutivos practicados: la racha actual (contando
// hacia atrás desde hoy, o desde ayer si hoy aún no hay actividad registrada) y
// la racha más larga registrada nunca. $hayActividadHoy se puede pasar si ya se
// conoce (evita repetir la consulta); si se omite, se calcula aquí.
function calcularRachas($db, $hayActividadHoy = null) {
    if ($hayActividadHoy === null) {
        $stmt = $db->prepare("SELECT SUM(tiempo_segundos) as total FROM actividades a
                              JOIN sesiones s ON a.sesion_id = s.id
                              WHERE s.fecha = CURDATE()");
        $stmt->execute();
        $hayActividadHoy = ($stmt->fetch()['total'] ?? 0) > 0;
    }

    $stmt = $db->query("SELECT DISTINCT fecha FROM sesiones ORDER BY fecha DESC");
    $fechasSesiones = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $rachaActual = 0;
    $rachaMasLarga = 0;
    $rachaTemp = 0;

    if (!empty($fechasSesiones)) {
        $hoy = new DateTime();
        $hoy->setTime(0, 0, 0);

        $fechaCheck = clone $hoy;
        if (!$hayActividadHoy) {
            $fechaCheck->modify('-1 day');
        }

        foreach ($fechasSesiones as $fecha) {
            $fechaSesion = new DateTime($fecha);
            $fechaSesion->setTime(0, 0, 0);

            if ($fechaSesion == $fechaCheck) {
                $rachaActual++;
                $fechaCheck->modify('-1 day');
            } else {
                break;
            }
        }

        $fechaAnterior = null;
        foreach ($fechasSesiones as $fecha) {
            $fechaSesion = new DateTime($fecha);

            if ($fechaAnterior === null) {
                $rachaTemp = 1;
            } else {
                $diff = $fechaAnterior->diff($fechaSesion);
                if ($diff->days == 1) {
                    $rachaTemp++;
                } else {
                    $rachaMasLarga = max($rachaMasLarga, $rachaTemp);
                    $rachaTemp = 1;
                }
            }

            $fechaAnterior = $fechaSesion;
        }
        $rachaMasLarga = max($rachaMasLarga, $rachaTemp);
    }

    return ['actual' => $rachaActual, 'mas_larga' => $rachaMasLarga];
}

// ============================================
// Resúmenes de periodo (pantalla previa a la primera sesión de la semana, del
// mes o del año, con los datos del periodo natural anterior)
// ============================================

// Por cada periodo, clave en `configuracion` donde se guarda el último periodo
// para el que ya se mostró el resumen (así no se repite en cada sesión), y el
// formato de date() que identifica el periodo en curso.
define('CLAVE_RESUMEN_SEMANAL_MOSTRADO', 'resumen_semanal_mostrado_yearweek');
define('CLAVE_RESUMEN_MENSUAL_MOSTRADO', 'resumen_mensual_mostrado_yearmonth');
define('CLAVE_RESUMEN_ANUAL_MOSTRADO', 'resumen_anual_mostrado_year');

const RESUMEN_PERIODOS = [
    'semana' => ['clave' => CLAVE_RESUMEN_SEMANAL_MOSTRADO, 'formato' => 'oW',
                 'descripcion' => 'Última semana (año+nº ISO) en que se mostró el resumen semanal'],
    'mes'    => ['clave' => CLAVE_RESUMEN_MENSUAL_MOSTRADO, 'formato' => 'Ym',
                 'descripcion' => 'Último mes (año+mes) en que se mostró el resumen mensual'],
    'anio'   => ['clave' => CLAVE_RESUMEN_ANUAL_MOSTRADO, 'formato' => 'Y',
                 'descripcion' => 'Último año en que se mostró el resumen anual'],
];

// True si aún no se ha mostrado el resumen del periodo ('semana', 'mes', 'anio') en curso.
// Si la clave aún no existe (instalación nueva o recién añadido ese resumen), se
// crea con el periodo en curso sin mostrar nada: el primer resumen saldrá al
// empezar el periodo siguiente, en vez de uno a medias o de antes de usar la app.
function debeMostrarResumen($db, $periodo) {
    $cfg = RESUMEN_PERIODOS[$periodo];
    $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = :k");
    $stmt->execute([':k' => $cfg['clave']]);
    $guardado = $stmt->fetchColumn();
    if ($guardado === false) {
        guardarResumenMostrado($db, $periodo);
        return false;
    }
    return $guardado !== date($cfg['formato']);
}

function guardarResumenMostrado($db, $periodo) {
    $cfg = RESUMEN_PERIODOS[$periodo];
    $valor = date($cfg['formato']);
    $stmt = $db->prepare("INSERT INTO configuracion (clave, valor, descripcion) VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE valor = ?");
    $stmt->execute([$cfg['clave'], $valor, $cfg['descripcion'], $valor]);
}

// Resumen que toca mostrar antes de la próxima sesión, o null si ninguno. Si
// hay varios pendientes (p. ej. el 1 de enero), gana el de periodo más amplio,
// que ya incluye los datos de los otros (ver marcarResumenMostrado).
function resumenPendiente($db) {
    foreach (['anio', 'mes', 'semana'] as $periodo) {
        if (debeMostrarResumen($db, $periodo)) {
            return $periodo;
        }
    }
    return null;
}

// Marca el periodo en curso como ya mostrado (se llama al confirmar el resumen
// y pasar a la sesión, no solo al visitarlo voluntariamente). También marca los
// periodos más cortos, para no encadenar varios resúmenes seguidos.
function marcarResumenMostrado($db, $periodo) {
    $orden = ['semana', 'mes', 'anio'];
    foreach (array_slice($orden, 0, array_search($periodo, $orden) + 1) as $p) {
        guardarResumenMostrado($db, $p);
    }
}

// Tiempo total practicado (segundos) y días distintos con sesión en [$inicio, $finExclusivo).
function tiempoYDiasEnRango($db, $inicio, $finExclusivo) {
    $stmt = $db->prepare("
        SELECT SUM(a.tiempo_segundos) as segundos, COUNT(DISTINCT s.fecha) as dias
        FROM actividades a
        JOIN sesiones s ON a.sesion_id = s.id
        WHERE s.fecha >= :inicio AND s.fecha < :fin
    ");
    $stmt->execute([':inicio' => $inicio, ':fin' => $finExclusivo]);
    $r = $stmt->fetch();
    return [
        'segundos' => (int)($r['segundos'] ?? 0),
        'dias' => (int)($r['dias'] ?? 0),
    ];
}

// Media de fallos/día de una pieza (todo tipo de pasada) en [$inicio, $finExclusivo).
// Devuelve null si no hay ningún día practicado en el rango (igual criterio que
// mediaFallosDia30, pero sobre un rango de fechas arbitrario en vez de "últimos 30 días").
function mediaFallosRango($db, $piezaId, $inicio, $finExclusivo) {
    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT DATE(f.fecha_registro)) as dias,
            SUM(f.cantidad) as total
        FROM fallos f
        WHERE f.pieza_id = :pieza_id
          AND f.fecha_registro >= :inicio
          AND f.fecha_registro < :fin
    ");
    $stmt->execute([':pieza_id' => $piezaId, ':inicio' => $inicio, ':fin' => $finExclusivo]);
    $r = $stmt->fetch();
    $dias = (int)($r['dias'] ?? 0);
    if ($dias === 0) {
        return null;
    }
    return ['media' => ($r['total'] ?? 0) / $dias, 'dias' => $dias];
}

// Puntuación total del repertorio en una fecha de referencia: para cada pieza
// con al menos $minDias días practicados en los 30 días anteriores a
// $fechaReferencia (exclusive), suma (10 - media de fallos/día en esa ventana).
// Excluye piezas con menos práctica en la ventana, para que una sesión aislada
// no decida la puntuación de la pieza. Usado en los resúmenes de periodo y, con
// ventana mensual en vez de rodante, en el informe anual.
function puntuacionTotalEnFecha($db, $fechaReferencia, $minDias = 5) {
    $fechaInicio = date('Y-m-d', strtotime($fechaReferencia . ' -30 days'));
    $stmt = $db->prepare("
        SELECT DISTINCT p.id
        FROM fallos f
        JOIN piezas p ON f.pieza_id = p.id
        WHERE f.fecha_registro >= :inicio AND f.fecha_registro < :fin
    ");
    $stmt->execute([':inicio' => $fechaInicio, ':fin' => $fechaReferencia]);
    $piezaIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $total = 0;
    $piezasContadas = 0;
    foreach ($piezaIds as $piezaId) {
        $r = mediaFallosRango($db, $piezaId, $fechaInicio, $fechaReferencia);
        if ($r !== null && $r['dias'] >= $minDias) {
            $total += 10 - $r['media'];
            $piezasContadas++;
        }
    }
    return ['total' => round($total, 1), 'piezas' => $piezasContadas];
}

// Límites del periodo natural anterior al actual ('inicio'/'fin', fin exclusivo)
// y del previo a ese ('inicio_previo'), para el resumen de semana, mes o año.
function limitesResumen($periodo) {
    switch ($periodo) {
        case 'mes':
            $fin = date('Y-m-01');
            $inicio = date('Y-m-d', strtotime($fin . ' -1 month'));
            $inicioPrevio = date('Y-m-d', strtotime($fin . ' -2 months'));
            break;
        case 'anio':
            $fin = date('Y-01-01');
            $inicio = date('Y-m-d', strtotime($fin . ' -1 year'));
            $inicioPrevio = date('Y-m-d', strtotime($fin . ' -2 years'));
            break;
        default:
            $fin = date('Y-m-d', strtotime('monday this week'));
            $inicio = date('Y-m-d', strtotime($fin . ' -7 days'));
            $inicioPrevio = date('Y-m-d', strtotime($fin . ' -14 days'));
    }
    return ['inicio' => $inicio, 'fin' => $fin, 'inicio_previo' => $inicioPrevio];
}

// Construye los datos del resumen de un periodo ('semana', 'mes' o 'anio'):
// compara el periodo natural (semana lun-dom, mes o año) inmediatamente
// anterior al actual con el previo a ese, sin importar qué día del periodo en
// curso se esté mostrando el resumen.
function obtenerResumenPeriodo($db, $periodo) {
    $lim = limitesResumen($periodo);
    $inicioPasado = $lim['inicio'];
    $finPasado = $lim['fin'];
    $inicioPrevio = $lim['inicio_previo'];

    $r = [
        'periodo' => $periodo,
        'inicio' => $inicioPasado,
        'fin' => $finPasado, // exclusivo
        'dias_periodo' => (int)round((strtotime($finPasado) - strtotime($inicioPasado)) / 86400),
    ];

    $r['tiempo'] = tiempoYDiasEnRango($db, $inicioPasado, $finPasado);
    $r['tiempo_previo'] = tiempoYDiasEnRango($db, $inicioPrevio, $inicioPasado);

    // Puntuación total del repertorio: snapshot rodante de 30 días a cierre de
    // cada periodo, para poder mostrar la diferencia periodo contra periodo.
    $r['puntuacion'] = puntuacionTotalEnFecha($db, $finPasado, 5);
    $r['puntuacion_previa'] = puntuacionTotalEnFecha($db, $inicioPasado, 5);
    $r['puntuacion_diff'] = round($r['puntuacion']['total'] - $r['puntuacion_previa']['total'], 1);

    // Piezas trabajadas en el periodo, con su media de fallos y comparación
    // con el periodo previo (solo cuenta como "mejora" si hay datos en ambos).
    $stmt = $db->prepare("
        SELECT DISTINCT p.id, p.compositor, p.titulo
        FROM fallos f
        JOIN piezas p ON f.pieza_id = p.id
        WHERE f.fecha_registro >= :inicio AND f.fecha_registro < :fin
    ");
    $stmt->execute([':inicio' => $inicioPasado, ':fin' => $finPasado]);
    $piezasTrabajadas = $stmt->fetchAll();

    $mejoras = [];
    foreach ($piezasTrabajadas as $pieza) {
        $actual = mediaFallosRango($db, $pieza['id'], $inicioPasado, $finPasado);
        $anterior = mediaFallosRango($db, $pieza['id'], $inicioPrevio, $inicioPasado);
        if ($actual === null || $anterior === null) {
            continue;
        }
        $delta = $anterior['media'] - $actual['media'];
        if ($delta > 0.1) {
            $mejoras[] = [
                'pieza' => $pieza,
                'media_actual' => $actual['media'],
                'media_anterior' => $anterior['media'],
                'delta' => $delta,
            ];
        }
    }
    usort($mejoras, fn($a, $b) => $b['delta'] <=> $a['delta']);
    $r['mejoras'] = $mejoras;
    $r['logro_pieza'] = $mejoras[0] ?? null;

    // Piezas nuevas añadidas al repertorio en el periodo
    $stmt = $db->prepare("
        SELECT id, compositor, titulo FROM piezas
        WHERE fecha_creacion >= :inicio AND fecha_creacion < :fin
        ORDER BY fecha_creacion
    ");
    $stmt->execute([':inicio' => $inicioPasado, ':fin' => $finPasado]);
    $r['piezas_nuevas'] = $stmt->fetchAll();

    // Avisos de progresión pendientes (tempo subido / graduación a mantenimiento),
    // ya evaluados en evaluarProgresionMensual (se llama antes en la página).
    $stmt = $db->query("
        SELECT id, compositor, titulo, tempo, tempo_objetivo, sugerencia_tempo_pendiente, aviso_graduacion_pendiente
        FROM piezas
        WHERE activa = 1 AND (sugerencia_tempo_pendiente IS NOT NULL OR aviso_graduacion_pendiente = 1)
        ORDER BY compositor, titulo
    ");
    $r['avisos_progresion'] = $stmt->fetchAll();

    // Técnica: ejercicios trabajados en el periodo y BPM medio, comparado con el previo
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total,
            SUM(resultado = 'bien') as bien,
            SUM(resultado = 'mal') as mal,
            COUNT(DISTINCT ejercicio_id) as ejercicios_distintos,
            AVG(bpm_practicado) as bpm_medio
        FROM sesion_tecnica_ejercicios
        WHERE fecha >= :inicio AND fecha < :fin
    ");
    $stmt->execute([':inicio' => $inicioPasado, ':fin' => $finPasado]);
    $tecnica = $stmt->fetch();
    $tecnica['total'] = (int)($tecnica['total'] ?? 0);
    $tecnica['bien'] = (int)($tecnica['bien'] ?? 0);
    $tecnica['mal'] = (int)($tecnica['mal'] ?? 0);

    $stmt->execute([':inicio' => $inicioPrevio, ':fin' => $inicioPasado]);
    $tecnicaPrevia = $stmt->fetch();
    $tecnica['bpm_medio_previo'] = $tecnicaPrevia['bpm_medio'] ?? null;
    $r['tecnica'] = $tecnica;

    // Aviso neutro de "periodo flojo": bajada apreciable de tiempo o de días
    // respecto al periodo anterior (solo si ese periodo anterior sí tuvo práctica).
    $r['periodo_flojo'] = false;
    if ($r['tiempo_previo']['dias'] > 0) {
        $bajadaTiempo = $r['tiempo']['segundos'] < $r['tiempo_previo']['segundos'] * 0.7;
        $bajadaDias = $r['tiempo']['dias'] < $r['tiempo_previo']['dias'];
        $r['periodo_flojo'] = $bajadaTiempo || $bajadaDias;
    }

    return $r;
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
