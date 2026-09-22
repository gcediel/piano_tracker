const express = require('express');
const router  = express.Router();
const pool    = require('../database');
const h       = require('../helpers');

router.post('/', async (req, res) => {
  const { accion } = req.body;
  let mensaje = '', error = '';

  try {
    if (accion === 'crear') {
      const bpm = parseInt(req.body.bpm || 120);
      if (bpm < 20 || bpm > 300) {
        error = 'El BPM debe estar entre 20 y 300.';
      } else {
        await pool.execute(`INSERT INTO ejercicios_tecnica (nombre,bpm,comentarios) VALUES (?,?,?)`,
          [String(req.body.nombre || '').trim(), bpm, String(req.body.comentarios || '').trim() || null]);
        mensaje = 'Ejercicio añadido correctamente.';
      }
    }

    if (accion === 'editar') {
      const bpm = parseInt(req.body.bpm || 120);
      if (bpm < 20 || bpm > 300) {
        error = 'El BPM debe estar entre 20 y 300.';
      } else {
        await pool.execute(`UPDATE ejercicios_tecnica SET nombre=?, bpm=?, comentarios=? WHERE id=?`,
          [String(req.body.nombre || '').trim(), bpm, String(req.body.comentarios || '').trim() || null, parseInt(req.body.id)]);
        mensaje = 'Ejercicio actualizado correctamente.';
      }
    }

    if (accion === 'activar' || accion === 'desactivar') {
      const activo = accion === 'activar' ? 1 : 0;
      await pool.execute(`UPDATE ejercicios_tecnica SET activo=? WHERE id=?`, [activo, parseInt(req.body.id)]);
      mensaje = activo ? 'Ejercicio activado.' : 'Ejercicio desactivado.';
    }

    if (accion === 'resetear_bpm') {
      const id = parseInt(req.body.id);
      await pool.execute(`UPDATE ejercicios_tecnica SET bpm=120 WHERE id=?`, [id]);
      await pool.execute(`DELETE FROM sesion_tecnica_ejercicios WHERE ejercicio_id=?`, [id]);
      mensaje = 'BPM reiniciado a 120 y prácticas borradas.';
    }

    if (accion === 'eliminar') {
      const id = parseInt(req.body.id);
      const [[{ n }]] = await pool.execute(`SELECT COUNT(*) AS n FROM sesion_tecnica_ejercicios WHERE ejercicio_id=?`, [id]);
      if (n > 0) {
        error = 'No se puede eliminar: el ejercicio tiene registros de práctica. Puedes desactivarlo.';
      } else {
        await pool.execute(`DELETE FROM ejercicios_tecnica WHERE id=?`, [id]);
        mensaje = 'Ejercicio eliminado.';
      }
    }
  } catch (e) {
    error = 'Error: ' + e.message;
  }

  if (mensaje && !error) return res.redirect('/tecnica?ok=' + encodeURIComponent(mensaje));
  await renderPage(req, res, mensaje, error);
});

router.get('/', async (req, res) => {
  const mensaje = req.query.ok ? String(req.query.ok) : '';
  await renderPage(req, res, mensaje, '');
});

async function renderPage(req, res, mensaje, error) {
  try {
    let ejercicioEditar = null;
    if (req.query.editar) {
      const [[row]] = await pool.execute(`SELECT * FROM ejercicios_tecnica WHERE id=?`, [req.query.editar]);
      ejercicioEditar = row || null;
    }

    const [ejercicios] = await pool.execute(`
      SELECT e.*, COUNT(se.id) AS num_reproducciones
      FROM ejercicios_tecnica e
      LEFT JOIN sesion_tecnica_ejercicios se ON se.ejercicio_id = e.id
      GROUP BY e.id
      ORDER BY e.bpm ASC, e.nombre ASC
    `);

    // Orden de "próxima práctica": mismo criterio que la cola de una sesión de
    // técnica por ejercicios (ver server/routes/sesion.js) — veces practicado
    // en las últimas 30 sesiones de técnica por ejercicios (no en total: un
    // ejercicio antiguo muy practicado no debe quedar siempre al final),
    // empatando por fecha de última práctica, BPM y nombre.
    const [sesionesRows] = await pool.execute(`
      SELECT a.sesion_id AS sid
      FROM actividades a JOIN sesiones s ON s.id = a.sesion_id
      WHERE a.tipo = 'tecnica_ejercicios'
      GROUP BY a.sesion_id
      ORDER BY MAX(s.fecha) DESC, a.sesion_id DESC
      LIMIT 30
    `);
    const sesionesIn = sesionesRows.length ? sesionesRows.map(r => r.sid).join(',') : '0';
    const [rankRows] = await pool.execute(`
      SELECT et.id,
             COALESCE(reciente.veces, 0) AS veces_recientes,
             ultima.fecha AS ultima_fecha
      FROM ejercicios_tecnica et
      LEFT JOIN (
        SELECT ste.ejercicio_id, COUNT(*) AS veces
        FROM sesion_tecnica_ejercicios ste
        JOIN actividades a ON a.id = ste.actividad_id
        WHERE a.sesion_id IN (${sesionesIn})
        GROUP BY ste.ejercicio_id
      ) reciente ON reciente.ejercicio_id = et.id
      LEFT JOIN (
        SELECT ejercicio_id, MAX(fecha) AS fecha
        FROM sesion_tecnica_ejercicios
        GROUP BY ejercicio_id
      ) ultima ON ultima.ejercicio_id = et.id
      WHERE et.activo = 1
      ORDER BY veces_recientes ASC, ultima_fecha ASC, et.bpm ASC, et.nombre ASC
    `);
    const ordenProximaPractica = {};
    rankRows.forEach((r, i) => { ordenProximaPractica[r.id] = i + 1; });

    res.render('tecnica', { pageTitle: 'Técnica - Piano Tracker', currentPage: 'tecnica', ejercicios, ejercicioEditar, mensaje, error, ordenProximaPractica, h });
  } catch (err) {
    console.error('[tecnica]', err);
    res.status(500).send('Error: ' + err.message);
  }
}

module.exports = router;
