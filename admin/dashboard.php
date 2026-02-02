<?php
/**
 * Dashboard - Panel de Tickets estilo Freshdesk Completo
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/freshdesk_functions.php';

requiereRol(['admin', 'supervisor', 'funcionario']);

$usuario = obtenerUsuarioActual();

// Si es funcionario, mostrar solo estadísticas de sus tickets asignados
if ($usuario['rol'] === 'funcionario') {
    $stats = obtenerEstadisticasUsuario($usuario['id']);
} else {
    $stats = obtenerEstadisticas();
}

$etiquetas = obtenerEtiquetas();
$notificaciones_count = contarNotificacionesNoLeidas($usuario['id']);


$filtro_estado = $_GET['estado'] ?? '';
$filtro_busqueda = $_GET['q'] ?? '';
$filtro_etiqueta = $_GET['etiqueta'] ?? '';
$filtro_vista = $_GET['vista'] ?? '';
$filtro_sla = $_GET['sla'] ?? '';

$filtros = [];
if ($filtro_estado)
    $filtros['estado_id'] = (int) $filtro_estado;
if ($filtro_busqueda)
    $filtros['busqueda'] = $filtro_busqueda;

// Si es funcionario, solo ve tickets asignados a él
if ($usuario['rol'] === 'funcionario') {
    $filtros['asignado_id'] = $usuario['id'];
}

$tickets = obtenerTickets($filtros, 50);
$estados = obtenerEstados();
$vistas = obtenerVistasPersonalizadas($usuario['id']);


$ticket_actual = null;
$comentarios = [];
$ticket_etiquetas = [];
$sla_info = null;
$usuarios_viendo = [];
$respuestas_predefinidas = [];

if (isset($_GET['id'])) {
    $ticket_actual = obtenerTicket((int) $_GET['id']);
    if ($ticket_actual) {
        $comentarios = obtenerComentarios($ticket_actual['id']);
        $ticket_etiquetas = obtenerEtiquetasTicket($ticket_actual['id']);
        $sla_info = verificarSLAVencido($ticket_actual['id']);
        $respuestas_predefinidas = obtenerRespuestasPredefinidas($ticket_actual['departamento_id'], $usuario['id']);
        registrarActividadTicket($ticket_actual['id'], $usuario['id'], 'viendo');
        $usuarios_viendo = obtenerUsuariosViendoTicket($ticket_actual['id'], $usuario['id']);
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['comentario']) && $ticket_actual) {
        $es_interno = isset($_POST['es_interno']);
        agregarComentario($ticket_actual['id'], $usuario['id'], limpiarInput($_POST['comentario']), $es_interno);

        // NOTIFICAR AL USUARIO (Si no es interno y el autor NO es el mismo usuario)
        if (!$es_interno && $ticket_actual['ciudadano_id'] != $usuario['id']) {
            crearNotificacion(
                $ticket_actual['ciudadano_id'],
                "Nueva respuesta en Ticket #{$ticket_actual['id']}",
                "El agente {$usuario['nombres']} ha respondido a tu solicitud: " . substr(limpiarInput($_POST['comentario']), 0, 50) . "...",
                'info',
                $ticket_actual['id']
            );
        }


        if (!$es_interno && in_array($usuario['rol'], ['admin', 'supervisor', 'funcionario'])) {
            $db = Database::getInstance();
            $db->query("UPDATE tickets SET fecha_primera_respuesta = COALESCE(fecha_primera_respuesta, CURRENT_TIMESTAMP), sla_respuesta_cumplido = TRUE WHERE id = ?", [$ticket_actual['id']]);
        }

        header("Location: /admin/dashboard.php?id=" . $ticket_actual['id'] . "&msg=comentario");
        exit;
    }
    if (isset($_POST['actualizar']) && $ticket_actual) {
        $datos = [
            'estado_id' => (int) $_POST['estado_id'],
            'prioridad_id' => (int) $_POST['prioridad_id'],
            'asignado_id' => $_POST['asignado_id'] ?: null
        ];

        if ((int) $_POST['estado_id'] == 5) {
            $db = Database::getInstance();
            $db->query("UPDATE tickets SET fecha_resolucion = CURRENT_TIMESTAMP, sla_resolucion_cumplido = TRUE WHERE id = ? AND fecha_resolucion IS NULL", [$ticket_actual['id']]);


            crearEncuestaSatisfaccion($ticket_actual['id']);
        }

        actualizarTicket($ticket_actual['id'], $datos);


        if ((int) $_POST['prioridad_id'] != $ticket_actual['prioridad_id']) {
            aplicarSLA($ticket_actual['id']);
        }

        header("Location: /admin/dashboard.php?id=" . $ticket_actual['id'] . "&msg=actualizado");
        exit;
    }
    if (isset($_POST['agregar_etiqueta']) && $ticket_actual) {
        agregarEtiquetaTicket($ticket_actual['id'], (int) $_POST['etiqueta_id']);
        header("Location: /admin/dashboard.php?id=" . $ticket_actual['id']);
        exit;
    }
    if (isset($_POST['quitar_etiqueta']) && $ticket_actual) {
        eliminarEtiquetaTicket($ticket_actual['id'], (int) $_POST['etiqueta_id']);
        header("Location: /admin/dashboard.php?id=" . $ticket_actual['id']);
        exit;
    }
}

$prioridades = obtenerPrioridades();
$funcionarios = $ticket_actual ? obtenerFuncionarios($ticket_actual['departamento_id']) : [];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesa de Ayuda - Municipalidad</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .sla-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .sla-ok {
            background: #d4edda;
            color: #155724;
        }

        .sla-warning {
            background: #fff3cd;
            color: #856404;
        }

        .sla-danger {
            background: #f8d7da;
            color: #721c24;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        .etiqueta {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            color: white;
            margin-right: 4px;
            margin-bottom: 4px;
        }

        .etiqueta .quitar {
            cursor: pointer;
            opacity: 0.7;
        }

        .etiqueta .quitar:hover {
            opacity: 1;
        }

        .respuesta-rapida {
            cursor: pointer;
            padding: 8px 12px;
            border: 1px solid var(--gris-200);
            border-radius: 4px;
            margin-bottom: 8px;
            transition: all 0.15s;
        }

        .respuesta-rapida:hover {
            background: var(--gris-50);
            border-color: var(--color-acento);
        }

        .colision-alerta {
            background: #fff3cd;
            padding: 8px 12px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-overlay.activo {
            display: flex;
        }

        .modal-contenido {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow: auto;
        }

        .modal-header {
            padding: 16px;
            border-bottom: 1px solid var(--gris-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 16px;
        }

        .tickets-fusionados {
            background: var(--gris-50);
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 8px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>

<body>
    <div class="layout-app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="sidebar-logo-icon">M</div>
                    <span class="sidebar-titulo">Municipalidad</span>
                </div>
            </div>

            <nav class="sidebar-menu">
                <div class="menu-seccion">Soporte</div>
                <a href="/admin/dashboard.php" class="menu-item activo">
                    <i class="bi bi-inbox"></i>
                    <span>Tickets</span>
                    <?php if ($stats['pendientes'] > 0): ?>
                        <span class="badge"><?= $stats['pendientes'] ?></span>
                    <?php endif; ?>
                </a>

                <div class="menu-seccion">Vistas</div>
                <?php foreach ($vistas as $v): ?>
                    <a href="/admin/dashboard.php?vista=<?= $v['id'] ?>" class="menu-item">
                        <i class="bi bi-eye"></i>
                        <span><?= htmlspecialchars($v['nombre']) ?></span>
                    </a>
                <?php endforeach; ?>

                <div class="menu-seccion">Filtros Rápidos</div>
                <a href="/admin/dashboard.php?sla=vencido"
                    class="menu-item <?= $filtro_sla === 'vencido' ? 'activo' : '' ?>">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>SLA Vencido</span>
                </a>
                <a href="/admin/dashboard.php?asignado=yo" class="menu-item">
                    <i class="bi bi-person-check"></i>
                    <span>Asignados a mí</span>
                </a>

                <?php if (tieneRol(['admin', 'supervisor'])): ?>
                    <div class="menu-seccion">Administración</div>
                    <a href="/admin/usuarios.php" class="menu-item">
                        <i class="bi bi-people"></i>
                        <span>Usuarios</span>
                    </a>
                    <a href="/admin/respuestas-predefinidas.php" class="menu-item">
                        <i class="bi bi-chat-square-text"></i>
                        <span>Respuestas</span>
                    </a>
                    <a href="/admin/automatizaciones.php" class="menu-item">
                        <i class="bi bi-lightning"></i>
                        <span>Automatizaciones</span>
                    </a>
                    <a href="/admin/sla.php" class="menu-item">
                        <i class="bi bi-clock-history"></i>
                        <span>Políticas SLA</span>
                    </a>
                    <a href="/admin/reportes.php" class="menu-item">
                        <i class="bi bi-bar-chart"></i>
                        <span>Reportes</span>
                    </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="usuario-info">
                    <div class="usuario-avatar">
                        <?= strtoupper(substr($usuario['nombres'], 0, 1) . substr($usuario['apellidos'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="usuario-nombre"><?= htmlspecialchars($usuario['nombres']) ?></div>
                        <div class="usuario-rol"><?= ucfirst($usuario['rol']) ?></div>
                    </div>
                </div>
            </div>
        </aside>
        <div class="contenido-principal">
            <header class="topbar">
                <div class="topbar-izquierda">
                    <form method="GET" action="" style="display: flex; align-items: center; gap: 8px;">
                        <input type="text" name="q" class="form-control" style="width: 250px; padding: 6px 12px;"
                            placeholder="Buscar tickets..." value="<?= htmlspecialchars($filtro_busqueda) ?>">
                        <button type="submit" class="btn btn-secundario btn-sm">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>
                <div class="topbar-derecha">
                    <div style="position: relative;">
                        <button class="btn-icono" onclick="toggleMenuNotificaciones()">
                            <i class="bi bi-bell"></i>
                            <?php if ($notificaciones_count > 0): ?>
                                <span id="badge-notif"
                                    style="position: absolute; top: 0; right: 0; background: var(--estado-abierto); color: white; font-size: 10px; padding: 2px 5px; border-radius: 10px;"><?= $notificaciones_count ?></span>
                            <?php endif; ?>
                        </button>

                        <div id="menu-notificaciones"
                            style="display: none; position: absolute; right: 0; top: 40px; background: white; border: 1px solid var(--gris-200); border-radius: 8px; box-shadow: var(--sombra-lg); width: 300px; z-index: 1000; overflow: hidden;">
                            <div
                                style="padding: 12px; border-bottom: 1px solid var(--gris-200); font-weight: 600; background: var(--gris-50);">
                                Notificaciones</div>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php
                                $notis = obtenerNotificaciones($usuario['id']);
                                if (empty($notis)):
                                    ?>
                                    <div
                                        style="padding: 20px; text-align: center; color: var(--gris-500); font-size: 13px;">
                                        Sin notificaciones nuevas</div>
                                <?php else: ?>
                                    <?php foreach ($notis as $n): ?>
                                        <a href="<?= $n['ticket_id'] ? '/admin/dashboard.php?id=' . $n['ticket_id'] : '#' ?>"
                                            style="display: block; padding: 12px; border-bottom: 1px solid var(--gris-100); text-decoration: none; color: inherit; background: <?= $n['leida'] ? 'white' : '#f0f9ff' ?>;">
                                            <div style="font-size: 13px; font-weight: 600; margin-bottom: 4px;">
                                                <?= htmlspecialchars($n['titulo']) ?>
                                            </div>
                                            <div style="font-size: 12px; color: var(--gris-600);">
                                                <?= htmlspecialchars($n['mensaje']) ?>
                                            </div>
                                            <div style="font-size: 10px; color: var(--gris-400); margin-top: 4px;">
                                                <?= tiempoTranscurrido($n['created_at']) ?>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <a href="#" onclick="marcarTodasLeidas(); return false;"
                                style="display: block; padding: 8px; text-align: center; font-size: 11px; color: var(--color-acento); border-top: 1px solid var(--gris-200);">Marcar
                                todas como leídas</a>
                        </div>
                    </div>
                    <script>
                        function toggleMenuNotificaciones() {
                            var menu = document.getElementById('menu-notificaciones');
                            if (menu.style.display === 'none') {
                                menu.style.display = 'block';
                                // Marcar como leídas visualmente (opcional, o hacerlo vía AJAX)
                            } else {
                                menu.style.display = 'none';
                            }
                        }
                        function marcarTodasLeidas() {
                            fetch('/api/notificaciones.php?accion=marcar_leidas').then(() => {
                                document.getElementById('badge-notif').style.display = 'none';
                                document.getElementById('menu-notificaciones').style.display = 'none';
                            });
                        }
                    </script>
                    <a href="/nuevo-ticket.php" class="btn btn-primario btn-sm">
                        <i class="bi bi-plus"></i> Nuevo Ticket
                    </a>
                    <a href="/logout.php" class="btn-icono" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </header>
            <div class="tickets-layout">
                <div class="tickets-lista">
                    <div class="tickets-lista-header">
                        <span class="tickets-lista-titulo">
                            Todos los Tickets (<?= count($tickets) ?>)
                        </span>
                    </div>
                    <div class="tickets-filtros">
                        <a href="/admin/dashboard.php"
                            class="filtro-btn <?= !$filtro_estado ? 'activo' : '' ?>">Todos</a>
                        <?php foreach ($estados as $e): ?>
                            <a href="/admin/dashboard.php?estado=<?= $e['id'] ?>"
                                class="filtro-btn <?= $filtro_estado == $e['id'] ? 'activo' : '' ?>">
                                <?= htmlspecialchars($e['nombre']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="tickets-lista-contenido">
                        <?php if (empty($tickets)): ?>
                            <div style="padding: 40px; text-align: center; color: var(--gris-500);">
                                <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 16px;"></i>
                                No hay tickets para mostrar
                            </div>
                        <?php else: ?>
                            <?php foreach ($tickets as $t): ?>
                                <?php
                                $t_sla = verificarSLAVencido($t['id']);
                                $tiene_sla_vencido = $t_sla && ($t_sla['respuesta_vencido'] || $t_sla['resolucion_vencido']);
                                ?>
                                <a href="/admin/dashboard.php?id=<?= $t['id'] ?><?= $filtro_estado ? '&estado=' . $filtro_estado : '' ?>"
                                    class="ticket-item <?= ($ticket_actual && $ticket_actual['id'] == $t['id']) ? 'seleccionado' : '' ?>"
                                    style="text-decoration: none; display: block; <?= $tiene_sla_vencido ? 'border-left: 3px solid #e74c3c;' : '' ?>">
                                    <div class="ticket-item-header">
                                        <span class="ticket-item-numero">
                                            #<?= $t['id'] ?>
                                            <?php if ($tiene_sla_vencido): ?>
                                                <i class="bi bi-exclamation-triangle-fill" style="color: #e74c3c;"
                                                    title="SLA Vencido"></i>
                                            <?php endif; ?>
                                        </span>
                                        <?php
                                        $estado_clase = 'estado-cerrado';
                                        if ($t['estado'] === 'Abierto')
                                            $estado_clase = 'estado-abierto';
                                        elseif ($t['estado'] === 'Pendiente')
                                            $estado_clase = 'estado-pendiente';
                                        elseif ($t['estado'] === 'Resuelto')
                                            $estado_clase = 'estado-resuelto';
                                        ?>
                                        <span
                                            class="ticket-item-estado <?= $estado_clase ?>"><?= htmlspecialchars($t['estado']) ?></span>
                                    </div>
                                    <div class="ticket-item-asunto"><?= htmlspecialchars($t['asunto']) ?></div>
                                    <div class="ticket-item-meta">
                                        <span class="ticket-item-solicitante">
                                            <i class="bi bi-person"></i>
                                            <?= htmlspecialchars($t['ciudadano_nombre'] ?? 'Sin nombre') ?>
                                        </span>
                                        <span><?= tiempoTranscurrido($t['created_at']) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ticket-detalle">
                    <?php if ($ticket_actual): ?>
                        <div class="ticket-detalle-header">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                                <div>
                                    <h1 class="ticket-detalle-titulo"><?= htmlspecialchars($ticket_actual['asunto']) ?></h1>
                                    <div class="ticket-detalle-meta">
                                        <span><i class="bi bi-ticket"></i>
                                            <?= htmlspecialchars($ticket_actual['numero']) ?></span>
                                        <span><i class="bi bi-folder"></i>
                                            <?= htmlspecialchars($ticket_actual['categoria'] ?? 'Sin categoría') ?></span>
                                        <span><i class="bi bi-clock"></i>
                                            <?= formatearFecha($ticket_actual['created_at']) ?></span>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <?php if ($sla_info): ?>
                                        <?php if ($sla_info['respuesta_vencido']): ?>
                                            <span class="sla-badge sla-danger"><i class="bi bi-exclamation-triangle"></i> SLA
                                                Respuesta Vencido</span>
                                        <?php elseif ($sla_info['respuesta_tiempo_restante']): ?>
                                            <span
                                                class="sla-badge <?= $sla_info['respuesta_tiempo_restante']->h < 2 ? 'sla-warning' : 'sla-ok' ?>">
                                                <i class="bi bi-clock"></i> Responder en
                                                <?= formatearTiempoSLA($sla_info['respuesta_tiempo_restante']) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <button class="btn btn-secundario btn-sm"
                                        onclick="document.getElementById('modalFusionar').classList.add('activo')">
                                        <i class="bi bi-link-45deg"></i> Fusionar
                                    </button>
                                </div>
                            </div>
                            <div style="margin-top: 12px;">
                                <?php foreach ($ticket_etiquetas as $et): ?>
                                    <span class="etiqueta" style="background: <?= $et['color'] ?>">
                                        <?= htmlspecialchars($et['nombre']) ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="quitar_etiqueta" value="1">
                                            <input type="hidden" name="etiqueta_id" value="<?= $et['id'] ?>">
                                            <button type="submit" class="quitar"
                                                style="background: none; border: none; color: white; padding: 0;">×</button>
                                        </form>
                                    </span>
                                <?php endforeach; ?>

                                <form method="POST" style="display: inline-flex; align-items: center; gap: 4px;">
                                    <input type="hidden" name="agregar_etiqueta" value="1">
                                    <select name="etiqueta_id" class="form-control"
                                        style="width: auto; padding: 2px 8px; font-size: 12px;">
                                        <option value="">+ Etiqueta</option>
                                        <?php foreach ($etiquetas as $et): ?>
                                            <option value="<?= $et['id'] ?>"><?= htmlspecialchars($et['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-secundario">Agregar</button>
                                </form>
                            </div>
                        </div>

                        <div class="ticket-detalle-contenido">
                            <div class="ticket-conversacion">
                                <?php if (!empty($usuarios_viendo)): ?>
                                    <div class="colision-alerta">
                                        <i class="bi bi-eye-fill"></i>
                                        <?php foreach ($usuarios_viendo as $uv): ?>
                                            <strong><?= htmlspecialchars($uv['nombre_usuario']) ?></strong> está viendo este ticket
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($_GET['msg'])): ?>
                                    <div class="alerta alerta-exito mb-2">
                                        <i class="bi bi-check-circle"></i>
                                        <?= $_GET['msg'] === 'comentario' ? 'Respuesta enviada' : 'Ticket actualizado' ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mensaje">
                                    <div class="mensaje-header">
                                        <div class="mensaje-autor">
                                            <div class="mensaje-avatar">
                                                <?= strtoupper(substr($ticket_actual['ciudadano_nombre'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="mensaje-nombre">
                                                    <?= htmlspecialchars($ticket_actual['ciudadano_nombre'] ?? 'Usuario') ?>
                                                </div>
                                                <div class="mensaje-rol">Solicitante</div>
                                            </div>
                                        </div>
                                        <span
                                            class="mensaje-fecha"><?= formatearFecha($ticket_actual['created_at']) ?></span>
                                    </div>
                                    <div class="mensaje-body">
                                        <?= nl2br(htmlspecialchars($ticket_actual['descripcion'])) ?>

                                        <?php if ($ticket_actual['ubicacion_direccion']): ?>
                                            <div
                                                style="margin-top: 16px; padding: 12px; background: var(--gris-50); border-radius: 4px;">
                                                <strong><i class="bi bi-geo-alt"></i> Ubicación:</strong>
                                                <?= htmlspecialchars($ticket_actual['ubicacion_direccion']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php foreach ($comentarios as $c): ?>
                                    <div class="mensaje <?= $c['es_interno'] ? 'nota-interna' : '' ?>">
                                        <div class="mensaje-header">
                                            <div class="mensaje-autor">
                                                <div class="mensaje-avatar <?= $c['rol'] !== 'ciudadano' ? 'agente' : '' ?>">
                                                    <?= strtoupper(substr($c['autor'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="mensaje-nombre">
                                                        <?= htmlspecialchars($c['autor']) ?>
                                                        <?php if ($c['es_interno']): ?>
                                                            <span
                                                                style="background: #f39c12; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 8px;">NOTA
                                                                PRIVADA</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mensaje-rol"><?= ucfirst($c['rol']) ?></div>
                                                </div>
                                            </div>
                                            <span class="mensaje-fecha"><?= formatearFecha($c['created_at']) ?></span>
                                        </div>
                                        <div class="mensaje-body">
                                            <?= nl2br(htmlspecialchars($c['comentario'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!empty($respuestas_predefinidas)): ?>
                                    <div style="margin-top: 16px; margin-bottom: 8px;">
                                        <button type="button" class="btn btn-sm btn-secundario" onclick="toggleRespuestas()">
                                            <i class="bi bi-chat-square-text"></i> Respuestas Rápidas
                                        </button>
                                    </div>
                                    <div id="respuestasRapidas" style="display: none; margin-bottom: 16px;">
                                        <?php foreach ($respuestas_predefinidas as $rp): ?>
                                            <div class="respuesta-rapida"
                                                onclick="usarRespuesta('<?= htmlspecialchars(addslashes($rp['contenido'])) ?>')">
                                                <strong style="font-size: 13px;"><?= htmlspecialchars($rp['titulo']) ?></strong>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="responder-box"
                                    style="margin-top: 24px; background: white; padding: 16px; border-radius: 6px; box-shadow: var(--sombra-sm);">
                                    <form method="POST" action="">
                                        <textarea name="comentario" id="comentarioTexto" class="responder-textarea"
                                            placeholder="Escriba su respuesta..." required></textarea>
                                        <div class="responder-acciones">
                                            <label
                                                style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                                                <input type="checkbox" name="es_interno" value="1">
                                                Nota privada (solo agentes)
                                            </label>
                                            <button type="submit" class="btn btn-primario">
                                                <i class="bi bi-send"></i> Enviar Respuesta
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="ticket-propiedades">
                                <form method="POST" action="">
                                    <input type="hidden" name="actualizar" value="1">

                                    <div class="propiedad-grupo">
                                        <div class="propiedad-titulo">Estado</div>
                                        <select name="estado_id" class="propiedad-select">
                                            <?php foreach ($estados as $e): ?>
                                                <option value="<?= $e['id'] ?>" <?= $ticket_actual['estado_id'] == $e['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($e['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="propiedad-grupo">
                                        <div class="propiedad-titulo">Prioridad</div>
                                        <select name="prioridad_id" class="propiedad-select">
                                            <?php foreach ($prioridades as $p): ?>
                                                <option value="<?= $p['id'] ?>" <?= $ticket_actual['prioridad_id'] == $p['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($p['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <?php if (tieneRol(['admin', 'supervisor'])): ?>
                                        <div class="propiedad-grupo">
                                            <div class="propiedad-titulo">Asignado a</div>
                                            <select name="asignado_id" class="propiedad-select">
                                                <option value="">-- Sin asignar --</option>
                                                <?php foreach ($funcionarios as $f): ?>
                                                    <option value="<?= $f['id'] ?>" <?= $ticket_actual['asignado_id'] == $f['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($f['nombres'] . ' ' . $f['apellidos']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php else: ?>
                                        <div class="propiedad-grupo">
                                            <div class="propiedad-titulo">Asignado a</div>
                                            <div class="propiedad-valor">
                                                <?= htmlspecialchars($ticket_actual['asignado_nombre'] ?? 'Sin asignar') ?>
                                            </div>
                                            <input type="hidden" name="asignado_id"
                                                value="<?= $ticket_actual['asignado_id'] ?>">
                                        </div>
                                    <?php endif; ?>

                                    <div class="propiedad-grupo">
                                        <button type="submit" class="btn btn-primario btn-bloque btn-sm">
                                            Actualizar
                                        </button>
                                    </div>
                                </form>
                                <?php if ($sla_info): ?>
                                    <div class="propiedad-grupo">
                                        <div class="propiedad-titulo">SLA</div>
                                        <?php if ($sla_info['respuesta_vencido']): ?>
                                            <div style="color: #e74c3c; font-size: 12px;">⚠️ Respuesta vencida</div>
                                        <?php elseif ($sla_info['respuesta_tiempo_restante']): ?>
                                            <div style="font-size: 12px;">Respuesta:
                                                <?= formatearTiempoSLA($sla_info['respuesta_tiempo_restante']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($sla_info['resolucion_vencido']): ?>
                                            <div style="color: #e74c3c; font-size: 12px;">⚠️ Resolución vencida</div>
                                        <?php elseif ($sla_info['resolucion_tiempo_restante']): ?>
                                            <div style="font-size: 12px;">Resolución:
                                                <?= formatearTiempoSLA($sla_info['resolucion_tiempo_restante']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="propiedad-grupo">
                                    <div class="propiedad-titulo">Solicitante</div>
                                    <div class="propiedad-valor">
                                        <strong><?= htmlspecialchars($ticket_actual['ciudadano_nombre'] ?? 'N/A') ?></strong><br>
                                        <small
                                            style="color: var(--gris-500);"><?= htmlspecialchars($ticket_actual['ciudadano_email'] ?? '') ?></small>
                                    </div>
                                </div>

                                <div class="propiedad-grupo">
                                    <div class="propiedad-titulo">Departamento</div>
                                    <div class="propiedad-valor">
                                        <?= htmlspecialchars($ticket_actual['departamento'] ?? 'N/A') ?>
                                    </div>
                                </div>

                                <div class="propiedad-grupo">
                                    <div class="propiedad-titulo">Creado</div>
                                    <div class="propiedad-valor"><?= formatearFecha($ticket_actual['created_at']) ?></div>
                                </div>

                                <?php if ($ticket_actual['satisfaccion']): ?>
                                    <div class="propiedad-grupo">
                                        <div class="propiedad-titulo">Satisfacción</div>
                                        <div class="propiedad-valor">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?= $i <= $ticket_actual['satisfaccion'] ? '-fill' : '' ?>"
                                                    style="color: #f39c12;"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div
                            style="flex: 1; display: flex; align-items: center; justify-content: center; color: var(--gris-500);">
                            <div style="text-align: center;">
                                <i class="bi bi-inbox"
                                    style="font-size: 64px; display: block; margin-bottom: 16px; opacity: 0.3;"></i>
                                <h3 style="margin-bottom: 8px; color: var(--gris-600);">Seleccione un ticket</h3>
                                <p>Haga clic en un ticket de la lista para ver los detalles</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="modalFusionar">
        <div class="modal-contenido">
            <div class="modal-header">
                <h3>Fusionar Tickets</h3>
                <button onclick="document.getElementById('modalFusionar').classList.remove('activo')"
                    style="background: none; border: none; font-size: 20px; cursor: pointer;">×</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 16px; color: var(--gris-600);">Ingrese el número del ticket que desea fusionar
                    con este:</p>
                <form method="POST" action="/admin/fusionar.php">
                    <input type="hidden" name="ticket_principal" value="<?= $ticket_actual['id'] ?? '' ?>">
                    <div class="form-grupo">
                        <label class="form-label">Número de ticket</label>
                        <input type="text" name="ticket_secundario" class="form-control"
                            placeholder="TKT-20260121-00001">
                    </div>
                    <button type="submit" class="btn btn-primario btn-bloque">Fusionar</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleRespuestas() {
            var el = document.getElementById('respuestasRapidas');
            el.style.display = el.style.display === 'none' ? 'block' : 'none';
        }

        function usarRespuesta(texto) {
            document.getElementById('comentarioTexto').value = texto;
            document.getElementById('respuestasRapidas').style.display = 'none';
        }

        function toggleNotificaciones() {
            alert('Panel de notificaciones en desarrollo');
        }

        <?php if ($ticket_actual): ?>
            setInterval(function () {
                fetch('/api/index.php?accion=actividad&ticket_id=<?= $ticket_actual['id'] ?>');
            }, 30000);
        <?php endif; ?>
    </script>
</body>

</html>