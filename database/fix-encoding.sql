-- Script para corregir caracteres ?? en la base de datos PostgreSQL
-- Ejecutar en pgAdmin o psql

-- ============================================
-- CORREGIR CATEGORÍAS - Nombres
-- ============================================

UPDATE categorias SET nombre = REPLACE(nombre, 'Capacitaci??n', 'Capacitación') WHERE nombre LIKE '%Capacitaci??n%';
UPDATE categorias SET nombre = REPLACE(nombre, 'Configuraci??n', 'Configuración') WHERE nombre LIKE '%Configuraci??n%';
UPDATE categorias SET nombre = REPLACE(nombre, 'Electr??nico', 'Electrónico') WHERE nombre LIKE '%Electr??nico%';
UPDATE categorias SET nombre = REPLACE(nombre, 'Electr??nica', 'Electrónica') WHERE nombre LIKE '%Electr??nica%';
UPDATE categorias SET nombre = REPLACE(nombre, 'Creaci??n', 'Creación') WHERE nombre LIKE '%Creaci??n%';
UPDATE categorias SET nombre = REPLACE(nombre, 'Instalaci??n', 'Instalación') WHERE nombre LIKE '%Instalaci??n%';
UPDATE categorias SET nombre = REPLACE(nombre, 'Contrase??a', 'Contraseña') WHERE nombre LIKE '%Contrase??a%';
UPDATE categorias SET nombre = REPLACE(nombre, 'Conexi??n', 'Conexión') WHERE nombre LIKE '%Conexi??n%';
UPDATE categorias SET nombre = REPLACE(nombre, 'Gesti??n', 'Gestión') WHERE nombre LIKE '%Gesti??n%';
UPDATE categorias SET nombre = REPLACE(nombre, 'Telefon??a', 'Telefonía') WHERE nombre LIKE '%Telefon??a%';

-- ============================================
-- CORREGIR CATEGORÍAS - Descripciones
-- ============================================

UPDATE categorias SET descripcion = REPLACE(descripcion, 'actualizaci??n', 'actualización') WHERE descripcion LIKE '%actualizaci??n%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'Capacitaci??n', 'Capacitación') WHERE descripcion LIKE '%Capacitaci??n%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'capacitaci??n', 'capacitación') WHERE descripcion LIKE '%capacitaci??n%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'tecnol??gicas', 'tecnológicas') WHERE descripcion LIKE '%tecnol??gicas%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'est??', 'está') WHERE descripcion LIKE '%est??%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'Instalaci??n', 'Instalación') WHERE descripcion LIKE '%Instalaci??n%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'instalaci??n', 'instalación') WHERE descripcion LIKE '%instalaci??n%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'configuraci??n', 'configuración') WHERE descripcion LIKE '%configuraci??n%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'f??sicos', 'físicos') WHERE descripcion LIKE '%f??sicos%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'conexi??n', 'conexión') WHERE descripcion LIKE '%conexi??n%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'Olvid??', 'Olvidé') WHERE descripcion LIKE '%Olvid??%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'contrase??a', 'contraseña') WHERE descripcion LIKE '%contrase??a%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 't??cnico', 'técnico') WHERE descripcion LIKE '%t??cnico%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'perif??ricos', 'periféricos') WHERE descripcion LIKE '%perif??ricos%';
UPDATE categorias SET descripcion = REPLACE(descripcion, 'tel??fonos', 'teléfonos') WHERE descripcion LIKE '%tel??fonos%';

-- ============================================
-- CORREGIR USUARIOS - Nombres y Apellidos
-- ============================================

UPDATE usuarios SET nombres = REPLACE(nombres, 'Mar??a', 'María') WHERE nombres LIKE '%Mar??a%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'Mart??nez', 'Martínez') WHERE apellidos LIKE '%Mart??nez%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'P??rez', 'Pérez') WHERE apellidos LIKE '%P??rez%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'Gonz??lez', 'González') WHERE apellidos LIKE '%Gonz??lez%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'L??pez', 'López') WHERE apellidos LIKE '%L??pez%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'S??nchez', 'Sánchez') WHERE apellidos LIKE '%S??nchez%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'Ram??rez', 'Ramírez') WHERE apellidos LIKE '%Ram??rez%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'Garc??a', 'García') WHERE apellidos LIKE '%Garc??a%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'Fern??ndez', 'Fernández') WHERE apellidos LIKE '%Fern??ndez%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'Rodr??guez', 'Rodríguez') WHERE apellidos LIKE '%Rodr??guez%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'Jim??nez', 'Jiménez') WHERE apellidos LIKE '%Jim??nez%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'Hern??ndez', 'Hernández') WHERE apellidos LIKE '%Hern??ndez%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'D??az', 'Díaz') WHERE apellidos LIKE '%D??az%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'Nu??ez', 'Núñez') WHERE apellidos LIKE '%Nu??ez%';
UPDATE usuarios SET apellidos = REPLACE(apellidos, 'Mu??oz', 'Muñoz') WHERE apellidos LIKE '%Mu??oz%';

-- ============================================
-- VERIFICAR RESULTADOS
-- ============================================

SELECT nombre, descripcion FROM categorias ORDER BY nombre;
SELECT nombres, apellidos FROM usuarios WHERE rol IN ('admin', 'supervisor', 'funcionario') ORDER BY nombres;
