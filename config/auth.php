<?php
session_start();

require_once __DIR__ . '/database.php';

// Verificar si el usuario está autenticado
function estaAutenticado() {
    return isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true;
}

// Verificar contraseña
function verificarPassword($password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'password_hash'");
    $stmt->execute();
    $resultado = $stmt->fetch();
    
    if (!$resultado) {
        return false;
    }
    
    return password_verify($password, $resultado['valor']);
}

// Cambiar contraseña
function cambiarPassword($passwordNueva) {
    $db = getDB();
    $hash = password_hash($passwordNueva, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE configuracion SET valor = :hash WHERE clave = 'password_hash'");
    return $stmt->execute([':hash' => $hash]);
}

// Iniciar sesión
function iniciarSesion() {
    $_SESSION['autenticado'] = true;
    $_SESSION['tiempo_login'] = time();
}

// Cerrar sesión
function cerrarSesion() {
    session_unset();
    session_destroy();
}

// Requerir autenticación (usar en páginas protegidas)
function requerirAuth() {
    if (!estaAutenticado()) {
        header('Location: /piano/login.php');
        exit;
    }
}
