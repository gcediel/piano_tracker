const express = require('express');
const router  = express.Router();
const multer  = require('multer');
const mysql   = require('mysql2/promise');
const config  = require('../config');
const pool    = require('../database');
const h       = require('../helpers');

const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 50 * 1024 * 1024 } });

const TABLAS_BACKUP = ['configuracion', 'piezas', 'ejercicios_tecnica', 'sesiones', 'actividades', 'fallos', 'sesion_tecnica_ejercicios'];

// ─── Exportar CSV ─────────────────────────────────────────────────────────────
router.get('/export-csv', async (req, res) => {
  try {
    const [rows] = await pool.execute(`
      SELECT s.fecha AS fecha, a.tipo,
             ROUND(a.tiempo_segundos/60) AS minutos,
             a.notas, p.titulo, p.compositor, COALESCE(f.cantidad,0) AS fallos
      FROM sesiones s JOIN actividades a ON s.id=a.sesion_id
      LEFT JOIN piezas p ON a.pieza_id=p.id
      LEFT JOIN fallos f ON f.actividad_id=a.id
      ORDER BY s.fecha DESC, a.orden
    `);
    const today = h.todayISO();
    res.setHeader('Content-Type', 'text/csv; charset=utf-8');
    res.setHeader('Content-Disposition', `attachment; filename="piano_tracker_${today}.csv"`);
    res.write('﻿'); // BOM
    res.write('Fecha,Tipo Actividad,Tiempo (min),Notas,Pieza,Compositor,Fallos\n');
    rows.forEach(r => {
      const row = [r.fecha, r.tipo, r.minutos, r.notas || '', r.titulo || '', r.compositor || '', r.fallos]
        .map(v => `"${String(v).replace(/"/g, '""')}"`).join(',');
      res.write(row + '\n');
    });
    res.end();
  } catch (err) {
    res.status(500).send('Error al exportar CSV: ' + err.message);
  }
});

