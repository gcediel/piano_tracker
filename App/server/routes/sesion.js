const express = require('express');
const router  = express.Router();
const pool    = require('../database');
const h       = require('../helpers');

const ACTIVIDAD_COLS = `a.*, p.compositor, p.titulo, p.tempo, p.estado AS pieza_estado, p.programa_midi, p.tono_nombre, p.tono_msb, p.tono_lsb, p.tono_pc`;

// ─── AJAX ─────────────────────────────────────────────────────────────────────

router.post('/', async (req, res, next) => {
  if (req.headers['x-requested-with'] !== 'XMLHttpRequest') return next();

  const { accion } = req.body;
  try {
    switch (accion) {

      case 'iniciar': {
        await pool.execute(`UPDATE actividades SET estado='en_curso', fecha_inicio=NOW() WHERE id=?`, [req.body.actividad_id]);
        await pool.execute(`UPDATE sesiones SET estado='en_curso' WHERE id=(SELECT sesion_id FROM actividades WHERE id=?) AND estado='planificada'`, [req.body.actividad_id]);
        return res.json({ success: true });
      }

      case 'guardar': {
        await pool.execute(`UPDATE actividades SET tiempo_segundos=? WHERE id=?`, [req.body.tiempo, req.body.actividad_id]);
        return res.json({ success: true });
      }

      case 'guardar_notas': {
        await pool.execute(`UPDATE actividades SET notas=? WHERE id=?`, [req.body.notas, req.body.actividad_id]);
        return res.json({ success: true });
      }

      case 'guardar_tempo': {
        const piezaId = parseInt(req.body.pieza_id);
        const tempo   = parseInt(req.body.tempo);
        if (piezaId < 1 || tempo < 20 || tempo > 300) return res.json({ success: false, error: 'Datos inválidos' });
        await pool.execute(`UPDATE piezas SET tempo=? WHERE id=?`, [tempo, piezaId]);
        return res.json({ success: true });
      }

      case 'guardar_tono': {
        const piezaId = parseInt(req.body.pieza_id);
        if (piezaId < 1) return res.json({ success: false, error: 'Datos inválidos' });
        const msb = req.body.tono_msb !== '' && req.body.tono_msb != null ? parseInt(req.body.tono_msb) : null;
        const lsb = req.body.tono_lsb !== '' && req.body.tono_lsb != null ? parseInt(req.body.tono_lsb) : null;
        const pc  = req.body.tono_pc  !== '' && req.body.tono_pc  != null ? parseInt(req.body.tono_pc)  : null;
        const nombre = req.body.tono_nombre || null;
        await pool.execute(`UPDATE piezas SET tono_msb=?, tono_lsb=?, tono_pc=?, tono_nombre=? WHERE id=?`, [msb, lsb, pc, nombre, piezaId]);
        return res.json({ success: true });
      }

      case 'ejercicio_valorar': {
        const actividadId = parseInt(req.body.actividad_id);
        const ejercicioId = parseInt(req.body.ejercicio_id);
        const resultado   = ['bien', 'neutro', 'mal'].includes(req.body.resultado) ? req.body.resultado : 'mal';
        const bpmActual   = parseInt(req.body.bpm_actual);

        let nuevoBpm;
        if (resultado === 'bien') nuevoBpm = bpmActual + 1;
        else if (resultado === 'mal') nuevoBpm = Math.max(20, bpmActual - 1);
        else nuevoBpm = bpmActual;

        await pool.execute(`UPDATE ejercicios_tecnica SET bpm=? WHERE id=?`, [nuevoBpm, ejercicioId]);
        await pool.execute(
          `INSERT INTO sesion_tecnica_ejercicios (actividad_id,ejercicio_id,resultado,bpm_practicado) VALUES (?,?,?,?)`,
          [actividadId, ejercicioId, resultado, bpmActual]
        );

        return res.json({ success: true, nuevo_bpm: nuevoBpm });
      }

      case 'completar_pieza': {
        const { actividad_id, pieza_id, fallos, tiempo } = req.body;
        const tipoPasada = ['libre', 'metronomo'].includes(req.body.tipo_pasada) ? req.body.tipo_pasada : 'metronomo';
        await pool.execute(`UPDATE actividades SET tiempo_segundos=? WHERE id=?`, [tiempo, actividad_id]);
        const cambioNivel = await h.registrarFallo(pool, actividad_id, pieza_id, fallos, tipoPasada);
        const [piezasYaRows] = await pool.execute(`SELECT pieza_id FROM fallos WHERE actividad_id=?`, [actividad_id]);
        const piezasYa = piezasYaRows.map(r => r.pieza_id);
        const sig = await h.obtenerPiezaSugerida(pool, piezasYa);
        const tono = sig ? h.resolverTonoMidi(sig) : null;
        return res.json({
          success: true,
          cambio_nivel: cambioNivel,
          siguiente_pieza: sig ? { id: sig.id, compositor: sig.compositor, titulo: sig.titulo, tempo: sig.tempo, estado: sig.estado, tono } : null,
        });
      }

      case 'terminar_repertorio':
      case 'siguiente': {
        const { actividad_id, tiempo, pieza_id, fallos } = req.body;
        const tipoPasada = ['libre', 'metronomo'].includes(req.body.tipo_pasada) ? req.body.tipo_pasada : 'metronomo';
        await pool.execute(`UPDATE actividades SET tiempo_segundos=? WHERE id=?`, [tiempo, actividad_id]);
        let cambioNivel = null;
        if (pieza_id) {
          cambioNivel = await h.registrarFallo(pool, actividad_id, pieza_id, fallos || 0, tipoPasada);
        }
        await pool.execute(`UPDATE actividades SET estado='completada', fecha_fin=NOW() WHERE id=?`, [actividad_id]);
        const [[row]] = await pool.execute(`SELECT sesion_id FROM actividades WHERE id=?`, [actividad_id]);
        const sesionId = row?.sesion_id;
        const [[{ n }]] = await pool.execute(`SELECT COUNT(*) AS n FROM actividades WHERE sesion_id=? AND estado IN ('pendiente','en_curso')`, [sesionId]);
        return res.json({ success: true, cambio_nivel: cambioNivel, hay_siguiente: n > 0 });
      }

      case 'finalizar': {
        const { sesion_id, actividad_id, tiempo, pieza_id, fallos } = req.body;
        const tipoPasada = ['libre', 'metronomo'].includes(req.body.tipo_pasada) ? req.body.tipo_pasada : 'metronomo';
        await pool.execute(`UPDATE actividades SET tiempo_segundos=? WHERE id=?`, [tiempo, actividad_id]);
        let cambioNivel = null;
        if (pieza_id) {
          cambioNivel = await h.registrarFallo(pool, actividad_id, pieza_id, fallos || 0, tipoPasada);
        }
        await pool.execute(`UPDATE actividades SET estado='completada', fecha_fin=NOW() WHERE id=?`, [actividad_id]);
        await pool.execute(`UPDATE actividades SET estado='completada' WHERE sesion_id=? AND estado='pendiente'`, [sesion_id]);
        await pool.execute(`UPDATE sesiones SET estado='finalizada' WHERE id=?`, [sesion_id]);
        return res.json({ success: true, cambio_nivel: cambioNivel });
      }

      default:
        return res.json({ success: false, error: 'Acción no reconocida' });
    }
  } catch (e) {
    console.error('[sesion AJAX]', e);
    return res.json({ success: false, error: e.message });
  }
});

