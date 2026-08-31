const mysql  = require('mysql2/promise');
const config = require('./config');

const pool = mysql.createPool(config.db);

pool.getConnection()
  .then(conn => { conn.release(); console.log('[DB] Conexión a MariaDB establecida (' + config.db.host + '/' + config.db.database + ')'); })
  .catch(err  => { console.error('[DB] Error de conexión:', err.message); });

module.exports = pool;
