<?php
/**
 * Funciones principales del sistema
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

// Obtener URL base desde .env o generar una
function getBaseUrl() {
    // Intentar cargar desde .env si existe
    if (isset($_ENV['APP_URL']) && !empty($_ENV['APP_URL'])) {
        return rtrim($_ENV['APP_URL'], '/');
    }
    
    // Fallback: construir desde variables de servidor
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

function requiereAutenticacion()
{
    if (!estaAutenticado()) {
        $baseUrl = getBaseUrl();
        header('Location: ' . $baseUrl . '/login.php');
        exit;
    }
}

function requiereRol($roles)
{
    requiereAutenticacion();
    if (!tieneRol($roles)) {
        $baseUrl = getBaseUrl();
        header('Location: ' . $baseUrl . '/sin-permiso.php');
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

// Notificar por correo al técnico asignado
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
    
    // Enviar notificación al ciudadano si es respuesta de admin/supervisor/soporte_ti y NO es interno
    if (!$es_interno) {
        $usuario = $db->fetch("SELECT id, rol FROM usuarios WHERE id = ?", [$usuario_id]);
        if ($usuario && in_array($usuario['rol'], ['admin', 'supervisor', 'funcionario', 'soporte_ti'])) {
            $ticket = obtenerTicket($ticket_id);
            if ($ticket && $ticket['ciudadano_email']) {
                error_log("Intentando enviar notificación a: " . $ticket['ciudadano_email']);
                $resultado = enviarNotificacionRespuestaTicket($ticket, $usuario_id, $comentario);
                if ($resultado) {
                    error_log("Notificación enviada exitosamente a: " . $ticket['ciudadano_email']);
                } else {
                    error_log("Fallo al enviar notificación a: " . $ticket['ciudadano_email']);
                }
            } else {
                if (!$ticket) {
                    error_log("Error: Ticket no encontrado para ID: $ticket_id");
                }
                if (!$ticket['ciudadano_email']) {
                    error_log("Error: Ticket sin email de ciudadano. Ticket ID: $ticket_id");
                }
            }
        }
    }
    
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

/**
 * Obtiene los adjuntos de un ticket
 * 
 * @param int $ticket_id ID del ticket
 * @return array Lista de adjuntos
 */
function obtenerAdjuntosTicket($ticket_id)
{
    require_once __DIR__ . '/ImageHandler.php';
    return ImageHandler::obtenerAdjuntosTicket($ticket_id);
}

/**
 * Muestra HTML para los adjuntos de un ticket
 * 
 * @param int $ticket_id ID del ticket
 * @return string HTML con los adjuntos
 */
