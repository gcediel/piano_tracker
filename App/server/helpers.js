// ─── Formato de tiempo ───────────────────────────────────────────────────────

function formatearTiempo(segundos) {
  segundos = parseInt(segundos) || 0;
  const h = Math.floor(segundos / 3600);
  const m = Math.floor((segundos % 3600) / 60);
  const s = segundos % 60;
  return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}

function formatearTiempoBreve(segundos) {
  segundos = parseInt(segundos) || 0;
  if (segundos === 0) return '-';
  const h = Math.floor(segundos / 3600);
  const m = Math.floor((segundos % 3600) / 60);
  return h > 0 ? `${h}:${String(m).padStart(2,'0')}` : `${m}'`;
}

// ─── Fechas ──────────────────────────────────────────────────────────────────

function formatDate(dateStr) {
  if (!dateStr) return '';
  const str = dateStr instanceof Date ? dateStr.toISOString() : String(dateStr);
  const [y, m, d] = str.split('T')[0].split('-');
  return `${d}/${m}/${y}`;
}

function todayISO() {
  return new Date().toISOString().split('T')[0];
}

// weeksAgo=0 → semana natural (lun-dom) en curso; 1 → la semana pasada; 2 → la
// anterior a esa; etc. endExclusive es el lunes siguiente (útil para comparar
// columnas DATETIME con >= / <, en vez del BETWEEN inclusivo que vale para DATE).
function getWeekBounds(weeksAgo = 0) {
  const today = new Date();
  const day  = today.getDay(); // 0=Dom
  const diff = day === 0 ? -6 : 1 - day;
  const monday = new Date(today);
  monday.setDate(today.getDate() + diff - 7 * weeksAgo);
  const sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6);
  const nextMonday = new Date(monday);
  nextMonday.setDate(monday.getDate() + 7);
  return {
    start: monday.toISOString().split('T')[0],
    end:   sunday.toISOString().split('T')[0],
    endExclusive: nextMonday.toISOString().split('T')[0],
  };
}

function getMonthBounds(year, month) {
  const y = year  || new Date().getFullYear();
  const m = month || (new Date().getMonth() + 1);
  const start   = `${y}-${String(m).padStart(2,'0')}-01`;
  const lastDay = new Date(y, m, 0).getDate();
  const end     = `${y}-${String(m).padStart(2,'0')}-${String(lastDay).padStart(2,'0')}`;
  return { start, end, daysInMonth: lastDay };
}

function getDayOfYear() {
  const now   = new Date();
  const start = new Date(now.getFullYear(), 0, 0);
  return Math.floor((now - start) / 86400000);
}

// ─── Nombres de actividad ────────────────────────────────────────────────────

const NOMBRES_ACTIVIDAD = {
  calentamiento:      'Calentamiento',
  tecnica:            'Técnica',
  tecnica_ejercicios: 'Técnica',
  practica_tecnica:   'Práctica de técnica',
  practica:           'Práctica de repertorio',
  repertorio:         'Repertorio',
  improvisacion:      'Improvisación',
  composicion:        'Composición',
};

function getNombreActividad(tipo) {
  return NOMBRES_ACTIVIDAD[tipo] || tipo;
}

// ─── Instrumentos GM (fallback, 128) ─────────────────────────────────────────

