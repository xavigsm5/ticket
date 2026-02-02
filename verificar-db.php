<?php
/**
 * Script de Verificación de Base de Datos
 * Revisa todas las tablas y muestra su estado
 * 
 * Ejecutar: http://localhost/verificar-db.php
 */

// Solo permitir en localhost
if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    die('Este script solo está disponible en localhost');
}

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Verificación de Base de Datos</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    h1 { color: #1a365d; }
    h2 { color: #2c5282; margin-top: 30px; }
    .tabla-info { background: white; border-radius: 8px; padding: 20px; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .tabla-nombre { font-weight: bold; color: #1a365d; font-size: 18px; }
    .tabla-count { color: #48bb78; font-weight: bold; }
    .tabla-vacia { color: #e53e3e; }
    .ok { color: #48bb78; }
    .error { color: #e53e3e; }
    .warning { color: #ed8936; }
    table { border-collapse: collapse; width: 100%; margin-top: 10px; font-size: 13px; }
    th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
    th { background: #edf2f7; }
    .resumen { background: #1a365d; color: white; padding: 20px; border-radius: 8px; margin-top: 30px; }
</style></head><body>";

echo "<h1>🔍 Verificación de Base de Datos - Sistema de Tickets</h1>";
echo "<p>Fecha: " . date('Y-m-d H:i:s') . "</p>";

try {
    $db = Database::getInstance();

    // Obtener todas las tablas
    $tablas = $db->fetchAll("
        SELECT tablename 
        FROM pg_tables 
        WHERE schemaname = 'public' 
        ORDER BY tablename
    ");

    echo "<h2>📊 Resumen de Tablas</h2>";

    $total_tablas = count($tablas);
    $tablas_con_datos = 0;
    $tablas_vacias = 0;
    $detalles = [];

    foreach ($tablas as $tabla) {
        $nombre = $tabla['tablename'];

        // Contar registros
        $count = $db->fetch("SELECT COUNT(*) as total FROM " . $nombre);
        $total = $count['total'];

        // Obtener columnas
        $columnas = $db->fetchAll("
            SELECT column_name, data_type, is_nullable
            FROM information_schema.columns 
            WHERE table_name = ?
            ORDER BY ordinal_position
        ", [$nombre]);

        // Obtener muestra de datos si hay registros
        $muestra = [];
        if ($total > 0) {
            $muestra = $db->fetchAll("SELECT * FROM " . $nombre . " LIMIT 3");
            $tablas_con_datos++;
        } else {
            $tablas_vacias++;
        }

        $detalles[] = [
            'nombre' => $nombre,
            'total' => $total,
            'columnas' => $columnas,
            'muestra' => $muestra
        ];
    }

    // Mostrar resumen
    echo "<div class='resumen'>";
    echo "<h3>📈 Estadísticas Generales</h3>";
    echo "<p><strong>Total de tablas:</strong> $total_tablas</p>";
    echo "<p><strong>Tablas con datos:</strong> <span class='ok'>$tablas_con_datos</span></p>";
    echo "<p><strong>Tablas vacías:</strong> <span class='" . ($tablas_vacias > 0 ? "warning" : "ok") . "'>$tablas_vacias</span></p>";
    echo "</div>";

    // Mostrar cada tabla
    echo "<h2>📋 Detalle por Tabla</h2>";

    foreach ($detalles as $detalle) {
        $nombre = $detalle['nombre'];
        $total = $detalle['total'];
        $columnas = $detalle['columnas'];
        $muestra = $detalle['muestra'];

        echo "<div class='tabla-info'>";
        echo "<div class='tabla-nombre'>📁 " . strtoupper($nombre) . "</div>";

        if ($total > 0) {
            echo "<p class='tabla-count'>✅ $total registro(s)</p>";
        } else {
            echo "<p class='tabla-vacia'>⚠️ Tabla vacía</p>";
        }

        // Mostrar columnas
        echo "<details><summary>Ver columnas (" . count($columnas) . ")</summary>";
        echo "<table><tr><th>Columna</th><th>Tipo</th><th>Nullable</th></tr>";
        foreach ($columnas as $col) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($col['column_name']) . "</td>";
            echo "<td>" . htmlspecialchars($col['data_type']) . "</td>";
            echo "<td>" . ($col['is_nullable'] == 'YES' ? 'Sí' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table></details>";

        // Mostrar muestra de datos
        if (!empty($muestra)) {
            echo "<details><summary>Ver muestra de datos (máx 3)</summary>";
            echo "<table><tr>";
            $keys = array_keys($muestra[0]);
            foreach ($keys as $key) {
                echo "<th>" . htmlspecialchars($key) . "</th>";
            }
            echo "</tr>";
            foreach ($muestra as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    $display = is_null($value) ? '<em>NULL</em>' : htmlspecialchars(substr((string) $value, 0, 50));
                    if (strlen((string) $value) > 50)
                        $display .= '...';
                    echo "<td>" . $display . "</td>";
                }
                echo "</tr>";
            }
            echo "</table></details>";
        }

        echo "</div>";
    }

    // Verificaciones específicas del sistema
    echo "<h2>🔧 Verificaciones Específicas</h2>";

    // Verificar usuarios
    echo "<div class='tabla-info'>";
    echo "<h3>👤 Usuarios por Rol</h3>";
    $roles = $db->fetchAll("SELECT rol, COUNT(*) as total FROM usuarios GROUP BY rol ORDER BY total DESC");
    echo "<table><tr><th>Rol</th><th>Cantidad</th></tr>";
    foreach ($roles as $r) {
        echo "<tr><td>" . htmlspecialchars($r['rol']) . "</td><td>" . $r['total'] . "</td></tr>";
    }
    echo "</table></div>";

    // Verificar tickets
    $tickets_count = $db->fetch("SELECT COUNT(*) as total FROM tickets");
    if ($tickets_count['total'] > 0) {
        echo "<div class='tabla-info'>";
        echo "<h3>🎫 Tickets por Estado</h3>";
        $estados = $db->fetchAll("
            SELECT e.nombre as estado, COUNT(t.id) as total 
            FROM estados e 
            LEFT JOIN tickets t ON t.estado_id = e.id 
            GROUP BY e.id, e.nombre 
            ORDER BY e.id
        ");
        echo "<table><tr><th>Estado</th><th>Cantidad</th></tr>";
        foreach ($estados as $e) {
            echo "<tr><td>" . htmlspecialchars($e['estado']) . "</td><td>" . $e['total'] . "</td></tr>";
        }
        echo "</table></div>";
    }

    // Verificar categorías
    echo "<div class='tabla-info'>";
    echo "<h3>📂 Categorías Activas</h3>";
    $cats = $db->fetchAll("SELECT nombre FROM categorias WHERE activo = true ORDER BY nombre");
    echo "<p>Total: " . count($cats) . " categorías activas</p>";
    echo "<ul style='column-count: 3; font-size: 12px;'>";
    foreach ($cats as $c) {
        echo "<li>" . htmlspecialchars($c['nombre']) . "</li>";
    }
    echo "</ul></div>";

} catch (Exception $e) {
    echo "<p class='error'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='/'>Volver al Inicio</a> | <a href='/admin/dashboard.php'>Dashboard Admin</a></p>";
echo "</body></html>";
?>