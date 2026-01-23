<?php
require_once 'config/database.php';

$mensaje = '';
$error = '';
$mostrarSetup = false;

// Verificar si ya existe una contraseña configurada
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'password_hash'");
    $stmt->execute();
    $resultado = $stmt->fetch();
    
    if (!$resultado || empty($resultado['valor'])) {
        $mostrarSetup = true;
    } else {
        // Ya hay contraseña configurada, redirigir al login
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    $error = 'Error de conexión a la base de datos: ' . $e->getMessage();
}

// Procesar establecimiento de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['establecer_password'])) {
    $password = $_POST['password'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';
    
    if (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $confirmar) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertar o actualizar la contraseña
            $stmt = $db->prepare("INSERT INTO configuracion (clave, valor, descripcion) 
                                  VALUES ('password_hash', :hash, 'Hash de la contraseña de acceso')
                                  ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmt->execute([':hash' => $hash]);
            
            $mensaje = 'Contraseña establecida correctamente. Redirigiendo al login...';
            header('refresh:2;url=login.php');
        } catch (Exception $e) {
            $error = 'Error al establecer la contraseña: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración inicial - Piano Tracker</title>
    <link rel="stylesheet" href="/piano/assets/css/style.css">
    <style>
        .setup-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }
        
        .setup-box {
            background: white;
            padding: 3rem;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
        }
        
        .setup-box h1 {
            text-align: center;
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 2rem;
        }
        
        .setup-box .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        
        .setup-box .form-group {
            margin-bottom: 1.5rem;
        }
        
        .setup-box input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            border: 2px solid #ddd;
            border-radius: 4px;
            transition: border-color 0.3s;
        }
        
        .setup-box input[type="password"]:focus {
            outline: none;
            border-color: var(--secondary);
        }
        
        .setup-box .btn {
            width: 100%;
            padding: 0.75rem;
            font-size: 1.1rem;
        }
        
        .setup-box .success {
            background: #d4edda;
            color: #155724;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .setup-box .error {
            background: #f8d7da;
            color: #721c24;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .setup-box .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .setup-box .info-box ul {
            margin: 0.5rem 0 0 1.5rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-box">
            <h1>🎹 Piano Tracker</h1>
            <div class="subtitle">Configuración inicial</div>
            
            <?php if ($mensaje): ?>
            <div class="success"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($mostrarSetup): ?>
            <div class="info-box">
                <strong>Primera instalación detectada</strong>
                <p>No hay contraseña configurada. Por favor, establece una contraseña para proteger tu aplicación.</p>
                <ul>
                    <li>Mínimo 6 caracteres</li>
                    <li>Usa una contraseña segura</li>
                    <li>Anótala en lugar seguro</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="establecer_password" value="1">
                
                <div class="form-group">
                    <label for="password">Nueva contraseña</label>
                    <input type="password" id="password" name="password" required minlength="6" autofocus>
                </div>
                
                <div class="form-group">
                    <label for="confirmar">Confirmar contraseña</label>
                    <input type="password" id="confirmar" name="confirmar" required minlength="6">
                </div>
                
                <button type="submit" class="btn btn-success">Establecer contraseña</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
