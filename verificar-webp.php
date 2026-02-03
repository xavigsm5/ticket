<?php
/**
 * Script de prueba para conversión de imágenes a WebP
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ImageHandler.php';

echo "<h1>Verificación del Sistema de Conversión a WebP</h1>";

// 1. Verificar extensión GD
echo "<h2>1. Verificación de Extensión GD</h2>";
if (extension_loaded('gd')) {
    echo "✅ Extensión GD está instalada<br>";
    $gd_info = gd_info();
    echo "<pre>" . print_r($gd_info, true) . "</pre>";
} else {
    echo "❌ Extensión GD NO está instalada<br>";
}

// 2. Verificar soporte WebP
echo "<h2>2. Verificación de Soporte WebP</h2>";
if (function_exists('imagewebp')) {
    echo "✅ imagewebp() está disponible<br>";
    
    if (function_exists('imagecreatefromwebp')) {
        echo "✅ imagecreatefromwebp() está disponible<br>";
    } else {
        echo "⚠️ imagecreatefromwebp() NO está disponible (solo lectura limitada)<br>";
    }
} else {
    echo "❌ imagewebp() NO está disponible<br>";
}

// 3. Verificar formatos de imagen soportados
echo "<h2>3. Formatos de Imagen Soportados</h2>";
$formatos = [
    'JPEG' => 'imagecreatefromjpeg',
    'PNG' => 'imagecreatefrompng',
    'GIF' => 'imagecreatefromgif',
    'BMP' => 'imagecreatefrombmp',
    'WebP' => 'imagecreatefromwebp'
];

foreach ($formatos as $nombre => $funcion) {
    if (function_exists($funcion)) {
        echo "✅ $nombre soportado ($funcion)<br>";
    } else {
        echo "❌ $nombre NO soportado ($funcion)<br>";
    }
}

// 4. Verificar tabla en base de datos
echo "<h2>4. Verificación de Base de Datos</h2>";
try {
    $db = Database::getInstance();
    
    // Verificar existencia de tabla
    $table_exists = $db->fetch(
        "SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = 'ticket_adjuntos'
        )"
    );
    
    if ($table_exists['exists']) {
        echo "✅ Tabla 'ticket_adjuntos' existe<br>";
        
        // Verificar estructura
        $columns = $db->fetchAll(
            "SELECT column_name, data_type, is_nullable 
             FROM information_schema.columns 
             WHERE table_name = 'ticket_adjuntos' 
             ORDER BY ordinal_position"
        );
        
        echo "<h3>Estructura de la tabla:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Columna</th><th>Tipo</th><th>Nullable</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($col['column_name']) . "</td>";
            echo "<td>" . htmlspecialchars($col['data_type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['is_nullable']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "❌ Tabla 'ticket_adjuntos' NO existe<br>";
        echo "<p>Ejecuta el script: database/update_adjuntos_bytea.sql</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error al verificar base de datos: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// 5. Resumen
echo "<h2>5. Resumen</h2>";
$todo_ok = extension_loaded('gd') && function_exists('imagewebp');
if ($todo_ok) {
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; color: #155724;'>";
    echo "✅ <strong>Sistema listo para convertir imágenes a WebP</strong><br>";
    echo "Las imágenes subidas por los funcionarios se convertirán automáticamente a WebP para reducir el tamaño.";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; color: #721c24;'>";
    echo "❌ <strong>Sistema NO está listo</strong><br>";
    echo "Debes instalar o habilitar la extensión GD con soporte WebP en PHP.";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='nuevo-ticket.php'>← Volver a crear ticket</a></p>";