const GM_INSTRUMENTS = [
  'Acoustic Grand Piano','Bright Acoustic Piano','Electric Grand Piano','Honky-tonk Piano',
  'Electric Piano 1','Electric Piano 2','Harpsichord','Clavinet',
  'Celesta','Glockenspiel','Music Box','Vibraphone',
  'Marimba','Xylophone','Tubular Bells','Dulcimer',
  'Drawbar Organ','Percussive Organ','Rock Organ','Church Organ',
  'Reed Organ','Accordion','Harmonica','Tango Accordion',
  'Nylon Guitar','Steel Guitar','Jazz Guitar','Clean Guitar',
  'Muted Guitar','Overdriven Guitar','Distortion Guitar','Guitar Harmonics',
  'Acoustic Bass','Finger Bass','Pick Bass','Fretless Bass',
  'Slap Bass 1','Slap Bass 2','Synth Bass 1','Synth Bass 2',
  'Violin','Viola','Cello','Contrabass',
  'Tremolo Strings','Pizzicato Strings','Orchestral Harp','Timpani',
  'String Ensemble 1','String Ensemble 2','Synth Strings 1','Synth Strings 2',
  'Choir Aahs','Voice Oohs','Synth Choir','Orchestra Hit',
  'Trumpet','Trombone','Tuba','Muted Trumpet',
  'French Horn','Brass Section','Synth Brass 1','Synth Brass 2',
  'Soprano Sax','Alto Sax','Tenor Sax','Baritone Sax',
  'Oboe','English Horn','Bassoon','Clarinet',
  'Piccolo','Flute','Recorder','Pan Flute',
  'Blown Bottle','Shakuhachi','Whistle','Ocarina',
  'Lead 1 (square)','Lead 2 (sawtooth)','Lead 3 (calliope)','Lead 4 (chiff)',
  'Lead 5 (charang)','Lead 6 (voice)','Lead 7 (fifths)','Lead 8 (bass+lead)',
  'Pad 1 (new age)','Pad 2 (warm)','Pad 3 (polysynth)','Pad 4 (choir)',
  'Pad 5 (bowed)','Pad 6 (metallic)','Pad 7 (halo)','Pad 8 (sweep)',
  'FX 1 (rain)','FX 2 (soundtrack)','FX 3 (crystal)','FX 4 (atmosphere)',
  'FX 5 (brightness)','FX 6 (goblins)','FX 7 (echoes)','FX 8 (sci-fi)',
  'Sitar','Banjo','Shamisen','Koto',
  'Kalimba','Bag pipe','Fiddle','Shanai',
  'Tinkle Bell','Agogo','Steel Drums','Woodblock',
  'Taiko Drum','Melodic Tom','Synth Drum','Reverse Cymbal',
  'Guitar Fret Noise','Breath Noise','Seashore','Bird Tweet',
  'Telephone Ring','Helicopter','Applause','Gunshot',
];

// ─── Resolución del tono MIDI a enviar para una pieza ────────────────────────
// Si la pieza tiene un tono Roland asignado (msb/lsb/pc), se usa tal cual.
// Si no, se usa el instrumento General MIDI (programa_midi, 0-127) como fallback,
// convertido a Program Change en base 1 (igual que en el manual del Roland).

function resolverTonoMidi(pieza) {
  if (!pieza) return { msb: null, lsb: null, pc: 1, nombre: 'Acoustic Grand Piano' };
  if (pieza.tono_pc != null && pieza.tono_pc !== '') {
    return { msb: pieza.tono_msb, lsb: pieza.tono_lsb, pc: pieza.tono_pc, nombre: pieza.tono_nombre };
  }
  const prog = pieza.programa_midi || 0;
  return { msb: null, lsb: null, pc: prog + 1, nombre: GM_INSTRUMENTS[prog] };
}

// ─── Algoritmo de selección de pieza ─────────────────────────────────────────
// Puntuación = SUM((10-fallos) * peso_temporal) / ponderacion
// Menor puntuación = mayor prioridad
//
// El peso temporal decae exponencialmente (semivida 15 días) en vez de cortar
// en seco a los 30 días, para no igualar "nunca evaluada" con "dominada y sin
// fallos recientes". Solo cuentan los fallos de la pasada "con metrónomo".
// Las piezas en mantenimiento reciben una penalización grande: solo se
// seleccionan cuando ya no queda ninguna pieza en aprendizaje disponible.

