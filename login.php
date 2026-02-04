<?php
/**
 * Login del sistema
 */
require_once __DIR__ . '/includes/functions.php';

iniciarSesionSegura();

if (estaAutenticado()) {
    $usuario = obtenerUsuarioActual();
    $baseUrl = getBaseUrl();
    if (in_array($usuario['rol'], ['admin', 'soporte_ti'])) {
        header('Location: ' . $baseUrl . '/admin/dashboard.php');
    } else {
        header('Location: ' . $baseUrl . '/funcionario/mis-tickets.php');
    }
    exit;
}

$error = '';
$email = '';

// Autenticar contra M365 via IMAP
function autenticarViaIMAP($email, $password) {
    $imapHost = 'outlook.office365.com';
    $imapPort = 993;
    
    // Construir string de conexión IMAP con SSL
    $mailbox = "{" . $imapHost . ":" . $imapPort . "/imap/ssl/novalidate-cert}INBOX";
    
    // Intentar conexión IMAP
    $connection = @imap_open($mailbox, $email, $password, 0, 1);
    
    if ($connection) {
        imap_close($connection);
        // Limpiar alertas
        imap_errors();
        imap_alerts();
        return ['success' => true, 'error' => null];
    }
    
    // Capturar errores de IMAP
    $errors = imap_errors();
    $alerts = imap_alerts();
    
    $errorMsg = '';
    if ($errors) {
        $errorMsg = implode(' | ', $errors);
    }
    if ($alerts) {
        $errorMsg .= ' ' . implode(' | ', $alerts);
    }
    
    // Log del error para debugging
    error_log("IMAP Auth Error for $email: $errorMsg");
    
    return ['success' => false, 'error' => $errorMsg];
}

