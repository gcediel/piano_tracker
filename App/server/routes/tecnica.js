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

    // Ejercicios con el número de prácticas en los últimos 30 días (mismo
    // criterio que usa la cola de la sesión, ver server/routes/sesion.js).
    const [ejercicios] = await pool.execute(`
      SELECT e.*, COALESCE(r.veces, 0) AS practicas_30d
      FROM ejercicios_tecnica e
      LEFT JOIN (
        SELECT ejercicio_id, COUNT(*) AS veces
        FROM sesion_tecnica_ejercicios
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY ejercicio_id
      ) r ON r.ejercicio_id = e.id
      ORDER BY e.bpm ASC, e.nombre ASC
    `);

    // Orden de "próxima práctica": mismo criterio que la cola de una sesión de
    // técnica por ejercicios — menos prácticas en los últimos 30 días primero;
    // en caso de empate, BPM más bajo y luego nombre.
    const ordenProximaPractica = {};
    ejercicios
      .filter(e => e.activo)
      .sort((a, b) => a.practicas_30d - b.practicas_30d || a.bpm - b.bpm || a.nombre.localeCompare(b.nombre))
      .forEach((e, i) => { ordenProximaPractica[e.id] = i + 1; });

    res.render('tecnica', { pageTitle: 'Técnica - Piano Tracker', currentPage: 'tecnica', ejercicios, ejercicioEditar, mensaje, error, ordenProximaPractica, h });
  } catch (err) {
    console.error('[tecnica]', err);
    res.status(500).send('Error: ' + err.message);
  }
}

module.exports = router;