async function obtenerPiezaSugerida(pool, piezasYaSeleccionadas = []) {
  const [piezas] = await pool.execute('SELECT * FROM piezas WHERE activa = 1');
  if (piezas.length === 0) return null;

  const scores = [];
  for (const pieza of piezas) {
    if (piezasYaSeleccionadas.includes(pieza.id)) continue;

    const [rows] = await pool.execute(`
      SELECT
        COUNT(*) AS num_fallos,
        COALESCE(SUM(
          GREATEST(0, 10 - f.cantidad) * POW(0.5, DATEDIFF(CURDATE(), DATE(f.fecha_registro)) / 15)
        ), 0) AS suma_ponderada
      FROM fallos f
      WHERE f.pieza_id = ?
        AND f.tipo_pasada = 'metronomo'
        AND f.fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
    `, [pieza.id]);

    const numFallos = parseInt(rows[0]?.num_fallos) || 0;
    let score;
    if (numFallos === 0) {
      score = -1; // nunca evaluada con metrónomo: prioridad máxima
    } else {
      const sumaPonderada = parseFloat(rows[0]?.suma_ponderada) || 0;
      score = sumaPonderada / Math.max(parseFloat(pieza.ponderacion) || 1, 0.01);
    }

    if (pieza.estado === 'mantenimiento') score += 1000000;

    scores.push({ pieza, score });
  }

  if (scores.length === 0) return null;
  scores.sort((a, b) => a.score - b.score);
  return scores[0].pieza;
}

// ─── Progresión automática de tempo y mantenimiento (ver auditoria_pedagogica.md) ──

const INCREMENTO_TEMPO_PROGRESION = 5;  // BPM sugeridos al subir tempo (rango acordado: 4-6)
const MESES_PARA_GRADUACION = 3;        // meses consecutivos cumpliendo el umbral de graduación para pasar a mantenimiento
const TEMPO_DEMOCION_MARGEN = 8;        // BPM por debajo del tempo objetivo al volver de mantenimiento a aprendizaje
const UMBRAL_FALLOS_GRADUACION = 0.25;  // media máxima de fallos/día para que un mes cuente hacia la graduación (no exige 0)
const DIAS_MINIMOS_GRADUACION = 8;      // días mínimos practicados con metrónomo ese mes para que cuente (evita graduar por 1-2 sesiones sueltas)

// Media y días practicados "con metrónomo" de una pieza en el mes natural que empieza en primerDiaMes (string 'YYYY-MM-DD').
// Devuelve null si no hay ningún día practicado ese mes.
async function mediaFallosMetronomo(pool, piezaId, primerDiaMes) {
  const [y, m] = primerDiaMes.split('-').map(Number);
  const finExclusivo = m === 12 ? `${y + 1}-01-01` : `${y}-${String(m + 1).padStart(2, '0')}-01`;

  const [rows] = await pool.execute(`
    SELECT
      COUNT(DISTINCT DATE(f.fecha_registro)) AS dias_practicados,
      COALESCE(SUM(f.cantidad), 0) AS total_fallos
    FROM fallos f
    WHERE f.pieza_id = ?
      AND f.tipo_pasada = 'metronomo'
      AND f.fecha_registro >= ?
      AND f.fecha_registro < ?
  `, [piezaId, primerDiaMes, finExclusivo]);

  const dias = parseInt(rows[0]?.dias_practicados) || 0;
  if (dias === 0) return null;
  return { media: (parseFloat(rows[0]?.total_fallos) || 0) / dias, dias };
}

