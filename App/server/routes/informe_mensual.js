const express = require('express');
const router  = express.Router();
const pool    = require('../database');
const h       = require('../helpers');

const TIPOS = ['calentamiento', 'tecnica', 'practica', 'repertorio', 'improvisacion', 'composicion'];
const TIPOS_NOMBRES = {
  calentamiento: 'Calentamiento', tecnica: 'Técnica', practica: 'Práctica',
  repertorio: 'Repertorio', improvisacion: 'Improvisación', composicion: 'Composición',
};

const COLORES_ACTIVIDADES = {
  calentamiento: '#FF6B6B', tecnica: '#4ECDC4', practica: '#45B7D1',
  repertorio: '#FFA07A', improvisacion: '#98D8C8', composicion: '#C7CEEA',
};

// Paleta adaptada para daltonismo, específica de este informe (distinta de h.getColorFallos)
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
function claseCeldaFallo(fallos) {
  if (fallos === null || fallos === undefined) return null;
  if (fallos === 0) return 'celda-fallo-0';
  if (fallos === 1) return 'celda-fallo-1';
  if (fallos === 2) return 'celda-fallo-2';
  if (fallos === 3) return 'celda-fallo-3';
  if (fallos === 4) return 'celda-fallo-4';
  return 'celda-fallo-5plus';
}

router.get('/', async (req, res) => {
  try {
    const mes  = req.query.mes  || String(new Date().getMonth() + 1).padStart(2, '0');
    const anio = req.query.anio || String(new Date().getFullYear());
    const { start: fechaInicio, end: fechaFin, daysInMonth: diasDelMes } = h.getMonthBounds(parseInt(anio), parseInt(mes));
    const todosDias = Array.from({ length: diasDelMes }, (_, i) => i + 1);

    // Matriz [tipo][dia] = tiempo
    const [datosActividades] = await pool.execute(`
      SELECT DAY(s.fecha) AS dia,
        CASE WHEN a.tipo IN ('tecnica','tecnica_ejercicios','practica_tecnica') THEN 'tecnica' ELSE a.tipo END AS tipo,
        SUM(a.tiempo_segundos) AS tiempo_total
      FROM sesiones s LEFT JOIN actividades a ON s.id = a.sesion_id
      WHERE s.fecha BETWEEN ? AND ? AND s.estado = 'finalizada'
      GROUP BY DAY(s.fecha), CASE WHEN a.tipo IN ('tecnica','tecnica_ejercicios','practica_tecnica') THEN 'tecnica' ELSE a.tipo END
    `, [fechaInicio, fechaFin]);

    const matrizActividades = {};
    TIPOS.forEach(tipo => {
      matrizActividades[tipo] = {};
      todosDias.forEach(dia => { matrizActividades[tipo][dia] = 0; });
    });
    datosActividades.forEach(d => {
      if (d.tipo && d.dia && matrizActividades[d.tipo]) matrizActividades[d.tipo][d.dia] = parseInt(d.tiempo_total) || 0;
    });

    const totalesPorTipo = {};
    TIPOS.forEach(tipo => { totalesPorTipo[tipo] = Object.values(matrizActividades[tipo]).reduce((a, b) => a + b, 0); });

    const totalesPorDia = {};
    todosDias.forEach(dia => {
      let total = 0;
      TIPOS.forEach(tipo => { total += matrizActividades[tipo][dia] || 0; });
      totalesPorDia[dia] = total;
    });

    const tiempoTotalMes = Object.values(totalesPorTipo).reduce((a, b) => a + b, 0);

    const porcentajes = {};
    if (tiempoTotalMes > 0) TIPOS.forEach(tipo => { porcentajes[tipo] = (totalesPorTipo[tipo] / tiempoTotalMes) * 100; });

    const actividades = TIPOS.map(tipo => ({ tipo, nombre: TIPOS_NOMBRES[tipo], tiempo_total: totalesPorTipo[tipo] }));

    const diasPracticadosPorTipo = {};
    TIPOS.forEach(tipo => {
      diasPracticadosPorTipo[tipo] = Object.values(matrizActividades[tipo]).filter(t => t > 0).length;
    });

    const diasPracticadosTotales = todosDias.filter(dia => (totalesPorDia[dia] || 0) > 0).length;

    // Piezas de repertorio practicadas, con fallos por día. Solo se cuentan los
    // fallos "con metrónomo" (la interpretación real): es el mismo criterio que
    // usa la app para sugerir subidas de tempo y el paso a mantenimiento.
    const [datosPiezas] = await pool.execute(`
      SELECT p.id, p.compositor, p.titulo, p.libro, p.grado, p.instrumento, p.tempo,
        p.tempo_objetivo, p.estado, p.ponderacion,
        DAY(s.fecha) AS dia, f.cantidad AS fallos
      FROM piezas p
      JOIN fallos f ON p.id = f.pieza_id
      JOIN actividades a ON f.actividad_id = a.id
      JOIN sesiones s ON a.sesion_id = s.id
      WHERE s.fecha BETWEEN ? AND ? AND a.tipo = 'repertorio' AND f.tipo_pasada = 'metronomo'
      ORDER BY p.libro, p.grado, p.compositor, p.titulo
    `, [fechaInicio, fechaFin]);

    const piezasMap = {};
    datosPiezas.forEach(d => {
      if (!piezasMap[d.id]) {
        const fallosPorDia = {};
        todosDias.forEach(dd => { fallosPorDia[dd] = null; });
        piezasMap[d.id] = {
          compositor: d.compositor, titulo: d.titulo, libro: d.libro, grado: d.grado,
          instrumento: d.instrumento, tempo: d.tempo, tempo_objetivo: d.tempo_objetivo, estado: d.estado,
          ponderacion: d.ponderacion, fallos_por_dia: fallosPorDia,
        };
      }
      piezasMap[d.id].fallos_por_dia[d.dia] = parseInt(d.fallos);
    });

    const piezas = Object.values(piezasMap).map(pieza => {
      let diasPracticados = 0, totalFallos = 0;
      Object.values(pieza.fallos_por_dia).forEach(f => { if (f !== null) { diasPracticados++; totalFallos += f; } });
      return { ...pieza, dias_practicados: diasPracticados, media_fallos: diasPracticados > 0 ? totalFallos / diasPracticados : 0 };
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
      const m = p.media_fallos;
      if (m < 0.5) categorias.excelente.count++;
      else if (m < 1.5) categorias.muy_bien.count++;
      else if (m < 2.5) categorias.bien.count++;
      else if (m < 3.5) categorias.aceptable.count++;
      else if (m <= 5) categorias.mejorable.count++;
      else categorias.atencion.count++;
    });

    res.render('informe_mensual', {
      pageTitle: 'Informe Mensual - Piano Tracker', currentPage: 'informes',
      mes, anio, diasDelMes, todosDias,
      matrizActividades, totalesPorTipo, totalesPorDia, tiempoTotalMes, porcentajes,
      actividades, diasPracticadosPorTipo, diasPracticadosTotales,
      piezas, categorias, totalPiezas: piezas.length,
      TIPOS, TIPOS_NOMBRES, COLORES_ACTIVIDADES,
      getColorFallos, getColorTextoFallos, claseCeldaFallo,
      h,
    });
  } catch (err) {
    console.error('[informe_mensual]', err);
    res.status(500).send('Error: ' + err.message);
  }
});

module.exports = router;
