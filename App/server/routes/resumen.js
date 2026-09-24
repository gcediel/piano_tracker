const express = require('express');
const router  = express.Router();
const pool    = require('../database');
const h       = require('../helpers');

const MESES_MIN = h.MESES.map(m => m.toLowerCase());

// Textos que cambian según el periodo (género y número incluidos).
function textosResumen(periodo, inicio, fin) {
  const [anio, mes, dia] = inicio.split('-');
  const [, mesFin, diaFin] = fin.split('-');
  if (periodo === 'mes') {
    return {
      titulo: `Resumen de ${MESES_MIN[parseInt(mes) - 1]} de ${anio}`,
      anterior: 'el mes anterior',
      vs: 'mes anterior',
      pasado: 'el mes pasado',
      sinPractica: 'No hubo ninguna sesión registrada el mes pasado. ¡Este es un buen mes para retomarlo!',
      flojo: 'Este mes bajaste el ritmo respecto al anterior. No pasa nada, pero si puedes, intenta recuperarlo este mes.',
      logro: 'Logro del mes',
      este: 'este mes',
      informeUrl: `/informe-mensual?mes=${mes}&anio=${anio}`,
      informeTexto: '📊 Ver informe mensual',
    };
  }
  if (periodo === 'anio') {
    return {
      titulo: `Resumen del año ${anio}`,
      anterior: 'el año anterior',
      vs: 'año anterior',
      pasado: 'el año pasado',
      sinPractica: 'No hubo ninguna sesión registrada el año pasado. ¡Este es un buen año para retomarlo!',
      flojo: 'Este año bajaste el ritmo respecto al anterior. No pasa nada, pero si puedes, intenta recuperarlo este año.',
      logro: 'Logro del año',
      este: 'este año',
      informeUrl: `/informe-anual?anio=${anio}`,
      informeTexto: '📊 Ver informe anual',
    };
  }
  return {
    titulo: `Resumen de la semana del ${dia}/${mes} al ${diaFin}/${mesFin}`,
    anterior: 'la semana anterior',
    vs: 'semana anterior',
    pasado: 'la semana pasada',
    sinPractica: 'No hubo ninguna sesión registrada la semana pasada. ¡Esta es una buena semana para retomarlo!',
    flojo: 'Esta semana bajaste el ritmo respecto a la anterior. No pasa nada, pero si puedes, intenta recuperarlo esta semana.',
    logro: 'Logro de la semana',
    este: 'esta semana',
  };
}

router.get('/', async (req, res) => {
  try {
    // Periodo del resumen: semana (por defecto), mes o año natural anterior al actual.
    const periodo = ['semana', 'mes', 'anio'].includes(req.query.periodo) ? req.query.periodo : 'semana';

    await h.evaluarProgresionMensual(pool);

    // Al confirmar desde aquí, se marca el periodo como mostrado y se pasa a
    // planificar/continuar la sesión. Visitar esta página sin confirmar (p. ej.
    // desde el botón "Resumen semanal" del dashboard) no marca nada: así se
    // puede reabrir voluntariamente sin afectar al aviso automático.
    if (req.query.continuar) {
      await h.marcarResumenMostrado(pool, periodo);
      return res.redirect('/sesion');
    }

    const resumen = await h.obtenerResumenPeriodo(pool, periodo);
    const textos = textosResumen(periodo, resumen.inicio, resumen.fin);
    res.render('resumen', {
      pageTitle: `${textos.titulo} - Piano Tracker`, currentPage: 'resumen',
      resumen, textos, h,
    });
  } catch (err) {
    console.error('[resumen]', err);
    res.status(500).send('Error: ' + err.message);
  }
});

module.exports = router;