// Evalúa el mes natural anterior para cada pieza en aprendizaje con tempo objetivo
// que aún no haya sido evaluada ese mes, y deja marcada una sugerencia pendiente
// de subida de tempo o de graduación a mantenimiento (la confirma el usuario).
async function evaluarProgresionMensual(pool) {
  const hoy = new Date();
  const mesAnteriorDate = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
  const primerDiaMesAnterior = `${mesAnteriorDate.getFullYear()}-${String(mesAnteriorDate.getMonth() + 1).padStart(2, '0')}-01`;

  const [piezas] = await pool.execute(`
    SELECT * FROM piezas
    WHERE activa = 1 AND estado = 'aprendizaje' AND tempo_objetivo IS NOT NULL
      AND (mes_evaluado IS NULL OR mes_evaluado < ?)
  `, [primerDiaMesAnterior]);

  for (const pieza of piezas) {
    const datosMes = await mediaFallosMetronomo(pool, pieza.id, primerDiaMesAnterior);
    if (datosMes === null) continue; // sin datos ese mes: se reintenta en la próxima carga
    const { media, dias } = datosMes;

    if (media <= 1 && pieza.tempo < pieza.tempo_objetivo) {
      const nuevoTempo = Math.min(pieza.tempo_objetivo, pieza.tempo + INCREMENTO_TEMPO_PROGRESION);
      await pool.execute(
        `UPDATE piezas SET mes_evaluado = ?, sugerencia_tempo_pendiente = ? WHERE id = ?`,
        [primerDiaMesAnterior, nuevoTempo, pieza.id]
      );
    } else if (pieza.tempo >= pieza.tempo_objetivo) {
      // Graduación a mantenimiento: umbral más exigente que el de subir tempo
      // (no hace falta 0 fallos, pero sí un mínimo de días practicados para
      // que el mes cuente y no baste una sesión suelta con suerte).
      const cuentaMes = media <= UMBRAL_FALLOS_GRADUACION && dias >= DIAS_MINIMOS_GRADUACION;
      const meses = cuentaMes ? pieza.meses_objetivo_consecutivos + 1 : 0;

      if (meses >= MESES_PARA_GRADUACION) {
        // Alcanzado el umbral: se aplica directamente el paso a mantenimiento
        // (mismo cambio que el botón manual "A mantenimiento" de repertorio);
        // el aviso solo informa de que ya ha ocurrido, no pide confirmación.
        await pool.execute(
          `UPDATE piezas SET mes_evaluado = ?, estado = 'mantenimiento',
             meses_objetivo_consecutivos = 0, aviso_graduacion_pendiente = 1 WHERE id = ?`,
          [primerDiaMesAnterior, pieza.id]
        );
      } else {
        await pool.execute(
          `UPDATE piezas SET mes_evaluado = ?, meses_objetivo_consecutivos = ? WHERE id = ?`,
          [primerDiaMesAnterior, meses, pieza.id]
        );
      }
    } else {
      await pool.execute(
        `UPDATE piezas SET mes_evaluado = ?, meses_objetivo_consecutivos = 0 WHERE id = ?`,
        [primerDiaMesAnterior, pieza.id]
      );
    }
  }
}

// Comprueba si una pieza en mantenimiento debe volver a aprendizaje: sus dos
// últimos registros de fallos (de cualquier tipo de pasada) tienen cantidad > 0.
// Si se dispara la démotion, baja el tempo por debajo del objetivo en vez de
// reiniciar la escalera desde cero.
async function revisarDemocionMantenimiento(pool, piezaId) {
  const [ultimos] = await pool.execute(
    `SELECT cantidad FROM fallos WHERE pieza_id = ? ORDER BY fecha_registro DESC LIMIT 2`,
    [piezaId]
  );
  if (ultimos.length < 2 || ultimos[0].cantidad <= 0 || ultimos[1].cantidad <= 0) return false;

  const [[pieza]] = await pool.execute(`SELECT tempo, tempo_objetivo FROM piezas WHERE id = ?`, [piezaId]);

  if (pieza && pieza.tempo_objetivo) {
    const nuevoTempo = Math.max(20, pieza.tempo_objetivo - TEMPO_DEMOCION_MARGEN);
    await pool.execute(`
      UPDATE piezas SET estado='aprendizaje', meses_objetivo_consecutivos=0, mes_evaluado=NULL,
        sugerencia_tempo_pendiente=NULL, aviso_graduacion_pendiente=0, tempo=?
      WHERE id=?
    `, [nuevoTempo, piezaId]);
  } else {
    await pool.execute(`
      UPDATE piezas SET estado='aprendizaje', meses_objetivo_consecutivos=0, mes_evaluado=NULL,
        sugerencia_tempo_pendiente=NULL, aviso_graduacion_pendiente=0
      WHERE id=?
    `, [piezaId]);
  }
  return true;
}

