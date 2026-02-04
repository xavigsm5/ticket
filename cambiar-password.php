<?php
/**
 * Cambio de contraseña
 */
require_once __DIR__ . '/includes/functions.php';

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    header('Location: /login.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    // Validaciones
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $mensaje = 'Todos los campos son obligatorios';
        $tipo_mensaje = 'error';
    } elseif ($password_nueva !== $password_confirmar) {
        $mensaje = 'Las contraseñas nuevas no coinciden';
        $tipo_mensaje = 'error';
    } elseif (strlen($password_nueva) < 5) {
        $mensaje = 'La contraseña debe tener al menos 5 caracteres';
        $tipo_mensaje = 'error';
    } else {
        // Verificar contraseña actual
        $db = Database::getInstance();
        $usuario_db = $db->fetch("SELECT password FROM usuarios WHERE id = ?", [$usuario['id']]);
        
        if (!$usuario_db || !password_verify($password_actual, $usuario_db['password'])) {
            $mensaje = 'La contraseña actual es incorrecta';
            $tipo_mensaje = 'error';
        } else {
            // Actualizar contraseña
            $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            $db->query("UPDATE usuarios SET password = ? WHERE id = ?", [$nuevo_hash, $usuario['id']]);
            
            $mensaje = '¡Contraseña actualizada correctamente!';
            $tipo_mensaje = 'exito';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - Mesa de Ayuda</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .cambiar-container {
            max-width: 500px;
            margin: 60px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .cambiar-container h2 {
            margin-bottom: 25px;
            color: #0066cc;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 0 2px rgba(0,102,204,0.2);
        }
        .btn-cambiar {
            width: 100%;
            padding: 12px;
            background: #0066cc;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-cambiar:hover {
            background: #0052a3;
        }
        .mensaje {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .volver-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }
        .volver-link:hover {
            color: #0066cc;
        }
        .usuario-info {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .usuario-info .email {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body style="background: #f5f7fa;">
    <div class="cambiar-container">
        <h2>🔐 Cambiar Contraseña</h2>
        
        <div class="usuario-info">
            <strong><?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?></strong>
            <div class="email"><?= htmlspecialchars($usuario['email']) ?></div>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mensaje mensaje-<?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="password_actual">Contraseña Actual</label>
                <input type="password" id="password_actual" name="password_actual" required>
            </div>
            
            <div class="form-group">
                <label for="password_nueva">Nueva Contraseña</label>
                <input type="password" id="password_nueva" name="password_nueva" required minlength="5">
            </div>
            
            <div class="form-group">
                <label for="password_confirmar">Confirmar Nueva Contraseña</label>
                <input type="password" id="password_confirmar" name="password_confirmar" required minlength="5">
            </div>
            
            <button type="submit" class="btn-cambiar">Cambiar Contraseña</button>
        </form>
        
        <a href="javascript:history.back()" class="volver-link">← Volver</a>
    </div>
</body>
</html>
