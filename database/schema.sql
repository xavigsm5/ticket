SET client_encoding = 'UTF8';

-- ============================================
-- SISTEMA DE TICKETS MUNICIPAL
-- Esquema de Base de Datos PostgreSQL
-- ============================================


DROP TABLE IF EXISTS ticket_historial CASCADE;
DROP TABLE IF EXISTS ticket_archivos CASCADE;
DROP TABLE IF EXISTS ticket_comentarios CASCADE;
DROP TABLE IF EXISTS tickets CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;
DROP TABLE IF EXISTS departamentos CASCADE;
DROP TABLE IF EXISTS categorias CASCADE;
DROP TABLE IF EXISTS prioridades CASCADE;
DROP TABLE IF EXISTS estados CASCADE;

-- ============================================
-- TABLA: ESTADOS
-- ============================================
CREATE TABLE estados (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#6c757d',
    icono VARCHAR(50) DEFAULT 'bi-circle',
    orden INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO estados (nombre, color, icono, orden) VALUES
('Pendiente', '#ffc107', 'bi-clock', 1),
('En Revisión', '#17a2b8', 'bi-eye', 2),
('En Proceso', '#007bff', 'bi-gear', 3),
('En Espera', '#fd7e14', 'bi-pause-circle', 4),
('Resuelto', '#28a745', 'bi-check-circle', 5),
('Cerrado', '#6c757d', 'bi-x-circle', 6),
('Rechazado', '#dc3545', 'bi-slash-circle', 7);

-- ============================================
-- TABLA: PRIORIDADES
-- ============================================
CREATE TABLE prioridades (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#6c757d',
    nivel INT DEFAULT 0,
    tiempo_respuesta_horas INT DEFAULT 48,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO prioridades (nombre, color, nivel, tiempo_respuesta_horas) VALUES
('Baja', '#28a745', 1, 72),
('Normal', '#17a2b8', 2, 48),
('Alta', '#ffc107', 3, 24),
('Urgente', '#fd7e14', 4, 8),
('Crítica', '#dc3545', 5, 2);

-- ============================================
-- TABLA: DEPARTAMENTOS
-- ============================================
CREATE TABLE departamentos (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    email VARCHAR(255),
    telefono VARCHAR(20),
    responsable_nombre VARCHAR(100),
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO departamentos (nombre, descripcion, email) VALUES
('Obras Públicas', 'Departamento de infraestructura y obras municipales', 'obras@municipalidad.cl'),
('Tránsito y Transporte', 'Regulación del tránsito y transporte público', 'transito@municipalidad.cl'),
('Desarrollo Social', 'Programas sociales y asistencia ciudadana', 'social@municipalidad.cl'),
('Medio Ambiente', 'Gestión ambiental y áreas verdes', 'ambiente@municipalidad.cl'),
('Seguridad Ciudadana', 'Coordinación con fuerzas de seguridad', 'seguridad@municipalidad.cl'),
('Rentas y Patentes', 'Administración de tributos municipales', 'rentas@municipalidad.cl'),
('Dirección de Aseo y Ornato', 'Limpieza y mantención de espacios públicos', 'aseo@municipalidad.cl'),
('Atención Ciudadana', 'Oficina de información y reclamos', 'atencion@municipalidad.cl');

-- ============================================
-- TABLA: CATEGORÍAS
-- ============================================
CREATE TABLE categorias (
    id SERIAL PRIMARY KEY,
    departamento_id INT REFERENCES departamentos(id) ON DELETE SET NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(50) DEFAULT 'bi-folder',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categorias (departamento_id, nombre, descripcion, icono) VALUES
(1, 'Baches y Pavimento', 'Reparación de calles y veredas', 'bi-cone-striped'),
(1, 'Alumbrado Público', 'Fallas en luminarias y postes', 'bi-lightbulb'),
(1, 'Alcantarillado', 'Problemas de drenaje y aguas lluvias', 'bi-water'),
(2, 'Señalética', 'Solicitud o reparación de señales de tránsito', 'bi-signpost'),
(2, 'Semáforos', 'Fallas en semáforos', 'bi-stoplights'),
(3, 'Subsidios', 'Consultas sobre subsidios sociales', 'bi-cash-stack'),
(3, 'Adulto Mayor', 'Programas para tercera edad', 'bi-person-heart'),
(4, 'Poda de Árboles', 'Solicitud de poda o retiro de árboles', 'bi-tree'),
(4, 'Basura y Escombros', 'Retiro de residuos ilegales', 'bi-trash'),
(5, 'Ruidos Molestos', 'Denuncias por contaminación acústica', 'bi-volume-up'),
(5, 'Seguridad Vecinal', 'Problemas de seguridad en el barrio', 'bi-shield-exclamation'),
(6, 'Patentes Comerciales', 'Consultas sobre permisos comerciales', 'bi-shop'),
(6, 'Permisos de Circulación', 'Consultas sobre permisos vehiculares', 'bi-car-front'),
(7, 'Recolección de Basura', 'Problemas con servicio de basura', 'bi-trash3'),
(7, 'Limpieza de Calles', 'Solicitud de barrido y limpieza', 'bi-stars'),
(8, 'Información General', 'Consultas generales sobre la municipalidad', 'bi-info-circle'),
(8, 'Reclamos', 'Reclamos sobre servicios municipales', 'bi-exclamation-triangle');

-- ============================================
-- TABLA: USUARIOS
-- ============================================
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    rut VARCHAR(12) UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    direccion TEXT,
    rol VARCHAR(20) DEFAULT 'ciudadano' CHECK (rol IN ('ciudadano', 'funcionario', 'supervisor', 'admin')),
    departamento_id INT REFERENCES departamentos(id) ON DELETE SET NULL,
    avatar VARCHAR(255),
    activo BOOLEAN DEFAULT TRUE,
    email_verificado BOOLEAN DEFAULT FALSE,
    ultimo_acceso TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Usuario administrador por defecto (contraseña: admin123)
INSERT INTO usuarios (rut, email, password, nombres, apellidos, rol) VALUES
('11111111-1', 'admin@municipalidad.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'Sistema', 'admin');

-- ============================================
-- TABLA: TICKETS
-- ============================================
CREATE TABLE tickets (
    id SERIAL PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    ciudadano_id INT NOT NULL REFERENCES usuarios(id),
    asignado_id INT REFERENCES usuarios(id),
    categoria_id INT REFERENCES categorias(id),
    estado_id INT DEFAULT 1 REFERENCES estados(id),
    prioridad_id INT DEFAULT 2 REFERENCES prioridades(id),
    
    asunto VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    ubicacion_direccion VARCHAR(255),
    ubicacion_referencia TEXT,
    ubicacion_lat DECIMAL(10, 8),
    ubicacion_lng DECIMAL(11, 8),
    
    fecha_limite TIMESTAMP,
    fecha_primera_respuesta TIMESTAMP,
    fecha_resolucion TIMESTAMP,
    
    satisfaccion INT CHECK (satisfaccion BETWEEN 1 AND 5),
    comentario_satisfaccion TEXT,
    
    es_publico BOOLEAN DEFAULT TRUE,
    es_anonimo BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE OR REPLACE FUNCTION generar_numero_ticket()
RETURNS TRIGGER AS $$
BEGIN
    NEW.numero := 'TKT-' || TO_CHAR(CURRENT_DATE, 'YYYYMMDD') || '-' || LPAD(NEW.id::TEXT, 5, '0');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;


CREATE TRIGGER trigger_numero_ticket
    BEFORE INSERT ON tickets
    FOR EACH ROW
    WHEN (NEW.numero IS NULL OR NEW.numero = '')
    EXECUTE FUNCTION generar_numero_ticket();

-- ============================================
-- TABLA: COMENTARIOS DE TICKETS
-- ============================================
CREATE TABLE ticket_comentarios (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    usuario_id INT NOT NULL REFERENCES usuarios(id),
    comentario TEXT NOT NULL,
    es_interno BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLA: ARCHIVOS DE TICKETS
-- ============================================
CREATE TABLE ticket_archivos (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    usuario_id INT NOT NULL REFERENCES usuarios(id),
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100),
    tamano INT,
    ruta VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLA: HISTORIAL DE TICKETS
-- ============================================
CREATE TABLE ticket_historial (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    usuario_id INT REFERENCES usuarios(id),
    accion VARCHAR(50) NOT NULL,
    descripcion TEXT,
    valor_anterior TEXT,
    valor_nuevo TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- ÍNDICES PARA OPTIMIZACIÓN
-- ============================================
CREATE INDEX idx_tickets_ciudadano ON tickets(ciudadano_id);
CREATE INDEX idx_tickets_asignado ON tickets(asignado_id);
CREATE INDEX idx_tickets_estado ON tickets(estado_id);
CREATE INDEX idx_tickets_categoria ON tickets(categoria_id);
CREATE INDEX idx_tickets_fecha ON tickets(created_at DESC);
CREATE INDEX idx_tickets_numero ON tickets(numero);
CREATE INDEX idx_comentarios_ticket ON ticket_comentarios(ticket_id);
CREATE INDEX idx_archivos_ticket ON ticket_archivos(ticket_id);
CREATE INDEX idx_historial_ticket ON ticket_historial(ticket_id);

-- ============================================
-- VISTA: RESUMEN DE TICKETS
-- ============================================
CREATE OR REPLACE VIEW vista_tickets_resumen AS
SELECT 
    t.id,
    t.numero,
    t.asunto,
    t.descripcion,
    t.ubicacion_direccion,
    t.created_at,
    t.updated_at,
    t.fecha_limite,
    e.nombre as estado,
    e.color as estado_color,
    p.nombre as prioridad,
    p.color as prioridad_color,
    c.nombre as categoria,
    d.nombre as departamento,
    CONCAT(uc.nombres, ' ', uc.apellidos) as ciudadano_nombre,
    uc.email as ciudadano_email,
    CONCAT(ua.nombres, ' ', ua.apellidos) as asignado_nombre,
    (SELECT COUNT(*) FROM ticket_comentarios tc WHERE tc.ticket_id = t.id) as total_comentarios,
    (SELECT COUNT(*) FROM ticket_archivos ta WHERE ta.ticket_id = t.id) as total_archivos
FROM tickets t
LEFT JOIN estados e ON t.estado_id = e.id
LEFT JOIN prioridades p ON t.prioridad_id = p.id
LEFT JOIN categorias c ON t.categoria_id = c.id
LEFT JOIN departamentos d ON c.departamento_id = d.id
LEFT JOIN usuarios uc ON t.ciudadano_id = uc.id
LEFT JOIN usuarios ua ON t.asignado_id = ua.id;

-- ============================================
-- VISTA: ESTADÍSTICAS POR DEPARTAMENTO
-- ============================================
CREATE OR REPLACE VIEW vista_estadisticas_departamento AS
SELECT 
    d.id as departamento_id,
    d.nombre as departamento,
    COUNT(t.id) as total_tickets,
    COUNT(CASE WHEN e.nombre = 'Pendiente' THEN 1 END) as pendientes,
    COUNT(CASE WHEN e.nombre IN ('En Revisión', 'En Proceso') THEN 1 END) as en_proceso,
    COUNT(CASE WHEN e.nombre IN ('Resuelto', 'Cerrado') THEN 1 END) as resueltos,
    ROUND(AVG(EXTRACT(EPOCH FROM (t.fecha_resolucion - t.created_at))/3600)::numeric, 2) as promedio_horas_resolucion
FROM departamentos d
LEFT JOIN categorias c ON c.departamento_id = d.id
LEFT JOIN tickets t ON t.categoria_id = c.id
LEFT JOIN estados e ON t.estado_id = e.id
WHERE d.activo = TRUE
GROUP BY d.id, d.nombre
ORDER BY d.nombre;