// ─── Exportar copia de seguridad SQL ──────────────────────────────────────────
router.get('/export-sql', async (req, res) => {
  try {
    const today = h.todayISO();
    res.setHeader('Content-Type', 'application/sql; charset=utf-8');
    res.setHeader('Content-Disposition', `attachment; filename="piano_tracker_backup_${today}.sql"`);
    res.write(`-- Piano Tracker Desktop — Backup MySQL/MariaDB\n-- Fecha: ${new Date().toISOString()}\n\nSET FOREIGN_KEY_CHECKS = 0;\n\n`);

    for (const tabla of TABLAS_BACKUP) {
      const [filas] = await pool.execute(`SELECT * FROM \`${tabla}\``);
      res.write(`-- Tabla: ${tabla}\nDELETE FROM \`${tabla}\`;\n`);
      for (const fila of filas) {
        const cols = Object.keys(fila).map(c => `\`${c}\``).join(',');
        const vals = Object.values(fila).map(v => {
          if (v === null) return 'NULL';
          if (v instanceof Date) return `'${v.toISOString().replace('T', ' ').substring(0, 19)}'`;
          return `'${String(v).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
        }).join(',');
        res.write(`INSERT INTO \`${tabla}\` (${cols}) VALUES (${vals});\n`);
      }
      res.write('\n');
    }
    res.write('SET FOREIGN_KEY_CHECKS = 1;\n-- Fin del backup\n');
    res.end();
  } catch (err) {
    res.status(500).send('Error al exportar SQL: ' + err.message);
  }
});

// ─── Importar SQL ─────────────────────────────────────────────────────────────
router.post('/import-sql', upload.single('sql_file'), async (req, res) => {
  if (!req.file) return res.redirect('/admin?error=' + encodeURIComponent('No se seleccionó ningún archivo'));
  try {
    const sql  = req.file.buffer.toString('utf8');
    const conn = await mysql.createConnection({ ...config.db, multipleStatements: true });
    await conn.query(sql);
    await conn.end();
    res.redirect('/admin?mensaje=' + encodeURIComponent('✓ Backup importado correctamente.'));
  } catch (err) {
    console.error('[admin import-sql]', err);
    res.redirect('/admin?error=' + encodeURIComponent(err.message));
  }
});

// ─── GET principal ────────────────────────────────────────────────────────────
router.get('/', async (req, res) => {
  const mensaje = req.query.mensaje ? decodeURIComponent(req.query.mensaje) : '';
  const error   = req.query.error   ? decodeURIComponent(req.query.error)   : '';
  await renderAdmin(res, mensaje, error);
});

// ─── POST principal ───────────────────────────────────────────────────────────
router.post('/', async (req, res) => {
  let mensaje = '', error = '';
  const { accion } = req.body;

  try {
    if (accion === 'guardar_bpm') {
      const bpmTecnica  = parseInt(req.body.metro_bpm_tecnica  || 144);
      const bpmPractica = parseInt(req.body.metro_bpm_practica || 92);
      if (bpmTecnica < 20 || bpmTecnica > 300 || bpmPractica < 20 || bpmPractica > 300) {
        error = 'Los valores de BPM deben estar entre 20 y 300.';
      } else {
        await upsertConfig('metro_bpm_tecnica',  String(bpmTecnica),  'BPM por defecto del metrónomo para Técnica');
        await upsertConfig('metro_bpm_practica', String(bpmPractica), 'BPM por defecto del metrónomo para Práctica');
        mensaje = '✓ Configuración del metrónomo guardada correctamente.';
      }
    }

    if (accion === 'resetear_bpm_tecnica') {
      const bpmReset = parseInt(req.body.bpm_reset || 120);
      if (bpmReset < 20 || bpmReset > 300) {
        error = 'El BPM debe estar entre 20 y 300.';
      } else {
        const [info] = await pool.execute(`UPDATE ejercicios_tecnica SET bpm=?`, [bpmReset]);
        mensaje = `✓ Se han reseteado ${info.affectedRows} ejercicios de técnica a ${bpmReset} BPM.`;
      }
    }

    if (accion === 'eliminar_todo') {
      const conn = await pool.getConnection();
      try {
        await conn.beginTransaction();
        await conn.execute('SET FOREIGN_KEY_CHECKS = 0');
        await conn.execute('DELETE FROM sesion_tecnica_ejercicios');
        await conn.execute('DELETE FROM fallos');
        await conn.execute('DELETE FROM actividades');
        await conn.execute('DELETE FROM sesiones');
        await conn.execute('ALTER TABLE sesion_tecnica_ejercicios AUTO_INCREMENT = 1');
        await conn.execute('ALTER TABLE fallos      AUTO_INCREMENT = 1');
        await conn.execute('ALTER TABLE actividades AUTO_INCREMENT = 1');
        await conn.execute('ALTER TABLE sesiones    AUTO_INCREMENT = 1');
        await conn.execute('SET FOREIGN_KEY_CHECKS = 1');
        await conn.commit();
        mensaje = '✓ Todos los datos de práctica han sido eliminados.';
      } catch (e) {
        await conn.rollback();
        await conn.execute('SET FOREIGN_KEY_CHECKS = 1');
        throw e;
      } finally {
        conn.release();
      }
    }
  } catch (e) {
    error = 'Error: ' + e.message;
  }

  await renderAdmin(res, mensaje, error);
});

async function upsertConfig(clave, valor, descripcion) {
  await pool.execute(
    `INSERT INTO configuracion (clave,valor,descripcion) VALUES (?,?,?)
     ON DUPLICATE KEY UPDATE valor=VALUES(valor)`,
    [clave, valor, descripcion]
  );
}

async function renderAdmin(res, mensaje, error) {
  try {
    const [cfgRows] = await pool.execute(`SELECT clave,valor FROM configuracion`);
    const cfg = {};
    cfgRows.forEach(r => { cfg[r.clave] = r.valor; });
    res.render('admin', {
      pageTitle: 'Administración - Piano Tracker', currentPage: 'admin',
      bpmTecnica:  parseInt(cfg.metro_bpm_tecnica  || 144),
      bpmPractica: parseInt(cfg.metro_bpm_practica || 92),
      mensaje, error, h,
    });
  } catch (err) {
    console.error('[admin render]', err);
    res.status(500).send('Error: ' + err.message);
  }
}

module.exports = router;
