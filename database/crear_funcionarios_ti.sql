-- ============================================
-- SCRIPT: CREAR FUNCIONARIOS ÁREA INFORMÁTICA
-- Ejecutar en PostgreSQL para crear los usuarios técnicos
-- ============================================

SET client_encoding = 'UTF8';

-- Contraseña para todos: soporte123
-- Hash bcrypt de 'soporte123'
-- Si quieres otra contraseña, genera el hash con PHP: password_hash('tu_password', PASSWORD_DEFAULT)

-- Primero verificar que el departamento de Informática exista
INSERT INTO departamentos (id, nombre, descripcion, email)
VALUES (1, 'Área de Informática', 'Departamento de Tecnologías de la Información', 'informatica@municipalidad.cl')
ON CONFLICT (id) DO NOTHING;

-- ============================================
-- USUARIOS TÉCNICOS DE INFORMÁTICA
-- ============================================

-- Jefe/Supervisor del Área de TI
INSERT INTO usuarios (rut, email, password, nombres, apellidos, rol, departamento_id, activo)
VALUES ('12345678-9', 'jefe.ti@municipalidad.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos', 'Mendoza', 'supervisor', 1, TRUE)
ON CONFLICT (email) DO UPDATE SET 
    nombres = EXCLUDED.nombres,
    apellidos = EXCLUDED.apellidos,
    rol = EXCLUDED.rol,
    activo = TRUE;

-- Encargado de REDES
INSERT INTO usuarios (rut, email, password, nombres, apellidos, rol, departamento_id, activo)
VALUES ('11111111-2', 'redes@municipalidad.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pedro', 'González', 'funcionario', 1, TRUE)
ON CONFLICT (email) DO UPDATE SET 
    nombres = EXCLUDED.nombres,
    apellidos = EXCLUDED.apellidos,
    rol = EXCLUDED.rol,
    activo = TRUE;

