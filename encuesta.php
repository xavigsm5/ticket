<?php
/**
 * Encuesta de Satisfacción - CSAT
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/freshdesk_functions.php';

$token = $_GET['token'] ?? '';
$encuesta = null;
$enviado = false;
$error = '';

if (empty($token)) {
    $error = 'Token de encuesta inválido.';
} else {
    $encuesta = obtenerEncuestaPorToken($token);
    if (!$encuesta) {
        $error = 'Esta encuesta ya fue respondida o el enlace ha expirado.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $encuesta) {
    $calificacion = (int)($_POST['calificacion'] ?? 0);
    $comentario = limpiarInput($_POST['comentario'] ?? '');
    
    if ($calificacion >= 1 && $calificacion <= 5) {
        responderEncuesta($token, $calificacion, $comentario);
        $enviado = true;
    } else {
        $error = 'Por favor seleccione una calificación.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encuesta de Satisfacción - Municipalidad</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .encuesta-contenedor {
            max-width: 500px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .estrellas {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 24px 0;
        }
        .estrella {
            font-size: 40px;
            color: var(--gris-300);
            cursor: pointer;
            transition: all 0.15s;
        }
        .estrella:hover,
        .estrella.activa {
            color: #f39c12;
            transform: scale(1.1);
        }
        .estrella-label {
            display: flex;
            justify-content: space-between;
            color: var(--gris-500);
            font-size: 12px;
            margin-bottom: 24px;
        }
        .exito-icono {
            width: 80px;
            height: 80px;
            background: #d4edda;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
    </style>
</head>
<body style="background: var(--gris-100);">
    <header class="header-publico">
        <div class="header-contenido" style="justify-content: center;">
            <div class="header-logo">
                <div class="header-logo-icon">M</div>
                <span class="header-titulo">Municipalidad</span>
            </div>
        </div>
    </header>

    <div class="encuesta-contenedor">
        <?php if ($enviado): ?>
        <div class="tarjeta">
            <div class="tarjeta-body" style="text-align: center; padding: 40px;">
                <div class="exito-icono">
                    <i class="bi bi-check-lg" style="font-size: 40px; color: #28a745;"></i>
                </div>
                <h2 style="margin-bottom: 16px; color: var(--gris-900);">¡Gracias por su opinión!</h2>
                <p style="color: var(--gris-600); margin-bottom: 24px;">
                    Su retroalimentación nos ayuda a mejorar nuestros servicios municipales.
                </p>
                <a href="/" class="btn btn-primario">Volver al inicio</a>
            </div>
        </div>
        
        <?php elseif ($error && !$encuesta): ?>
        <div class="tarjeta">
            <div class="tarjeta-body" style="text-align: center; padding: 40px;">
                <div class="exito-icono" style="background: #f8d7da;">
                    <i class="bi bi-x-lg" style="font-size: 40px; color: #dc3545;"></i>
                </div>
                <h2 style="margin-bottom: 16px; color: var(--gris-900);">Enlace no válido</h2>
                <p style="color: var(--gris-600); margin-bottom: 24px;">
                    <?= $error ?>
                </p>
                <a href="/" class="btn btn-secundario">Ir al inicio</a>
            </div>
        </div>
        
        <?php elseif ($encuesta): ?>
        <div class="tarjeta">
            <div class="tarjeta-body" style="padding: 32px;">
                <div style="text-align: center; margin-bottom: 24px;">
                    <h2 style="color: var(--gris-900); margin-bottom: 8px;">¿Cómo fue su experiencia?</h2>
                    <p style="color: var(--gris-600); font-size: 14px;">
                        Ayúdenos a mejorar calificando la atención recibida
                    </p>
                </div>
                
                <div style="background: var(--gris-50); padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                    <div style="font-size: 12px; color: var(--gris-500); text-transform: uppercase; margin-bottom: 4px;">Ticket</div>
                    <div style="font-weight: 600; color: var(--gris-900);"><?= htmlspecialchars($encuesta['numero']) ?></div>
                    <div style="font-size: 14px; color: var(--gris-600);"><?= htmlspecialchars($encuesta['asunto']) ?></div>
                </div>
                
                <?php if ($error): ?>
                <div class="alerta alerta-error" style="margin-bottom: 16px;">
                    <i class="bi bi-exclamation-circle"></i>
                    <?= $error ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="calificacion" id="calificacionInput" value="">
                    
                    <div class="estrellas">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi bi-star-fill estrella" data-valor="<?= $i ?>" onclick="seleccionarEstrella(<?= $i ?>)"></i>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="estrella-label">
                        <span>Muy insatisfecho</span>
                        <span>Muy satisfecho</span>
                    </div>
                    
                    <div class="form-grupo">
                        <label class="form-label">Comentario (opcional)</label>
                        <textarea name="comentario" class="form-control" rows="3" 
                                  placeholder="Cuéntenos más sobre su experiencia..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primario btn-bloque btn-lg">
                        Enviar Calificación
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer class="footer" style="position: fixed; bottom: 0; left: 0; right: 0;">
        <div class="contenedor">
            <div class="footer-contenido" style="justify-content: center;">
                <div>© <?= date('Y') ?> Municipalidad</div>
            </div>
        </div>
    </footer>

    <script>
    function seleccionarEstrella(valor) {
        document.getElementById('calificacionInput').value = valor;
        
        document.querySelectorAll('.estrella').forEach(function(estrella, index) {
            if (index < valor) {
                estrella.classList.add('activa');
            } else {
                estrella.classList.remove('activa');
            }
        });
    }
    </script>
</body>
</html>