// Nivel de "media de fallos/día" (últimos 30 días) según la leyenda de repertorio.
// Devuelve null si media es null. rango: 0 (peor) a 5 (mejor).
function nivelFallos(media) {
  if (media === null || media === undefined) return null;
  const m = parseFloat(media);
  if (m < 0.5) return { rango: 5, texto: 'Excelente', color: '#2E5F8A' };
  if (m < 1.5) return { rango: 4, texto: 'Muy bien', color: '#4A7BA7' };
  if (m < 2.5) return { rango: 3, texto: 'Bien', color: '#A3C1DA' };
  if (m < 3.5) return { rango: 2, texto: 'Aceptable', color: '#D4E89E' };
  if (m <= 5)  return { rango: 1, texto: 'Mejorable', color: '#9B9B9B' };
  return { rango: 0, texto: 'Atención', color: '#E57373' };
}

// Media de fallos/día de una pieza en los últimos 30 días (todo tipo de pasada),
// igual que el listado de repertorio. Devuelve null si no hay días practicados.
async function mediaFallosDia30(pool, piezaId) {
  const [rows] = await pool.execute(`
    SELECT
      COUNT(DISTINCT DATE(f.fecha_registro)) AS dias,
      COALESCE(SUM(f.cantidad), 0) AS total
    FROM fallos f
    WHERE f.pieza_id = ? AND f.fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  `, [piezaId]);
  const dias = parseInt(rows[0]?.dias) || 0;
  if (dias === 0) return null;
  return Math.round(((parseFloat(rows[0]?.total) || 0) / dias) * 100) / 100;
}

// Inserta un registro de fallos para una pieza y, si está en mantenimiento,
// comprueba si debe volver a aprendizaje. Devuelve datos de cambio de nivel
// (para celebrar/avisar en el frontend) si el nivel de "media de fallos/día
// (30 días)" de la pieza cambia con este registro, o null si no hay datos
// previos que comparar o el nivel no cambia.
async function registrarFallo(pool, actividadId, piezaId, cantidad, tipoPasada) {
  const nivelAntes = nivelFallos(await mediaFallosDia30(pool, piezaId));

  await pool.execute(
    `INSERT INTO fallos (actividad_id, pieza_id, cantidad, tipo_pasada, fecha_registro) VALUES (?,?,?,?,NOW())`,
    [actividadId, piezaId, cantidad, tipoPasada]
  );
  const [[pieza]] = await pool.execute(`SELECT compositor, titulo, estado FROM piezas WHERE id = ?`, [piezaId]);
  if (pieza && pieza.estado === 'mantenimiento') {
    await revisarDemocionMantenimiento(pool, piezaId);
  }

  const nivelDespues = nivelFallos(await mediaFallosDia30(pool, piezaId));

  if (!nivelAntes || !nivelDespues || nivelDespues.rango === nivelAntes.rango) {
    return null;
  }

  return {
    pieza: { compositor: pieza.compositor, titulo: pieza.titulo },
    direccion: nivelDespues.rango > nivelAntes.rango ? 'sube' : 'baja',
    anterior: nivelAntes,
    nuevo: nivelDespues,
  };
}

// ─── Rachas de días consecutivos practicados ─────────────────────────────────
// (movida aquí desde routes/dashboard.js para poder reutilizarla también en el
// resumen semanal, sin duplicar la lógica).

