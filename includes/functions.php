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
    
    $ticketCreado = $stmt->fetch();
    
    // Enviar notificación por correo a funcionarios de TI
    if ($ticketCreado) {
        enviarNotificacionNuevoTicketATI($ticketCreado, $datos);
    }
    
    return $ticketCreado;
}

/**
 * Enviar notificación por correo al funcionario asignado y administrador
 */
function enviarNotificacionNuevoTicketATI($ticket, $datos)
{
    try {
        $db = Database::getInstance();
        
        // Obtener información del ciudadano/creador
        $creador = $db->fetch(
            "SELECT nombres, apellidos, email, rol FROM usuarios WHERE id = ?",
            [$datos['ciudadano_id']]
        );
        
        // Obtener categoría del ticket
        $categoria = $db->fetch(
            "SELECT nombre FROM categorias WHERE id = ?",
            [$datos['categoria_id']]
        );
        
        // Obtener prioridad
        $prioridad = $db->fetch(
            "SELECT nombre, color FROM prioridades WHERE id = ?",
            [$datos['prioridad_id'] ?? 2]
        );
        
        // Obtener correos de destinatarios
        $correosFuncionarios = [];
        
        // 1. Buscar el ticket completo para obtener el asignado_id
        $ticketCompleto = $db->fetch(
            "SELECT asignado_id FROM tickets WHERE id = ?",
            [$ticket['id']]
        );
        
        // 2. Si hay un funcionario asignado, agregarlo
        if ($ticketCompleto && $ticketCompleto['asignado_id']) {
            $funcionarioAsignado = $db->fetch(
                "SELECT email FROM usuarios WHERE id = ? AND activo = TRUE",
                [$ticketCompleto['asignado_id']]
            );
            if ($funcionarioAsignado) {
                $correosFuncionarios[] = $funcionarioAsignado['email'];
            }
        }
        
        // 3. Agregar el administrador principal
        $admin = $db->fetch(
            "SELECT email FROM usuarios WHERE rol = 'admin' AND activo = TRUE ORDER BY id ASC LIMIT 1"
        );
        if ($admin && !in_array($admin['email'], $correosFuncionarios)) {
            $correosFuncionarios[] = $admin['email'];
        }
        
        // Si no hay destinatarios, salir
        if (empty($correosFuncionarios)) {
            error_log("No se encontraron destinatarios para notificar el ticket #{$ticket['numero']}");
            return;
        }
        
        // Preparar el contenido del correo
        $nombreCreador = $creador ? ($creador['nombres'] . ' ' . $creador['apellidos']) : 'Usuario';
        $emailCreador = $creador['email'] ?? '';
        $nombreCategoria = $categoria['nombre'] ?? 'Sin categoría';
        $nombrePrioridad = $prioridad['nombre'] ?? 'Normal';
        $colorPrioridad = $prioridad['color'] ?? '#6c757d';
        
        // Obtener adjuntos del ticket
        $adjuntos = $db->fetchAll(
            "SELECT nombre_archivo, ruta_archivo FROM ticket_adjuntos WHERE ticket_id = ?",
            [$ticket['id']]
        );
        
        $adjuntosHtml = '';
        if (!empty($adjuntos)) {
            $adjuntosHtml = "<hr style='border: none; border-top: 1px solid #dee2e6; margin: 15px 0;'>
                <p><span class='label'>Archivos Adjuntos:</span></p>
                <ul style='margin: 5px 0; padding-left: 20px;'>";
            foreach ($adjuntos as $adj) {
                $adjuntosHtml .= "<li><a href='http://localhost:8080/{$adj['ruta_archivo']}' style='color: #0d6efd;'>{$adj['nombre_archivo']}</a></li>";
            }
            $adjuntosHtml .= "</ul>";
        }
        
        $asunto = "Nuevo Ticket #{$ticket['numero']} - {$datos['asunto']}";
        
        $cuerpo = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0d6efd; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
                .ticket-info { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid {$colorPrioridad}; }
                .label { font-weight: bold; color: #495057; }
                .btn { display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
                .footer { text-align: center; color: #6c757d; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🎫 Nuevo Ticket Creado</h2>
                </div>
                <div class='content'>
                    <div class='ticket-info'>
                        <p><span class='label'>Número de Ticket:</span> #{$ticket['numero']}</p>
                        <p><span class='label'>Asunto:</span> {$datos['asunto']}</p>
                        <p><span class='label'>Categoría:</span> {$nombreCategoria}</p>
                        <p><span class='label'>Prioridad:</span> <span style='color: {$colorPrioridad}; font-weight: bold;'>{$nombrePrioridad}</span></p>
                        <p><span class='label'>Solicitante:</span> {$nombreCreador} ({$emailCreador})</p>
                        <hr style='border: none; border-top: 1px solid #dee2e6; margin: 15px 0;'>
                        <p><span class='label'>Descripción:</span></p>
                        <p style='background: #e9ecef; padding: 10px; border-radius: 4px;'>" . nl2br(htmlspecialchars($datos['descripcion'])) . "</p>
                        {$adjuntosHtml}
                    </div>
                    <a href='http://localhost:8080/admin/ticket-detalle.php?id={$ticket['id']}' class='btn'>Ver Ticket Completo</a>
                </div>
                <div class='footer'>
                    <p>Sistema de Tickets - Mesa de Ayuda Municipal</p>
                    <p>Este es un correo automático, por favor no responder.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Determinar método de envío según el rol del creador
        require_once __DIR__ . '/MailHandler.php';
        $mailHandler = new MailHandler();
        
        // Si el creador es funcionario y tiene tokens OAuth2, enviar desde su cuenta con su firma
        if ($creador && in_array($creador['rol'], ['funcionario', 'admin', 'supervisor'])) {
            // Verificar si el funcionario tiene tokens OAuth2
            $tieneTokens = $db->fetch(
                "SELECT id FROM oauth_tokens WHERE usuario_id = ?",
                [$datos['ciudadano_id']]
            );
            
            if ($tieneTokens) {
                // Enviar desde la cuenta del funcionario usando Microsoft Graph API
                // Esto incluirá automáticamente la firma de Outlook del usuario
                error_log("Enviando notificación de ticket #{$ticket['numero']} desde cuenta del funcionario ID: {$datos['ciudadano_id']}");
                
                // Enviar al primer destinatario con los demás en CC
                $destinatarioPrincipal = array_shift($correosFuncionarios);
                $envioExitoso = $mailHandler->enviarCorreoDesdeUsuario(
                    $datos['ciudadano_id'],
                    $destinatarioPrincipal,
                    $asunto,
                    $cuerpo,
                    $correosFuncionarios // Los demás en CC
                );
                
                if ($envioExitoso) {
                    error_log("Notificación enviada exitosamente desde cuenta del funcionario");
                } else {
                    error_log("Error al enviar desde cuenta del funcionario, usando método SMTP de respaldo");
                    // Fallback a SMTP genérico
                    foreach (array_merge([$destinatarioPrincipal], $correosFuncionarios) as $correo) {
                        $mailHandler->enviarCorreo($correo, $asunto, $cuerpo);
                    }
                }
                return;
            }
        }
        
        // Envío por defecto: usar SMTP genérico (para ciudadanos o funcionarios sin OAuth)
        error_log("Enviando notificación de ticket #{$ticket['numero']} usando SMTP genérico");
        foreach ($correosFuncionarios as $correo) {
            $mailHandler->enviarCorreo($correo, $asunto, $cuerpo);
        }
        
        error_log("Notificación de ticket #{$ticket['numero']} enviada a: " . implode(', ', $correosFuncionarios));
        
    } catch (Exception $e) {
        error_log("Error enviando notificación de ticket: " . $e->getMessage());
        // No detener la creación del ticket si falla el correo
    }
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
    return $db->fetchAll("SELECT c.*, d.nombre as departamento FROM categorias c LEFT JOIN departamentos d ON c.departamento_id = d.id WHERE c.activo = TRUE ORDER BY c.nombre");
}
function obtenerFuncionarios($dep_id = null)
{
    $db = Database::getInstance();
    return $dep_id ? $db->fetchAll("SELECT id, nombres, apellidos, email, rol FROM usuarios WHERE rol IN ('soporte_ti', 'admin') AND activo = TRUE AND departamento_id = ?", [$dep_id]) : $db->fetchAll("SELECT id, nombres, apellidos, email, rol FROM usuarios WHERE rol IN ('soporte_ti', 'admin') AND activo = TRUE");
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

function obtenerEstadisticasUsuario($usuario_id)
{
    $db = Database::getInstance();
    return [
        'total' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE asignado_id = ?", [$usuario_id])['total'],
        'pendientes' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE estado_id = 1 AND asignado_id = ?", [$usuario_id])['total'],
        'en_proceso' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE estado_id IN (2,3) AND asignado_id = ?", [$usuario_id])['total'],
        'resueltos' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE estado_id IN (5,6) AND asignado_id = ?", [$usuario_id])['total'],
        'hoy' => $db->fetch("SELECT COUNT(*) as total FROM tickets WHERE DATE(created_at) = CURRENT_DATE AND asignado_id = ?", [$usuario_id])['total']
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
