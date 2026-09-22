const express = require('express');
const router  = express.Router();
const pool    = require('../database');
const h       = require('../helpers');

const TIPOS = ['calentamiento', 'tecnica', 'practica', 'repertorio', 'improvisacion', 'composicion'];
const MESES_CORTOS = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
const COLORES_ACTIVIDADES = {
  calentamiento: '#FF6B6B', tecnica: '#4ECDC4', practica: '#45B7D1',
  repertorio: '#FFA07A', improvisacion: '#98D8C8', composicion: '#C7CEEA',
};

function getColorFallos(media) {
  if (media === null || media === undefined || media === 0) return '#2E5F8A';
  if (media < 0.5) return '#2E5F8A';
  if (media < 1.5) return '#4A7BA7';
  if (media < 2.5) return '#A3C1DA';
  if (media < 3.5) return '#D4E89E';
  if (media <= 5) return '#9B9B9B';
  return '#E57373';
}
function getColorTextoFallos(media) {
  if (media === null || media === undefined || media === 0) return 'white';
  if (media < 0.5) return 'white';
  if (media < 1.5) return 'white';
  if (media < 2.5) return 'black';
  if (media < 3.5) return 'black';
  if (media <= 5) return 'white';
  return 'white';
}
function claseCeldaMedia(media) {
  if (media === null || media === undefined) return 'celda-vacia';
  if (media < 0.5) return 'celda-fallo-excelente';
  if (media < 1.5) return 'celda-fallo-muy-bien';
  if (media < 2.5) return 'celda-fallo-bien';
  if (media < 3.5) return 'celda-fallo-aceptable';
  if (media <= 5) return 'celda-fallo-mejorable';
  return 'celda-fallo-atencion';
}

