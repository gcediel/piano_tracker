const express = require('express');
const router  = express.Router();
const pool    = require('../database');
const h       = require('../helpers');

async function autoCorregirSesiones() {
  const today = h.todayISO();
  const [colgadas] = await pool.execute(`SELECT id FROM sesiones WHERE estado = 'en_curso' AND fecha < ?`, [today]);
  for (const row of colgadas) {
    await pool.execute(`UPDATE actividades SET estado='completada', fecha_fin=NOW() WHERE sesion_id=? AND estado IN ('pendiente','en_curso')`, [row.id]);
    await pool.execute(`UPDATE sesiones SET estado='finalizada' WHERE id=?`, [row.id]);
  }
}

async function tiempoEntre(fechaInicio, fechaFin) {
  const [[row]] = await pool.execute(`
    SELECT COALESCE(SUM(a.tiempo_segundos),0) AS total
    FROM actividades a JOIN sesiones s ON a.sesion_id = s.id
    WHERE s.fecha BETWEEN ? AND ?
  `, [fechaInicio, fechaFin]);
  return row.total;
}

async function diasPracticadosEntre(fechaInicio, fechaFin) {
  const [[row]] = await pool.execute(`
    SELECT COUNT(DISTINCT fecha) AS n FROM sesiones
    WHERE fecha BETWEEN ? AND ? AND estado = 'finalizada'
  `, [fechaInicio, fechaFin]);
  return row.n;
}

async function calcularRachas() {
  const [rows] = await pool.execute(`
    SELECT DISTINCT fecha FROM sesiones WHERE estado = 'finalizada' ORDER BY fecha DESC
  `);
  const fechas = rows.map(r => r.fecha);
  if (!fechas.length) return { actual: 0, maxima: 0 };

  const dias = fechas.map(f => Math.floor(new Date(f + 'T00:00:00Z').getTime() / 86400000));
  const hoy = Math.floor(new Date(h.todayISO() + 'T00:00:00Z').getTime() / 86400000);

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

router.get('/', async (req, res) => {
  try {
    await autoCorregirSesiones();
    await h.evaluarProgresionMensual(pool);

    const [sugerenciasPendientes] = await pool.execute(`
      SELECT id, compositor, titulo, tempo, tempo_objetivo, sugerencia_tempo_pendiente, sugerencia_graduacion_pendiente
      FROM piezas
      WHERE activa = 1 AND (sugerencia_tempo_pendiente IS NOT NULL OR sugerencia_graduacion_pendiente = 1)
      ORDER BY compositor, titulo
    `);

    const today = h.todayISO();
    const week  = h.getWeekBounds();
    const month = h.getMonthBounds();
    const year  = { start: `${new Date().getFullYear()}-01-01`, end: `${new Date().getFullYear()}-12-31` };

    const tiempoHoy    = await tiempoEntre(today, today);
    const tiempoSemana = await tiempoEntre(week.start, week.end);
    const tiempoMes    = await tiempoEntre(month.start, month.end);
    const tiempoAnio   = await tiempoEntre(year.start, year.end);

    const hoyDate = new Date();
    const diaSemana = hoyDate.getDay() === 0 ? 7 : hoyDate.getDay();
    const pctSemana = Math.round(((await diasPracticadosEntre(week.start, today)) / diaSemana) * 100);
    const pctMes    = Math.round(((await diasPracticadosEntre(month.start, today)) / hoyDate.getDate()) * 100);
    const diasAnio  = await diasPracticadosEntre(year.start, today);

    const rachas = await calcularRachas();

    const [[sesionEnCurso]] = await pool.execute(`SELECT * FROM sesiones WHERE estado = 'en_curso' LIMIT 1`);

    const [ultimasSesiones] = await pool.execute(`
      SELECT s.id, s.fecha,
        COALESCE(SUM(a.tiempo_segundos),0) AS tiempo_total,
        (SELECT ROUND(AVG(f.cantidad),2) FROM fallos f
          JOIN actividades a2 ON f.actividad_id = a2.id
          WHERE a2.sesion_id = s.id AND a2.tipo = 'repertorio') AS media_fallos
      FROM sesiones s
      LEFT JOIN actividades a ON a.sesion_id = s.id
      WHERE s.estado = 'finalizada'
      GROUP BY s.id
      ORDER BY s.fecha DESC, s.id DESC
      LIMIT 5
    `);

    res.render('index', {
      pageTitle: 'Piano Tracker', currentPage: 'index',
      tiempoHoy, tiempoSemana, tiempoMes, tiempoAnio,
      pctSemana, pctMes, diasAnio, diaSemana,
      rachas, sesionEnCurso: sesionEnCurso || null, ultimasSesiones,
      sugerenciasPendientes,
      h,
    });
  } catch (err) {
    console.error('[dashboard]', err);
    res.status(500).send('Error: ' + err.message);
  }
});

module.exports = router;
