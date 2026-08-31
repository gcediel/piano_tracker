const express = require('express');
const router  = express.Router();
const pool    = require('../database');
const h       = require('../helpers');

function toIntOrNull(v) {
  if (v === undefined || v === null || v === '') return null;
  const n = parseInt(v);
  return Number.isNaN(n) ? null : n;
}

router.post('/', async (req, res) => {
  const { accion } = req.body;
  let mensaje = '', error = '';

  try {
    if (accion === 'crear') {
      await pool.execute(`
        INSERT INTO piezas (compositor,titulo,libro,grado,tempo,tempo_objetivo,ponderacion,programa_midi,tono_nombre,tono_msb,tono_lsb,tono_pc)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
      `, [
        req.body.compositor, req.body.titulo, req.body.libro || null, toIntOrNull(req.body.grado),
        toIntOrNull(req.body.tempo), toIntOrNull(req.body.tempo_objetivo),
        parseFloat(req.body.ponderacion) || 1.0, toIntOrNull(req.body.programa_midi) || 0,
        req.body.tono_nombre || null, toIntOrNull(req.body.tono_msb), toIntOrNull(req.body.tono_lsb), toIntOrNull(req.body.tono_pc),
      ]);
      mensaje = 'Pieza añadida correctamente';
    }

    if (accion === 'editar') {
      await pool.execute(`
        UPDATE piezas SET compositor=?,titulo=?,libro=?,grado=?,tempo=?,tempo_objetivo=?,ponderacion=?,programa_midi=?,
          tono_nombre=?,tono_msb=?,tono_lsb=?,tono_pc=?
        WHERE id=?
      `, [
        req.body.compositor, req.body.titulo, req.body.libro || null, toIntOrNull(req.body.grado),
        toIntOrNull(req.body.tempo), toIntOrNull(req.body.tempo_objetivo),
        parseFloat(req.body.ponderacion) || 1.0, toIntOrNull(req.body.programa_midi) || 0,
        req.body.tono_nombre || null, toIntOrNull(req.body.tono_msb), toIntOrNull(req.body.tono_lsb), toIntOrNull(req.body.tono_pc),
        req.body.id,
      ]);
      mensaje = 'Pieza actualizada correctamente';
    }

    if (accion === 'marcar_mantenimiento') {
      await pool.execute(`UPDATE piezas SET estado='mantenimiento', meses_objetivo_consecutivos=0, aviso_graduacion_pendiente=0 WHERE id=?`, [req.body.id]);
      mensaje = 'Pieza marcada como mantenimiento';
    }

    if (accion === 'marcar_aprendizaje') {
      await pool.execute(`UPDATE piezas SET estado='aprendizaje' WHERE id=?`, [req.body.id]);
      mensaje = 'Pieza devuelta a aprendizaje';
    }

    if (accion === 'desactivar') {
      await pool.execute(`UPDATE piezas SET activa=0 WHERE id=?`, [req.body.id]);
      mensaje = 'Pieza desactivada correctamente';
    }

    if (accion === 'activar') {
      await pool.execute(`UPDATE piezas SET activa=1 WHERE id=?`, [req.body.id]);
      mensaje = 'Pieza activada correctamente';
    }

    if (accion === 'eliminar') {
      const [[{ n }]] = await pool.execute(`SELECT COUNT(*) AS n FROM fallos WHERE pieza_id=?`, [req.body.id]);
      if (n > 0) {
        error = 'No se puede eliminar esta pieza porque tiene registros de práctica asociados. Puedes desactivarla en su lugar.';
      } else {
        await pool.execute(`DELETE FROM piezas WHERE id=?`, [req.body.id]);
        mensaje = 'Pieza eliminada correctamente';
      }
    }
  } catch (e) {
    error = 'Error: ' + e.message;
  }

  await renderPage(req, res, mensaje, error);
});

router.get('/', async (req, res) => {
  await renderPage(req, res, '', '');
});

async function renderPage(req, res, mensaje, error) {
  try {
    let editando = null;
    if (req.query.editar) {
      const [[row]] = await pool.execute(`SELECT * FROM piezas WHERE id=?`, [req.query.editar]);
      editando = row || null;
    }

    const [piezas] = await pool.execute(`
      SELECT p.*,
        (SELECT ROUND(SUM(f.cantidad) / NULLIF(COUNT(DISTINCT DATE(f.fecha_registro)),0), 2)
         FROM fallos f WHERE f.pieza_id = p.id AND DATE(f.fecha_registro) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS media_fallos,
        (SELECT MAX(DATE(f2.fecha_registro)) FROM fallos f2 WHERE f2.pieza_id = p.id) AS ultima_practica
      FROM piezas p
      ORDER BY p.compositor, p.titulo
    `);

    const piezasActivas   = piezas.filter(p => p.activa);
    const piezasInactivas = piezas.filter(p => !p.activa);

    res.render('repertorio', { pageTitle: 'Repertorio - Piano Tracker', currentPage: 'repertorio', piezasActivas, piezasInactivas, editando, mensaje, error, h });
  } catch (err) {
    console.error('[repertorio]', err);
    res.status(500).send('Error: ' + err.message);
  }
}

module.exports = router;
