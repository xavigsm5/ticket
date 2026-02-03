-- ============================================
-- ACTUALIZACIÓN: Almacenar adjuntos en base de datos
-- ============================================

-- Eliminar tabla anterior si existe
DROP TABLE IF EXISTS ticket_adjuntos CASCADE;

-- Crear tabla para almacenar archivos en base de datos
CREATE TABLE ticket_adjuntos (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    usuario_id INT NOT NULL REFERENCES usuarios(id),
    nombre_original VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100),
    tamano INT,
    contenido BYTEA NOT NULL,  -- Almacenar el archivo directamente
    es_imagen BOOLEAN DEFAULT FALSE,
    convertido_webp BOOLEAN DEFAULT FALSE,  -- Indica si se convirtió a WebP
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para mejor rendimiento
CREATE INDEX idx_ticket_adjuntos_ticket ON ticket_adjuntos(ticket_id);
CREATE INDEX idx_ticket_adjuntos_usuario ON ticket_adjuntos(usuario_id);

-- Comentarios
COMMENT ON TABLE ticket_adjuntos IS 'Almacena archivos adjuntos de tickets directamente en la base de datos';
COMMENT ON COLUMN ticket_adjuntos.contenido IS 'Contenido binario del archivo';
COMMENT ON COLUMN ticket_adjuntos.convertido_webp IS 'TRUE si la imagen original fue convertida a WebP para reducir tamaño';
