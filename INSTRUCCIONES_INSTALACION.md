# 📋 Instrucciones de Instalación - Sistema de Tickets Municipal

## Requisitos Previos

- Docker Desktop instalado y en ejecución
- Git instalado
- Puerto 8080 disponible (o modificar en `docker-compose.yml`)

## 🚀 Instalación Rápida

### 1. Clonar el repositorio

```bash
git clone https://github.com/TU_USUARIO/TU_REPOSITORIO.git
cd TU_REPOSITORIO
```

### 2. Crear archivo de configuración de base de datos

Crear el archivo `config/database.php` con el siguiente contenido:

```php
<?php
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $host = getenv('DB_HOST') ?: 'tickets_db';
        $port = getenv('DB_PORT') ?: '5432';
        $dbname = getenv('DB_NAME') ?: 'tickets_municipal';
        $user = getenv('DB_USER') ?: 'postgres';
        $password = getenv('DB_PASSWORD') ?: 'postgres123';
        
        try {
            $this->conn = new PDO(
                "pgsql:host=$host;port=$port;dbname=$dbname",
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $this->conn->exec("SET NAMES 'UTF8'");
        } catch(PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params);
    }
    
    public function insert($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result[0]['id'] ?? null;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}
```

### 3. Levantar los contenedores

```bash
docker-compose up -d
```

Esto creará y ejecutará:
- Servidor web PHP con Apache (puerto 8080)
- Base de datos PostgreSQL (puerto 5432)

### 4. Inicializar la base de datos

```bash
# Ejecutar el schema principal
docker exec -i tickets_db psql -U postgres -d tickets_municipal < database/schema.sql

# Ejecutar funcionalidades Freshdesk
docker exec -i tickets_db psql -U postgres -d tickets_municipal < database/freshdesk_schema.sql
docker exec -i tickets_db psql -U postgres -d tickets_municipal < database/freshdesk_features.sql

# Actualizar tabla de adjuntos para WebP
docker exec -i tickets_db psql -U postgres -d tickets_municipal < database/update_adjuntos_bytea.sql

# Crear usuarios de TI
docker exec -i tickets_db psql -U postgres -d tickets_municipal < database/crear_funcionarios_ti.sql
```

### 5. Acceder al sistema

Abrir el navegador en: **http://localhost:8080**

## 👥 Usuarios de Prueba

### Administrador
- **Email:** admin@municipal.gob
- **Password:** admin123

### Supervisor TI
- **Email:** supervisor.ti@municipal.gob
- **Password:** supervisor123

### Funcionario TI
- **Email:** funcionario.ti@municipal.gob
- **Password:** funcionario123

## 🧪 Verificar Instalación

1. **Verificar conversión WebP:**
   - Visitar: http://localhost:8080/verificar-webp.php
   - Debe mostrar ✅ en todas las verificaciones

2. **Crear ticket de prueba:**
   - Ir a: http://localhost:8080/nuevo-ticket.php
   - Subir imágenes JPG/PNG
   - Verificar que se conviertan a WebP

## 🛠️ Comandos Útiles

### Ver logs de los contenedores
```bash
docker-compose logs -f
```

### Reiniciar servicios
```bash
docker-compose restart
```

### Detener servicios
```bash
docker-compose down
```

### Reconstruir contenedores (si hay cambios en Dockerfile)
```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Acceder a la base de datos
```bash
docker exec -it tickets_db psql -U postgres -d tickets_municipal
```

## 📝 Configuración Adicional

### Cambiar puerto del servidor web

Editar `docker-compose.yml` y cambiar:
```yaml
ports:
  - "8080:80"  # Cambiar 8080 por el puerto deseado
```

### Variables de entorno

Crear archivo `.env` para configuración personalizada:
```env
DB_HOST=tickets_db
DB_PORT=5432
DB_NAME=tickets_municipal
DB_USER=postgres
DB_PASSWORD=postgres123
```

## 🐛 Solución de Problemas

### Puerto 8080 ya está en uso
```bash
# Cambiar puerto en docker-compose.yml o detener el servicio que lo usa
netstat -ano | findstr :8080
```

### Error de conexión a la base de datos
```bash
# Verificar que el contenedor de BD esté corriendo
docker ps

# Ver logs de la BD
docker logs tickets_db
```

### Permisos de archivos
```bash
# Dar permisos a la carpeta uploads
docker exec tickets_web chown -R www-data:www-data /var/www/html/uploads
docker exec tickets_web chmod -R 755 /var/www/html/uploads
```

## 📧 Soporte

Para problemas o dudas, contactar al equipo de desarrollo.

---

**Última actualización:** Febrero 2026
