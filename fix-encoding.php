<?php
/**
 * Script para corregir caracteres corruptos en la base de datos
 * Los ?? son caracteres UTF-8 que se guardaron incorrectamente
 * 
 * Ejecutar una sola vez: http://localhost/fix-encoding.php
 */

// Solo permitir en localhost
if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    die('Este script solo está disponible en localhost');
}

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Corregir Encoding</title></head><body>";
echo "<h1>Corrección de Caracteres en Base de Datos</h1>";

try {
    $db = Database::getInstance();

    // Correcciones de patrones ?? a caracteres correctos
    $correcciones = [
        // Vocales con tilde
        '??n' => 'ón',      // Capacitación, Configuración, Instalación, Conexión, Gestión
        '??a' => 'ía',      // Telefonía, Contraseña (a veces)
        '??s' => 'ás',      // físicos
        '??c' => 'éc',      // técnico
        '??' => 'ó',        // Olvidó
        'Electr??nico' => 'Electrónico',
        'Electr??nica' => 'Electrónica',
        'tecnol??gicas' => 'tecnológicas',
        'perif??ricos' => 'periféricos',
        'tel??fonos' => 'teléfonos',
        'f??sicos' => 'físicos',
        't??cnico' => 'técnico',
        'est??' => 'está',
        'Olvid??' => 'Olvidé',
    ];

    // Correcciones específicas para nombres completos
    $correcciones_nombres = [
        'Capacitaci??n' => 'Capacitación',
        'Configuraci??n' => 'Configuración',
        'Correo Electr??nico' => 'Correo Electrónico',
        'Creaci??n' => 'Creación',
        'Firma Electr??nica' => 'Firma Electrónica',
        'Instalaci??n' => 'Instalación',
        'Contrase??a' => 'Contraseña',
        'Conexi??n' => 'Conexión',
        'Gesti??n' => 'Gestión',
        'Telefon??a' => 'Telefonía',
        'actualizaci??n' => 'actualización',
        'configuraci??n' => 'configuración',
        'instalaci??n' => 'instalación',
        'conexi??n' => 'conexión',
        // Apellidos comunes
        'Mart??nez' => 'Martínez',
        'P??rez' => 'Pérez',
        'Gonz??lez' => 'González',
        'L??pez' => 'López',
        'S??nchez' => 'Sánchez',
        'Ram??rez' => 'Ramírez',
        'Garc??a' => 'García',
        'Fern??ndez' => 'Fernández',
        'Rodr??guez' => 'Rodríguez',
        'Jim??nez' => 'Jiménez',
        'Hern??ndez' => 'Hernández',
        'D??az' => 'Díaz',
        'N????ez' => 'Núñez',
        'Nu??ez' => 'Núñez',
        'Mu??oz' => 'Muñoz',
        'Mar??a' => 'María',
    ];

    $total_actualizados = 0;

    // ============ CATEGORÍAS ============
    echo "<h2>1. Corrigiendo Categorías (nombre y descripción)...</h2>";

    // Primero las correcciones específicas
    foreach ($correcciones_nombres as $incorrecto => $correcto) {
        // Nombre
        $result = $db->query(
            "UPDATE categorias SET nombre = REPLACE(nombre, ?, ?) WHERE nombre LIKE ?",
            [$incorrecto, $correcto, '%' . $incorrecto . '%']
        );
        $count = $result->rowCount();
        if ($count > 0) {
            echo "✓ Nombre: '$incorrecto' → '$correcto' ($count)<br>";
            $total_actualizados += $count;
        }

        // Descripción
        $result = $db->query(
            "UPDATE categorias SET descripcion = REPLACE(descripcion, ?, ?) WHERE descripcion LIKE ?",
            [$incorrecto, $correcto, '%' . $incorrecto . '%']
        );
        $count = $result->rowCount();
        if ($count > 0) {
            echo "✓ Descripción: '$incorrecto' → '$correcto' ($count)<br>";
            $total_actualizados += $count;
        }
    }

    // Luego las correcciones generales
    foreach ($correcciones as $incorrecto => $correcto) {
        $result = $db->query(
            "UPDATE categorias SET nombre = REPLACE(nombre, ?, ?) WHERE nombre LIKE ?",
            [$incorrecto, $correcto, '%' . $incorrecto . '%']
        );
        $count = $result->rowCount();
        if ($count > 0) {
            echo "✓ Nombre: '$incorrecto' → '$correcto' ($count)<br>";
            $total_actualizados += $count;
        }

        $result = $db->query(
            "UPDATE categorias SET descripcion = REPLACE(descripcion, ?, ?) WHERE descripcion LIKE ?",
            [$incorrecto, $correcto, '%' . $incorrecto . '%']
        );
        $count = $result->rowCount();
        if ($count > 0) {
            echo "✓ Descripción: '$incorrecto' → '$correcto' ($count)<br>";
            $total_actualizados += $count;
        }
    }

    // ============ USUARIOS ============
    echo "<h2>2. Corrigiendo Usuarios...</h2>";
    foreach ($correcciones_nombres as $incorrecto => $correcto) {
        $result = $db->query(
            "UPDATE usuarios SET nombres = REPLACE(nombres, ?, ?) WHERE nombres LIKE ?",
            [$incorrecto, $correcto, '%' . $incorrecto . '%']
        );
        $count = $result->rowCount();
        if ($count > 0) {
            echo "✓ Nombres: '$incorrecto' → '$correcto' ($count)<br>";
            $total_actualizados += $count;
        }

        $result = $db->query(
            "UPDATE usuarios SET apellidos = REPLACE(apellidos, ?, ?) WHERE apellidos LIKE ?",
            [$incorrecto, $correcto, '%' . $incorrecto . '%']
        );
        $count = $result->rowCount();
        if ($count > 0) {
            echo "✓ Apellidos: '$incorrecto' → '$correcto' ($count)<br>";
            $total_actualizados += $count;
        }
    }

    // ============ DEPARTAMENTOS ============
    echo "<h2>3. Corrigiendo Departamentos...</h2>";
    foreach ($correcciones_nombres as $incorrecto => $correcto) {
        $result = $db->query(
            "UPDATE departamentos SET nombre = REPLACE(nombre, ?, ?) WHERE nombre LIKE ?",
            [$incorrecto, $correcto, '%' . $incorrecto . '%']
        );
        $count = $result->rowCount();
        if ($count > 0) {
            echo "✓ '$incorrecto' → '$correcto' ($count)<br>";
            $total_actualizados += $count;
        }
    }

    // ============ RESULTADO ============
    echo "<hr><h2>✅ Resultado Final</h2>";
    echo "<p><strong>Total de registros actualizados:</strong> $total_actualizados</p>";

    // Mostrar categorías actuales
    echo "<h3>Categorías corregidas:</h3>";
    $categorias = $db->fetchAll("SELECT nombre, descripcion FROM categorias ORDER BY nombre");
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
    echo "<tr><th>Nombre</th><th>Descripción</th></tr>";
    foreach ($categorias as $cat) {
        echo "<tr><td>" . htmlspecialchars($cat['nombre']) . "</td><td>" . htmlspecialchars($cat['descripcion'] ?? '') . "</td></tr>";
    }
    echo "</table>";

    // Mostrar funcionarios
    echo "<h3>Funcionarios corregidos:</h3>";
    $usuarios = $db->fetchAll("SELECT nombres, apellidos FROM usuarios WHERE rol IN ('funcionario', 'supervisor', 'admin') ORDER BY nombres LIMIT 20");
    echo "<ul>";
    foreach ($usuarios as $u) {
        echo "<li>" . htmlspecialchars($u['nombres'] . ' ' . $u['apellidos']) . "</li>";
    }
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='/'>Ir al Inicio</a> | <a href='/nuevo-ticket.php'>Nueva Solicitud</a></p>";
echo "</body></html>";
?>