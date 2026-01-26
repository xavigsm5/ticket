<?php
/**
 * API para operaciones AJAX - Funcionalidades Freshdesk
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/freshdesk_functions.php';

header('Content-Type: application/json');

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

switch ($accion) {
    case 'categorias':
        $departamento_id = (int)($_GET['departamento_id'] ?? 0);
        if (!$departamento_id) {
            respuestaJson(['error' => 'Departamento requerido'], 400);
        }
        $categorias = obtenerCategorias($departamento_id);
        respuestaJson($categorias);
        break;
        
    case 'buscar_ticket':
        $numero = limpiarInput($_GET['numero'] ?? '');
        if (empty($numero)) {
            respuestaJson(['error' => 'Número de ticket requerido'], 400);
        }
        $ticket = obtenerTicketPorNumero($numero);
        if (!$ticket) {
            respuestaJson(['error' => 'Ticket no encontrado'], 404);
        }
        respuestaJson([
            'numero' => $ticket['numero'],
            'asunto' => $ticket['asunto'],
            'estado' => $ticket['estado'],
            'estado_color' => $ticket['estado_color'],
            'categoria' => $ticket['categoria'],
            'departamento' => $ticket['departamento'],
            'created_at' => $ticket['created_at'],
            'updated_at' => $ticket['updated_at']
        ]);
        break;
        
    case 'estadisticas':
        if (!estaAutenticado() || !tieneRol(['admin', 'supervisor'])) {
            respuestaJson(['error' => 'No autorizado'], 403);
        }
        $stats = obtenerEstadisticas();
        respuestaJson($stats);
        break;
    
    case 'actividad':
        if (!estaAutenticado()) {
            respuestaJson(['error' => 'No autorizado'], 403);
        }
        $ticket_id = (int)($_GET['ticket_id'] ?? 0);
        if ($ticket_id) {
            $usuario = obtenerUsuarioActual();
            registrarActividadTicket($ticket_id, $usuario['id'], 'viendo');
            respuestaJson(['ok' => true]);
        }
        break;
    
    case 'respuestas_predefinidas':
        if (!estaAutenticado()) {
            respuestaJson(['error' => 'No autorizado'], 403);
        }
        $departamento_id = (int)($_GET['departamento_id'] ?? 0);
        $usuario = obtenerUsuarioActual();
        $respuestas = obtenerRespuestasPredefinidas($departamento_id, $usuario['id']);
        respuestaJson($respuestas);
        break;
    
    case 'obtener_respuesta':
        if (!estaAutenticado()) {
            respuestaJson(['error' => 'No autorizado'], 403);
        }
        $id = (int)($_GET['id'] ?? 0);
        $respuesta = obtenerRespuestaPredefinida($id);
        respuestaJson($respuesta ?: ['error' => 'No encontrada']);
        break;
    
    case 'etiquetas':
        $etiquetas = obtenerEtiquetas();
        respuestaJson($etiquetas);
        break;
    
    case 'notificaciones':
        if (!estaAutenticado()) {
            respuestaJson(['error' => 'No autorizado'], 403);
        }
        $usuario = obtenerUsuarioActual();
        $notificaciones = obtenerNotificaciones($usuario['id'], true);
        respuestaJson($notificaciones);
        break;
    
    case 'marcar_notificacion_leida':
        if (!estaAutenticado()) {
            respuestaJson(['error' => 'No autorizado'], 403);
        }
        $id = (int)($_POST['id'] ?? 0);
        marcarNotificacionLeida($id);
        respuestaJson(['ok' => true]);
        break;
    
    case 'verificar_sla':
        if (!estaAutenticado()) {
            respuestaJson(['error' => 'No autorizado'], 403);
        }
        $ticket_id = (int)($_GET['ticket_id'] ?? 0);
        $sla = verificarSLAVencido($ticket_id);
        respuestaJson($sla ?: []);
        break;
        
    default:
        respuestaJson(['error' => 'Acción no válida'], 400);
}
