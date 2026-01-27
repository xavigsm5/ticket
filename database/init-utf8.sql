-- ============================================
-- CONFIGURACIÓN UTF-8 PARA POSTGRESQL
-- Ejecutar ANTES de cargar datos
-- ============================================

-- Establecer charset UTF-8 para la sesión
SET client_encoding = 'UTF8';

-- Alternar la base de datos existente si es necesario
-- ALTER DATABASE tickets_municipal SET client_encoding = 'UTF8';

-- Verificar que el encoding sea correcto
-- SELECT datname, encoding, encoding_name FROM pg_database WHERE datname = 'tickets_municipal';
