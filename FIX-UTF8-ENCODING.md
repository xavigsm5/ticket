# Solución: Caracteres con ?? en nuevo-ticket.php

## Problema
Los nombres de las categorías se muestran con `??` en lugar de caracteres especiales como tildes (á, é, í, ó, ú, ñ).

## Causa
Problema de codificación de caracteres UTF-8 entre la base de datos PostgreSQL y la aplicación PHP.

## Soluciones Implementadas

### 1. Configuración de Base de Datos (`config/database.php`)
- ✓ Añadido `options='--client_encoding=UTF8'` al DSN
- ✓ Añadido comando `SET client_encoding = 'UTF8'` después de conectar

### 2. Configuración de PHP (`includes/functions.php`)
- ✓ Añadido header `Content-Type: text/html; charset=utf-8` automáticamente

### 3. Scripts SQL
- ✓ Añadido `SET client_encoding = 'UTF8'` al inicio de:
  - `database/schema.sql`
  - `database/soporte_ti.sql`

## Pasos para Aplicar la Solución

### Opción A: Si la base de datos ya existe
1. Conectar a PostgreSQL:
   ```bash
   psql -U postgres -d tickets_municipal
   ```

2. Ejecutar en la consola SQL:
   ```sql
   SET client_encoding = 'UTF8';
   SELECT datname, encoding, encoding_name FROM pg_database WHERE datname = 'tickets_municipal';
   ```

3. Si el encoding no es UTF8, recrear la base de datos:
   ```bash
   dropdb tickets_municipal
   createdb -U postgres -E UTF8 -T template0 tickets_municipal
   psql -U postgres -d tickets_municipal < database/schema.sql
   psql -U postgres -d tickets_municipal < database/soporte_ti.sql
   ```

### Opción B: Verificar desde la Web
1. Abrir en el navegador: `http://localhost/repair-encoding.php`
2. Verificar si los caracteres especiales se ven correctamente
3. Si ves las tildes y ñ correctamente en esa página, el problema está resuelto

### Opción C: Desde Docker
Si usas Docker, asegúrate que el contenedor PostgreSQL tenga UTF-8:

```yaml
# docker-compose.yml
services:
  postgres:
    environment:
      POSTGRES_INITDB_ARGS: "-E UTF8"
```

## Verificación
Después de aplicar cualquier solución:

1. Ir a `http://localhost/nuevo-ticket.php`
2. Verificar que se vean correctamente:
   - "Redes y Conectividad"
   - "Accesos y Permisos"
   - "Contraseña"
   - "Telefonía IP"
   - "Configuración de Red"
   - Etc.

Si ves ?? en lugar de caracteres especiales, aún hay un problema.

## Ayuda
- Ejecuta `repair-encoding.php` para diagnosticar
- Verifica que `meta charset="UTF-8"` esté en el HTML (ya está configurado)
- Revisa los logs de PostgreSQL para errores de encoding
