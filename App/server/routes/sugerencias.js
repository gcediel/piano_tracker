const express = require('express');
const router  = express.Router();
const pool    = require('../database');

router.post('/', async (req, res) => {
  const accion  = req.body.accion;
  const piezaId = parseInt(req.body.pieza_id) || 0;

  try {
    switch (accion) {
      case 'aplicar_tempo':
        await pool.execute(
          `UPDATE piezas SET tempo = sugerencia_tempo_pendiente, sugerencia_tempo_pendiente = NULL
           WHERE id = ? AND sugerencia_tempo_pendiente IS NOT NULL`,
          [piezaId]
        );
        return res.json({ success: true });

      case 'descartar_tempo':
        await pool.execute(`UPDATE piezas SET sugerencia_tempo_pendiente = NULL WHERE id = ?`, [piezaId]);
        return res.json({ success: true });

      case 'descartar_graduacion':
        // La graduación a mantenimiento ya se aplicó automáticamente; esto solo
        // descarta el aviso (ver evaluarProgresionMensual en helpers.js).
        await pool.execute(`UPDATE piezas SET aviso_graduacion_pendiente = 0 WHERE id = ?`, [piezaId]);
        return res.json({ success: true });

      default:
        return res.status(400).json({ success: false, error: 'Acción no válida' });
    }
  } catch (e) {
    console.error('[sugerencias]', e);
    return res.status(500).json({ success: false, error: e.message });
  }
});

module.exports = router;
