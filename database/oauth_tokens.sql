-- Tabla para almacenar tokens OAuth2 de usuarios
CREATE TABLE IF NOT EXISTS oauth_tokens (
    id SERIAL PRIMARY KEY,
    usuario_id INT UNIQUE REFERENCES usuarios(id) ON DELETE CASCADE,
    access_token TEXT,
    refresh_token TEXT,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_oauth_tokens_usuario ON oauth_tokens(usuario_id);
