<?php
/**
 * Descargar adjuntos desde la base de datos
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ImageHandler.php';

// Verificar que se proporcione el ID del adjunto
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('ID de adjunto no válido');
}

$adjunto_id = (int)$_GET['id'];

// Obtener el adjunto
$adjunto = ImageHandler::obtenerAdjunto($adjunto_id);

if (!$adjunto) {
    http_response_code(404);
    die('Adjunto no encontrado');
}

// Verificar permisos (opcional - ajustar según tu lógica de autenticación)
// TODO: Agregar verificación de que el usuario tiene acceso al ticket

// Decodificar el contenido base64
$contenido = base64_decode($adjunto['contenido_base64']);

// Limpiar cualquier salida previa
if (ob_get_level()) {
    ob_end_clean();
}

// Configurar headers para la descarga
header('Content-Type: ' . $adjunto['tipo_mime']);
header('Content-Length: ' . strlen($contenido));

// Si se solicita descarga (download=1), forzar descarga, sino mostrar inline
if (isset($_GET['download']) && $_GET['download'] == '1') {
    header('Content-Disposition: attachment; filename="' . $adjunto['nombre_original'] . '"');
} else {
    // Para imágenes WebP, asegurarse de que se muestren correctamente
    if ($adjunto['es_imagen']) {
        $extension = $adjunto['convertido_webp'] ? '.webp' : pathinfo($adjunto['nombre_original'], PATHINFO_EXTENSION);
        header('Content-Disposition: inline; filename="' . pathinfo($adjunto['nombre_original'], PATHINFO_FILENAME) . $extension . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $adjunto['nombre_original'] . '"');
    }
}

// Evitar caché para archivos privados
header('Cache-Control: private, max-age=0, no-cache');
header('Pragma: no-cache');

// Enviar el contenido
echo $contenido;
exit;
