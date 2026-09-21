const express = require('express');
const router  = express.Router();
const pool    = require('../database');
const h       = require('../helpers');

router.get('/', async (req, res) => {
  try {
    await h.evaluarProgresionMensual(pool);

    // Al confirmar desde aquí, se marca la semana como mostrada y se pasa a
    // planificar/continuar la sesión. Visitar esta página sin confirmar (p. ej.
    // desde el botón "Resumen semanal" del dashboard) no marca nada: así se
    // puede reabrir voluntariamente sin afectar al aviso automático.
    if (req.query.continuar) {
      await h.marcarResumenSemanalMostrado(pool);
      return res.redirect('/sesion');
    }

    const resumen = await h.obtenerResumenSemanal(pool);
    res.render('resumen_semanal', {
      pageTitle: 'Resumen semanal - Piano Tracker', currentPage: 'resumen_semanal',
      resumen, h,
    });
  } catch (err) {
    console.error('[resumen_semanal]', err);
    res.status(500).send('Error: ' + err.message);
  }
});

module.exports = router;
