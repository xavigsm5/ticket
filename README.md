# Sistema de Tickets Municipal

Sistema profesional de gestión de tickets para municipalidades, desarrollado en PHP con PostgreSQL.

## 🏛️ Características

- **Portal Ciudadano**: Registro, creación y seguimiento de solicitudes
- **Panel Administrativo**: Gestión completa de tickets para funcionarios
- **Gestión por Departamentos**: Categorización por áreas municipales
- **Sistema de Comentarios**: Comunicación bidireccional
- **Estadísticas en Tiempo Real**: Dashboard con métricas
- **Roles de Usuario**: Ciudadano, Funcionario, Supervisor, Administrador

## 📋 Requisitos

- PHP 7.4 o superior
- PostgreSQL 12 o superior
- Extensiones PHP: pdo, pdo_pgsql

## 🚀 Instalación

### 1. Configurar Base de Datos

Crear base de datos en PostgreSQL:

```sql
CREATE DATABASE tickets_municipal;
```

Ejecutar el script de esquema:

```bash
psql -U postgres -d tickets_municipal -f database/schema.sql
```

### 2. Configurar Conexión

Editar `config/database.php` con los datos de conexión:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'tickets_municipal');
define('DB_USER', 'postgres');
define('DB_PASS', 'tu_contraseña');
```

### 3. Configurar Servidor Web

#### Apache (con .htaccess)

Asegúrese de que mod_rewrite esté habilitado.

#### PHP Built-in Server (desarrollo)

```bash
cd /ruta/al/proyecto
php -S localhost:8000
```

### 4. Acceso Inicial

Usuario administrador por defecto:

- **Email**: admin@municipalidad.cl
- **Contraseña**: password

⚠️ **Cambiar la contraseña inmediatamente después del primer acceso.**

## 📁 Estructura del Proyecto

```
Ticket/
├── admin/                  # Panel administrativo
│   ├── dashboard.php
│   ├── tickets.php
│   └── ticket-detalle.php
├── api/                    # Endpoints API
│   └── index.php
├── assets/
│   └── css/
│       └── style.css       # Estilos del sistema
├── ciudadano/              # Portal ciudadano
│   ├── mis-tickets.php
│   └── ticket.php
├── config/
│   └── database.php        # Configuración BD
├── database/
│   └── schema.sql          # Esquema de BD
├── includes/
│   └── functions.php       # Funciones del sistema
├── uploads/                # Archivos adjuntos
├── index.php               # Página principal
├── login.php               # Inicio de sesión
├── logout.php              # Cerrar sesión
├── nuevo-ticket.php        # Crear ticket público
└── registro.php            # Registro de usuarios
```

## 👥 Roles de Usuario

| Rol             | Descripción                            |
| --------------- | -------------------------------------- |
| **ciudadano**   | Puede crear y ver sus propios tickets  |
| **funcionario** | Gestiona tickets de su departamento    |
| **supervisor**  | Gestiona tickets y usuarios de su área |
| **admin**       | Acceso total al sistema                |

## 🔧 Configuración Adicional

### Correo Electrónico

Para habilitar notificaciones por email, configurar las constantes SMTP en `config/database.php`.

### Uploads

El directorio `uploads/` debe tener permisos de escritura:

```bash
chmod 755 uploads/
```

## 📊 Estados de Tickets

1. **Pendiente** - Recién creado
2. **En Revisión** - Siendo evaluado
3. **En Proceso** - En trabajo
4. **En Espera** - Requiere información adicional
5. **Resuelto** - Solucionado
6. **Cerrado** - Finalizado
7. **Rechazado** - No procede

## 🔒 Seguridad

- Contraseñas hasheadas con bcrypt
- Protección contra SQL Injection (PDO prepared statements)
- Sanitización de inputs
- Sesiones seguras
- Validación de RUT chileno

## 📝 Licencia

Sistema desarrollado para uso institucional municipal.

---