async function calcularRachas(pool) {
  const [rows] = await pool.execute(`
    SELECT DISTINCT fecha FROM sesiones WHERE estado = 'finalizada' ORDER BY fecha DESC
  `);
  const fechas = rows.map(r => r.fecha);
  if (!fechas.length) return { actual: 0, maxima: 0 };

  const dias = fechas.map(f => Math.floor(new Date(f + 'T00:00:00Z').getTime() / 86400000));
  const hoy = Math.floor(new Date(todayISO() + 'T00:00:00Z').getTime() / 86400000);

  let actual = 0;
  if (dias[0] === hoy || dias[0] === hoy - 1) {
    actual = 1;
    for (let i = 1; i < dias.length; i++) {
      if (dias[i - 1] - dias[i] === 1) actual++;
      else break;
    }
  }

  let maxima = 1, racha = 1;
  for (let i = 1; i < dias.length; i++) {
    if (dias[i - 1] - dias[i] === 1) { racha++; maxima = Math.max(maxima, racha); }
    else racha = 1;
  }
  maxima = Math.max(maxima, actual);

  return { actual, maxima };
}

// ─── Resumen semanal (pantalla previa al empezar la primera sesión de la semana) ──

// Clave en `configuracion` donde se guarda la última semana (año+nº ISO, ej.
// "202538") para la que ya se mostró el resumen, y así no repetirlo en cada
// sesión de la semana. Se comparte con la app PHP: usan la misma base de datos.
const CLAVE_RESUMEN_SEMANAL_MOSTRADO = 'resumen_semanal_mostrado_yearweek';

// Año ISO 8601 + nº de semana ISO, igual que PHP date('oW').
function isoYearWeek(date) {
  const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
  const dayNum = d.getUTCDay() || 7;
  d.setUTCDate(d.getUTCDate() + 4 - dayNum);
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
  const weekNo = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
  return `${d.getUTCFullYear()}${String(weekNo).padStart(2, '0')}`;
}

async function debeMostrarResumenSemanal(pool) {
  const semanaActual = isoYearWeek(new Date());
  const [rows] = await pool.execute(`SELECT valor FROM configuracion WHERE clave = ?`, [CLAVE_RESUMEN_SEMANAL_MOSTRADO]);
  return !rows.length || rows[0].valor !== semanaActual;
}

async function marcarResumenSemanalMostrado(pool) {
  const semanaActual = isoYearWeek(new Date());
  await pool.execute(
    `INSERT INTO configuracion (clave, valor, descripcion) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE valor = ?`,
    [CLAVE_RESUMEN_SEMANAL_MOSTRADO, semanaActual, 'Última semana (año+nº ISO) en que se mostró el resumen semanal', semanaActual]
  );
}

// Tiempo total practicado (segundos) y días distintos con sesión en [inicio, finInclusive]
// (sesiones.fecha es DATE, así que el BETWEEN inclusivo es exacto).
async function tiempoYDiasEnRango(pool, inicio, finInclusive) {
  const [[row]] = await pool.execute(`
    SELECT COALESCE(SUM(a.tiempo_segundos),0) AS segundos, COUNT(DISTINCT s.fecha) AS dias
    FROM actividades a JOIN sesiones s ON a.sesion_id = s.id
    WHERE s.fecha BETWEEN ? AND ?
  `, [inicio, finInclusive]);
  return { segundos: row.segundos, dias: row.dias };
}

// Media de fallos/día de una pieza (todo tipo de pasada) en [inicio, finExclusivo).
// fecha_registro es DATETIME, por eso el límite superior es exclusivo (el lunes
// siguiente), igual que mediaFallosMetronomo. Devuelve null si no hay ningún día
// practicado en el rango.
async function mediaFallosRango(pool, piezaId, inicio, finExclusivo) {
  const [rows] = await pool.execute(`
    SELECT COUNT(DISTINCT DATE(f.fecha_registro)) AS dias, COALESCE(SUM(f.cantidad),0) AS total
    FROM fallos f
    WHERE f.pieza_id = ? AND f.fecha_registro >= ? AND f.fecha_registro < ?
  `, [piezaId, inicio, finExclusivo]);
  const dias = parseInt(rows[0]?.dias) || 0;
  if (dias === 0) return null;
  return { media: (parseFloat(rows[0]?.total) || 0) / dias, dias };
}

