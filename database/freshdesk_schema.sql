-- ============================================
-- ACTUALIZACIÓN FRESHDESK
-- Nuevas tablas y campos para funcionalidades avanzadas
-- ============================================

-- 1. ETIQUETAS
CREATE TABLE IF NOT EXISTS etiquetas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#6c757d',
    activo BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS ticket_etiquetas (
    ticket_id INT REFERENCES tickets(id) ON DELETE CASCADE,
    etiqueta_id INT REFERENCES etiquetas(id) ON DELETE CASCADE,
    PRIMARY KEY (ticket_id, etiqueta_id)
);

-- 2. RESPUESTAS PREDEFINIDAS
CREATE TABLE IF NOT EXISTS respuestas_predefinidas (
    id SERIAL PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    contenido TEXT NOT NULL,
    departamento_id INT REFERENCES departamentos(id) ON DELETE SET NULL,
    usuario_id INT REFERENCES usuarios(id) ON DELETE SET NULL,
    es_global BOOLEAN DEFAULT FALSE,
    uso_count INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. SLA (ACUERDOS DE NIVEL DE SERVICIO)
CREATE TABLE IF NOT EXISTS sla_politicas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    prioridad_id INT REFERENCES prioridades(id),
    departamento_id INT REFERENCES departamentos(id) ON DELETE SET NULL,
    tiempo_primera_respuesta_horas DECIMAL(10,2) NOT NULL,
    tiempo_resolucion_horas DECIMAL(10,2) NOT NULL,
    activo BOOLEAN DEFAULT TRUE
);

-- Campos SLA en tickets
ALTER TABLE tickets 
ADD COLUMN IF NOT EXISTS sla_politica_id INT REFERENCES sla_politicas(id),
ADD COLUMN IF NOT EXISTS sla_respuesta_vencimiento TIMESTAMP,
ADD COLUMN IF NOT EXISTS sla_resolucion_vencimiento TIMESTAMP,
ADD COLUMN IF NOT EXISTS fusionado_en INT REFERENCES tickets(id);

-- 4. ENCUESTAS SATISFACCIÓN (CSAT)
CREATE TABLE IF NOT EXISTS encuestas_satisfaccion (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE UNIQUE,
    token VARCHAR(64) NOT NULL UNIQUE,
    calificacion INT CHECK (calificacion BETWEEN 1 AND 5),
    comentario TEXT,
    respondido_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. VISTAS PERSONALIZADAS
CREATE TABLE IF NOT EXISTS vistas_personalizadas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    filtros JSONB NOT NULL,
    es_compartida BOOLEAN DEFAULT FALSE,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. TICKETS RELACIONADOS / FUSIÓN
CREATE TABLE IF NOT EXISTS tickets_relacionados (
    id SERIAL PRIMARY KEY,
    ticket_principal_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    ticket_relacionado_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    tipo VARCHAR(20) DEFAULT 'relacionado', -- relacionado, fusionado, bloquea_a, bloqueado_por
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. NOTIFICACIONES
CREATE TABLE IF NOT EXISTS notificaciones (
    id SERIAL PRIMARY KEY,
    usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    ticket_id INT REFERENCES tickets(id) ON DELETE SET NULL,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT,
    leido BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. ACTIVIDAD (COLISIÓN)
CREATE TABLE IF NOT EXISTS ticket_actividad (
    ticket_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    accion VARCHAR(50) DEFAULT 'viendo',
    ultima_actividad TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ticket_id, usuario_id)
);

-- 9. AUTOMATIZACIONES
CREATE TABLE IF NOT EXISTS automatizaciones (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    evento VARCHAR(50) NOT NULL, -- ticket_creado, ticket_actualizado, respuesta_enviada
    condiciones JSONB NOT NULL,
    acciones JSONB NOT NULL,
    orden INT DEFAULT 0,
    ejecuciones INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 10. CAMPOS PERSONALIZADOS
CREATE TABLE IF NOT EXISTS campos_personalizados (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL, -- texto, numero, fecha, select, checkbox
    opciones JSONB, -- para selects
    departamento_id INT REFERENCES departamentos(id) ON DELETE SET NULL,
    orden INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS ticket_campos_valores (
    ticket_id INT REFERENCES tickets(id) ON DELETE CASCADE,
    campo_id INT REFERENCES campos_personalizados(id) ON DELETE CASCADE,
    valor TEXT,
    PRIMARY KEY (ticket_id, campo_id)
);

-- ASIGNACIÓN AUTOMÁTICA POR CATEGORÍA
CREATE TABLE IF NOT EXISTS categoria_asignacion (
    categoria_id INT REFERENCES categorias(id) ON DELETE CASCADE,
    usuario_id INT REFERENCES usuarios(id) ON DELETE CASCADE,
    es_principal BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (categoria_id, usuario_id)
);

-- Configuración de Correo
CREATE TABLE IF NOT EXISTS configuracion_correo (
    id SERIAL PRIMARY KEY,
    host VARCHAR(255) NOT NULL,
    port INT DEFAULT 993,
    usuario VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    protocolo VARCHAR(10) DEFAULT 'imap', -- imap, pop3
    encryption VARCHAR(10) DEFAULT 'ssl',
    carpeta VARCHAR(50) DEFAULT 'INBOX',
    activo BOOLEAN DEFAULT TRUE,
    ultimo_chequeo TIMESTAMP
);

-- Insertar SLA por defecto
INSERT INTO sla_politicas (nombre, prioridad_id, tiempo_primera_respuesta_horas, tiempo_resolucion_horas) VALUES
('SLA Estándar - Baja', 1, 24, 72),
('SLA Estándar - Normal', 2, 12, 48),
('SLA Estándar - Alta', 3, 4, 24),
('SLA Estándar - Urgente', 4, 1, 8),
('SLA Estándar - Crítica', 5, 0.50, 4);

-- Vista Completa para Automatizaciones
CREATE OR REPLACE VIEW vista_tickets_completa AS
SELECT 
    t.*,
    e.nombre as estado,
    p.nombre as prioridad,
    c.nombre as categoria,
    d.nombre as departamento,
    d.id as departamento_id,
    u.email as ciudadano_email
FROM tickets t
LEFT JOIN estados e ON t.estado_id = e.id
LEFT JOIN prioridades p ON t.prioridad_id = p.id
LEFT JOIN categorias c ON t.categoria_id = c.id
LEFT JOIN departamentos d ON c.departamento_id = d.id
LEFT JOIN usuarios u ON t.ciudadano_id = u.id;
