const express = require('express');
const path    = require('path');
const config  = require('./config');

const app = express();

// ─── Template engine ─────────────────────────────────────────────────────────
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, '..', 'views'));

// ─── Middleware ───────────────────────────────────────────────────────────────
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use('/assets', express.static(path.join(__dirname, '..', 'assets')));

// ─── Rutas ────────────────────────────────────────────────────────────────────
app.use('/',                   require('./routes/dashboard'));
app.use('/sesion',             require('./routes/sesion'));
app.use('/repertorio',         require('./routes/repertorio'));
app.use('/tecnica',            require('./routes/tecnica'));
app.use('/informes',           require('./routes/informes'));
app.use('/informe-mensual',    require('./routes/informe_mensual'));
app.use('/informe-anual',      require('./routes/informe_anual'));
app.use('/admin',              require('./routes/admin'));
app.use('/gestionar-sesiones', require('./routes/gestionar_sesiones'));
app.use('/roland',             require('./routes/roland'));
app.use('/ajax/sugerencias',   require('./routes/sugerencias'));

// ─── Error handler ────────────────────────────────────────────────────────────
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(500).send('<pre>' + err.stack + '</pre>');
});

app.listen(config.port, '127.0.0.1', () => {
  console.log(`Piano Tracker server running on http://127.0.0.1:${config.port}`);
});

module.exports = app;