// Texto HTML breve de comparación con la semana anterior, para las stat-box.
function compararTexto(actual, previo) {
  if (!previo) {
    return actual > 0 ? ' <small style="opacity:0.7;">(la semana anterior no hubo práctica)</small>' : '';
  }
  const pct = Math.round(((actual - previo) / previo) * 100);
  if (pct === 0) return ' <small style="opacity:0.7;">(igual que la semana anterior)</small>';
  const signo = pct > 0 ? '+' : '';
  return ` <small style="opacity:0.7;">(${signo}${pct}% vs. semana anterior)</small>`;
}

// Puntuación total del repertorio en una fecha de referencia: para cada pieza
// con al menos minDias días practicados en los 30 días anteriores a
// fechaReferencia (exclusive), suma (10 - media de fallos/día en esa ventana).
// Excluye piezas con menos práctica en la ventana, para que una sesión aislada
// no decida la puntuación de la pieza. Usado en el resumen semanal y, con
// ventana mensual en vez de rodante, en el informe anual.
async function puntuacionTotalEnFecha(pool, fechaReferencia, minDias = 5) {
  const inicio = new Date(fechaReferencia + 'T00:00:00Z');
  inicio.setUTCDate(inicio.getUTCDate() - 30);
  const fechaInicio = inicio.toISOString().split('T')[0];

  const [piezasTrabajadas] = await pool.execute(`
    SELECT DISTINCT p.id
    FROM fallos f JOIN piezas p ON f.pieza_id = p.id
    WHERE f.fecha_registro >= ? AND f.fecha_registro < ?
  `, [fechaInicio, fechaReferencia]);

  let total = 0;
  let piezasContadas = 0;
  for (const { id } of piezasTrabajadas) {
    const r = await mediaFallosRango(pool, id, fechaInicio, fechaReferencia);
    if (r && r.dias >= minDias) {
      total += 10 - r.media;
      piezasContadas++;
    }
  }
  return { total: Math.round(total * 10) / 10, piezas: piezasContadas };
}