// Obtener usuario de BD o crear uno nuevo
function obtenerOCrearUsuarioIMAP($email) {
    $db = Database::getInstance();
    
    // Buscar usuario existente
    $usuario = $db->fetch("SELECT * FROM usuarios WHERE email = ? AND activo = TRUE", [$email]);
    
    if ($usuario) {
        return $usuario;
    }
    
    // Extraer nombre del email (parte antes del @)
    $nombreBase = explode('@', $email)[0];
    // Separar por puntos para obtener nombres y apellidos
    $partes = explode('.', $nombreBase);
    $nombres = ucfirst($partes[0] ?? 'Usuario');
    $apellidos = isset($partes[1]) ? ucfirst($partes[1]) : 'Microsoft365';
    
    // Crear usuario con rol 'funcionario'
    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $rutTemporal = 'M365-' . time() . '-' . rand(1000, 9999);
    
    try {
        $db->query(
            "INSERT INTO usuarios (rut, email, password, nombres, apellidos, rol, activo, email_verificado) 
             VALUES (?, ?, ?, ?, ?, 'funcionario', TRUE, TRUE)",
            [$rutTemporal, $email, $passwordHash, $nombres, $apellidos]
        );
        
        // Obtener el usuario recién creado
        return $db->fetch("SELECT * FROM usuarios WHERE email = ?", [$email]);
    } catch (Exception $e) {
        error_log("Error creando usuario IMAP: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = limpiarInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos.';
    } else {
        // Validar dominios permitidos (para pruebas)
        $dominiosPermitidos = ['@quintanormal.cl', '@municipalidad.cl', '@caschile.cl'];
        $dominioValido = false;
        
        foreach ($dominiosPermitidos as $dominio) {
            if (substr(strtolower($email), -strlen($dominio)) === $dominio) {
                $dominioValido = true;
                break;
            }
        }
        
        // También permitir si el usuario ya existe en la base de datos (ej: soporte externo)
        if (!$dominioValido) {
            $db = Database::getInstance();
            $usuarioExistente = $db->fetch("SELECT id FROM usuarios WHERE email = ? AND activo = TRUE", [$email]);
            if ($usuarioExistente) {
                $dominioValido = true;
            }
        }
        
        if (!$dominioValido) {
            $error = 'Solo se permiten correos institucionales @quintanormal.cl o @municipalidad.cl';
        } else {
            // Determinar método de autenticación según el dominio
            $esQuintaNormal = str_ends_with(strtolower($email), '@quintanormal.cl');
            
            if ($esQuintaNormal) {
                // @quintanormal.cl → Autenticación IMAP contra Microsoft 365
                $authResult = autenticarViaIMAP($email, $password);
                
                if ($authResult['success']) {
                    // Autenticación IMAP exitosa - obtener o crear usuario
                    $usuario = obtenerOCrearUsuarioIMAP($email);
                    
                    if ($usuario) {
                        // Establecer variables de sesión
                        $_SESSION['usuario_id'] = $usuario['id'];
                        $_SESSION['user_id'] = $usuario['id'];
                        $_SESSION['usuario_rol'] = $usuario['rol'];
                        $_SESSION['user_rol'] = $usuario['rol'];
                        $_SESSION['usuario_nombre'] = $usuario['nombres'] . ' ' . $usuario['apellidos'];
                        
                        // Actualizar último acceso
                        $db = Database::getInstance();
                        $db->query("UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = ?", [$usuario['id']]);
                        
                        // Redirigir según rol
                        $baseUrl = getBaseUrl();
                        if (in_array($usuario['rol'], ['admin', 'soporte_ti'])) {
                            header('Location: ' . $baseUrl . '/admin/dashboard.php');
                        } else {
                            header('Location: ' . $baseUrl . '/funcionario/mis-tickets.php');
                        }
                        exit;
                    } else {
                        $error = 'Error al procesar la cuenta de usuario.';
                    }
                } else {
                    $imapError = $authResult['error'] ?? '';
                    
                    if (stripos($imapError, 'AUTHENTICATE') !== false || stripos($imapError, 'LOGIN') !== false) {
                        $error = 'Error de autenticación Microsoft 365. Use el botón "Ingresar con Microsoft 365" o verifique sus credenciales.';
                    } elseif (stripos($imapError, 'connection') !== false || stripos($imapError, 'timeout') !== false) {
                        $error = 'No se pudo conectar al servidor de Microsoft 365.';
                    } else {
                        $error = 'Credenciales incorrectas. Use el botón "Ingresar con Microsoft 365" para cuentas @quintanormal.cl.';
                    }
                }
            } else {
                // @municipalidad.cl (y otros) → Autenticación por base de datos
                $db = Database::getInstance();
                $usuario = $db->fetch("SELECT * FROM usuarios WHERE email = ? AND activo = TRUE", [$email]);
                
                if ($usuario && password_verify($password, $usuario['password'])) {
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['user_id'] = $usuario['id'];
                    $_SESSION['usuario_rol'] = $usuario['rol'];
                    $_SESSION['user_rol'] = $usuario['rol'];
                    $_SESSION['usuario_nombre'] = $usuario['nombres'] . ' ' . $usuario['apellidos'];
                    
                    $db->query("UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = ?", [$usuario['id']]);
                    
                    $baseUrl = getBaseUrl();
                    if (in_array($usuario['rol'], ['admin', 'soporte_ti'])) {
                        header('Location: ' . $baseUrl . '/admin/dashboard.php');
                    } else {
                        header('Location: ' . $baseUrl . '/funcionario/mis-tickets.php');
                    }
                    exit;
                } else {
                    $error = 'Credenciales incorrectas.';
                }
            }
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
                        <label class="form-label" for="email">Correo institucional</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="usuario@quintanormal.cl" value="<?= htmlspecialchars($email) ?>" required>
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
                
                <!-- Separador -->
                <div style="margin: 24px 0; display: flex; align-items: center; gap: 16px;">
                    <div style="flex: 1; height: 1px; background: var(--gris-200);"></div>
                    <span style="font-size: 13px; color: var(--gris-500);">o continúe con</span>
                    <div style="flex: 1; height: 1px; background: var(--gris-200);"></div>
                </div>
                
                <!-- Botón Microsoft 365 -->
                <a href="/callback.php" class="btn btn-bloque btn-lg" style="background: #2F2F2F; color: #fff; display: flex; align-items: center; justify-content: center; gap: 12px; text-decoration: none; border: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 21 21">
                        <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                        <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                        <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                        <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
                    </svg>
                    Ingresar con Microsoft 365
                </a>
                <p style="margin-top: 8px; font-size: 11px; color: var(--gris-500); text-align: center;">
                    Para funcionarios @quintanormal.cl
                </p>
                
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