// ─── POST normal: crear sesión ────────────────────────────────────────────────

router.post('/', async (req, res) => {
  let mensaje = '', error = '';
  if (!req.body.crear_sesion) return res.redirect('/sesion');

  const today        = h.todayISO();
  const iniciarAhora = req.body.iniciar_ahora !== undefined;
  const conn = await pool.getConnection();
  let sesionId = null;

  try {
    await conn.beginTransaction();

    await conn.execute(`DELETE FROM sesiones WHERE estado='planificada' AND fecha=?`, [today]);
    const [sesResult] = await conn.execute(`INSERT INTO sesiones (fecha,estado) VALUES (?,?)`, [today, 'planificada']);
    sesionId = sesResult.insertId;

    const actividades = Object.values(req.body.actividades || {});
    const piezasSeleccionadas = [];
    let orden = 1;

    for (const act of actividades) {
      let piezaId = null;
      if (act.tipo === 'repertorio') {
        const sugerida = await h.obtenerPiezaSugerida(pool, piezasSeleccionadas);
        if (sugerida) { piezaId = sugerida.id; piezasSeleccionadas.push(piezaId); }
      }
      await conn.execute(
        `INSERT INTO actividades (sesion_id,orden,tipo,pieza_id,notas,estado) VALUES (?,?,?,?,?,'pendiente')`,
        [sesionId, orden++, act.tipo, piezaId, act.notas || null]
      );
    }

    await conn.commit();

    if (iniciarAhora) { conn.release(); return res.redirect(`/sesion?sesion=${sesionId}`); }
    mensaje = '✓ Sesión preparada correctamente. Puedes iniciarla cuando quieras desde el Dashboard.';
  } catch (e) {
    await conn.rollback();
    error = 'Error al crear sesión: ' + e.message;
  } finally {
    conn.release();
  }

  await renderPlanificacion(req, res, mensaje, error);
});

