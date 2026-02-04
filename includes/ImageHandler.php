<?php
/**
 * Manejo de imágenes y conversión a WebP
 */

class ImageHandler {
    
    // Formatos de imagen soportados
    const SUPPORTED_IMAGE_TYPES = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/bmp',
        'image/webp'
    ];
    
    // Calidad WebP (0-100)
    const WEBP_QUALITY = 85;
    
    // Máximo 5MB por imagen
    const MAX_IMAGE_SIZE = 5 * 1024 * 1024;
    
    // Valida si el MIME es imagen soportada
    public static function esImagenSoportada($mime_type) {
        return in_array(strtolower($mime_type), self::SUPPORTED_IMAGE_TYPES);
    }
    
    // Convierte imagen a WebP
    public static function convertirAWebP($ruta_temporal, $mime_type) {
        try {
            if (!extension_loaded('gd')) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Extensión GD no está instalada'
                ];
            }
            
            if (!function_exists('imagewebp')) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Soporte WebP no disponible en GD'
                ];
            }
            
            // Crear recurso de imagen según el tipo
            $imagen = null;
            switch (strtolower($mime_type)) {
                case 'image/jpeg':
                case 'image/jpg':
                    $imagen = @imagecreatefromjpeg($ruta_temporal);
                    break;
                case 'image/png':
                    $imagen = @imagecreatefrompng($ruta_temporal);
                    // Preservar transparencia
                    if ($imagen) {
                        imagealphablending($imagen, false);
                        imagesavealpha($imagen, true);
                    }
                    break;
                case 'image/gif':
                    $imagen = @imagecreatefromgif($ruta_temporal);
                    break;
                case 'image/bmp':
                    $imagen = @imagecreatefrombmp($ruta_temporal);
                    break;
                case 'image/webp':
                    $imagen = @imagecreatefromwebp($ruta_temporal);
                    break;
                default:
                    return [
                        'success' => false,
                        'data' => null,
                        'error' => 'Tipo de imagen no soportado'
                    ];
            }
            
            if (!$imagen) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'No se pudo cargar la imagen'
                ];
            }
            
            // Convertir a WebP en buffer de salida
            ob_start();
            imagewebp($imagen, null, self::WEBP_QUALITY);
            $webp_data = ob_get_clean();
            
            // Liberar memoria
            imagedestroy($imagen);
            
            return [
                'success' => true,
                'data' => $webp_data,
                'error' => null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Error al convertir imagen: ' . $e->getMessage()
            ];
        }
    }
    
    // Redimensiona imagen si es muy grande
    public static function redimensionarImagen($imagen, $max_ancho = 1920, $max_alto = 1080) {
        $ancho_actual = imagesx($imagen);
        $alto_actual = imagesy($imagen);
        
        // Si la imagen es más pequeña que los límites, retornar sin cambios
        if ($ancho_actual <= $max_ancho && $alto_actual <= $max_alto) {
            return $imagen;
        }
        
        // Calcular nuevas dimensiones manteniendo proporción
        $ratio = min($max_ancho / $ancho_actual, $max_alto / $alto_actual);
        $nuevo_ancho = round($ancho_actual * $ratio);
        $nuevo_alto = round($alto_actual * $ratio);
        
        // Crear nueva imagen redimensionada
        $imagen_nueva = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
        
        // Preservar transparencia para PNG
        imagealphablending($imagen_nueva, false);
        imagesavealpha($imagen_nueva, true);
        $transparente = imagecolorallocatealpha($imagen_nueva, 0, 0, 0, 127);
        imagefill($imagen_nueva, 0, 0, $transparente);
        
        // Redimensionar
        imagecopyresampled(
            $imagen_nueva, $imagen,
            0, 0, 0, 0,
            $nuevo_ancho, $nuevo_alto,
            $ancho_actual, $alto_actual
        );
        
        return $imagen_nueva;
    }
    
    // Procesa archivo subido y guarda en BD (convierte a WebP si es imagen)
    public static function procesarYGuardarAdjunto($ticket_id, $usuario_id, $archivo) {
        $nombre_original = basename($archivo['name']);
        $mime_type = $archivo['type'];
        $tamano_original = $archivo['size'];
        $ruta_temporal = $archivo['tmp_name'];
        
        $es_imagen = self::esImagenSoportada($mime_type);
        $convertido_webp = false;
        $contenido = null;
        
        // Si es una imagen, convertir a WebP
        if ($es_imagen && $tamano_original <= self::MAX_IMAGE_SIZE) {
            $resultado = self::convertirAWebP($ruta_temporal, $mime_type);
            
            if ($resultado['success']) {
                $contenido = $resultado['data'];
                $mime_type = 'image/webp';
                $tamano_original = strlen($contenido);
                $convertido_webp = true;
            } else {
                // Si falla la conversión, guardar archivo original
                $contenido = file_get_contents($ruta_temporal);
            }
        } else {
            // No es imagen o es muy grande, guardar tal cual
            $contenido = file_get_contents($ruta_temporal);
        }
        
        if (!$contenido) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'No se pudo leer el contenido del archivo'
            ];
        }
        
        // Guardar en base de datos
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            // Usar PDO para manejar datos binarios correctamente
            $stmt = $pdo->prepare(
                "INSERT INTO ticket_adjuntos 
                (ticket_id, usuario_id, nombre_original, tipo_mime, tamano, contenido, es_imagen, convertido_webp) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                RETURNING id"
            );
            
            $stmt->bindValue(1, $ticket_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $usuario_id, PDO::PARAM_INT);
            $stmt->bindValue(3, $nombre_original, PDO::PARAM_STR);
            $stmt->bindValue(4, $mime_type, PDO::PARAM_STR);
            $stmt->bindValue(5, $tamano_original, PDO::PARAM_INT);
            $stmt->bindValue(6, $contenido, PDO::PARAM_LOB);
            $stmt->bindValue(7, $es_imagen, PDO::PARAM_BOOL);
            $stmt->bindValue(8, $convertido_webp, PDO::PARAM_BOOL);
            
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado && isset($resultado['id'])) {
                return [
                    'success' => true,
                    'id' => $resultado['id'],
                    'error' => null,
                    'convertido_webp' => $convertido_webp,
                    'tamano_final' => $tamano_original
                ];
            } else {
                return [
                    'success' => false,
                    'id' => null,
                    'error' => 'Error al insertar en base de datos'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Error al guardar: ' . $e->getMessage()
            ];
        }
    }
    
    // Obtener adjunto por ID
    public static function obtenerAdjunto($adjunto_id) {
        $db = Database::getInstance();
        
        $adjunto = $db->fetch(
            "SELECT id, ticket_id, usuario_id, nombre_original, tipo_mime, tamano, 
                    encode(contenido, 'base64') as contenido_base64, 
                    es_imagen, convertido_webp, created_at
             FROM ticket_adjuntos 
             WHERE id = ?",
            [$adjunto_id]
        );
        
        return $adjunto;
    }
    
    // Listar adjuntos de un ticket
    public static function obtenerAdjuntosTicket($ticket_id) {
        $db = Database::getInstance();
        
        $adjuntos = $db->fetchAll(
            "SELECT id, usuario_id, nombre_original, tipo_mime, tamano, 
                    es_imagen, convertido_webp, created_at
             FROM ticket_adjuntos 
             WHERE ticket_id = ?
             ORDER BY created_at ASC",
            [$ticket_id]
        );
        
        return $adjuntos ?: [];
    }
}
