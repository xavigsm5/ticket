-- ============================================
-- MESA DE AYUDA TI - ÁREA DE INFORMÁTICA
-- Configuración específica para Soporte Técnico
-- ============================================


DELETE FROM ticket_etiquetas;
DELETE FROM ticket_comentarios;
DELETE FROM ticket_archivos;
DELETE FROM ticket_historial;
DELETE FROM tickets;
DELETE FROM categorias;
DELETE FROM departamentos;

-- ============================================
-- DEPARTAMENTO: INFORMÁTICA MUNICIPAL
-- ============================================
INSERT INTO departamentos (id, nombre, descripcion, email) VALUES
(1, 'Área de Informática', 'Departamento de Tecnologías de la Información', 'informatica@municipalidad.cl');

-- Reset sequence
SELECT setval('departamentos_id_seq', 1, true);

-- ============================================
-- CATEGORÍAS DE SOPORTE TI
-- ============================================
INSERT INTO categorias (departamento_id, nombre, descripcion, icono) VALUES
-- Redes y Conectividad
(1, 'Redes y Conectividad', 'Problemas de red, internet, WiFi, cables de red, switches', 'bi-router'),
(1, 'Sin Conexión a Internet', 'No hay acceso a internet en el equipo', 'bi-wifi-off'),
(1, 'Red Lenta', 'La conexión a internet o red local es muy lenta', 'bi-speedometer'),
(1, 'Configuración de Red', 'Configurar IP, VPN, proxy, acceso a carpetas compartidas', 'bi-hdd-network'),

-- Hardware
(1, 'Problemas de Hardware', 'Fallas en equipos físicos: PC, monitores, teclados, mouse', 'bi-pc-display'),
(1, 'Impresoras', 'Instalación, configuración o fallas de impresoras', 'bi-printer'),
(1, 'Teclado/Mouse', 'Problemas con teclado, mouse u otros periféricos', 'bi-keyboard'),
(1, 'Monitor/Pantalla', 'Problemas con monitores o pantallas', 'bi-display'),
(1, 'Solicitud de Equipamiento', 'Solicitar nuevo equipo, teclado, mouse, etc.', 'bi-box-seam'),

-- Software
(1, 'Instalación de Software', 'Solicitar instalación de programas', 'bi-download'),
(1, 'Problemas con Software', 'Errores o fallas en programas instalados', 'bi-bug'),
(1, 'Microsoft Office', 'Problemas con Word, Excel, PowerPoint, Outlook', 'bi-file-earmark-word'),
(1, 'Navegador Web', 'Problemas con Chrome, Firefox, Edge', 'bi-globe'),
(1, 'Antivirus', 'Alertas de virus, malware, actualización de antivirus', 'bi-shield-exclamation'),

-- Sistemas Municipales
(1, 'Sistema de Gestión Municipal', 'Problemas con sistemas internos del municipio', 'bi-building'),
(1, 'Firma Electrónica', 'Problemas con certificados o firma digital', 'bi-pen'),
(1, 'Correo Electrónico', 'Problemas con email municipal, Outlook, webmail', 'bi-envelope'),
(1, 'Accesos y Permisos', 'Solicitar acceso a sistemas, carpetas o permisos', 'bi-key'),

-- Cuentas y Contraseñas
(1, 'Reseteo de Contraseña', 'Olvidé mi contraseña, necesito restablecer acceso', 'bi-lock'),
(1, 'Creación de Usuario', 'Solicitar nuevo usuario para funcionario', 'bi-person-plus'),
(1, 'Desbloqueo de Cuenta', 'Mi cuenta está bloqueada', 'bi-unlock'),

-- Telefonía
(1, 'Telefonía IP', 'Problemas con teléfonos IP o anexos', 'bi-telephone'),
(1, 'Videoconferencia', 'Problemas con Teams, Zoom, Meet', 'bi-camera-video'),

-- Otros
(1, 'Soporte General', 'Consultas generales de soporte técnico', 'bi-question-circle'),
(1, 'Capacitación', 'Solicitar capacitación en herramientas tecnológicas', 'bi-mortarboard'),
(1, 'Otro', 'Otros problemas no categorizados', 'bi-three-dots');

-- ============================================
-- USUARIOS TI (Técnicos del Área)
-- ============================================
-- Contraseña por defecto: soporte123 (hash bcrypt)
INSERT INTO usuarios (rut, email, password, nombres, apellidos, rol, departamento_id)
VALUES
-- Jefe del Área
('12345678-9', 'jefe.ti@municipalidad.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos', 'Mendoza', 'supervisor', 1),

-- Encargado de Redes
('11111111-2', 'redes@municipalidad.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pedro', 'González (Redes)', 'funcionario', 1),