router.get('/', async (req, res) => {
  try {
    let anio = req.query.anio ? parseInt(req.query.anio) : new Date().getFullYear();
    if (anio < 2000 || anio > 2100) anio = new Date().getFullYear();
    const fechaInicio = `${anio}-01-01`, fechaFin = `${anio}-12-31`;
    const todosMeses = Array.from({ length: 12 }, (_, i) => i + 1);

    const [datosActividades] = await pool.execute(`
      SELECT CASE WHEN a.tipo IN ('tecnica','tecnica_ejercicios','practica_tecnica') THEN 'tecnica' ELSE a.tipo END AS tipo,
        MONTH(s.fecha) AS mes,
        SUM(a.tiempo_segundos) AS tiempo_total,
        COUNT(DISTINCT DATE(s.fecha)) AS dias_practicados
      FROM actividades a JOIN sesiones s ON a.sesion_id = s.id
      WHERE s.fecha BETWEEN ? AND ?
      GROUP BY CASE WHEN a.tipo IN ('tecnica','tecnica_ejercicios','practica_tecnica') THEN 'tecnica' ELSE a.tipo END, MONTH(s.fecha)
    `, [fechaInicio, fechaFin]);

    const actividadesMap = {};
    TIPOS.forEach(tipo => {
      actividadesMap[tipo] = {
        tipo, nombre: h.getNombreActividad(tipo),
        tiempos_por_mes: Object.fromEntries(todosMeses.map(m => [m, 0])),
        dias_por_mes: Object.fromEntries(todosMeses.map(m => [m, 0])),
        total_tiempo: 0, total_dias: 0,
      };
    });
    datosActividades.forEach(d => {
      const act = actividadesMap[d.tipo];
      if (!act) return;
      act.tiempos_por_mes[d.mes] = parseInt(d.tiempo_total) || 0;
      act.dias_por_mes[d.mes] = parseInt(d.dias_practicados) || 0;
      act.total_tiempo += parseInt(d.tiempo_total) || 0;
      act.total_dias += parseInt(d.dias_practicados) || 0;
    });
    const actividades = TIPOS.map(t => actividadesMap[t]);
    const tiempoTotalAnio = actividades.reduce((s, a) => s + a.total_tiempo, 0);

    const [[{ total_dias: diasTotalAnio }]] = await pool.execute(
      `SELECT COUNT(DISTINCT DATE(s.fecha)) AS total_dias FROM sesiones s WHERE s.fecha BETWEEN ? AND ?`, [fechaInicio, fechaFin]
    );

    // Solo se cuentan los fallos "con metrónomo" (la interpretación real): es el
    // mismo criterio que usa la app para sugerir subidas de tempo y mantenimiento.
    const [datosPiezas] = await pool.execute(`
      SELECT p.id, p.compositor, p.titulo, p.libro, p.grado, p.instrumento, p.tempo,
        p.tempo_objetivo, p.estado, p.ponderacion,
        MONTH(s.fecha) AS mes, SUM(f.cantidad) AS total_fallos, COUNT(DISTINCT DATE(f.fecha_registro)) AS dias_practicados
      FROM piezas p JOIN fallos f ON p.id = f.pieza_id
      JOIN actividades a ON f.actividad_id = a.id JOIN sesiones s ON a.sesion_id = s.id
      WHERE s.fecha BETWEEN ? AND ? AND a.tipo = 'repertorio' AND f.tipo_pasada = 'metronomo'
      GROUP BY p.id, MONTH(s.fecha)
      ORDER BY p.libro, p.grado, p.compositor, p.titulo
    `, [fechaInicio, fechaFin]);

    const piezasMap = {};
    datosPiezas.forEach(d => {
      if (!piezasMap[d.id]) {
        piezasMap[d.id] = {
          compositor: d.compositor, titulo: d.titulo, libro: d.libro, grado: d.grado,
          instrumento: d.instrumento, tempo: d.tempo, tempo_objetivo: d.tempo_objetivo, estado: d.estado,
          ponderacion: d.ponderacion,
          medias_por_mes: Object.fromEntries(todosMeses.map(m => [m, null])),
          dias_por_mes: Object.fromEntries(todosMeses.map(m => [m, 0])),
          dias_practicados_anio: 0, total_fallos_anio: 0,
        };
      }
      const totalFallos = parseInt(d.total_fallos) || 0;
      const diasPracticados = parseInt(d.dias_practicados) || 0;
      piezasMap[d.id].medias_por_mes[d.mes] = diasPracticados > 0 ? totalFallos / diasPracticados : 0;
      piezasMap[d.id].dias_por_mes[d.mes] = diasPracticados;
      piezasMap[d.id].dias_practicados_anio += diasPracticados;
      piezasMap[d.id].total_fallos_anio += totalFallos;
    });
    const piezas = Object.values(piezasMap).map(p => ({
      ...p, media_fallos_anio: p.dias_practicados_anio > 0 ? p.total_fallos_anio / p.dias_practicados_anio : 0,
    }));

    // Puntuación total del repertorio por mes: mismo criterio que en el resumen
    // semanal (ver puntuacionTotalEnFecha en helpers.js), pero con ventana de
    // calendario mensual en vez de rodante de 30 días. Suma, por mes, (10 - media
    // de fallos) de las piezas con al menos 5 días practicados ese mes.
    const puntuacionPorMes = Object.fromEntries(todosMeses.map(m => [m, 0]));
    const piezasPuntuadasPorMes = Object.fromEntries(todosMeses.map(m => [m, 0]));
    piezas.forEach(p => {
      todosMeses.forEach(m => {
        const dias = p.dias_por_mes[m];
        const media = p.medias_por_mes[m];
        if (dias >= 5 && media !== null) {
          puntuacionPorMes[m] += 10 - media;
          piezasPuntuadasPorMes[m]++;
        }
      });
    });

    const categorias = {
      excelente: { count: 0, color: '#2E5F8A', label: 'Excelente (< 0.5)' },
      muy_bien:  { count: 0, color: '#4A7BA7', label: 'Muy bien (0.5-1.5)' },
      bien:      { count: 0, color: '#A3C1DA', label: 'Bien (1.5-2.5)' },
      aceptable: { count: 0, color: '#D4E89E', label: 'Aceptable (2.5-3.5)' },
      mejorable: { count: 0, color: '#9B9B9B', label: 'Mejorable (3.5-5)' },
      atencion:  { count: 0, color: '#E57373', label: 'Atención (> 5)' },
    };
    piezas.forEach(p => {
      const m = p.media_fallos_anio;
      if (m < 0.5) categorias.excelente.count++;
      else if (m < 1.5) categorias.muy_bien.count++;
      else if (m < 2.5) categorias.bien.count++;
      else if (m < 3.5) categorias.aceptable.count++;
      else if (m <= 5) categorias.mejorable.count++;
      else categorias.atencion.count++;
    });

    res.render('informe_anual', {
      pageTitle: 'Informe Anual - Piano Tracker', currentPage: 'informes',
      anio, todosMeses, MESES_CORTOS, actividades, tiempoTotalAnio, diasTotalAnio,
      piezas, categorias, totalPiezas: piezas.length,
      puntuacionPorMes, piezasPuntuadasPorMes,
      COLORES_ACTIVIDADES, getColorFallos, getColorTextoFallos, claseCeldaMedia,
      h,
    });
  } catch (err) {
    console.error('[informe_anual]', err);
    res.status(500).send('Error: ' + err.message);
  }
});

module.exports = router;
