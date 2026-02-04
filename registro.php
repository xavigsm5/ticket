<?php
/**
 * Registro de usuarios
 */
require_once __DIR__ . '/includes/functions.php';

iniciarSesionSegura();

if (estaAutenticado()) {
    header('Location: /ciudadano/mis-tickets.php');
    exit;
}

$errores = [];
$exito = false;
$datos = ['rut' => '', 'nombres' => '', 'apellidos' => '', 'email' => '', 'telefono' => '', 'direccion' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'rut' => limpiarInput($_POST['rut'] ?? ''),
        'nombres' => limpiarInput($_POST['nombres'] ?? ''),
        'apellidos' => limpiarInput($_POST['apellidos'] ?? ''),
        'email' => limpiarInput($_POST['email'] ?? ''),
        'telefono' => limpiarInput($_POST['telefono'] ?? ''),
        'direccion' => limpiarInput($_POST['direccion'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validaciones
    if (empty($datos['nombres'])) $errores[] = 'El nombre es requerido';
    if (empty($datos['apellidos'])) $errores[] = 'Los apellidos son requeridos';
    if (empty($datos['email'])) $errores[] = 'El email es requerido';
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'El email no es válido';
    if (strlen($password) < 6) $errores[] = 'La contraseña debe tener al menos 6 caracteres';
    if ($password !== $password_confirm) $errores[] = 'Las contraseñas no coinciden';
    
    if (empty($errores)) {
        $db = Database::getInstance();
        
        // Verificar si correo o RUT ya existen
        $condicion_rut = !empty($datos['rut']) ? "OR rut = ?" : "";
        $params = !empty($datos['rut']) ? [$datos['email'], $datos['rut']] : [$datos['email']];
        
        $existe = $db->fetch("SELECT id, email, rut FROM usuarios WHERE email = ? $condicion_rut", $params);
        
        if ($existe) {
            if ($existe['email'] === $datos['email']) {
                $errores[] = 'Ya existe una cuenta con este correo electrónico';
            }
            if (!empty($datos['rut']) && $existe['rut'] === $datos['rut']) {
                $errores[] = 'Este RUT ya se encuentra registrado';
            }
        } else {
            // Intentar registro protegido
            try {
                $db->query(
                    "INSERT INTO usuarios (rut, email, password, nombres, apellidos, telefono, direccion, rol) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'ciudadano')",
                    [
                        $datos['rut'] ?: null,
                        $datos['email'],
                        password_hash($password, PASSWORD_DEFAULT),
                        $datos['nombres'],
                        $datos['apellidos'],
                        $datos['telefono'] ?: null,
                        $datos['direccion'] ?: null
                    ]
                );
                $exito = true;
            } catch (PDOException $e) {
                // Capturar error de duplicado por si acaso (race condition)
                if (strpos($e->getMessage(), 'usuarios_rut_key') !== false) {
                    $errores[] = 'Este RUT ya se encuentra registrado';
                } elseif (strpos($e->getMessage(), 'usuarios_email_key') !== false) {
                    $errores[] = 'Este correo ya se encuentra registrado';
                } else {
                    $errores[] = 'Error al registrar usuario: ' . $e->getMessage();
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
    <title>Registro - Sistema de Tickets Municipal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="login-pagina">
        <div class="login-izquierda">
            <div class="login-branding">
                <div style="width:100px;height:100px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:var(--espaciado-xl);">
                    <i class="bi bi-person-plus" style="font-size:3rem;color:var(--color-primario)"></i>
                </div>
                <h1>Cree su Cuenta</h1>
                <p>
                    Regístrese para poder crear y dar seguimiento a sus solicitudes 
                    de manera fácil y rápida. Solo necesita sus datos básicos.
                </p>
                <ul style="margin-top: var(--espaciado-xl); list-style: none;">
                    <li style="display: flex; align-items: center; gap: var(--espaciado-md); margin-bottom: var(--espaciado-md);">
                        <i class="bi bi-check-circle-fill" style="color: var(--color-acento);"></i>
                        Seguimiento en tiempo real de sus solicitudes
                    </li>
                    <li style="display: flex; align-items: center; gap: var(--espaciado-md); margin-bottom: var(--espaciado-md);">
                        <i class="bi bi-check-circle-fill" style="color: var(--color-acento);"></i>
                        Notificaciones por correo electrónico
                    </li>
                    <li style="display: flex; align-items: center; gap: var(--espaciado-md); margin-bottom: var(--espaciado-md);">
                        <i class="bi bi-check-circle-fill" style="color: var(--color-acento);"></i>
                        Historial completo de trámites
                    </li>
                    <li style="display: flex; align-items: center; gap: var(--espaciado-md);">
                        <i class="bi bi-check-circle-fill" style="color: var(--color-acento);"></i>
                        Comunicación directa con funcionarios
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="login-derecha" style="width: 550px;">
            <div class="login-formulario" style="max-width: 450px;">
                <?php if ($exito): ?>
                <div style="text-align: center;">
                    <div style="width:80px;height:80px;background:var(--color-exito);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto var(--espaciado-lg);">
                        <i class="bi bi-check-lg" style="font-size:2.5rem;color:white;"></i>
                    </div>
                    <h2>¡Registro Exitoso!</h2>
                    <p style="color: var(--gris-600); margin: var(--espaciado-md) 0 var(--espaciado-xl);">
                        Su cuenta ha sido creada correctamente. Ahora puede iniciar sesión.
                    </p>
                    <a href="/login.php" class="btn btn-primario btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Ir a Iniciar Sesión
                    </a>
                </div>
                <?php else: ?>
                <h2>Crear Cuenta</h2>
                <p>Complete el formulario para registrarse</p>
                
                <?php if (!empty($errores)): ?>
                <div class="alerta alerta-error">
                    <i class="bi bi-exclamation-circle"></i>
                    <div>
                        <ul style="margin: 0; padding-left: var(--espaciado-lg);">
                            <?php foreach ($errores as $error): ?>
                            <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-fila">
                        <div class="form-grupo">
                            <label class="form-label requerido" for="nombres">Nombres</label>
                            <input type="text" id="nombres" name="nombres" class="form-control" 
                                   value="<?= htmlspecialchars($datos['nombres']) ?>" required>
                        </div>
                        <div class="form-grupo">
                            <label class="form-label requerido" for="apellidos">Apellidos</label>
                            <input type="text" id="apellidos" name="apellidos" class="form-control" 
                                   value="<?= htmlspecialchars($datos['apellidos']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-fila">
                        <div class="form-grupo">
                            <label class="form-label" for="rut">RUT (Opcional)</label>
                            <input type="text" id="rut" name="rut" class="form-control" 
                                   placeholder="12.345.678-9" value="<?= htmlspecialchars($datos['rut']) ?>">
                        </div>
                        <div class="form-grupo">
                            <label class="form-label" for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" class="form-control" 
                                   placeholder="+56 9 1234 5678" value="<?= htmlspecialchars($datos['telefono']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-grupo">
                        <label class="form-label requerido" for="email">Correo Electrónico</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="correo@ejemplo.com" value="<?= htmlspecialchars($datos['email']) ?>" required>
                    </div>
                    
                    <div class="form-grupo">
                        <label class="form-label" for="direccion">Dirección</label>
                        <input type="text" id="direccion" name="direccion" class="form-control" 
                               placeholder="Calle, número, comuna" value="<?= htmlspecialchars($datos['direccion']) ?>">
                    </div>
                    
                    <div class="form-fila">
                        <div class="form-grupo">
                            <label class="form-label requerido" for="password">Contraseña</label>
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="Mínimo 6 caracteres" required>
                        </div>
                        <div class="form-grupo">
                            <label class="form-label requerido" for="password_confirm">Confirmar</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control" 
                                   placeholder="Repita la contraseña" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primario btn-bloque btn-lg" style="margin-top: var(--espaciado-md);">
                        <i class="bi bi-person-plus"></i> Crear Cuenta
                    </button>
                </form>
                
                <div style="margin-top: var(--espaciado-xl); text-align: center; padding-top: var(--espaciado-lg); border-top: 1px solid var(--gris-200);">
                    <p style="font-size: 0.875rem; color: var(--gris-600);">
                        ¿Ya tiene cuenta? <a href="/login.php" style="font-weight: 600;">Inicie sesión</a>
                    </p>
                </div>
                
                <div style="margin-top: var(--espaciado-lg); text-align: center;">
                    <a href="/" style="font-size: 0.875rem; color: var(--gris-500);">
                        <i class="bi bi-arrow-left"></i> Volver al inicio
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