-- Encargado de SOPORTE TÉCNICO / SOFTWARE
INSERT INTO usuarios (rut, email, password, nombres, apellidos, rol, departamento_id, activo)
VALUES ('11111111-3', 'soporte@municipalidad.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'María', 'López', 'funcionario', 1, TRUE)
ON CONFLICT (email) DO UPDATE SET 
    nombres = EXCLUDED.nombres,
    apellidos = EXCLUDED.apellidos,
    rol = EXCLUDED.rol,
    activo = TRUE;

-- Encargado de SISTEMAS
INSERT INTO usuarios (rut, email, password, nombres, apellidos, rol, departamento_id, activo)
VALUES ('11111111-4', 'sistemas@municipalidad.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan', 'Pérez', 'funcionario', 1, TRUE)
ON CONFLICT (email) DO UPDATE SET 
    nombres = EXCLUDED.nombres,
    apellidos = EXCLUDED.apellidos,
    rol = EXCLUDED.rol,
    activo = TRUE;

-- Encargado de HARDWARE
INSERT INTO usuarios (rut, email, password, nombres, apellidos, rol, departamento_id, activo)
VALUES ('11111111-5', 'hardware@municipalidad.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana', 'Martínez', 'funcionario', 1, TRUE)
ON CONFLICT (email) DO UPDATE SET 
    nombres = EXCLUDED.nombres,
    apellidos = EXCLUDED.apellidos,
    rol = EXCLUDED.rol,
    activo = TRUE;

-- ============================================
-- TABLA DE ASIGNACIÓN AUTOMÁTICA POR CATEGORÍA
-- ============================================

CREATE TABLE IF NOT EXISTS categoria_asignacion (
    id SERIAL PRIMARY KEY,
    categoria_id INT NOT NULL REFERENCES categorias(id) ON DELETE CASCADE,
    usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    es_principal BOOLEAN DEFAULT TRUE,
    UNIQUE(categoria_id, usuario_id)
);

-- Limpiar asignaciones anteriores
DELETE FROM categoria_asignacion;

-- ============================================
-- ASIGNAR CATEGORÍAS A CADA TÉCNICO
-- ============================================

-- REDES: Categorías de red, internet, conectividad
INSERT INTO categoria_asignacion (categoria_id, usuario_id, es_principal)
SELECT c.id, u.id, TRUE
FROM categorias c, usuarios u
WHERE u.email = 'redes@municipalidad.cl'
  AND (c.nombre ILIKE '%red%' 
       OR c.nombre ILIKE '%internet%' 
       OR c.nombre ILIKE '%conectividad%' 
       OR c.nombre ILIKE '%wifi%'
       OR c.nombre ILIKE '%conexión%')
ON CONFLICT (categoria_id, usuario_id) DO NOTHING;

-- HARDWARE: Categorías de equipos físicos
INSERT INTO categoria_asignacion (categoria_id, usuario_id, es_principal)
SELECT c.id, u.id, TRUE
FROM categorias c, usuarios u
WHERE u.email = 'hardware@municipalidad.cl'
  AND (c.nombre ILIKE '%hardware%' 
       OR c.nombre ILIKE '%impresora%' 
       OR c.nombre ILIKE '%teclado%' 
       OR c.nombre ILIKE '%mouse%' 
       OR c.nombre ILIKE '%monitor%'
       OR c.nombre ILIKE '%pantalla%'
       OR c.nombre ILIKE '%equipamiento%')
ON CONFLICT (categoria_id, usuario_id) DO NOTHING;

-- SISTEMAS: Categorías de sistemas, cuentas, accesos
INSERT INTO categoria_asignacion (categoria_id, usuario_id, es_principal)
SELECT c.id, u.id, TRUE
FROM categorias c, usuarios u
WHERE u.email = 'sistemas@municipalidad.cl'
  AND (c.nombre ILIKE '%sistema%' 
       OR c.nombre ILIKE '%firma%' 
       OR c.nombre ILIKE '%correo%' 
       OR c.nombre ILIKE '%acceso%'
       OR c.nombre ILIKE '%permiso%'
       OR c.nombre ILIKE '%contraseña%'
       OR c.nombre ILIKE '%usuario%'
       OR c.nombre ILIKE '%cuenta%'
       OR c.nombre ILIKE '%desbloqueo%')
ON CONFLICT (categoria_id, usuario_id) DO NOTHING;

-- SOPORTE: Categorías de software y soporte general
INSERT INTO categoria_asignacion (categoria_id, usuario_id, es_principal)
SELECT c.id, u.id, TRUE
FROM categorias c, usuarios u
WHERE u.email = 'soporte@municipalidad.cl'
  AND (c.nombre ILIKE '%software%' 
       OR c.nombre ILIKE '%office%' 
       OR c.nombre ILIKE '%navegador%' 
       OR c.nombre ILIKE '%antivirus%'
       OR c.nombre ILIKE '%instalación%'
       OR c.nombre ILIKE '%soporte%'
       OR c.nombre ILIKE '%capacitación%'
       OR c.nombre ILIKE '%telefonía%'
       OR c.nombre ILIKE '%video%'
       OR c.nombre ILIKE '%otro%')
ON CONFLICT (categoria_id, usuario_id) DO NOTHING;

-- ============================================
-- VERIFICAR ASIGNACIONES
-- ============================================
SELECT 
    c.nombre as categoria,
    u.email as tecnico_asignado,
    CONCAT(u.nombres, ' ', u.apellidos) as nombre_tecnico
FROM categoria_asignacion ca
JOIN categorias c ON ca.categoria_id = c.id
JOIN usuarios u ON ca.usuario_id = u.id
ORDER BY u.email, c.nombre;

-- ============================================
-- RESUMEN DE USUARIOS CREADOS
-- ============================================
SELECT 
    email,
    CONCAT(nombres, ' ', apellidos) as nombre,
    rol,
    CASE 
        WHEN email = 'jefe.ti@municipalidad.cl' THEN 'Supervisor - Ve todos los tickets'
        WHEN email = 'redes@municipalidad.cl' THEN 'Técnico Redes - Solo tickets de red'
        WHEN email = 'hardware@municipalidad.cl' THEN 'Técnico Hardware - Solo tickets de equipos'
        WHEN email = 'sistemas@municipalidad.cl' THEN 'Técnico Sistemas - Solo tickets de sistemas/cuentas'
        WHEN email = 'soporte@municipalidad.cl' THEN 'Técnico Soporte - Solo tickets de software'
    END as descripcion
FROM usuarios 
WHERE departamento_id = 1
ORDER BY rol DESC, email;

-- ============================================
-- CREDENCIALES DE ACCESO
-- ============================================
/*
+---------------------------+------------------+--------------+
| Email                     | Contraseña       | Rol          |
+---------------------------+------------------+--------------+
| jefe.ti@municipalidad.cl  | soporte123       | Supervisor   |
| redes@municipalidad.cl    | soporte123       | Funcionario  |
| hardware@municipalidad.cl | soporte123       | Funcionario  |
| sistemas@municipalidad.cl | soporte123       | Funcionario  |
| soporte@municipalidad.cl  | soporte123       | Funcionario  |
+---------------------------+------------------+--------------+

Nota: El admin principal sigue siendo admin@municipalidad.cl con password admin123
*/
