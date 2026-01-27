<?php
/**
 * Script de Reparación de Codificación UTF-8
 * Ejecutar manualmente si hay problemas de caracteres
 * 
 * Uso: http://localhost/repair-encoding.php
 */

// Solo permitir en desarrollo
if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    die('Este script solo está disponible en localhost');
}

require_once __DIR__ . '/config/database.php';

echo "<h1>Reparación de Codificación UTF-8</h1>";

try {
    $db = Database::getInstance();
    
    // Verificar encoding actual
    echo "<h2>1. Verificando encoding de la base de datos...</h2>";
    $result = $db->fetch("SHOW client_encoding");
    echo "Client encoding: " . ($result ? implode(', ', $result) : 'error') . "<br>";
    
    $result = $db->fetch("SELECT datname, encoding, encoding_name FROM pg_database WHERE datname = current_database()");
    if ($result) {
        echo "Database encoding: " . $result['encoding_name'] . "<br>";
    }
    
    // Configurar UTF-8 para la sesión
    echo "<h2>2. Configurando UTF-8...</h2>";
    $db->query("SET client_encoding = 'UTF8'");
    echo "✓ Client encoding establecido a UTF-8<br>";
    
    // Verificar categorías
    echo "<h2>3. Categorías en la base de datos:</h2>";
    $categorias = $db->fetchAll("SELECT id, nombre FROM categorias ORDER BY nombre");
    
    foreach ($categorias as $cat) {
        echo htmlspecialchars($cat['id'] . ' - ' . $cat['nombre']) . "<br>";
    }
    
    echo "<p><strong>Total de categorías:</strong> " . count($categorias) . "</p>";
    
    echo "<h2>4. Verificación completada</h2>";
    echo "<p>Si los caracteres especiales (á, é, í, ó, ú, ñ, etc.) se ven correctamente arriba, el encoding está funcionando bien.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='/nuevo-ticket.php'>Volver a Nueva Solicitud</a></p>";
?>
