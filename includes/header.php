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
    <link rel="stylesheet" href="/piano/assets/css/style.css">
</head>
<body>
    <header>
        <nav>
            <div class="container">
                <h1>🎹 Piano Tracker</h1>
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
