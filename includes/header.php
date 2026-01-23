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
                        <rect width="44" height="30" fill="#ffffff" rx="3"/>
                        <rect width="44" height="30" fill="none" stroke="#34495e" stroke-width="1" rx="3"/>
                        
                        <!-- Teclas blancas (más anchas y visibles) -->
                        <rect x="6" y="6" width="4.5" height="18" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="10.5" y="6" width="4.5" height="18" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="15" y="6" width="4.5" height="18" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="19.5" y="6" width="4.5" height="18" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="24" y="6" width="4.5" height="18" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="28.5" y="6" width="4.5" height="18" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        <rect x="33" y="6" width="4.5" height="18" fill="#ecf0f1" stroke="#2c3e50" stroke-width="0.8"/>
                        
                        <!-- Teclas negras (más grandes y oscuras) -->
                        <rect x="9" y="6" width="3" height="11" fill="#1a1a1a" stroke="#000000" stroke-width="0.5"/>
                        <rect x="13.5" y="6" width="3" height="11" fill="#1a1a1a" stroke="#000000" stroke-width="0.5"/>
                        <rect x="22.5" y="6" width="3" height="11" fill="#1a1a1a" stroke="#000000" stroke-width="0.5"/>
                        <rect x="27" y="6" width="3" height="11" fill="#1a1a1a" stroke="#000000" stroke-width="0.5"/>
                        <rect x="31.5" y="6" width="3" height="11" fill="#1a1a1a" stroke="#000000" stroke-width="0.5"/>
                    </svg>
                    Piano Tracker
                </h1>
                <ul class="nav-menu">
                    <li><a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>Inicio</a></li>
                    <li><a href="repertorio.php" <?php echo basename($_SERVER['PHP_SELF']) == 'repertorio.php' ? 'class="active"' : ''; ?>>Repertorio</a></li>
                    <li><a href="sesion.php" <?php echo basename($_SERVER['PHP_SELF']) == 'sesion.php' ? 'class="active"' : ''; ?>>Sesión</a></li>
                    <li><a href="informes.php" <?php echo basename($_SERVER['PHP_SELF']) == 'informes.php' ? 'class="active"' : ''; ?>>Informes</a></li>
                    <li><a href="admin.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'class="active"' : ''; ?>>Admin</a></li>
                    <li><a href="logout.php" style="color: #e74c3c;">Salir</a></li>
                </ul>
            </div>
        </nav>
    </header>
    <main class="container">
