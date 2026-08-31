<?php
// Configuración de la base de datos
// INSTRUCCIONES: Copiar este archivo como database.php y configurar con tus credenciales
define('DB_HOST', 'localhost');           // Servidor de base de datos
define('DB_NAME', 'piano_tracker');        // Nombre de la base de datos
define('DB_USER', 'tu_usuario_mysql');     // Usuario de MySQL
define('DB_PASS', 'tu_contraseña_mysql');  // Contraseña de MySQL
define('DB_CHARSET', 'utf8mb4');

// Crear conexión PDO
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    return $pdo;
}

require_once __DIR__ . '/../includes/funciones.php';