function mostrarAdjuntosHTML($ticket_id)
{
    $adjuntos = obtenerAdjuntosTicket($ticket_id);
    
    if (empty($adjuntos)) {
        return '';
    }
    
    $html = '<div class="adjuntos-lista">';
    $html .= '<h6 style="margin-bottom: 12px;"><i class="bi bi-paperclip"></i> Archivos Adjuntos (' . count($adjuntos) . ')</h6>';
    
    // Separar imágenes de otros archivos
    $imagenes = [];
    $otros_archivos = [];
    
    foreach ($adjuntos as $adjunto) {
        if ($adjunto['es_imagen']) {
            $imagenes[] = $adjunto;
        } else {
            $otros_archivos[] = $adjunto;
        }
    }
    
    // Mostrar previsualizaciones de imágenes
    if (!empty($imagenes)) {
        $html .= '<div class="galeria-adjuntos" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-bottom: 16px;">';
        
        foreach ($imagenes as $img) {
            $html .= '<div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #f7fafc;">';
            $html .= '<a href="/descargar-adjunto.php?id=' . $img['id'] . '" target="_blank" style="display: block; text-decoration: none; height: 140px; overflow: hidden;">';
            $html .= '<img src="/descargar-adjunto.php?id=' . $img['id'] . '" alt="' . htmlspecialchars($img['nombre_original']) . '" style="width: 100%; height: 100%; object-fit: cover; cursor: pointer;">';
            $html .= '</a>';
            $html .= '<div style="padding: 8px; font-size: 0.75rem; text-align: center; border-top: 1px solid #e2e8f0; background: white;">';
            $html .= '<small style="color: #718096; display: block; word-break: break-word;">' . htmlspecialchars(substr($img['nombre_original'], 0, 20)) . '</small>';
            if ($img['convertido_webp']) {
                $html .= '<span class="badge bg-success" style="font-size: 0.65rem; margin-top: 4px;">WebP</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    // Mostrar otros archivos como lista
    if (!empty($otros_archivos)) {
        $html .= '<div class="lista-archivos">';
        $html .= '<div style="margin-bottom: 8px; font-size: 0.875rem; font-weight: 500; color: #2d3748;">Otros Archivos</div>';
        $html .= '<div class="list-group" style="list-style: none; padding: 0;">';
        
        foreach ($otros_archivos as $archivo) {
            $tamano_kb = round($archivo['tamano'] / 1024, 2);
            $html .= '<a href="/descargar-adjunto.php?id=' . $archivo['id'] . '" class="list-group-item list-group-item-action" target="_blank" style="padding: 8px 12px; border: 1px solid #e2e8f0; margin-bottom: 4px; border-radius: 4px; text-decoration: none; color: inherit; display: flex; align-items: center; gap: 8px;">';
            $html .= '<i class="bi bi-file-earmark"></i>';
            $html .= '<span style="flex: 1;">' . htmlspecialchars($archivo['nombre_original']) . '</span>';
            $html .= '<small style="color: #718096;">(' . $tamano_kb . ' KB)</small>';
            $html .= '</a>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Enviar notificación de respuesta al ciudadano que creó el ticket
 */
function enviarNotificacionRespuestaTicket($ticket, $usuario_respondente_id, $comentario)
{
    try {
        $db = Database::getInstance();
        
        // Obtener datos del usuario que responde
        $usuario_respondente = $db->fetch("SELECT id, nombres, apellidos, email FROM usuarios WHERE id = ?", [$usuario_respondente_id]);
        
        if (!$usuario_respondente) {
            error_log("Error: Usuario respondente no encontrado. ID: $usuario_respondente_id");
            return false;
        }
        
        if (!$ticket['ciudadano_email']) {
            error_log("Error: Email del ciudadano no disponible en ticket ID: " . $ticket['id']);
            return false;
        }
        
        // Preparar contenido HTML del correo
        $nombre_respondente = $usuario_respondente['nombres'] . ' ' . $usuario_respondente['apellidos'];
        $baseUrl = getBaseUrl();
        
        error_log("Preparando correo para: " . $ticket['ciudadano_email'] . " desde: " . $nombre_respondente);
        
        $html = "
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #0066cc; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
                    .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
                    .ticket-info { background-color: white; padding: 10px; margin: 10px 0; border-left: 4px solid #0066cc; }
                    .mensaje-box { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #e0e0e0; }
                    .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
                    .button { display: inline-block; background-color: #0066cc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 10px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>✉ Ha recibido una respuesta a su ticket</h2>
                    </div>
                    <div class='content'>
                        <p>Estimado,</p>
                        
                        <p><strong>$nombre_respondente</strong> del área de Informática ha respondido a su solicitud.</p>
                        
                        <div class='ticket-info'>
                            <strong>Detalle del Ticket:</strong><br>
                            <strong>Ticket:</strong> {$ticket['numero']}<br>
                            <strong>Asunto:</strong> {$ticket['asunto']}<br>
                            <strong>Categoría:</strong> {$ticket['categoria']}<br>
                            <strong>Estado:</strong> <span style='color: white; background-color: {$ticket['estado_color']}; padding: 3px 8px; border-radius: 3px;'>{$ticket['estado']}</span>
                        </div>
                        
                        <div class='mensaje-box'>
                            <strong>Respuesta:</strong><br>
                            <br>
                            " . nl2br(htmlspecialchars($comentario)) . "
                        </div>
                        
                        <p>Puede acceder al sistema para ver más detalles y continuar la comunicación si lo requiere.</p>
                        
                        <a href='$baseUrl/funcionario/ticket.php?id={$ticket['id']}' class='button'>Ir al Sistema</a>
                        
                        <div class='footer'>
                            <p><strong>Sistema de Tickets - Mesa de Ayuda Municipal</strong></p>
                            <p>Este es un correo automático. Por favor, no responda a este correo directamente. Use el sistema de tickets para comunicarse.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Cargar MailHandler si no está cargado
        if (!class_exists('MailHandler')) {
            require_once __DIR__ . '/MailHandler.php';
        }
        
        error_log("Creando instancia de MailHandler...");
        $mailHandler = new MailHandler();
        
        $resultado = false;
        
        // 1. Intentar con tokens del usuario que responde
        error_log("Intentando enviar correo a: " . $ticket['ciudadano_email'] . " usando Graph API desde usuario ID: " . $usuario_respondente_id);
        
        // Verificar si el usuario tiene tokens OAuth
        $tokenUsuario = $db->fetch("SELECT id FROM oauth_tokens WHERE usuario_id = ?", [$usuario_respondente_id]);
        
        if ($tokenUsuario) {
            $resultado = $mailHandler->enviarCorreoDesdeUsuario(
                $usuario_respondente_id,
                $ticket['ciudadano_email'],
                "Respuesta a tu ticket [{$ticket['numero']}]",
                $html
            );
        }
        
        // 2. Si no tiene tokens o falló, buscar cualquier cuenta del sistema con tokens válidos
        if (!$resultado) {
            error_log("Usuario sin tokens o Graph API falló. Buscando cuenta del sistema con tokens...");
            
            $cuentaSistema = $db->fetch(
                "SELECT ot.usuario_id, u.email 
                 FROM oauth_tokens ot 
                 JOIN usuarios u ON ot.usuario_id = u.id 
                 WHERE ot.expires_at > NOW() 
                 ORDER BY ot.expires_at DESC 
                 LIMIT 1"
            );
            
            if ($cuentaSistema) {
                error_log("Usando cuenta del sistema: " . $cuentaSistema['email'] . " (ID: " . $cuentaSistema['usuario_id'] . ")");
                $resultado = $mailHandler->enviarCorreoDesdeUsuario(
                    $cuentaSistema['usuario_id'],
                    $ticket['ciudadano_email'],
                    "Respuesta a tu ticket [{$ticket['numero']}]",
                    $html
                );
            }
        }
        
        // 3. Como último recurso, intentar SMTP tradicional
        if (!$resultado) {
            error_log("Graph API falló, intentando con SMTP tradicional...");
            $resultado = $mailHandler->enviarCorreo(
                $ticket['ciudadano_email'],
                "Respuesta a tu ticket [{$ticket['numero']}]",
                $html
            );
        }
        
        if ($resultado) {
            error_log("✓ Correo enviado exitosamente a: " . $ticket['ciudadano_email']);
        } else {
            error_log("✗ Fallo al enviar correo a: " . $ticket['ciudadano_email']);
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Exception en enviarNotificacionRespuestaTicket: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}