// Construye los datos del resumen semanal: compara la semana natural (lun-dom)
// inmediatamente anterior a la actual con la semana previa a esa, sin importar
// qué día de la semana en curso se esté mostrando el resumen.
async function obtenerResumenSemanal(pool) {
  const pasada = getWeekBounds(1);
  const previa = getWeekBounds(2);

  const tiempo = await tiempoYDiasEnRango(pool, pasada.start, pasada.end);
  const tiempoPrevio = await tiempoYDiasEnRango(pool, previa.start, previa.end);

  // Puntuación total del repertorio: snapshot rodante de 30 días a cierre de
  // cada semana, para poder mostrar la diferencia semana contra semana.
  const puntuacion = await puntuacionTotalEnFecha(pool, pasada.endExclusive, 5);
  const puntuacionPrevia = await puntuacionTotalEnFecha(pool, previa.endExclusive, 5);
  const puntuacionDiff = Math.round((puntuacion.total - puntuacionPrevia.total) * 10) / 10;

  const [piezasTrabajadas] = await pool.execute(`
    SELECT DISTINCT p.id, p.compositor, p.titulo
    FROM fallos f JOIN piezas p ON f.pieza_id = p.id
    WHERE f.fecha_registro >= ? AND f.fecha_registro < ?
  `, [pasada.start, pasada.endExclusive]);

  const mejoras = [];
  for (const pieza of piezasTrabajadas) {
    const actual = await mediaFallosRango(pool, pieza.id, pasada.start, pasada.endExclusive);
    const anterior = await mediaFallosRango(pool, pieza.id, previa.start, previa.endExclusive);
    if (!actual || !anterior) continue;
    const delta = anterior.media - actual.media;
    if (delta > 0.1) {
      mejoras.push({ pieza, mediaActual: actual.media, mediaAnterior: anterior.media, delta });
    }
  }
  mejoras.sort((a, b) => b.delta - a.delta);

  const [piezasNuevas] = await pool.execute(`
    SELECT id, compositor, titulo FROM piezas
    WHERE fecha_creacion >= ? AND fecha_creacion < ?
    ORDER BY fecha_creacion
  `, [pasada.start, pasada.endExclusive]);

  const [avisosProgresion] = await pool.execute(`
    SELECT id, compositor, titulo, tempo, tempo_objetivo, sugerencia_tempo_pendiente, aviso_graduacion_pendiente
    FROM piezas
    WHERE activa = 1 AND (sugerencia_tempo_pendiente IS NOT NULL OR aviso_graduacion_pendiente = 1)
    ORDER BY compositor, titulo
  `);

  const [[tecnicaRow]] = await pool.execute(`
    SELECT COUNT(*) AS total,
           COALESCE(SUM(resultado='bien'),0) AS bien,
           COALESCE(SUM(resultado='mal'),0) AS mal,
           COUNT(DISTINCT ejercicio_id) AS ejercicios_distintos,
           AVG(bpm_practicado) AS bpm_medio
    FROM sesion_tecnica_ejercicios
    WHERE fecha >= ? AND fecha < ?
  `, [pasada.start, pasada.endExclusive]);

  const [[tecnicaPreviaRow]] = await pool.execute(`
    SELECT AVG(bpm_practicado) AS bpm_medio
    FROM sesion_tecnica_ejercicios
    WHERE fecha >= ? AND fecha < ?
  `, [previa.start, previa.endExclusive]);

  const tecnica = {
    total: tecnicaRow.total || 0,
    bien: tecnicaRow.bien || 0,
    mal: tecnicaRow.mal || 0,
    ejerciciosDistintos: tecnicaRow.ejercicios_distintos || 0,
    bpmMedio: parseFloat(tecnicaRow.bpm_medio) || 0,
    bpmMedioPrevio: tecnicaPreviaRow.bpm_medio !== null ? parseFloat(tecnicaPreviaRow.bpm_medio) : null,
  };

  let semanaFloja = false;
  if (tiempoPrevio.dias > 0) {
    const bajadaTiempo = tiempo.segundos < tiempoPrevio.segundos * 0.7;
    const bajadaDias = tiempo.dias < tiempoPrevio.dias;
    semanaFloja = bajadaTiempo || bajadaDias;
  }

  return {
    inicio: pasada.start, fin: pasada.end,
    tiempo, tiempoPrevio,
    puntuacion, puntuacionPrevia, puntuacionDiff,
    mejoras, logroPieza: mejoras[0] || null,
    piezasNuevas, avisosProgresion, tecnica, semanaFloja,
  };
}

// ─── Color de fallos (devuelve color CSS) ────────────────────────────────────

function getColorFallos(media) {
  const m = parseFloat(media);
  if (media === null || media === undefined || isNaN(m)) return '#999999';
  if (m < 0.5) return '#2E5F8A';
  if (m < 1.5) return '#4A7BA7';
  if (m < 2.5) return '#A3C1DA';
  if (m < 3.5) return '#D4E89E';
  if (m <= 5) return '#9B9B9B';
  return '#E57373';
}

// ─── Meses en castellano ──────────────────────────────────────────────────────

const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
               'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

module.exports = {
  formatearTiempo, formatearTiempoBreve,
  formatDate, todayISO, getWeekBounds, getMonthBounds, getDayOfYear,
  getNombreActividad, NOMBRES_ACTIVIDAD, GM_INSTRUMENTS,
  obtenerPiezaSugerida, getColorFallos, MESES,
  resolverTonoMidi,
  mediaFallosMetronomo, evaluarProgresionMensual, revisarDemocionMantenimiento, registrarFallo,
  calcularRachas, debeMostrarResumenSemanal, marcarResumenSemanalMostrado,
  tiempoYDiasEnRango, mediaFallosRango, compararTexto, obtenerResumenSemanal,
  puntuacionTotalEnFecha,
};
