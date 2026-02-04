<?php
/**
 * Funciones avanzadas (SLA, etiquetas, respuestas, etc.)
 */

require_once __DIR__ . '/functions.php';

// --- Etiquetas ---

function obtenerEtiquetas()
{
    $db = Database::getInstance();
    return $db->fetchAll("SELECT * FROM etiquetas WHERE activo = TRUE ORDER BY nombre");
}

function obtenerEtiquetasTicket($ticket_id)
{
    $db = Database::getInstance();
    return $db->fetchAll("
        SELECT e.* FROM etiquetas e
        INNER JOIN ticket_etiquetas te ON e.id = te.etiqueta_id
        WHERE te.ticket_id = ?
    ", [$ticket_id]);
}

function agregarEtiquetaTicket($ticket_id, $etiqueta_id)
{
    $db = Database::getInstance();
    try {
        $db->query(
            "INSERT INTO ticket_etiquetas (ticket_id, etiqueta_id) VALUES (?, ?) ON CONFLICT DO NOTHING",
            [$ticket_id, $etiqueta_id]
        );
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function eliminarEtiquetaTicket($ticket_id, $etiqueta_id)
{
    $db = Database::getInstance();
    $db->query(
        "DELETE FROM ticket_etiquetas WHERE ticket_id = ? AND etiqueta_id = ?",
        [$ticket_id, $etiqueta_id]
    );
}

function crearEtiqueta($nombre, $color = '#6c757d')
{
    $db = Database::getInstance();
    return $db->insert(
        "INSERT INTO etiquetas (nombre, color) VALUES (?, ?) RETURNING id",
        [strtolower($nombre), $color]
    );
}

// --- Respuestas predefinidas ---

function obtenerRespuestasPredefinidas($departamento_id = null, $usuario_id = null)
{
    $db = Database::getInstance();
    $sql = "SELECT * FROM respuestas_predefinidas WHERE activo = TRUE AND (es_global = TRUE";
    $params = [];

    if ($departamento_id) {
        $sql .= " OR departamento_id = ?";
        $params[] = $departamento_id;
    }
    if ($usuario_id) {
        $sql .= " OR usuario_id = ?";
        $params[] = $usuario_id;
    }

    $sql .= ") ORDER BY uso_count DESC, titulo";
    return $db->fetchAll($sql, $params);
}

function obtenerRespuestaPredefinida($id)
{
    $db = Database::getInstance();
    $db->query("UPDATE respuestas_predefinidas SET uso_count = uso_count + 1 WHERE id = ?", [$id]);
    return $db->fetch("SELECT * FROM respuestas_predefinidas WHERE id = ?", [$id]);
}

function crearRespuestaPredefinida($titulo, $contenido, $departamento_id = null, $usuario_id = null, $es_global = false)
{
    $db = Database::getInstance();
    return $db->insert("
        INSERT INTO respuestas_predefinidas (titulo, contenido, departamento_id, usuario_id, es_global)
        VALUES (?, ?, ?, ?, ?) RETURNING id
    ", [$titulo, $contenido, $departamento_id, $usuario_id, $es_global]);
}

// --- SLA ---

function obtenerPoliticasSLA()
{
    $db = Database::getInstance();
    return $db->fetchAll("SELECT * FROM sla_politicas WHERE activo = TRUE ORDER BY prioridad_id");
}

function obtenerPoliticaSLA($prioridad_id, $departamento_id = null)
{
    $db = Database::getInstance();
    if ($departamento_id) {
        $sla = $db->fetch("
            SELECT * FROM sla_politicas 
            WHERE activo = TRUE AND prioridad_id = ? AND departamento_id = ?
        ", [$prioridad_id, $departamento_id]);
        if ($sla)
            return $sla;
    }
    return $db->fetch("
        SELECT * FROM sla_politicas 
        WHERE activo = TRUE AND prioridad_id = ? AND departamento_id IS NULL
    ", [$prioridad_id]);
}

function aplicarSLA($ticket_id)
{
    $db = Database::getInstance();
    $ticket = $db->fetch("SELECT prioridad_id, categoria_id, created_at FROM tickets WHERE id = ?", [$ticket_id]);
    if (!$ticket)
        return false;
    $categoria = $db->fetch("SELECT departamento_id FROM categorias WHERE id = ?", [$ticket['categoria_id']]);
    $departamento_id = $categoria ? $categoria['departamento_id'] : null;

    $sla = obtenerPoliticaSLA($ticket['prioridad_id'], $departamento_id);

    if ($sla) {
        $created = new DateTime($ticket['created_at']);
        $respuesta_vence = clone $created;
        $resolucion_vence = clone $created;

        $respuesta_vence->modify("+{$sla['tiempo_primera_respuesta_horas']} hours");
        $resolucion_vence->modify("+{$sla['tiempo_resolucion_horas']} hours");

        $db->query("
            UPDATE tickets SET 
                sla_politica_id = ?,
                sla_respuesta_vencimiento = ?,
                sla_resolucion_vencimiento = ?
            WHERE id = ?
        ", [$sla['id'], $respuesta_vence->format('Y-m-d H:i:s'), $resolucion_vence->format('Y-m-d H:i:s'), $ticket_id]);

        return true;
    }
    return false;
}

function verificarSLAVencido($ticket_id)
{
    $db = Database::getInstance();
    $ticket = $db->fetch("
        SELECT sla_respuesta_vencimiento, sla_resolucion_vencimiento, 
               fecha_primera_respuesta, fecha_resolucion 
        FROM tickets WHERE id = ?
    ", [$ticket_id]);

    if (!$ticket)
        return null;

    $now = new DateTime();
    $resultado = [
        'respuesta_vencido' => false,
        'resolucion_vencido' => false,
        'respuesta_tiempo_restante' => null,
        'resolucion_tiempo_restante' => null
    ];

    if ($ticket['sla_respuesta_vencimiento'] && !$ticket['fecha_primera_respuesta']) {
        $vence = new DateTime($ticket['sla_respuesta_vencimiento']);
        if ($now > $vence) {
            $resultado['respuesta_vencido'] = true;
        } else {
            $resultado['respuesta_tiempo_restante'] = $now->diff($vence);
        }
    }

    if ($ticket['sla_resolucion_vencimiento'] && !$ticket['fecha_resolucion']) {
        $vence = new DateTime($ticket['sla_resolucion_vencimiento']);
        if ($now > $vence) {
            $resultado['resolucion_vencido'] = true;
        } else {
            $resultado['resolucion_tiempo_restante'] = $now->diff($vence);
        }
    }

    return $resultado;
}

function formatearTiempoSLA($interval)
{
    if (!$interval)
        return '';

    if ($interval->d > 0) {
        return $interval->d . 'd ' . $interval->h . 'h';
    } elseif ($interval->h > 0) {
        return $interval->h . 'h ' . $interval->i . 'm';
    } else {
        return $interval->i . 'm';
    }
}

// --- Satisfacción (CSAT) ---

function crearEncuestaSatisfaccion($ticket_id)
{
    $db = Database::getInstance();
    $token = bin2hex(random_bytes(32));

    $db->query("
        INSERT INTO encuestas_satisfaccion (ticket_id, token) 
        VALUES (?, ?)
        ON CONFLICT (ticket_id) DO NOTHING
    ", [$ticket_id, $token]);

    return $token;
}

function obtenerEncuestaPorToken($token)
{
    $db = Database::getInstance();
    return $db->fetch("
        SELECT es.*, t.numero, t.asunto 
        FROM encuestas_satisfaccion es
        INNER JOIN tickets t ON es.ticket_id = t.id
        WHERE es.token = ? AND es.respondido_at IS NULL
    ", [$token]);
}

function responderEncuesta($token, $calificacion, $comentario = null)
{
    $db = Database::getInstance();
    $encuesta = $db->fetch("SELECT id, ticket_id FROM encuestas_satisfaccion WHERE token = ?", [$token]);

    if (!$encuesta)
        return false;

    $db->query("
        UPDATE encuestas_satisfaccion 
        SET calificacion = ?, comentario = ?, respondido_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ", [$calificacion, $comentario, $encuesta['id']]);


    $db->query("UPDATE tickets SET satisfaccion = ? WHERE id = ?", [$calificacion, $encuesta['ticket_id']]);

    return true;
}

function obtenerEstadisticasCSAT($fecha_inicio = null, $fecha_fin = null)
{
    $db = Database::getInstance();
    $where = "WHERE respondido_at IS NOT NULL";
    $params = [];

    if ($fecha_inicio) {
        $where .= " AND respondido_at >= ?";
        $params[] = $fecha_inicio;
    }
    if ($fecha_fin) {
        $where .= " AND respondido_at <= ?";
        $params[] = $fecha_fin;
    }

    return $db->fetch("
        SELECT 
            COUNT(*) as total_respuestas,
            ROUND(AVG(calificacion), 2) as promedio,
            COUNT(CASE WHEN calificacion >= 4 THEN 1 END) as satisfechos,
            COUNT(CASE WHEN calificacion <= 2 THEN 1 END) as insatisfechos
        FROM encuestas_satisfaccion
        $where
    ", $params);
}

// --- Vistas personalizadas ---

function obtenerVistasPersonalizadas($usuario_id)
{
    $db = Database::getInstance();
    return $db->fetchAll("
        SELECT * FROM vistas_personalizadas 
        WHERE activo = TRUE AND (es_compartida = TRUE OR usuario_id = ?)
        ORDER BY nombre
    ", [$usuario_id]);
}

function crearVistaPersonalizada($nombre, $filtros, $usuario_id, $es_compartida = false)
{
    $db = Database::getInstance();
    return $db->insert("
        INSERT INTO vistas_personalizadas (nombre, filtros, usuario_id, es_compartida)
        VALUES (?, ?, ?, ?) RETURNING id
    ", [$nombre, json_encode($filtros), $usuario_id, $es_compartida]);
}

function aplicarFiltrosVista($vista_id)
{
    $db = Database::getInstance();
    $vista = $db->fetch("SELECT filtros FROM vistas_personalizadas WHERE id = ?", [$vista_id]);

    if (!$vista)
        return [];

    return json_decode($vista['filtros'], true) ?: [];
}

// --- Tickets relacionados y fusión ---

function fusionarTickets($ticket_principal_id, $ticket_secundario_id, $usuario_id)
{
    $db = Database::getInstance();


    $db->query("
        UPDATE ticket_comentarios SET ticket_id = ? WHERE ticket_id = ?
    ", [$ticket_principal_id, $ticket_secundario_id]);


    $db->query("
        UPDATE ticket_archivos SET ticket_id = ? WHERE ticket_id = ?
    ", [$ticket_principal_id, $ticket_secundario_id]);


    $db->query("
        UPDATE tickets SET fusionado_en = ?, estado_id = 6 WHERE id = ?
    ", [$ticket_principal_id, $ticket_secundario_id]);


    $db->query("
        INSERT INTO tickets_relacionados (ticket_principal_id, ticket_relacionado_id, tipo)
        VALUES (?, ?, 'fusionado')
    ", [$ticket_principal_id, $ticket_secundario_id]);


    registrarHistorial($ticket_principal_id, $usuario_id, 'fusion', "Ticket #$ticket_secundario_id fusionado");

    return true;
}

function obtenerTicketsRelacionados($ticket_id)
{
    $db = Database::getInstance();
    return $db->fetchAll("
        SELECT tr.*, t.numero, t.asunto, t.estado_id, e.nombre as estado
        FROM tickets_relacionados tr
        INNER JOIN tickets t ON t.id = tr.ticket_relacionado_id
        LEFT JOIN estados e ON t.estado_id = e.id
        WHERE tr.ticket_principal_id = ?
        
        UNION
        
        SELECT tr.*, t.numero, t.asunto, t.estado_id, e.nombre as estado
        FROM tickets_relacionados tr
        INNER JOIN tickets t ON t.id = tr.ticket_principal_id
        LEFT JOIN estados e ON t.estado_id = e.id
        WHERE tr.ticket_relacionado_id = ?
    ", [$ticket_id, $ticket_id]);
}

function relacionarTickets($ticket_id_1, $ticket_id_2, $tipo = 'relacionado')
{
    $db = Database::getInstance();
    try {
        $db->query("
            INSERT INTO tickets_relacionados (ticket_principal_id, ticket_relacionado_id, tipo)
            VALUES (?, ?, ?)
        ", [$ticket_id_1, $ticket_id_2, $tipo]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// --- Notificaciones ---




// --- Detección de colisión ---

function registrarActividadTicket($ticket_id, $usuario_id, $accion = 'viendo')
{
    $db = Database::getInstance();
    $db->query("
        INSERT INTO ticket_actividad (ticket_id, usuario_id, accion, ultima_actividad)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (ticket_id, usuario_id) 
        DO UPDATE SET accion = ?, ultima_actividad = CURRENT_TIMESTAMP
    ", [$ticket_id, $usuario_id, $accion, $accion]);
}

function obtenerUsuariosViendoTicket($ticket_id, $usuario_actual_id)
{
    $db = Database::getInstance();

    return $db->fetchAll("
        SELECT ta.*, CONCAT(u.nombres, ' ', u.apellidos) as nombre_usuario
        FROM ticket_actividad ta
        INNER JOIN usuarios u ON ta.usuario_id = u.id
        WHERE ta.ticket_id = ? 
        AND ta.usuario_id != ?
        AND ta.ultima_actividad > (CURRENT_TIMESTAMP - INTERVAL '2 minutes')
    ", [$ticket_id, $usuario_actual_id]);
}

// --- Automatizaciones ---

function ejecutarAutomatizaciones($evento, $ticket_id, $datos_extra = [])
{
    $db = Database::getInstance();

    $automatizaciones = $db->fetchAll("
        SELECT * FROM automatizaciones 
        WHERE activo = TRUE AND evento = ?
        ORDER BY orden
    ", [$evento]);

    foreach ($automatizaciones as $auto) {
        $condiciones = json_decode($auto['condiciones'], true) ?: [];
        $acciones = json_decode($auto['acciones'], true) ?: [];


        if (verificarCondiciones($ticket_id, $condiciones, $datos_extra)) {
            ejecutarAcciones($ticket_id, $acciones);


            $db->query("UPDATE automatizaciones SET ejecuciones = ejecuciones + 1 WHERE id = ?", [$auto['id']]);
        }
    }
}

function verificarCondiciones($ticket_id, $condiciones, $datos_extra)
{
    if (empty($condiciones))
        return true;

    $db = Database::getInstance();
    $ticket = $db->fetch("SELECT * FROM vista_tickets_completa WHERE id = ?", [$ticket_id]);

    foreach ($condiciones as $cond) {
        $campo = $cond['campo'] ?? '';
        $operador = $cond['operador'] ?? 'igual';
        $valor = $cond['valor'] ?? '';

        $valor_actual = $ticket[$campo] ?? $datos_extra[$campo] ?? null;

        switch ($operador) {
            case 'igual':
                if ($valor_actual != $valor)
                    return false;
                break;
            case 'no_igual':
                if ($valor_actual == $valor)
                    return false;
                break;
            case 'contiene':
                if (strpos($valor_actual, $valor) === false)
                    return false;
                break;
            case 'mayor_que':
                if ($valor_actual <= $valor)
                    return false;
                break;
            case 'menor_que':
                if ($valor_actual >= $valor)
                    return false;
                break;
        }
    }

    return true;
}

function ejecutarAcciones($ticket_id, $acciones)
{
    $db = Database::getInstance();

    foreach ($acciones as $accion) {
        $tipo = $accion['tipo'] ?? '';
        $valor = $accion['valor'] ?? '';

        switch ($tipo) {
            case 'cambiar_estado':
                $db->query("UPDATE tickets SET estado_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$valor, $ticket_id]);
                break;
            case 'cambiar_prioridad':
                $db->query("UPDATE tickets SET prioridad_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$valor, $ticket_id]);
                aplicarSLA($ticket_id);
                break;
            case 'asignar_por_categoria':
                $ticket = $db->fetch("SELECT categoria_id FROM tickets WHERE id = ?", [$ticket_id]);
                if ($ticket && $ticket['categoria_id']) {
                    $asignacion = $db->fetch("
                        SELECT usuario_id FROM categoria_asignacion 
                        WHERE categoria_id = ? AND es_principal = TRUE
                        LIMIT 1
                    ", [$ticket['categoria_id']]);
                    if ($asignacion) {
                        $db->query("UPDATE tickets SET asignado_id = ? WHERE id = ?", [$asignacion['usuario_id'], $ticket_id]);
                    }
                }
                break;
            case 'asignar':
                if ($valor === 'supervisor_departamento') {
                    $ticket = $db->fetch("
                        SELECT c.departamento_id 
                        FROM tickets t 
                        LEFT JOIN categorias c ON t.categoria_id = c.id 
                        WHERE t.id = ?
                    ", [$ticket_id]);
                    if ($ticket && $ticket['departamento_id']) {
                        $supervisor = $db->fetch("
                            SELECT id FROM usuarios 
                            WHERE departamento_id = ? AND rol = 'supervisor' AND activo = TRUE
                            LIMIT 1
                        ", [$ticket['departamento_id']]);
                        if ($supervisor) {
                            $db->query("UPDATE tickets SET asignado_id = ? WHERE id = ?", [$supervisor['id'], $ticket_id]);
                        }
                    }
                } else {
                    $db->query("UPDATE tickets SET asignado_id = ? WHERE id = ?", [$valor, $ticket_id]);
                }
                break;
            case 'agregar_etiqueta':
                agregarEtiquetaTicket($ticket_id, $valor);
                break;
            case 'notificar':
                $ticket = $db->fetch("SELECT asunto, asignado_id FROM tickets WHERE id = ?", [$ticket_id]);
                if ($ticket && $ticket['asignado_id']) {
                    crearNotificacion($ticket['asignado_id'], "Automatización ejecutada", $ticket['asunto'], 'automatizacion', $ticket_id);
                }
                break;
        }
    }
}


function obtenerTecnicoPorCategoria($categoria_id)
{
    $db = Database::getInstance();
    $asignacion = $db->fetch("
        SELECT u.id, u.nombres, u.apellidos, u.email
        FROM categoria_asignacion ca
        INNER JOIN usuarios u ON ca.usuario_id = u.id
        WHERE ca.categoria_id = ? AND ca.es_principal = TRUE
        LIMIT 1
    ", [$categoria_id]);
    return $asignacion;
}

// --- Campos personalizados ---

function obtenerCamposPersonalizados($departamento_id = null)
{
    $db = Database::getInstance();
    $where = "WHERE activo = TRUE";
    $params = [];

    if ($departamento_id) {
        $where .= " AND (departamento_id IS NULL OR departamento_id = ?)";
        $params[] = $departamento_id;
    }

    return $db->fetchAll("SELECT * FROM campos_personalizados $where ORDER BY orden", $params);
}

function obtenerValoresCamposTicket($ticket_id)
{
    $db = Database::getInstance();
    return $db->fetchAll("
        SELECT cp.*, tcv.valor
        FROM campos_personalizados cp
        LEFT JOIN ticket_campos_valores tcv ON cp.id = tcv.campo_id AND tcv.ticket_id = ?
        WHERE cp.activo = TRUE
        ORDER BY cp.orden
    ", [$ticket_id]);
}

function guardarValorCampoTicket($ticket_id, $campo_id, $valor)
{
    $db = Database::getInstance();
    $db->query("
        INSERT INTO ticket_campos_valores (ticket_id, campo_id, valor)
        VALUES (?, ?, ?)
        ON CONFLICT (ticket_id, campo_id) DO UPDATE SET valor = ?
    ", [$ticket_id, $campo_id, $valor, $valor]);
}


function crearTicketConSLA($ciudadano_id, $categoria_id, $asunto, $descripcion, $prioridad_id = 2, $ubicacion = null)
{
    $db = Database::getInstance();

    $datos = [
        'ciudadano_id' => $ciudadano_id,
        'categoria_id' => $categoria_id,
        'asunto' => $asunto,
        'descripcion' => $descripcion,
        'prioridad_id' => $prioridad_id,
        'ubicacion_direccion' => $ubicacion
    ];

    $resultado = crearTicket($datos);

    if ($resultado && isset($resultado['id'])) {
        $ticket_id = $resultado['id'];
        aplicarSLA($ticket_id);
        ejecutarAutomatizaciones('ticket_creado', $ticket_id);
        return $ticket_id;
    }

    return null;
}
