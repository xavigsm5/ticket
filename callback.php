<?php
/**
 * Callback de Azure AD - Sistema de Tickets Municipal
 * Maneja el retorno de autenticación OAuth2 con Microsoft 365
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

use TheNetworg\OAuth2\Client\Provider\Azure;

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Iniciar sesión de forma segura
iniciarSesionSegura();

// Si ya está autenticado, redirigir
if (estaAutenticado()) {
    $usuario = obtenerUsuarioActual();
    redirigirSegunRol($usuario['rol']);
    exit;
}

// Configuración de Azure AD desde .env
$clientId = $_ENV['AZURE_CLIENT_ID'] ?? '';
$clientSecret = $_ENV['AZURE_CLIENT_SECRET'] ?? '';
$tenantId = $_ENV['AZURE_TENANT_ID'] ?? '';
$redirectUri = $_ENV['AZURE_REDIRECT_URI'] ?? '';

// Validar configuración
if (empty($clientId) || empty($clientSecret) || empty($tenantId) || empty($redirectUri)) {
    mostrarError('Error de configuración: Las credenciales de Azure AD no están configuradas correctamente.');
    exit;
}

// Crear proveedor Azure
$provider = new Azure([
    'clientId'                => $clientId,
    'clientSecret'            => $clientSecret,
    'redirectUri'             => $redirectUri,
    'tenant'                  => $tenantId,
    'urlAuthorize'            => "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize",
    'urlAccessToken'          => "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
    'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
    'scopes'                  => ['openid', 'profile', 'email', 'User.Read'],
]);

try {
    // Paso 1: Si no hay código de autorización, iniciar el flujo OAuth
    if (!isset($_GET['code'])) {
        
        // Generar state para CSRF
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth2state'] = $state;
        
        // Obtener URL de autorización y redirigir
        $authorizationUrl = $provider->getAuthorizationUrl([
            'state' => $state,
            'scope' => ['openid', 'profile', 'email', 'User.Read'],
        ]);
        
        header('Location: ' . $authorizationUrl);
        exit;
    
    // Paso 2: Verificar state para prevenir CSRF
    } elseif (empty($_GET['state']) || ($_GET['state'] !== ($_SESSION['oauth2state'] ?? ''))) {
        
        unset($_SESSION['oauth2state']);
        mostrarError('Estado inválido. Por favor intente nuevamente.');
        exit;
    
    // Paso 3: Intercambiar código por token de acceso
    } else {
        
        unset($_SESSION['oauth2state']);
        
        // Obtener token de acceso
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);
        
        // Obtener información del usuario desde Microsoft Graph
        $me = $provider->get('https://graph.microsoft.com/v1.0/me', $accessToken);
        
        // Extraer datos del usuario
        $email = strtolower($me['mail'] ?? $me['userPrincipalName'] ?? '');
        $nombres = $me['givenName'] ?? '';
        $apellidos = $me['surname'] ?? '';
        $displayName = $me['displayName'] ?? '';
        
        // Validar que obtuvimos el email
        if (empty($email)) {
            mostrarError('No se pudo obtener el correo electrónico desde Microsoft.');
            exit;
        }
        
        // Validar que sea del dominio permitido
        $dominioPermitido = '@quintanormal.cl';
        if (!str_ends_with($email, $dominioPermitido)) {
            mostrarError("Solo se permite el acceso con cuentas del dominio {$dominioPermitido}");
            exit;
        }
        
        // Si no tenemos nombres, intentar extraer del displayName
        if (empty($nombres) && !empty($displayName)) {
            $partes = explode(' ', $displayName, 2);
            $nombres = $partes[0] ?? 'Usuario';
            $apellidos = $partes[1] ?? 'Microsoft';
        }
        
        // Valores por defecto si siguen vacíos
        $nombres = $nombres ?: 'Usuario';
        $apellidos = $apellidos ?: 'Microsoft';
        
        // Conectar a la base de datos
        $db = Database::getInstance();
        
        // Buscar si el usuario ya existe
        $usuario = $db->fetch(
            "SELECT * FROM usuarios WHERE LOWER(email) = ? AND activo = TRUE",
            [$email]
        );
        
        if ($usuario) {
            // Usuario existente: Iniciar sesión
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_rol'] = $usuario['rol'];
            $_SESSION['usuario_nombre'] = $usuario['nombres'] . ' ' . $usuario['apellidos'];
            
            // Actualizar último acceso
            $db->query(
                "UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = ?",
                [$usuario['id']]
            );
            
            // Redirigir según rol
            redirigirSegunRol($usuario['rol']);
            exit;
            
        } else {
            // Usuario nuevo: Crear automáticamente como funcionario
            
            // Generar contraseña aleatoria (el usuario no la usará, usa Microsoft)
            $passwordAleatorio = bin2hex(random_bytes(16));
            $passwordHash = password_hash($passwordAleatorio, PASSWORD_DEFAULT);
            
            // Generar RUT temporal único
            $rutTemporal = 'AZURE-' . time() . '-' . random_int(1000, 9999);
            
            // Insertar nuevo usuario
            $sql = "INSERT INTO usuarios (rut, email, password, nombres, apellidos, rol, activo, ultimo_acceso)
                    VALUES (?, ?, ?, ?, ?, 'funcionario', TRUE, CURRENT_TIMESTAMP)
                    RETURNING id";
            
            $stmt = $db->query($sql, [
                $rutTemporal,
                $email,
                $passwordHash,
                $nombres,
                $apellidos
            ]);
            
            $nuevoUsuario = $stmt->fetch();
            
            if ($nuevoUsuario) {
                // Iniciar sesión con el nuevo usuario
                $_SESSION['usuario_id'] = $nuevoUsuario['id'];
                $_SESSION['usuario_rol'] = 'funcionario';
                $_SESSION['usuario_nombre'] = $nombres . ' ' . $apellidos;
                
                // Redirigir al dashboard de funcionario
                header('Location: /admin/dashboard.php');
                exit;
            } else {
                mostrarError('Error al crear la cuenta de usuario.');
                exit;
            }
        }
    }
    
} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
    // Error de autenticación OAuth
    error_log('Azure OAuth Error: ' . $e->getMessage());
    mostrarError('Error de autenticación con Microsoft: ' . $e->getMessage());
    exit;
    
} catch (\Exception $e) {
    // Otros errores
    error_log('Azure Login Error: ' . $e->getMessage());
    mostrarError('Ocurrió un error durante el inicio de sesión. Por favor intente nuevamente.');
    exit;
}

/**
 * Redirige al usuario según su rol
 */
function redirigirSegunRol($rol) {
    if (in_array($rol, ['admin', 'supervisor', 'funcionario'])) {
        header('Location: /admin/dashboard.php');
    } else {
        header('Location: /ciudadano/mis-tickets.php');
    }
    exit;
}

/**
 * Muestra una página de error amigable
 */
function mostrarError($mensaje) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error de Autenticación - Mesa de Ayuda Municipal</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
        <div class="login-pagina">
            <div class="login-contenedor" style="max-width: 500px;">
                <div style="text-align: center; padding: 40px;">
                    <div style="font-size: 64px; color: #dc3545; margin-bottom: 20px;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h1 style="margin-bottom: 16px; color: #333;">Error de Autenticación</h1>
                    <p style="color: #666; margin-bottom: 24px;"><?= htmlspecialchars($mensaje) ?></p>
                    <a href="/login.php" class="btn btn-primario">
                        <i class="bi bi-arrow-left"></i> Volver al inicio de sesión
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
