<?php
require_once 'config/auth.php';

// Si ya está autenticado, redirigir al inicio
if (estaAutenticado()) {
    header('Location: index.php');
    exit;
}

// Verificar si hay contraseña configurada
$db = getDB();
$stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'password_hash'");
$stmt->execute();
$resultado = $stmt->fetch();

if (!$resultado || empty($resultado['valor'])) {
    // No hay contraseña configurada, ir a setup
    header('Location: setup.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    if (verificarPassword($password)) {
        iniciarSesion();
        header('Location: index.php');
        exit;
    } else {
        $error = 'Contraseña incorrecta';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Piano Tracker</title>
    <link rel="stylesheet" href="/piano/assets/css/style.css">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }
        
        .login-box {
            background: white;
            padding: 3rem;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 100%;
        }
        
        .login-box h1 {
            text-align: center;
            color: var(--primary);
            margin-bottom: 2rem;
            font-size: 2rem;
        }
        
        .login-box .form-group {
            margin-bottom: 1.5rem;
        }
        
        .login-box input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            border: 2px solid #ddd;
            border-radius: 4px;
            transition: border-color 0.3s;
        }
        
        .login-box input[type="password"]:focus {
            outline: none;
            border-color: var(--secondary);
        }
        
        .login-box .btn {
            width: 100%;
            padding: 0.75rem;
            font-size: 1.1rem;
        }
        
        .login-box .error {
            background: #f8d7da;
            color: #721c24;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .login-box .info {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>🎹 Piano Tracker</h1>
            
            <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                
                <button type="submit" class="btn btn-primary">Acceder</button>
            </form>
        </div>
    </div>
</body>
</html>
