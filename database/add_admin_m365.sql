-- ============================================
-- Script para configurar roles de administradores M365
-- Mario Pérez = admin (puede asignar tickets)
-- Cristian Beltrand = supervisor (puede asignar tickets)
-- Los demás = soporte_ti (solo ven tickets de su área)
-- ============================================

-- Asegurar que el CHECK constraint incluya soporte_ti
ALTER TABLE usuarios DROP CONSTRAINT IF EXISTS usuarios_rol_check;
ALTER TABLE usuarios ADD CONSTRAINT usuarios_rol_check 
    CHECK (rol IN ('ciudadano', 'funcionario', 'supervisor', 'admin', 'soporte_ti'));

-- Mario Pérez = Admin
UPDATE usuarios 
SET rol = 'admin',
    updated_at = CURRENT_TIMESTAMP
WHERE LOWER(email) = 'mario.perez@quintanormal.cl'
  AND activo = TRUE;

-- Cristian Beltrand = Supervisor
UPDATE usuarios 
SET rol = 'supervisor',
    updated_at = CURRENT_TIMESTAMP
WHERE LOWER(email) = 'cristian.beltrand@quintanormal.cl'
  AND activo = TRUE;

-- Verificar los cambios
SELECT id, email, nombres, apellidos, rol, departamento_id, activo 
FROM usuarios 
WHERE LOWER(email) IN ('mario.perez@quintanormal.cl', 'cristian.beltrand@quintanormal.cl');

-- Mostrar todos los administradores y supervisores del sistema
SELECT id, email, nombres, apellidos, rol, departamento_id, ultimo_acceso
FROM usuarios 
WHERE rol IN ('admin', 'supervisor', 'soporte_ti')
  AND activo = TRUE
ORDER BY rol, email;
