// INSTRUCCIONES: Copiar este archivo como config.js y configurar con tus credenciales reales.
module.exports = {
  // Base de datos compartida con la aplicación web (misma base MySQL/MariaDB).
  db: {
    host:               'localhost',        // Servidor de base de datos
    user:               'tu_usuario_mysql',
    password:           'tu_contraseña_mysql',
    database:           'piano_tracker',
    charset:            'utf8mb4',
    waitForConnections: true,
    connectionLimit:    10,
    timezone:           'local',
    dateStrings:        true,
  },
  port: 3712,
};
