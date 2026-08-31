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

function getWeekBounds() {
  const today = new Date();
  const day  = today.getDay(); // 0=Dom
  const diff = day === 0 ? -6 : 1 - day;
  const monday = new Date(today);
  monday.setDate(today.getDate() + diff);
  const sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6);
  return {
    start: monday.toISOString().split('T')[0],
    end:   sunday.toISOString().split('T')[0],
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
const MESES_PARA_GRADUACION = 3;        // meses consecutivos a tempo objetivo con media <=1 para pasar a mantenimiento
const TEMPO_DEMOCION_MARGEN = 8;        // BPM por debajo del tempo objetivo al volver de mantenimiento a aprendizaje

// Media de fallos "con metrónomo" de una pieza en el mes natural que empieza en primerDiaMes (string 'YYYY-MM-DD').
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
  return (parseFloat(rows[0]?.total_fallos) || 0) / dias;
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
    const media = await mediaFallosMetronomo(pool, pieza.id, primerDiaMesAnterior);
    if (media === null) continue; // sin datos ese mes: se reintenta en la próxima carga

    if (media <= 1 && pieza.tempo < pieza.tempo_objetivo) {
      const nuevoTempo = Math.min(pieza.tempo_objetivo, pieza.tempo + INCREMENTO_TEMPO_PROGRESION);
      await pool.execute(
        `UPDATE piezas SET mes_evaluado = ?, sugerencia_tempo_pendiente = ? WHERE id = ?`,
        [primerDiaMesAnterior, nuevoTempo, pieza.id]
      );
    } else if (media <= 1 && pieza.tempo >= pieza.tempo_objetivo) {
      const meses = pieza.meses_objetivo_consecutivos + 1;
      const graduar = meses >= MESES_PARA_GRADUACION;
      await pool.execute(
        `UPDATE piezas SET mes_evaluado = ?, meses_objetivo_consecutivos = ?, sugerencia_graduacion_pendiente = ? WHERE id = ?`,
        [primerDiaMesAnterior, meses, graduar ? 1 : 0, pieza.id]
      );
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
        sugerencia_tempo_pendiente=NULL, sugerencia_graduacion_pendiente=0, tempo=?
      WHERE id=?
    `, [nuevoTempo, piezaId]);
  } else {
    await pool.execute(`
      UPDATE piezas SET estado='aprendizaje', meses_objetivo_consecutivos=0, mes_evaluado=NULL,
        sugerencia_tempo_pendiente=NULL, sugerencia_graduacion_pendiente=0
      WHERE id=?
    `, [piezaId]);
  }
  return true;
}

// Inserta un registro de fallos para una pieza y, si está en mantenimiento,
// comprueba si debe volver a aprendizaje.
async function registrarFallo(pool, actividadId, piezaId, cantidad, tipoPasada) {
  await pool.execute(
    `INSERT INTO fallos (actividad_id, pieza_id, cantidad, tipo_pasada, fecha_registro) VALUES (?,?,?,?,NOW())`,
    [actividadId, piezaId, cantidad, tipoPasada]
  );
  const [[pieza]] = await pool.execute(`SELECT estado FROM piezas WHERE id = ?`, [piezaId]);
  if (pieza && pieza.estado === 'mantenimiento') {
    await revisarDemocionMantenimiento(pool, piezaId);
  }
}

// ─── Color de fallos (devuelve color CSS) ────────────────────────────────────

function getColorFallos(media) {
  const m = parseFloat(media);
  if (media === null || media === undefined || isNaN(m)) return '#999999';
  if (m < 0.5) return '#27ae60';
  if (m < 1.5) return '#2ecc71';
  if (m < 3.0) return '#f39c12';
  if (m < 5.0) return '#e67e22';
  return '#e74c3c';
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
};
