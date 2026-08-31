<?php
require_once __DIR__ . '/../config/auth.php';
requerirAuth();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Piano Tracker'; ?></title>
    <!-- Favicon en múltiples formatos para máxima compatibilidad -->
    <link rel="icon" href="assets/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16.png">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <nav>
            <div class="container">
                <h1>
                    <svg width="44" height="30" viewBox="0 0 44 30" style="display: inline-block; vertical-align: middle; margin-right: 10px;">
                        <!-- Fondo blanco con borde -->
                        
                        <!-- Teclas blancas (más anchas y visibles) -->
                        <rect x="6" y="0" width="4.5" height="24" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="10.5" y="0" width="4.5" height="24" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="15" y="0" width="4.5" height="24" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="19.5" y="0" width="4.5" height="24" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="24" y="0" width="4.5" height="24" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="28.5" y="0" width="4.5" height="24" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="33" y="0" width="4.5" height="24" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        
                        <!-- Teclas negras (más grandes y oscuras) -->
                        <rect x="9" y="0" width="3" height="16" fill="#1a1a1a" stroke="#000000" stroke-width="0.5"/>
                        <rect x="13.5" y="0" width="3" height="16" fill="#1a1a1a" stroke="#000000" stroke-width="0.5"/>
                        <rect x="22.5" y="0" width="3" height="16" fill="#1a1a1a" stroke="#000000" stroke-width="0.5"/>
                        <rect x="27" y="0" width="3" height="16" fill="#1a1a1a" stroke="#000000" stroke-width="0.5"/>
                        <rect x="31.5" y="0" width="3" height="16" fill="#1a1a1a" stroke="#000000" stroke-width="0.5"/>
                    </svg>
                    Piano Tracker
                </h1>
                <ul class="nav-menu">
                    <li><a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>Inicio</a></li>
                    <li><a href="repertorio.php" <?php echo basename($_SERVER['PHP_SELF']) == 'repertorio.php' ? 'class="active"' : ''; ?>>Repertorio</a></li>
                    <li><a href="tecnica.php" <?php echo basename($_SERVER['PHP_SELF']) == 'tecnica.php' ? 'class="active"' : ''; ?>>Técnica</a></li>
                    <li><a href="sesion.php" <?php echo basename($_SERVER['PHP_SELF']) == 'sesion.php' ? 'class="active"' : ''; ?>>Sesión</a></li>
                    <li><a href="informes.php" <?php echo basename($_SERVER['PHP_SELF']) == 'informes.php' ? 'class="active"' : ''; ?>>Informes</a></li>
                    <li><a href="admin.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'class="active"' : ''; ?>>Admin</a></li>
                    <li><a href="logout.php" style="color: #e74c3c;">Salir</a></li>
                </ul>
            </div>
        </nav>
    </header>
    <main class="container">

<!-- Modal de confirmación reutilizable -->
<div id="modal-confirmar" class="modal-overlay" role="dialog" aria-modal="true">
    <div class="modal-box">
        <p id="modal-confirmar-texto"></p>
        <div class="modal-actions">
            <button id="modal-confirmar-no" class="btn btn-primary">Cancelar</button>
            <button id="modal-confirmar-si" class="btn btn-danger">Confirmar</button>
        </div>
    </div>
</div>
<script>
(function () {
    var modal    = document.getElementById('modal-confirmar');
    var texto    = document.getElementById('modal-confirmar-texto');
    var btnSi    = document.getElementById('modal-confirmar-si');
    var btnNo    = document.getElementById('modal-confirmar-no');
    var pendiente = null;

    function cerrar() { modal.classList.remove('visible'); pendiente = null; }

    window.confirmar = function (mensaje, onSi) {
        texto.textContent = mensaje;
        pendiente = onSi;
        modal.classList.add('visible');
        btnNo.focus();
    };

    btnSi.addEventListener('click', function () { var fn = pendiente; cerrar(); if (fn) fn(); });
    btnNo.addEventListener('click', cerrar);
    modal.addEventListener('click', function (e) { if (e.target === modal) cerrar(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrar(); });

    document.addEventListener('submit', function (e) {
        var msg = e.target.dataset.confirm;
        if (!msg) return;
        e.preventDefault();
        var form = e.target;
        confirmar(msg, function () { form.submit(); });
    }, true);
}());
</script>

<!-- Modal de aviso: cambio de nivel de una pieza (subida o bajada) -->
<div id="modal-aviso" class="modal-overlay" role="dialog" aria-modal="true">
    <div id="modal-aviso-box" class="modal-box modal-aviso-box">
        <div id="modal-aviso-icono" class="modal-aviso-icono"></div>
        <p id="modal-aviso-titulo" class="modal-aviso-titulo"></p>
        <p id="modal-aviso-pieza" class="modal-aviso-pieza"></p>
        <p id="modal-aviso-niveles" class="modal-aviso-niveles"></p>
        <div class="modal-actions">
            <button id="modal-aviso-cerrar" class="btn btn-primary">Aceptar</button>
        </div>
    </div>
</div>
<script>
(function () {
    var modal   = document.getElementById('modal-aviso');
    var box     = document.getElementById('modal-aviso-box');
    var icono   = document.getElementById('modal-aviso-icono');
    var titulo  = document.getElementById('modal-aviso-titulo');
    var pieza   = document.getElementById('modal-aviso-pieza');
    var niveles = document.getElementById('modal-aviso-niveles');
    var btnOk   = document.getElementById('modal-aviso-cerrar');
    var pendienteCerrar = null;

    function cerrar() {
        modal.classList.remove('visible');
        var fn = pendienteCerrar;
        pendienteCerrar = null;
        if (fn) fn();
    }

    // cambio: { pieza: {compositor, titulo}, direccion: 'sube'|'baja', anterior: {texto, color}, nuevo: {texto, color} }
    // onCerrar: callback opcional que se ejecuta al cerrar el aviso (p.ej. para continuar con una recarga)
    window.mostrarAvisoNivel = function (cambio, onCerrar) {
        var subida = cambio.direccion === 'sube';
        icono.textContent = subida ? '🎉' : '📉';
        titulo.textContent = subida ? '¡Subida de nivel!' : 'Bajada de nivel';
        box.classList.toggle('aviso-subida', subida);
        box.classList.toggle('aviso-bajada', !subida);
        pieza.textContent = cambio.pieza.compositor + ' - ' + cambio.pieza.titulo;

        niveles.innerHTML = '';
        var spanAntes = document.createElement('span');
        spanAntes.textContent = cambio.anterior.texto;
        spanAntes.style.color = cambio.anterior.color;
        spanAntes.style.fontWeight = 'bold';
        var spanDespues = document.createElement('span');
        spanDespues.textContent = cambio.nuevo.texto;
        spanDespues.style.color = cambio.nuevo.color;
        spanDespues.style.fontWeight = 'bold';
        niveles.appendChild(spanAntes);
        niveles.appendChild(document.createTextNode(' → '));
        niveles.appendChild(spanDespues);

        pendienteCerrar = onCerrar || null;
        modal.classList.add('visible');
        btnOk.focus();
    };

    btnOk.addEventListener('click', cerrar);
    modal.addEventListener('click', function (e) { if (e.target === modal) cerrar(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('visible')) cerrar(); });
}());
</script>