// ─── GET ──────────────────────────────────────────────────────────────────────

router.get('/', async (req, res) => {
  try {
    // Antes de planificar/iniciar una sesión nueva: si aún no se ha mostrado el
    // resumen semanal de la semana natural en curso, interponerlo primero (no
    // aplica al ver una sesión pasada ni al continuar una ya empezada).
    if (!req.query.ver && !req.query.continuar && !req.query.sesion && await h.debeMostrarResumenSemanal(pool)) {
      return res.redirect('/resumen-semanal');
    }

    if (req.query.ver) {
      const [[sesionVer]] = await pool.execute(`SELECT * FROM sesiones WHERE id=?`, [req.query.ver]);
      if (!sesionVer) return res.redirect('/sesion');
      const [actividadesVer] = await pool.execute(`SELECT ${ACTIVIDAD_COLS} FROM actividades a LEFT JOIN piezas p ON a.pieza_id=p.id WHERE a.sesion_id=? ORDER BY a.orden`, [sesionVer.id]);
      return res.render('sesion', baseSesionLocals({ sesionVer, actividadesVer }));
    }

    if (req.query.continuar) return res.redirect(`/sesion?sesion=${req.query.continuar}`);

    if (req.query.sesion) {
      const [[sesion]] = await pool.execute(`SELECT * FROM sesiones WHERE id=?`, [req.query.sesion]);
      if (!sesion) return res.redirect('/sesion');

      const [actividades] = await pool.execute(`SELECT ${ACTIVIDAD_COLS} FROM actividades a LEFT JOIN piezas p ON a.pieza_id=p.id WHERE a.sesion_id=? ORDER BY a.orden`, [sesion.id]);
      const actividadActual = actividades.find(a => a.estado === 'en_curso') || actividades.find(a => a.estado === 'pendiente') || null;
      const [piezasPracticadas] = await pool.execute(`
        SELECT p.compositor, p.titulo, f.cantidad AS fallos, DATE_FORMAT(f.fecha_registro,'%H:%i') AS hora
        FROM fallos f JOIN piezas p ON f.pieza_id=p.id JOIN actividades a ON f.actividad_id=a.id
        WHERE a.sesion_id=? ORDER BY f.fecha_registro DESC
      `, [sesion.id]);

      const hayActividadesCompletadas = actividades.some(a => a.estado === 'completada');
      const esUltimaActividad = actividadActual ? !actividades.some(a => a.id !== actividadActual.id && a.estado === 'pendiente') : false;

      let ejerciciosPendientes = [], totalEjercicios = 0, ejerciciosHechos = 0;
      if (actividadActual && actividadActual.tipo === 'tecnica_ejercicios') {
        const [sesionesRows] = await pool.execute(`
          SELECT a.sesion_id AS sid
          FROM actividades a JOIN sesiones s ON s.id = a.sesion_id
          WHERE a.tipo = 'tecnica_ejercicios'
          GROUP BY a.sesion_id
          ORDER BY MAX(s.fecha) DESC, a.sesion_id DESC
          LIMIT 30
        `);
        const sesionesRecientes = sesionesRows.map(r => r.sid);
        const sesionesIn = sesionesRecientes.length ? sesionesRecientes.join(',') : '0';

        const [pendientesRows] = await pool.execute(`
          SELECT et.*,
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
            AND et.id NOT IN (SELECT ejercicio_id FROM sesion_tecnica_ejercicios WHERE actividad_id = ?)
          ORDER BY veces_recientes ASC, ultima_fecha ASC, et.bpm ASC, et.nombre ASC
        `, [actividadActual.id]);
        ejerciciosPendientes = pendientesRows;

        const [[{ n: totalN }]] = await pool.execute(`SELECT COUNT(*) AS n FROM ejercicios_tecnica WHERE activo = 1`);
        totalEjercicios  = totalN;
        ejerciciosHechos = totalEjercicios - ejerciciosPendientes.length;
      }

      let defaultBpm = 92;
      if (actividadActual) {
        const [cfgRows] = await pool.execute(`SELECT clave,valor FROM configuracion WHERE clave IN ('metro_bpm_tecnica','metro_bpm_practica')`);
        const cfg = {};
        cfgRows.forEach(r => { cfg[r.clave] = parseInt(r.valor); });
        const bpmTecnica  = cfg.metro_bpm_tecnica  || 144;
        const bpmPractica = cfg.metro_bpm_practica || 92;
        if (['tecnica','tecnica_ejercicios','practica_tecnica'].includes(actividadActual.tipo)) defaultBpm = bpmTecnica;
        else if (actividadActual.tipo === 'repertorio') defaultBpm = actividadActual.tempo || bpmPractica;
        else defaultBpm = bpmPractica;

        if (actividadActual.tipo === 'tecnica_ejercicios' && ejerciciosPendientes.length) defaultBpm = ejerciciosPendientes[0].bpm;
      }

      let piezasYaCompletadas = 0;
      if (actividadActual) {
        const [[{ n }]] = await pool.execute(`SELECT COUNT(DISTINCT pieza_id) AS n FROM fallos WHERE actividad_id=?`, [actividadActual.id]);
        piezasYaCompletadas = n;
      }

      const tonoActual = actividadActual && actividadActual.pieza_id ? h.resolverTonoMidi(actividadActual) : null;

      return res.render('sesion', baseSesionLocals({
        sesion, actividades, actividadActual, piezasPracticadas, esUltimaActividad, hayActividadesCompletadas,
        defaultBpm, piezasYaCompletadas, ejerciciosPendientes, totalEjercicios, ejerciciosHechos, tonoActual,
      }));
    }

    await renderPlanificacion(req, res, '', '');
  } catch (err) {
    console.error('[sesion GET]', err);
    res.status(500).send('Error: ' + err.message);
  }
});

function baseSesionLocals(overrides) {
  return Object.assign({
    pageTitle: 'Sesión - Piano Tracker', currentPage: 'sesion',
    sesionVer: null, actividadesVer: [], sesion: null, actividades: [], actividadActual: null,
    piezasPracticadas: [], esUltimaActividad: false, hayActividadesCompletadas: false, defaultBpm: 92,
    piezasYaCompletadas: 0, ejerciciosPendientes: [], totalEjercicios: 0, ejerciciosHechos: 0, tonoActual: null,
    ultimasActividades: [], ultimaSesion: null, mensaje: '', error: '', h,
  }, overrides);
}

async function renderPlanificacion(req, res, mensaje, error) {
  const [[ultimaSesion]] = await pool.execute(`SELECT * FROM sesiones ORDER BY fecha DESC, id DESC LIMIT 1`);
  let ultimasActividades = [];
  if (ultimaSesion) {
    [ultimasActividades] = await pool.execute(`SELECT tipo,notas FROM actividades WHERE sesion_id=? ORDER BY orden`, [ultimaSesion.id]);
  }
  res.render('sesion', baseSesionLocals({ ultimaSesion: ultimaSesion || null, ultimasActividades, mensaje, error }));
}

module.exports = router;
