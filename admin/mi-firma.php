<?php
require_once __DIR__ . '/../includes/functions.php';
requiereAutenticacion();

$usuario = obtenerUsuarioActual();
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['firma'])) {
    $upload_dir = __DIR__ . '/../uploads/firmas/';
    
    if ($_FILES['firma']['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($_FILES['firma']['name'], PATHINFO_EXTENSION);
        $nombre_archivo = 'firma_' . $usuario['id'] . '_' . time() . '.' . $extension;
        $ruta_destino = $upload_dir . $nombre_archivo;
        
        // Validar que sea imagen
        $tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['firma']['type'], $tipos_permitidos)) {
            $error = 'Solo se permiten imágenes (JPG, PNG, GIF)';
        } elseif ($_FILES['firma']['size'] > 2 * 1024 * 1024) {
            $error = 'La imagen no debe superar 2MB';
        } else {
            if (move_uploaded_file($_FILES['firma']['tmp_name'], $ruta_destino)) {
                // Eliminar firma anterior si existe
                if ($usuario['firma_imagen'] && file_exists(__DIR__ . '/../' . $usuario['firma_imagen'])) {
                    unlink(__DIR__ . '/../' . $usuario['firma_imagen']);
                }
                
                // Actualizar base de datos
                $db = Database::getInstance();
                $ruta_relativa = 'uploads/firmas/' . $nombre_archivo;
                $db->query("UPDATE usuarios SET firma_imagen = ? WHERE id = ?", [$ruta_relativa, $usuario['id']]);
                
                $mensaje = 'Firma actualizada correctamente';
                $usuario['firma_imagen'] = $ruta_relativa;
            } else {
                $error = 'Error al subir la imagen';
            }
        }
    } else {
        $error = 'Error al subir el archivo';
    }
}

// Eliminar firma
if (isset($_POST['eliminar_firma'])) {
    if ($usuario['firma_imagen'] && file_exists(__DIR__ . '/../' . $usuario['firma_imagen'])) {
        unlink(__DIR__ . '/../' . $usuario['firma_imagen']);
    }
    $db = Database::getInstance();
    $db->query("UPDATE usuarios SET firma_imagen = NULL WHERE id = ?", [$usuario['id']]);
    $mensaje = 'Firma eliminada correctamente';
    $usuario['firma_imagen'] = null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Firma - Sistema de Tickets</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="header-admin">
        <div class="contenedor-fluid">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <h1 style="margin: 0; font-size: 20px;">Mi Firma de Correo</h1>
                <a href="/admin/dashboard.php" class="btn btn-secundario btn-sm">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </header>

    <main class="contenedor" style="padding: 40px 0; max-width: 800px;">
        <?php if ($mensaje): ?>
        <div class="alerta alerta-exito">
            <i class="bi bi-check-circle"></i> <?= $mensaje ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alerta alerta-error">
            <i class="bi bi-exclamation-circle"></i> <?= $error ?>
        </div>
        <?php endif; ?>

        <div style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <h2 style="margin-top: 0; color: #1a365d;">
                <i class="bi bi-pen"></i> Configurar Firma de Correo
            </h2>
            <p style="color: #666; margin-bottom: 30px;">
                Esta firma se agregará automáticamente a todos los correos que envíes desde el sistema.
            </p>

            <?php if ($usuario['firma_imagen']): ?>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; font-size: 16px;">Firma Actual:</h3>
                <img src="/<?= $usuario['firma_imagen'] ?>" alt="Firma actual" 
                     style="max-width: 100%; border: 1px solid #dee2e6; border-radius: 4px;">
                <form method="POST" style="margin-top: 15px;">
                    <button type="submit" name="eliminar_firma" class="btn btn-peligro btn-sm" 
                            onclick="return confirm('¿Seguro que deseas eliminar tu firma?')">
                        <i class="bi bi-trash"></i> Eliminar Firma
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grupo">
                    <label class="form-label">Subir Nueva Firma (Imagen)</label>
                    <input type="file" name="firma" class="form-control" accept="image/*" required>
                    <small style="color: #6c757d; display: block; margin-top: 8px;">
                        Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB<br>
                        Recomendamos usar una imagen de 400x150 píxeles aproximadamente.
                    </small>
                </div>

                <button type="submit" class="btn btn-primario">
                    <i class="bi bi-upload"></i> Subir Firma
                </button>
            </form>

            <hr style="margin: 30px 0; border: none; border-top: 1px solid #dee2e6;">

            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                <h4 style="margin: 0 0 10px 0; color: #856404; font-size: 14px;">
                    <i class="bi bi-lightbulb"></i> Consejos para tu firma:
                </h4>
                <ul style="margin: 0; padding-left: 20px; color: #856404; font-size: 13px;">
                    <li>Crea tu firma en un editor (Word, PowerPoint, etc.)</li>
                    <li>Toma una captura de pantalla de tu firma</li>
                    <li>Recorta la imagen para que solo incluya la firma</li>
                    <li>Sube la imagen aquí</li>
                </ul>
            </div>
        </div>
    </main>
</body>
</html>
