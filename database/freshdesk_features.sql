-- ============================================
-- FUNCIONALIDADES FRESHDESK
-- Esquema adicional para replicar Freshdesk
-- ============================================

-- ============================================
-- 1. ETIQUETAS (TAGS)
-- ============================================
CREATE TABLE IF NOT EXISTS etiquetas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#6c757d',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_etiquetas (
    ticket_id INT REFERENCES tickets(id) ON DELETE CASCADE,
    etiqueta_id INT REFERENCES etiquetas(id) ON DELETE CASCADE,
    PRIMARY KEY (ticket_id, etiqueta_id)
);

-- Etiquetas iniciales
INSERT INTO etiquetas (nombre, color) VALUES
('urgente', '#e74c3c'),
('seguimiento', '#3498db'),
('vip', '#9b59b6'),
('duplicado', '#95a5a6'),
('escalado', '#e67e22'),
('requiere-visita', '#1abc9c')
ON CONFLICT (nombre) DO NOTHING;

-- ============================================
-- 2. RESPUESTAS PREDEFINIDAS (CANNED RESPONSES)
-- ============================================
CREATE TABLE IF NOT EXISTS respuestas_predefinidas (
    id SERIAL PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    contenido TEXT NOT NULL,
    categoria VARCHAR(50),
    departamento_id INT REFERENCES departamentos(id) ON DELETE SET NULL,
    usuario_id INT REFERENCES usuarios(id),
    es_global BOOLEAN DEFAULT FALSE,
    uso_count INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Respuestas predefinidas iniciales
INSERT INTO respuestas_predefinidas (titulo, contenido, es_global) VALUES
('Saludo inicial', 'Estimado/a ciudadano/a,

Gracias por contactar a la Municipalidad. He recibido su solicitud y la estoy revisando.

Le mantendré informado sobre el avance.

Saludos cordiales,', TRUE),
('Solicitud de más información', 'Estimado/a ciudadano/a,

Para poder continuar con su solicitud, necesito que me proporcione la siguiente información adicional:

- [Especificar información requerida]

Quedo atento a su respuesta.

Saludos cordiales,', TRUE),
('Ticket resuelto', 'Estimado/a ciudadano/a,

Me complace informarle que su solicitud ha sido resuelta satisfactoriamente.

Si tiene alguna otra consulta, no dude en contactarnos.

Saludos cordiales,', TRUE),
('Derivación a otro departamento', 'Estimado/a ciudadano/a,

Su solicitud ha sido derivada al departamento correspondiente para su atención.

Ellos se pondrán en contacto con usted a la brevedad.

Saludos cordiales,', TRUE),
('Visita programada', 'Estimado/a ciudadano/a,

Le informamos que se ha programado una visita técnica para atender su solicitud.

Fecha tentativa: [FECHA]
Horario: [HORARIO]

Por favor confirme su disponibilidad.

Saludos cordiales,', TRUE)
ON CONFLICT DO NOTHING;

-- ============================================
-- 3. SLA (ACUERDOS DE NIVEL DE SERVICIO)
-- ============================================
CREATE TABLE IF NOT EXISTS sla_politicas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    tiempo_primera_respuesta_horas INT DEFAULT 24,
    tiempo_resolucion_horas INT DEFAULT 72,
    prioridad_id INT REFERENCES prioridades(id),
    departamento_id INT REFERENCES departamentos(id),
    horario_laboral BOOLEAN DEFAULT TRUE,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Políticas SLA por prioridad
INSERT INTO sla_politicas (nombre, tiempo_primera_respuesta_horas, tiempo_resolucion_horas, prioridad_id) VALUES
('SLA Crítica', 1, 4, 5),
('SLA Urgente', 4, 24, 4),
('SLA Alta', 8, 48, 3),
('SLA Normal', 24, 72, 2),
('SLA Baja', 48, 168, 1)
ON CONFLICT DO NOTHING;

-- Agregar campos SLA a tickets si no existen
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='tickets' AND column_name='sla_politica_id') THEN
        ALTER TABLE tickets ADD COLUMN sla_politica_id INT REFERENCES sla_politicas(id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='tickets' AND column_name='sla_respuesta_vencimiento') THEN
        ALTER TABLE tickets ADD COLUMN sla_respuesta_vencimiento TIMESTAMP;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='tickets' AND column_name='sla_resolucion_vencimiento') THEN
        ALTER TABLE tickets ADD COLUMN sla_resolucion_vencimiento TIMESTAMP;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='tickets' AND column_name='sla_respuesta_cumplido') THEN
        ALTER TABLE tickets ADD COLUMN sla_respuesta_cumplido BOOLEAN;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='tickets' AND column_name='sla_resolucion_cumplido') THEN
        ALTER TABLE tickets ADD COLUMN sla_resolucion_cumplido BOOLEAN;
    END IF;
END $$;

-- ============================================
-- 4. SATISFACCIÓN DEL CLIENTE (CSAT)
-- ============================================
CREATE TABLE IF NOT EXISTS encuestas_satisfaccion (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    calificacion INT NOT NULL CHECK (calificacion BETWEEN 1 AND 5),
    comentario TEXT,
    token VARCHAR(64) UNIQUE,
    respondido_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 5. CAMPOS PERSONALIZADOS
-- ============================================
CREATE TABLE IF NOT EXISTS campos_personalizados (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    etiqueta VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('texto', 'numero', 'fecha', 'select', 'checkbox', 'textarea')),
    opciones JSONB,
    requerido BOOLEAN DEFAULT FALSE,
    departamento_id INT REFERENCES departamentos(id),
    orden INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_campos_valores (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    campo_id INT NOT NULL REFERENCES campos_personalizados(id) ON DELETE CASCADE,
    valor TEXT,
    UNIQUE(ticket_id, campo_id)
);

-- ============================================
-- 6. VISTAS PERSONALIZADAS
-- ============================================
CREATE TABLE IF NOT EXISTS vistas_personalizadas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario_id INT REFERENCES usuarios(id),
    es_compartida BOOLEAN DEFAULT FALSE,
    filtros JSONB NOT NULL DEFAULT '{}',
    columnas JSONB DEFAULT '["numero", "asunto", "estado", "prioridad", "asignado", "created_at"]',
    orden_campo VARCHAR(50) DEFAULT 'created_at',
    orden_direccion VARCHAR(4) DEFAULT 'DESC',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vistas predefinidas
INSERT INTO vistas_personalizadas (nombre, es_compartida, filtros, usuario_id) VALUES
('Mis tickets abiertos', TRUE, '{"estado": [1], "asignado": "yo"}', NULL),
('Sin asignar', TRUE, '{"asignado": null}', NULL),
('Vencidos SLA', TRUE, '{"sla_vencido": true}', NULL),
('Alta prioridad', TRUE, '{"prioridad": [4, 5]}', NULL),
('Resueltos hoy', TRUE, '{"estado": [5], "fecha": "hoy"}', NULL)
ON CONFLICT DO NOTHING;

-- ============================================
-- 7. REGLAS DE AUTOMATIZACIÓN
-- ============================================
CREATE TABLE IF NOT EXISTS automatizaciones (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    evento VARCHAR(50) NOT NULL CHECK (evento IN ('ticket_creado', 'ticket_actualizado', 'tiempo', 'respuesta_ciudadano')),
    condiciones JSONB NOT NULL DEFAULT '[]',
    acciones JSONB NOT NULL DEFAULT '[]',
    orden INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    ejecuciones INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Automatizaciones ejemplo
INSERT INTO automatizaciones (nombre, descripcion, evento, condiciones, acciones) VALUES
('Auto-asignar por departamento', 'Asigna automáticamente tickets al supervisor del departamento', 'ticket_creado', 
 '[]', 
 '[{"tipo": "asignar", "valor": "supervisor_departamento"}]'),
('Alerta SLA próximo a vencer', 'Notifica cuando faltan 2 horas para vencer SLA', 'tiempo',
 '[{"campo": "sla_respuesta_vencimiento", "operador": "menor_que", "valor": "2_horas"}]',
 '[{"tipo": "notificar", "destinatario": "asignado"}]'),
('Cerrar tickets resueltos', 'Cierra automáticamente tickets resueltos después de 7 días sin respuesta', 'tiempo',
 '[{"campo": "estado_id", "operador": "igual", "valor": 5}, {"campo": "dias_sin_respuesta", "operador": "mayor_que", "valor": 7}]',
 '[{"tipo": "cambiar_estado", "valor": 6}]')
ON CONFLICT DO NOTHING;

-- ============================================
-- 8. TICKETS RELACIONADOS (FUSIÓN)
-- ============================================
CREATE TABLE IF NOT EXISTS tickets_relacionados (
    id SERIAL PRIMARY KEY,
    ticket_principal_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    ticket_relacionado_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('fusionado', 'relacionado', 'padre', 'hijo')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ticket_principal_id, ticket_relacionado_id)
);

-- Agregar campo para tickets fusionados
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='tickets' AND column_name='fusionado_en') THEN
        ALTER TABLE tickets ADD COLUMN fusionado_en INT REFERENCES tickets(id);
    END IF;
END $$;

-- ============================================
-- 9. NOTIFICACIONES
-- ============================================
CREATE TABLE IF NOT EXISTS notificaciones (
    id SERIAL PRIMARY KEY,
    usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    ticket_id INT REFERENCES tickets(id) ON DELETE CASCADE,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT,
    leido BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS configuracion_notificaciones (
    id SERIAL PRIMARY KEY,
    usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    tipo_notificacion VARCHAR(50) NOT NULL,
    email BOOLEAN DEFAULT TRUE,
    web BOOLEAN DEFAULT TRUE,
    UNIQUE(usuario_id, tipo_notificacion)
);

-- ============================================
-- 10. ACTIVIDAD/DETECCIÓN DE COLISIÓN
-- ============================================
CREATE TABLE IF NOT EXISTS ticket_actividad (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    usuario_id INT NOT NULL REFERENCES usuarios(id),
    accion VARCHAR(50) NOT NULL,
    ultima_actividad TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ticket_id, usuario_id)
);

-- Índice para consultas rápidas de actividad
CREATE INDEX IF NOT EXISTS idx_ticket_actividad_ticket ON ticket_actividad(ticket_id);
CREATE INDEX IF NOT EXISTS idx_ticket_actividad_tiempo ON ticket_actividad(ultima_actividad);
CREATE INDEX IF NOT EXISTS idx_notificaciones_usuario ON notificaciones(usuario_id, leido);
CREATE INDEX IF NOT EXISTS idx_tickets_sla ON tickets(sla_respuesta_vencimiento, sla_resolucion_vencimiento);

-- ============================================
-- VISTA ACTUALIZADA CON SLA
-- ============================================
CREATE OR REPLACE VIEW vista_tickets_completa AS
SELECT 
    t.id,
    t.numero,
    t.asunto,
    t.descripcion,
    t.ubicacion_direccion,
    t.created_at,
    t.updated_at,
    t.fecha_limite,
    t.fecha_primera_respuesta,
    t.fecha_resolucion,
    t.satisfaccion,
    t.sla_respuesta_vencimiento,
    t.sla_resolucion_vencimiento,
    t.sla_respuesta_cumplido,
    t.sla_resolucion_cumplido,
    t.estado_id,
    t.prioridad_id,
    t.categoria_id,
    t.ciudadano_id,
    t.asignado_id,
    e.nombre as estado,
    e.color as estado_color,
    p.nombre as prioridad,
    p.color as prioridad_color,
    c.nombre as categoria,
    c.departamento_id,
    d.nombre as departamento,
    CONCAT(uc.nombres, ' ', uc.apellidos) as ciudadano_nombre,
    uc.email as ciudadano_email,
    CONCAT(ua.nombres, ' ', ua.apellidos) as asignado_nombre,
    (SELECT COUNT(*) FROM ticket_comentarios tc WHERE tc.ticket_id = t.id) as total_comentarios,
    (SELECT COUNT(*) FROM ticket_archivos ta WHERE ta.ticket_id = t.id) as total_archivos,
    CASE 
        WHEN t.sla_respuesta_vencimiento IS NOT NULL AND t.fecha_primera_respuesta IS NULL 
             AND t.sla_respuesta_vencimiento < CURRENT_TIMESTAMP THEN TRUE
        ELSE FALSE
    END as sla_respuesta_vencido,
    CASE 
        WHEN t.sla_resolucion_vencimiento IS NOT NULL AND t.fecha_resolucion IS NULL 
             AND t.sla_resolucion_vencimiento < CURRENT_TIMESTAMP THEN TRUE
        ELSE FALSE
    END as sla_resolucion_vencido
FROM tickets t
LEFT JOIN estados e ON t.estado_id = e.id
LEFT JOIN prioridades p ON t.prioridad_id = p.id
LEFT JOIN categorias c ON t.categoria_id = c.id
LEFT JOIN departamentos d ON c.departamento_id = d.id
LEFT JOIN usuarios uc ON t.ciudadano_id = uc.id
LEFT JOIN usuarios ua ON t.asignado_id = ua.id
WHERE t.fusionado_en IS NULL;
