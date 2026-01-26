<?php
/**
 * Login - Sistema de Tickets Municipal
 */
require_once __DIR__ . '/includes/functions.php';

iniciarSesionSegura();

if (estaAutenticado()) {
    $usuario = obtenerUsuarioActual();
    if (in_array($usuario['rol'], ['admin', 'supervisor', 'funcionario'])) {
        header('Location: /admin/dashboard.php');
    } else {
        header('Location: /ciudadano/mis-tickets.php');
    }
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = limpiarInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos.';
    } else {
        $db = Database::getInstance();
        $usuario = $db->fetch("SELECT * FROM usuarios WHERE email = ? AND activo = TRUE", [$email]);
        
        if ($usuario && password_verify($password, $usuario['password'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_rol'] = $usuario['rol'];
            $_SESSION['usuario_nombre'] = $usuario['nombres'] . ' ' . $usuario['apellidos'];
            
            $db->query("UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = ?", [$usuario['id']]);
            
            if (in_array($usuario['rol'], ['admin', 'supervisor', 'funcionario'])) {
                header('Location: /admin/dashboard.php');
            } else {
                header('Location: /ciudadano/mis-tickets.php');
            }
            exit;
        } else {
            $error = 'Credenciales incorrectas.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Mesa de Ayuda Municipal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="login-pagina">
        <div class="login-contenedor">
            <div class="login-izquierda">
                <div class="login-logo">
                    <div class="login-logo-icon">
                        <i class="bi bi-building"></i>
                    </div>
                    <span class="login-logo-texto">Municipalidad</span>
                </div>
                
                <h1 class="login-titulo">Iniciar Sesión</h1>
                <p class="login-subtitulo">Ingrese a la mesa de ayuda municipal</p>
                
                <?php if ($error): ?>
                <div class="alerta alerta-error">
                    <i class="bi bi-exclamation-circle"></i>
                    <?= $error ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['logout'])): ?>
                <div class="alerta alerta-info">
                    <i class="bi bi-check-circle"></i>
                    Sesión cerrada correctamente
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-grupo">
                        <label class="form-label" for="email">Correo electrónico</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="usuario@municipalidad.cl" value="<?= htmlspecialchars($email) ?>" required>
                    </div>
                    
                    <div class="form-grupo">
                        <label class="form-label" for="password">Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="••••••••" required>
                    </div>
                    
                    <div class="form-grupo" style="display: flex; justify-content: space-between; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="recordar"> Recordarme
                        </label>
                        <a href="/recuperar-password.php" style="font-size: 13px;">¿Olvidó su contraseña?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primario btn-bloque btn-lg">
                        Iniciar Sesión
                    </button>
                </form>
                
                <div style="margin-top: 24px; text-align: center; padding-top: 24px; border-top: 1px solid var(--gris-200);">
                    <p style="font-size: 13px; color: var(--gris-600);">
                        ¿No tiene cuenta? <a href="/registro.php">Regístrese aquí</a>
                    </p>
                </div>
                
                <div style="margin-top: 16px; text-align: center;">
                    <a href="/" style="font-size: 13px; color: var(--gris-500);">
                        <i class="bi bi-arrow-left"></i> Volver al inicio
                    </a>
                </div>
            </div>
            
            <div class="login-derecha">
                <h2 class="login-info-titulo">Mesa de Ayuda Municipal</h2>
                <ul class="login-info-lista">
                    <li>
                        <i class="bi bi-check-circle-fill"></i>
                        Gestione todas las solicitudes ciudadanas en un solo lugar
                    </li>
                    <li>
                        <i class="bi bi-check-circle-fill"></i>
                        Asigne tickets a los agentes correspondientes
                    </li>
                    <li>
                        <i class="bi bi-check-circle-fill"></i>
                        Responda y resuelva consultas eficientemente
                    </li>
                    <li>
                        <i class="bi bi-check-circle-fill"></i>
                        Monitoree métricas de rendimiento
                    </li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
