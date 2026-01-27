<?php
/**
 * Script para crear funcionarios de TI
 * Ejecutar UNA VEZ desde el navegador: http://localhost/setup-funcionarios.php
 * ELIMINAR DESPUÉS DE USAR
 */

require_once __DIR__ . '/config/database.php';

echo "<h1>Creando Funcionarios de TI</h1>";
echo "<pre>";

try {
    $db = Database::getInstance();
    
    // Contraseña: soporte123
    $password_hash = password_hash('soporte123', PASSWORD_DEFAULT);
    echo "Hash generado para 'soporte123': $password_hash\n\n";
    
    // Contraseña admin: admin123
    $admin_hash = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Verificar/Crear departamento de Informática
    $dept = $db->fetch("SELECT id FROM departamentos WHERE nombre ILIKE '%informática%' OR nombre ILIKE '%informatica%' LIMIT 1");
    
    if (!$dept) {
        $db->query("INSERT INTO departamentos (nombre, descripcion, email) VALUES ('Área de Informática', 'Departamento de Tecnologías de la Información', 'informatica@municipalidad.cl')");
        $dept = $db->fetch("SELECT id FROM departamentos ORDER BY id DESC LIMIT 1");
        echo "✓ Departamento de Informática creado\n";
    } else {
        echo "✓ Departamento de Informática ya existe (ID: {$dept['id']})\n";
    }
    $dept_id = $dept['id'];
    
    // Lista de usuarios a crear
    $usuarios = [
        ['11111111-1', 'admin@municipalidad.cl', $admin_hash, 'Administrador', 'Sistema', 'admin', $dept_id],
        ['12345678-9', 'jefe.ti@municipalidad.cl', $password_hash, 'Carlos', 'Mendoza', 'supervisor', $dept_id],
        ['11111111-2', 'redes@municipalidad.cl', $password_hash, 'Pedro', 'González', 'funcionario', $dept_id],
        ['11111111-3', 'soporte@municipalidad.cl', $password_hash, 'María', 'López', 'funcionario', $dept_id],
        ['11111111-4', 'sistemas@municipalidad.cl', $password_hash, 'Juan', 'Pérez', 'funcionario', $dept_id],
        ['11111111-5', 'hardware@municipalidad.cl', $password_hash, 'Ana', 'Martínez', 'funcionario', $dept_id],
    ];
    
    echo "\n--- Creando/Actualizando usuarios ---\n";
    
    foreach ($usuarios as $u) {
        $existe = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$u[1]]);
        
        if ($existe) {
            // Actualizar contraseña y activar
            $db->query("UPDATE usuarios SET password = ?, activo = TRUE WHERE email = ?", [$u[2], $u[1]]);
            echo "✓ Usuario {$u[1]} actualizado (contraseña renovada)\n";
        } else {
            // Crear nuevo
            $db->query(
                "INSERT INTO usuarios (rut, email, password, nombres, apellidos, rol, departamento_id, activo) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)",
                [$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], $u[6]]
            );
            echo "✓ Usuario {$u[1]} creado\n";
        }
    }
    
    // Crear tabla categoria_asignacion si no existe
    echo "\n--- Configurando asignaciones automáticas ---\n";
    
    $db->query("CREATE TABLE IF NOT EXISTS categoria_asignacion (
        id SERIAL PRIMARY KEY,
        categoria_id INT NOT NULL REFERENCES categorias(id) ON DELETE CASCADE,
        usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
        es_principal BOOLEAN DEFAULT TRUE,
        UNIQUE(categoria_id, usuario_id)
    )");
    echo "✓ Tabla categoria_asignacion verificada\n";
    
    // Limpiar asignaciones anteriores
    $db->query("DELETE FROM categoria_asignacion");
    
    // Obtener IDs de usuarios
    $redes_id = $db->fetch("SELECT id FROM usuarios WHERE email = 'redes@municipalidad.cl'")['id'] ?? null;
    $hardware_id = $db->fetch("SELECT id FROM usuarios WHERE email = 'hardware@municipalidad.cl'")['id'] ?? null;
    $sistemas_id = $db->fetch("SELECT id FROM usuarios WHERE email = 'sistemas@municipalidad.cl'")['id'] ?? null;
    $soporte_id = $db->fetch("SELECT id FROM usuarios WHERE email = 'soporte@municipalidad.cl'")['id'] ?? null;
    
    // Asignar categorías
    $categorias = $db->fetchAll("SELECT id, nombre FROM categorias");
    
    foreach ($categorias as $cat) {
        $nombre = strtolower($cat['nombre']);
        $tecnico_id = null;
        
        // Determinar a quién asignar según el nombre de la categoría
        if (preg_match('/red|internet|conectividad|wifi|conexión/', $nombre)) {
            $tecnico_id = $redes_id;
        } elseif (preg_match('/hardware|impresora|teclado|mouse|monitor|pantalla|equipamiento/', $nombre)) {
            $tecnico_id = $hardware_id;
        } elseif (preg_match('/sistema|firma|correo|acceso|permiso|contraseña|usuario|cuenta|desbloqueo/', $nombre)) {
            $tecnico_id = $sistemas_id;
        } elseif (preg_match('/software|office|navegador|antivirus|instalación|soporte|capacitación|telefonía|video|otro/', $nombre)) {
            $tecnico_id = $soporte_id;
        }
        
        if ($tecnico_id) {
            $db->query(
                "INSERT INTO categoria_asignacion (categoria_id, usuario_id, es_principal) VALUES (?, ?, TRUE) ON CONFLICT DO NOTHING",
                [$cat['id'], $tecnico_id]
            );
            echo "  - '{$cat['nombre']}' → Técnico ID: $tecnico_id\n";
        }
    }
    
    echo "\n</pre>";
    echo "<h2 style='color: green;'>✅ Configuración completada</h2>";
    
    echo "<h3>Credenciales de acceso:</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Email</th><th>Contraseña</th><th>Rol</th></tr>";
    echo "<tr><td>admin@municipalidad.cl</td><td><strong>admin123</strong></td><td>Admin</td></tr>";
    echo "<tr><td>jefe.ti@municipalidad.cl</td><td><strong>soporte123</strong></td><td>Supervisor</td></tr>";
    echo "<tr><td>redes@municipalidad.cl</td><td><strong>soporte123</strong></td><td>Funcionario (Redes)</td></tr>";
    echo "<tr><td>hardware@municipalidad.cl</td><td><strong>soporte123</strong></td><td>Funcionario (Hardware)</td></tr>";
    echo "<tr><td>sistemas@municipalidad.cl</td><td><strong>soporte123</strong></td><td>Funcionario (Sistemas)</td></tr>";
    echo "<tr><td>soporte@municipalidad.cl</td><td><strong>soporte123</strong></td><td>Funcionario (Software)</td></tr>";
    echo "</table>";
    
    echo "<br><br><a href='/login.php' style='font-size: 18px;'>Ir al Login →</a>";
    echo "<br><br><strong style='color: red;'>⚠️ IMPORTANTE: Elimina este archivo (setup-funcionarios.php) después de usarlo</strong>";
    
} catch (Exception $e) {
    echo "\n<strong style='color: red;'>ERROR:</strong> " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
