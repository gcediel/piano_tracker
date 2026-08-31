const express = require('express');
const router  = express.Router();
const pool    = require('../database');
const h       = require('../helpers');

router.post('/', async (req, res) => {
  const { accion } = req.body;
  let mensaje = '', error = '';
  const conn = await pool.getConnection();

  try {
    await conn.beginTransaction();

    if (accion === 'crear') {
      const [sesResult] = await conn.execute(`INSERT INTO sesiones (fecha,estado) VALUES (?,'finalizada')`, [req.body.fecha]);
      await insertActividades(conn, sesResult.insertId, Object.values(req.body.actividades || {}), req.body.fecha);
      mensaje = 'Sesión creada correctamente';
    }

    if (accion === 'editar') {
      const sid = req.body.id;
      await conn.execute(`UPDATE sesiones SET fecha=?, estado='finalizada' WHERE id=?`, [req.body.fecha, sid]);
      const [actRows] = await conn.execute(`SELECT id FROM actividades WHERE sesion_id=?`, [sid]);
      if (actRows.length) {
        const ids = actRows.map(r => r.id);
        await conn.query(`DELETE FROM fallos WHERE actividad_id IN (${ids.map(() => '?').join(',')})`, ids);
      }
      await conn.execute(`DELETE FROM actividades WHERE sesion_id=?`, [sid]);
      await insertActividades(conn, sid, Object.values(req.body.actividades || {}), req.body.fecha);
      mensaje = 'Sesión actualizada correctamente';
    }

    if (accion === 'eliminar') {
      const sid = req.body.id;
      const [actRows] = await conn.execute(`SELECT id FROM actividades WHERE sesion_id=?`, [sid]);
      if (actRows.length) {
        const ids = actRows.map(r => r.id);
        await conn.query(`DELETE FROM fallos WHERE actividad_id IN (${ids.map(() => '?').join(',')})`, ids);
      }
      await conn.execute(`DELETE FROM actividades WHERE sesion_id=?`, [sid]);
      await conn.execute(`DELETE FROM sesiones WHERE id=?`, [sid]);
      mensaje = 'Sesión eliminada correctamente';
    }

    await conn.commit();
  } catch (e) {
    await conn.rollback();
    error = 'Error: ' + e.message;
  } finally {
    conn.release();
  }

  await renderPage(req, res, mensaje, error);
});

async function insertActividades(conn, sesionId, actividades, fecha) {
  let orden = 1;
  for (const act of actividades) {
    if (!act.tipo) continue;
    const tiempoSeg = parseInt(act.tiempo || 0);
    const [actResult] = await conn.execute(
      `INSERT INTO actividades (sesion_id,tipo,tiempo_segundos,notas,orden,estado) VALUES (?,?,?,?,?,'completada')`,
      [sesionId, act.tipo, tiempoSeg, act.notas || null, orden++]
    );
    if (act.tipo === 'repertorio' && act.pieza_id) {
      await conn.execute(
        `INSERT INTO fallos (actividad_id,pieza_id,cantidad,fecha_registro) VALUES (?,?,?,?)`,
        [actResult.insertId, act.pieza_id, parseInt(act.fallos || 0), `${fecha} 12:00:00`]
      );
    }
  }
}

router.get('/', async (req, res) => {
  await renderPage(req, res, '', '');
});

async function renderPage(req, res, mensaje, error) {
  try {
    let editando = null, actividadesEditar = [];
    if (req.query.editar) {
      const [[row]] = await pool.execute(`SELECT * FROM sesiones WHERE id=?`, [req.query.editar]);
      if (row) {
        editando = row;
        [actividadesEditar] = await pool.execute(
          `SELECT a.*, f.pieza_id AS fallos_pieza_id, COALESCE(f.cantidad,0) AS fallos
           FROM actividades a LEFT JOIN fallos f ON f.actividad_id=a.id
           WHERE a.sesion_id=? ORDER BY a.orden`,
          [editando.id]
        );
      }
    }

    const [sesiones] = await pool.execute(`
      SELECT s.id, s.fecha, s.estado,
        COUNT(a.id) AS num_actividades,
        COALESCE(SUM(a.tiempo_segundos),0) AS tiempo_total
      FROM sesiones s LEFT JOIN actividades a ON s.id=a.sesion_id
      GROUP BY s.id ORDER BY s.fecha DESC, s.id DESC LIMIT 100
    `);

    const [piezas] = await pool.execute(`SELECT id,compositor,titulo FROM piezas WHERE activa=1 ORDER BY compositor,titulo`);

    res.render('gestionar_sesiones', { pageTitle: 'Gestión de Sesiones - Piano Tracker', currentPage: 'gestionar', editando, actividadesEditar, sesiones, piezas, mensaje, error, h });
  } catch (err) {
    console.error('[gestionar_sesiones]', err);
    res.status(500).send('Error: ' + err.message);
  }
}

module.exports = router;