-- Encargado de Soporte Técnico
('11111111-3', 'soporte@municipalidad.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'María', 'López (Soporte)', 'funcionario', 1),

-- Encargado de Sistemas
('11111111-4', 'sistemas@municipalidad.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan', 'Pérez (Sistemas)', 'funcionario', 1),

-- Encargado de Hardware
('11111111-5', 'hardware@municipalidad.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana', 'Martínez (Hardware)', 'funcionario', 1);

-- ============================================
-- TABLA: ASIGNACIÓN AUTOMÁTICA POR CATEGORÍA
-- ============================================
CREATE TABLE IF NOT EXISTS categoria_asignacion (
    id SERIAL PRIMARY KEY,
    categoria_id INT NOT NULL REFERENCES categorias(id) ON DELETE CASCADE,
    usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    es_principal BOOLEAN DEFAULT TRUE,
    UNIQUE(categoria_id, usuario_id)
);


DO $$
DECLARE
    v_redes_id INT;
    v_soporte_id INT;
    v_sistemas_id INT;
    v_hardware_id INT;
    v_jefe_id INT;
BEGIN
    -- Obtener IDs de usuarios
    SELECT id INTO v_redes_id FROM usuarios WHERE email = 'redes@municipalidad.cl';
    SELECT id INTO v_soporte_id FROM usuarios WHERE email = 'soporte@municipalidad.cl';
    SELECT id INTO v_sistemas_id FROM usuarios WHERE email = 'sistemas@municipalidad.cl';
    SELECT id INTO v_hardware_id FROM usuarios WHERE email = 'hardware@municipalidad.cl';
    SELECT id INTO v_jefe_id FROM usuarios WHERE email = 'jefe.ti@municipalidad.cl';
    

    INSERT INTO categoria_asignacion (categoria_id, usuario_id) 
    SELECT id, v_redes_id FROM categorias WHERE nombre ILIKE '%red%' OR nombre ILIKE '%internet%' OR nombre ILIKE '%conectividad%' OR nombre ILIKE '%wifi%'
    ON CONFLICT DO NOTHING;
    
    INSERT INTO categoria_asignacion (categoria_id, usuario_id) 
    SELECT id, v_hardware_id FROM categorias WHERE nombre ILIKE '%hardware%' OR nombre ILIKE '%impresora%' OR nombre ILIKE '%teclado%' OR nombre ILIKE '%mouse%' OR nombre ILIKE '%monitor%' OR nombre ILIKE '%equip%'
    ON CONFLICT DO NOTHING;
    
    
    INSERT INTO categoria_asignacion (categoria_id, usuario_id) 
    SELECT id, v_sistemas_id FROM categorias WHERE nombre ILIKE '%sistema%' OR nombre ILIKE '%firma%' OR nombre ILIKE '%correo%' OR nombre ILIKE '%acceso%' OR nombre ILIKE '%permiso%' OR nombre ILIKE '%contraseña%' OR nombre ILIKE '%usuario%' OR nombre ILIKE '%cuenta%'
    ON CONFLICT DO NOTHING;
    

    INSERT INTO categoria_asignacion (categoria_id, usuario_id) 
    SELECT id, v_soporte_id FROM categorias WHERE nombre ILIKE '%software%' OR nombre ILIKE '%office%' OR nombre ILIKE '%navegador%' OR nombre ILIKE '%antivirus%' OR nombre ILIKE '%instala%' OR nombre ILIKE '%soporte%' OR nombre ILIKE '%capacitación%' OR nombre ILIKE '%otro%' OR nombre ILIKE '%telefonía%' OR nombre ILIKE '%video%'
    ON CONFLICT DO NOTHING;
    
END $$;

-- ============================================
-- RESPUESTAS PREDEFINIDAS PARA SOPORTE TI
-- ============================================
DELETE FROM respuestas_predefinidas;

INSERT INTO respuestas_predefinidas (titulo, contenido, es_global) VALUES
('Saludo TI', 'Estimado(a) usuario(a),

He recibido su solicitud de soporte técnico y la estoy analizando.

Le mantendré informado sobre el avance.

Saludos,
Área de Informática', TRUE),

('Solicitar más información', 'Estimado(a) usuario(a),

Para poder atender su solicitud, necesito la siguiente información:

- Número de inventario del equipo (etiqueta blanca):
- Ubicación exacta (edificio, piso, oficina):
- Descripción detallada del problema:
- ¿Desde cuándo ocurre el problema?:

Quedo atento a su respuesta.

Saludos,
Soporte TI', TRUE),

('Visitaré su puesto', 'Estimado(a) usuario(a),

Pasaré a revisar su equipo en los próximos minutos.

Por favor, mantenga el equipo encendido y disponible.

Saludos,
Soporte TI', TRUE),

('Problema resuelto', 'Estimado(a) usuario(a),

El problema ha sido solucionado correctamente.

Si vuelve a presentar inconvenientes, no dude en contactarnos.

Saludos,
Área de Informática', TRUE),

('Reseteo de contraseña', 'Estimado(a) usuario(a),

Su contraseña ha sido restablecida. La contraseña temporal es:

Contraseña: [CONTRASEÑA_TEMP]

Por seguridad, cámbiela inmediatamente al ingresar.

Saludos,
Soporte TI', TRUE),

('Escalado a proveedor', 'Estimado(a) usuario(a),

Su solicitud ha sido escalada al proveedor especializado para su revisión.

Le informaré cuando tengamos una respuesta.

Tiempo estimado: [TIEMPO]

Saludos,
Soporte TI', TRUE),

('Equipo para reparación', 'Estimado(a) usuario(a),

Es necesario retirar su equipo para realizar la reparación en nuestro taller.

Se le proporcionará un equipo de respaldo mientras tanto.

Tiempo estimado de reparación: [TIEMPO]

Saludos,
Soporte TI', TRUE),

('Instalación programada', 'Estimado(a) usuario(a),

La instalación solicitada ha sido programada para:

Fecha: [FECHA]
Hora aproximada: [HORA]

Por favor, asegúrese de estar disponible.

Saludos,
Soporte TI', TRUE);

-- ============================================
-- ETIQUETAS PARA SOPORTE TI
-- ============================================
DELETE FROM etiquetas;

INSERT INTO etiquetas (nombre, color) VALUES
('urgente', '#e74c3c'),
('vip', '#9b59b6'),
('remoto', '#3498db'),
('presencial', '#27ae60'),
('esperando-repuesto', '#f39c12'),
('escalado', '#e67e22'),
('recurrente', '#95a5a6'),
('nuevo-funcionario', '#1abc9c');

-- ============================================
-- PRIORIDADES AJUSTADAS PARA TI
-- ============================================
UPDATE prioridades SET tiempo_respuesta_horas = 1, tiempo_resolucion_horas = 4 WHERE nombre = 'Crítica';
UPDATE prioridades SET tiempo_respuesta_horas = 2, tiempo_resolucion_horas = 8 WHERE nombre = 'Urgente';
UPDATE prioridades SET tiempo_respuesta_horas = 4, tiempo_resolucion_horas = 24 WHERE nombre = 'Alta';
UPDATE prioridades SET tiempo_respuesta_horas = 8, tiempo_resolucion_horas = 48 WHERE nombre = 'Normal';
UPDATE prioridades SET tiempo_respuesta_horas = 24, tiempo_resolucion_horas = 72 WHERE nombre = 'Baja';

-- ============================================
-- AUTOMATIZACIÓN: ASIGNAR POR CATEGORÍA
-- ============================================
DELETE FROM automatizaciones;

INSERT INTO automatizaciones (nombre, descripcion, evento, condiciones, acciones, activo) VALUES
('Auto-asignar por categoría', 'Asigna automáticamente el ticket al técnico responsable de la categoría', 'ticket_creado', 
 '[]', 
 '[{"tipo": "asignar_por_categoria"}]', TRUE),
 
('Notificar SLA próximo a vencer', 'Alerta cuando faltan 30 minutos para vencer el SLA de respuesta', 'tiempo',
 '[{"campo": "minutos_para_vencer_sla", "operador": "menor_que", "valor": 30}]',
 '[{"tipo": "notificar", "destinatario": "asignado"}]', TRUE),

('Marcar urgente si gerencia', 'Aumenta prioridad si el solicitante es de gerencia', 'ticket_creado',
 '[{"campo": "solicitante_departamento", "operador": "contiene", "valor": "gerencia"}]',
 '[{"tipo": "cambiar_prioridad", "valor": 4}, {"tipo": "agregar_etiqueta", "valor": "vip"}]', TRUE);

-- Actualizar SLA existentes
UPDATE sla_politicas SET tiempo_primera_respuesta_horas = 1, tiempo_resolucion_horas = 4 WHERE prioridad_id = 5;
UPDATE sla_politicas SET tiempo_primera_respuesta_horas = 2, tiempo_resolucion_horas = 8 WHERE prioridad_id = 4;
UPDATE sla_politicas SET tiempo_primera_respuesta_horas = 4, tiempo_resolucion_horas = 24 WHERE prioridad_id = 3;
UPDATE sla_politicas SET tiempo_primera_respuesta_horas = 8, tiempo_resolucion_horas = 48 WHERE prioridad_id = 2;
UPDATE sla_politicas SET tiempo_primera_respuesta_horas = 24, tiempo_resolucion_horas = 72 WHERE prioridad_id = 1;
