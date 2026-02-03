<?php
/**
 * Clase para manejar conversión de imágenes a WebP y almacenamiento
 */

class ImageHandler {
    
    /**
     * Tipos de imagen soportados para conversión a WebP
     */
    const SUPPORTED_IMAGE_TYPES = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/bmp',
        'image/webp'
    ];
    
    /**
     * Calidad de compresión WebP (0-100)
     */
    const WEBP_QUALITY = 85;
    
    /**
     * Tamaño máximo para imágenes en bytes (5MB)
     */
    const MAX_IMAGE_SIZE = 5 * 1024 * 1024;
    
    /**
     * Verifica si el tipo MIME es una imagen soportada
     */
    public static function esImagenSoportada($mime_type) {
        return in_array(strtolower($mime_type), self::SUPPORTED_IMAGE_TYPES);
    }
    
    /**
     * Convierte una imagen a formato WebP
     * 
     * @param string $ruta_temporal Ruta al archivo temporal subido
     * @param string $mime_type Tipo MIME de la imagen
     * @return array ['success' => bool, 'data' => string|null, 'error' => string|null]
     */
    public static function convertirAWebP($ruta_temporal, $mime_type) {
        try {
            // Verificar extensión GD y soporte WebP
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
    
    /**
     * Redimensiona una imagen si excede el tamaño máximo manteniendo la proporción
     * 
     * @param resource $imagen Recurso de imagen GD
     * @param int $max_ancho Ancho máximo en pixeles
     * @param int $max_alto Alto máximo en pixeles
     * @return resource Recurso de imagen redimensionada
     */
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
    
    /**
     * Procesa un archivo subido: convierte a WebP si es imagen, guarda en BD
     * 
     * @param int $ticket_id ID del ticket
     * @param int $usuario_id ID del usuario que sube el archivo
     * @param array $archivo Información del archivo de $_FILES
     * @return array ['success' => bool, 'id' => int|null, 'error' => string|null]
     */
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
            
            // Escapar el contenido binario para PostgreSQL
            $contenido_escapado = pg_escape_bytea($contenido);
            
            $resultado = $db->query(
                "INSERT INTO ticket_adjuntos 
                (ticket_id, usuario_id, nombre_original, tipo_mime, tamano, contenido, es_imagen, convertido_webp) 
                VALUES (?, ?, ?, ?, ?, decode(?, 'escape'), ?, ?) 
                RETURNING id",
                [
                    $ticket_id,
                    $usuario_id,
                    $nombre_original,
                    $mime_type,
                    $tamano_original,
                    $contenido_escapado,
                    $es_imagen,
                    $convertido_webp
                ]
            );
            
            if ($resultado && isset($resultado[0]['id'])) {
                return [
                    'success' => true,
                    'id' => $resultado[0]['id'],
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
    
    /**
     * Obtiene un adjunto de la base de datos
     * 
     * @param int $adjunto_id ID del adjunto
     * @return array|null Array con información del adjunto o null si no existe
     */
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
    
    /**
     * Obtiene adjuntos de un ticket
     * 
     * @param int $ticket_id ID del ticket
     * @return array Lista de adjuntos
     */
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
