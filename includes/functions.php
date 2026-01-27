<?php
/**
 * Funciones del Sistema de Tickets Municipal
 */

// Establecer charset UTF-8 para todas las páginas
if (headers_sent() === false) {
    header('Content-Type: text/html; charset=utf-8');
}

require_once __DIR__ . '/../config/database.php';

function iniciarSesionSegura()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start(['cookie_httponly' => true, 'use_strict_mode' => true]);
    }
}

function estaAutenticado()
{
    iniciarSesionSegura();
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

function obtenerUsuarioActual()
{
    if (!estaAutenticado())
        return null;
    $db = Database::getInstance();
    return $db->fetch("SELECT u.*, d.nombre as departamento_nombre FROM usuarios u LEFT JOIN departamentos d ON u.departamento_id = d.id WHERE u.id = ? AND u.activo = TRUE", [$_SESSION['usuario_id']]);
}

// Función para crear notificación interna
function crearNotificacion($usuario_id, $titulo, $mensaje, $tipo = 'info', $ticket_id = null)
{
    if (!$usuario_id)
        return false;
    $db = Database::getInstance();
    $db->query(
        "INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, ticket_id) VALUES (?, ?, ?, ?, ?)",
        [$usuario_id, $titulo, $mensaje, $tipo, $ticket_id]
    );
    return true;
}

function contarNotificacionesNoLeidas($usuario_id)
{
    $db = Database::getInstance();
    $result = $db->fetch("SELECT COUNT(*) as total FROM notificaciones WHERE usuario_id = ? AND leido = FALSE", [$usuario_id]);
    return $result['total'] ?? 0;
}

function obtenerNotificaciones($usuario_id, $solo_no_leidas = false, $limite = 20)
{
    $db = Database::getInstance();
    $where = "WHERE usuario_id = ?";
    if ($solo_no_leidas) {
        $where .= " AND leido = FALSE";
    }

    return $db->fetchAll("
        SELECT n.*, t.numero as ticket_numero
        FROM notificaciones n
        LEFT JOIN tickets t ON n.ticket_id = t.id
        $where
        ORDER BY created_at DESC
        LIMIT $limite
    ", [$usuario_id]);
}

function marcarNotificacionLeida($notificacion_id)
{
    $db = Database::getInstance();
    $db->query("UPDATE notificaciones SET leido = TRUE WHERE id = ?", [$notificacion_id]);
}

function marcarTodasNotificacionesLeidas($usuario_id)
{
    $db = Database::getInstance();
    $db->query("UPDATE notificaciones SET leido = TRUE WHERE usuario_id = ?", [$usuario_id]);
}

// Alias para compatibilidad
function marcarNotificacionesLeidas($usuario_id)
{
    marcarTodasNotificacionesLeidas($usuario_id);
}

function tieneRol($roles)
{
    $usuario = obtenerUsuarioActual();
    if (!$usuario)
        return false;
    return in_array($usuario['rol'], is_array($roles) ? $roles : [$roles]);
}

function requiereAutenticacion()
{
    if (!estaAutenticado()) {
        header('Location: /login.php');
        exit;
    }
}

function requiereRol($roles)
{
    requiereAutenticacion();
    if (!tieneRol($roles)) {
        header('Location: /sin-permiso.php');
        exit;
    }
}

function obtenerTickets($filtros = [], $limite = 50, $offset = 0)
{
    $db = Database::getInstance();
    $where = ["1=1"];
    $params = [];

    if (!empty($filtros['estado_id'])) {
        $where[] = "t.estado_id = ?";
        $params[] = $filtros['estado_id'];
    }
    if (!empty($filtros['categoria_id'])) {
        $where[] = "t.categoria_id = ?";
        $params[] = $filtros['categoria_id'];
    }
    if (!empty($filtros['ciudadano_id'])) {
        $where[] = "t.ciudadano_id = ?";
        $params[] = $filtros['ciudadano_id'];
    }
    if (!empty($filtros['asignado_id'])) {
        $where[] = "t.asignado_id = ?";
        $params[] = $filtros['asignado_id'];
    }
    if (!empty($filtros['busqueda'])) {
        $where[] = "(t.numero ILIKE ? OR t.asunto ILIKE ?)";
        $params[] = '%' . $filtros['busqueda'] . '%';
        $params[] = '%' . $filtros['busqueda'] . '%';
    }

    $params[] = $limite;
    $params[] = $offset;
    return $db->fetchAll("SELECT t.*, e.nombre as estado, e.color as estado_color, p.nombre as prioridad, p.color as prioridad_color, c.nombre as categoria, d.nombre as departamento, CONCAT(uc.nombres, ' ', uc.apellidos) as ciudadano_nombre FROM tickets t LEFT JOIN estados e ON t.estado_id = e.id LEFT JOIN prioridades p ON t.prioridad_id = p.id LEFT JOIN categorias c ON t.categoria_id = c.id LEFT JOIN departamentos d ON c.departamento_id = d.id LEFT JOIN usuarios uc ON t.ciudadano_id = uc.id WHERE " . implode(' AND ', $where) . " ORDER BY t.created_at DESC LIMIT ? OFFSET ?", $params);
}

function obtenerTicket($id)
{
    $db = Database::getInstance();
    return $db->fetch("SELECT t.*, e.nombre as estado, e.color as estado_color, p.nombre as prioridad, p.color as prioridad_color, c.nombre as categoria, d.nombre as departamento, d.id as departamento_id, CONCAT(uc.nombres, ' ', uc.apellidos) as ciudadano_nombre, uc.email as ciudadano_email, CONCAT(ua.nombres, ' ', ua.apellidos) as asignado_nombre FROM tickets t LEFT JOIN estados e ON t.estado_id = e.id LEFT JOIN prioridades p ON t.prioridad_id = p.id LEFT JOIN categorias c ON t.categoria_id = c.id LEFT JOIN departamentos d ON c.departamento_id = d.id LEFT JOIN usuarios uc ON t.ciudadano_id = uc.id LEFT JOIN usuarios ua ON t.asignado_id = ua.id WHERE t.id = ?", [$id]);
}

function obtenerTicketPorNumero($numero)
{
    $db = Database::getInstance();
    $ticket = $db->fetch("SELECT id FROM tickets WHERE numero = ?", [$numero]);
    return $ticket ? obtenerTicket($ticket['id']) : null;
}

function crearTicket($datos)
{
    $db = Database::getInstance();
    $es_anonimo = !empty($datos['es_anonimo']) ? 'true' : 'false';
    
    // Buscar técnico asignado automáticamente según la categoría
    $asignado_id = null;
    if (!empty($datos['categoria_id'])) {
        $asignacion = $db->fetch(
            "SELECT usuario_id FROM categoria_asignacion WHERE categoria_id = ? AND es_principal = TRUE LIMIT 1",
            [$datos['categoria_id']]
        );
        if ($asignacion) {
            $asignado_id = $asignacion['usuario_id'];
        }
    }

    $stmt = $db->query(
        "INSERT INTO tickets (ciudadano_id, categoria_id, prioridad_id, asunto, descripcion, ubicacion_direccion, es_anonimo, asignado_id, numero) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '') RETURNING id, numero",
        [$datos['ciudadano_id'], $datos['categoria_id'], $datos['prioridad_id'] ?? 2, $datos['asunto'], $datos['descripcion'], $datos['ubicacion_direccion'] ?? null, $es_anonimo, $asignado_id]
    );
    return $stmt->fetch();
}

function actualizarTicket($id, $datos)
{
    $db = Database::getInstance();
    $campos = [];
    $valores = [];
    foreach (['categoria_id', 'estado_id', 'prioridad_id', 'asignado_id'] as $campo) {
        if (isset($datos[$campo])) {
            $campos[] = "$campo = ?";
            $valores[] = $datos[$campo];
        }
    }
    if (empty($campos))
        return false;
    $campos[] = "updated_at = CURRENT_TIMESTAMP";
    $valores[] = $id;
    return $db->query("UPDATE tickets SET " . implode(', ', $campos) . " WHERE id = ?", $valores);
}

function obtenerComentarios($ticket_id)
{
    $db = Database::getInstance();
    return $db->fetchAll("SELECT tc.*, CONCAT(u.nombres, ' ', u.apellidos) as autor, u.rol FROM ticket_comentarios tc JOIN usuarios u ON tc.usuario_id = u.id WHERE tc.ticket_id = ? ORDER BY tc.created_at ASC", [$ticket_id]);
}

function agregarComentario($ticket_id, $usuario_id, $comentario, $es_interno = false)
{
    $db = Database::getInstance();
    // Convertir booleano a formato PostgreSQL
    $es_interno_pg = $es_interno ? 'true' : 'false';
    $db->query("INSERT INTO ticket_comentarios (ticket_id, usuario_id, comentario, es_interno) VALUES (?, ?, ?, ?)", [$ticket_id, $usuario_id, $comentario, $es_interno_pg]);
    $db->query("UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$ticket_id]);
    return true;
}

function obtenerEstados()
{
    return Database::getInstance()->fetchAll("SELECT * FROM estados WHERE activo = TRUE ORDER BY orden");
}
function obtenerPrioridades()
{
    return Database::getInstance()->fetchAll("SELECT * FROM prioridades WHERE activo = TRUE ORDER BY nivel");
}
function obtenerDepartamentos()
{
    return Database::getInstance()->fetchAll("SELECT * FROM departamentos WHERE activo = TRUE ORDER BY nombre");
}
function obtenerCategorias($dep_id = null)
{
    $db = Database::getInstance();
    return $dep_id ? $db->fetchAll("SELECT c.*, d.nombre as departamento FROM categorias c JOIN departamentos d ON c.departamento_id = d.id WHERE c.activo = TRUE AND c.departamento_id = ? ORDER BY c.nombre", [$dep_id]) : $db->fetchAll("SELECT c.*, d.nombre as departamento FROM categorias c JOIN departamentos d ON c.departamento_id = d.id WHERE c.activo = TRUE ORDER BY d.nombre, c.nombre");
}

function obtenerTodasCategorias()
{
    $db = Database::getInstance();
    return $db->fetchAll("SELECT c.*, d.nombre as departamento FROM categorias c JOIN departamentos d ON c.departamento_id = d.id WHERE c.activo = TRUE ORDER BY c.nombre");
}
function obtenerFuncionarios($dep_id = null)
{
    $db = Database::getInstance();
    return $dep_id ? $db->fetchAll("SELECT id, nombres, apellidos, email, rol FROM usuarios WHERE rol IN ('funcionario', 'supervisor', 'admin') AND activo = TRUE AND departamento_id = ?", [$dep_id]) : $db->fetchAll("SELECT id, nombres, apellidos, email, rol FROM usuarios WHERE rol IN ('funcionario', 'supervisor', 'admin') AND activo = TRUE");
}

function obtenerEstadisticas()
{
    $db = Database::getInstance();
    return [
        'total' => $db->fetch("SELECT COUNT(*) as total FROM tickets")['total'],
        'pendientes' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE estado_id = 1")['total'],
        'en_proceso' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE estado_id IN (2,3)")['total'],
        'resueltos' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE estado_id IN (5,6)")['total'],
        'hoy' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE DATE(created_at) = CURRENT_DATE")['total']
    ];
}

function limpiarInput($data)
{
    return is_array($data) ? array_map('limpiarInput', $data) : htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
function formatearFecha($fecha, $formato = 'd/m/Y H:i')
{
    return $fecha ? (new DateTime($fecha))->format($formato) : '-';
}
function tiempoTranscurrido($fecha)
{
    $diff = (new DateTime())->diff(new DateTime($fecha));
    if ($diff->d > 0)
        return "hace {$diff->d} día" . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0)
        return "hace {$diff->h} hora" . ($diff->h > 1 ? 's' : '');
    if ($diff->i > 0)
        return "hace {$diff->i} min";
    return "ahora";
}
function respuestaJson($data, $codigo = 200)
{
    http_response_code($codigo);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function registrarHistorial($ticket_id, $usuario_id, $accion, $descripcion, $valor_anterior = null, $valor_nuevo = null)
{
    $db = Database::getInstance();
    $db->query(
        "INSERT INTO ticket_historial (ticket_id, usuario_id, accion, descripcion, valor_anterior, valor_nuevo) VALUES (?, ?, ?, ?, ?, ?)",
        [$ticket_id, $usuario_id, $accion, $descripcion, $valor_anterior, $valor_nuevo]
    );
}
