const express = require('express');
const router  = express.Router();
const pool    = require('../database');
const h       = require('../helpers');

const TECNICA_CASE = `CASE WHEN a.tipo IN ('tecnica','tecnica_ejercicios','practica_tecnica') THEN 'tecnica' ELSE a.tipo END`;

router.get('/', async (req, res) => {
  try {
    const periodo = req.query.periodo || 'mes';
    const hoy     = new Date();
    const mes     = req.query.mes  || String(hoy.getMonth() + 1).padStart(2, '0');
    const anio    = req.query.anio || String(hoy.getFullYear());

    let fechaInicio, fechaFin;
    switch (periodo) {
      case 'dia': {
        const today = h.todayISO();
        fechaInicio = today; fechaFin = today;
        break;
      }
      case 'semana': {
        const week = h.getWeekBounds();
        fechaInicio = week.start; fechaFin = week.end;
        break;
      }
      case 'anio':
        fechaInicio = `${anio}-01-01`;
        fechaFin    = `${anio}-12-31`;
        break;
      case 'mes':
      default: {
        const { start, end } = h.getMonthBounds(parseInt(anio), parseInt(mes));
        fechaInicio = start; fechaFin = end;
      }
    }

    // Tiempo por actividad (con "veces practicada" especial para repertorio)
    const [tiempoActividades] = await pool.execute(`
      SELECT ${TECNICA_CASE} AS tipo,
        SUM(a.tiempo_segundos) AS tiempo_total,
        COUNT(DISTINCT a.id) AS num_actividades
      FROM actividades a JOIN sesiones s ON a.sesion_id = s.id
      WHERE s.fecha BETWEEN ? AND ?
      GROUP BY ${TECNICA_CASE} ORDER BY tiempo_total DESC
    `, [fechaInicio, fechaFin]);

    const [[{ num_piezas: numPiezasRepertorio }]] = await pool.execute(`
      SELECT COUNT(*) AS num_piezas
      FROM fallos f JOIN actividades a ON f.actividad_id = a.id JOIN sesiones s ON a.sesion_id = s.id
      WHERE a.tipo = 'repertorio' AND s.fecha BETWEEN ? AND ?
    `, [fechaInicio, fechaFin]);

    const tiempoPorActividad = tiempoActividades.map(act => ({
      tipo: act.tipo,
      tiempo_total: act.tiempo_total,
      num_veces: act.tipo === 'repertorio' ? numPiezasRepertorio : act.num_actividades,
    }));

    const [fallosPorPieza] = await pool.execute(`
      SELECT p.id, p.compositor, p.titulo,
        COUNT(DISTINCT DATE(f.fecha_registro)) AS dias_practicados,
        ROUND(SUM(f.cantidad) / NULLIF(COUNT(DISTINCT DATE(f.fecha_registro)),0), 2) AS media_fallos_dia
      FROM piezas p JOIN fallos f ON p.id = f.pieza_id
      JOIN actividades a ON f.actividad_id = a.id JOIN sesiones s ON a.sesion_id = s.id
      WHERE s.fecha BETWEEN ? AND ?
      GROUP BY p.id ORDER BY dias_practicados DESC
    `, [fechaInicio, fechaFin]);

    const [[{ tiempoTotal }]] = await pool.execute(`
      SELECT COALESCE(SUM(a.tiempo_segundos),0) AS tiempoTotal
      FROM actividades a JOIN sesiones s ON a.sesion_id = s.id
      WHERE s.fecha BETWEEN ? AND ?
    `, [fechaInicio, fechaFin]);

    const [[{ numSesiones }]] = await pool.execute(`
      SELECT COUNT(*) AS numSesiones FROM sesiones WHERE fecha BETWEEN ? AND ? AND estado = 'finalizada'
    `, [fechaInicio, fechaFin]);

    const [tiempoPorDia] = await pool.execute(`
      SELECT s.fecha, s.id AS sesion_id,
        SUM(a.tiempo_segundos) AS tiempo_total,
        SUM(CASE WHEN a.tipo = 'calentamiento' THEN a.tiempo_segundos ELSE 0 END) AS tiempo_calentamiento,
        SUM(CASE WHEN a.tipo IN ('tecnica','tecnica_ejercicios') THEN a.tiempo_segundos ELSE 0 END) AS tiempo_tecnica,
        SUM(CASE WHEN a.tipo = 'practica_tecnica' THEN a.tiempo_segundos ELSE 0 END) AS tiempo_practica_tecnica,
        SUM(CASE WHEN a.tipo = 'practica' THEN a.tiempo_segundos ELSE 0 END) AS tiempo_practica,
        SUM(CASE WHEN a.tipo = 'repertorio' THEN a.tiempo_segundos ELSE 0 END) AS tiempo_repertorio,
        SUM(CASE WHEN a.tipo = 'improvisacion' THEN a.tiempo_segundos ELSE 0 END) AS tiempo_improvisacion,
        SUM(CASE WHEN a.tipo = 'composicion' THEN a.tiempo_segundos ELSE 0 END) AS tiempo_composicion,
        (SELECT ROUND(AVG(f.cantidad),2) FROM fallos f JOIN actividades a2 ON f.actividad_id = a2.id WHERE a2.sesion_id = s.id AND a2.tipo = 'repertorio') AS media_fallos_repertorio
      FROM sesiones s LEFT JOIN actividades a ON s.id = a.sesion_id
      WHERE s.fecha BETWEEN ? AND ?
      GROUP BY s.fecha, s.id ORDER BY s.fecha
    `, [fechaInicio, fechaFin]);

    res.render('informes', {
      pageTitle: 'Informes - Piano Tracker', currentPage: 'informes',
      periodo, mes, anio, fechaInicio, fechaFin,
      tiempoPorActividad, fallosPorPieza, tiempoPorDia,
      tiempoTotal, numSesiones,
      h,
    });
  } catch (err) {
    console.error('[informes]', err);
    res.status(500).send('Error: ' + err.message);
  }
});

module.exports = router;
