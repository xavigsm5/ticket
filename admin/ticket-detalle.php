<?php
/**
 * Detalle del ticket (admin)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/freshdesk_functions.php';

requiereRol(['admin', 'supervisor', 'funcionario']);

$usuario = obtenerUsuarioActual();
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /admin/tickets.php');
    exit;
}

$ticket = obtenerTicket($id);
if (!$ticket) {
    header('Location: /admin/tickets.php?error=notfound');
    exit;
}

$comentarios = obtenerComentarios($id);
$estados = obtenerEstados();
$prioridades = obtenerPrioridades();
$funcionarios = obtenerFuncionarios($ticket['departamento_id']);
$exito = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['comentario']) && !empty($_POST['comentario'])) {
        $es_interno = isset($_POST['es_interno']);
        agregarComentario($id, $usuario['id'], limpiarInput($_POST['comentario']), $es_interno);
        $exito = 'Comentario agregado correctamente.';
        $comentarios = obtenerComentarios($id);
    }


    if (isset($_POST['actualizar_ticket'])) {
        $datos = [];
        if (isset($_POST['estado_id']))
            $datos['estado_id'] = (int) $_POST['estado_id'];
        if (isset($_POST['prioridad_id']))
            $datos['prioridad_id'] = (int) $_POST['prioridad_id'];
        if (isset($_POST['asignado_id']))
            $datos['asignado_id'] = $_POST['asignado_id'] ?: null;

        actualizarTicket($id, $datos);

        // Verificar si cambió la prioridad para recalcular SLA
        if (isset($datos['prioridad_id']) && $datos['prioridad_id'] != $ticket['prioridad_id']) {
            aplicarSLA($id);
        }

        $exito = 'Ticket actualizado correctamente.';
        $ticket = obtenerTicket($id);
    }

    // Manejar Etiquetas
    if (isset($_POST['agregar_etiqueta']) && !empty($_POST['etiqueta_nombre'])) {
        $nombre = trim($_POST['etiqueta_nombre']);
        $color = $_POST['etiqueta_color'] ?? '#6c757d';

        // Buscar o crear etiqueta
        $db = Database::getInstance();
        $etiqueta = $db->fetch("SELECT id FROM etiquetas WHERE nombre = ?", [strtolower($nombre)]);

        if ($etiqueta) {
            $etiqueta_id = $etiqueta['id'];
        } else {
            $etiqueta_id = crearEtiqueta($nombre, $color);
        }

        if (agregarEtiquetaTicket($id, $etiqueta_id)) {
            $exito = 'Etiqueta agregada.';
        }
    }

    if (isset($_POST['eliminar_etiqueta'])) {
        eliminarEtiquetaTicket($id, (int) $_POST['eliminar_etiqueta']);
        $exito = 'Etiqueta eliminada.';
    }
}

// Datos Adicionales Freshdesk
$sla_status = verificarSLAVencido($id);
$etiquetas_ticket = obtenerEtiquetasTicket($id);
$respuestas_predefinidas = obtenerRespuestasPredefinidas(null, $usuario['id']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?= htmlspecialchars($ticket['numero']) ?> - Sistema Municipal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
    <div class="layout-app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="bi bi-building" style="font-size:2.5rem;color:var(--color-primario)"></i>
                </div>
                <div class="sidebar-titulo">Municipalidad</div>
                <div class="sidebar-subtitulo">Panel de Gestión</div>
            </div>

            <nav class="sidebar-menu">
                <div class="menu-seccion">Principal</div>
                <a href="/admin/dashboard.php" class="menu-item">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/admin/tickets.php" class="menu-item activo">
                    <i class="bi bi-ticket-detailed"></i>
                    <span>Tickets</span>
                </a>

                <div class="menu-seccion">Cuenta</div>
                <a href="/logout.php" class="menu-item">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="usuario-info">
                    <div class="usuario-avatar">
                        <?= strtoupper(substr($usuario['nombres'], 0, 1) . substr($usuario['apellidos'], 0, 1)) ?>
                    </div>
                    <div class="usuario-datos">
                        <div class="usuario-nombre"><?= htmlspecialchars($usuario['nombres']) ?></div>
                        <div class="usuario-rol"><?= ucfirst($usuario['rol']) ?></div>
                    </div>
                </div>
            </div>
        </aside>
        <div class="contenido-principal">
            <header class="topbar">
                <div class="topbar-izquierda">
                    <nav class="breadcrumb">
                        <a href="/admin/dashboard.php">Inicio</a>
                        <span class="breadcrumb-separador">/</span>
                        <a href="/admin/tickets.php">Tickets</a>
                        <span class="breadcrumb-separador">/</span>
                        <span><?= htmlspecialchars($ticket['numero']) ?></span>
                    </nav>
                </div>
                <div class="topbar-derecha">
                    <a href="/admin/tickets.php" class="btn btn-sm btn-secundario">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </header>

            <main class="pagina-contenido">
                <?php if ($exito): ?>
                    <div class="alerta alerta-exito">
                        <i class="bi bi-check-circle"></i>
                        <div><?= $exito ?></div>
                    </div>
                <?php endif; ?>

                <div class="ticket-detalle-grid">

                    <div class="ticket-info-principal">

                        <div class="tarjeta">
                            <div class="tarjeta-body">
                                <div class="ticket-encabezado">
                                    <div>
                                        <div class="ticket-id"><?= htmlspecialchars($ticket['numero']) ?></div>
                                        <h1 class="ticket-titulo"><?= htmlspecialchars($ticket['asunto']) ?></h1>
                                        <div class="ticket-meta">
                                            <div class="ticket-meta-item">
                                                <i class="bi bi-folder"></i>
                                                <span><?= htmlspecialchars($ticket['categoria']) ?></span>
                                            </div>
                                            <div class="ticket-meta-item">
                                                <i class="bi bi-building"></i>
                                                <span><?= htmlspecialchars($ticket['departamento']) ?></span>
                                            </div>
                                            <div class="ticket-meta-item">
                                                <i class="bi bi-calendar"></i>
                                                <span><?= formatearFecha($ticket['created_at']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        style="display: flex; gap: var(--espaciado-sm); align-items: center; flex-wrap: wrap;">
                                        <span class="badge badge-estado"
                                            style="background: <?= $ticket['estado_color'] ?>">
                                            <?= htmlspecialchars($ticket['estado']) ?>
                                        </span>
                                        <span class="badge badge-prioridad"
                                            style="border-color: <?= $ticket['prioridad_color'] ?>; color: <?= $ticket['prioridad_color'] ?>">
                                            <?= htmlspecialchars($ticket['prioridad']) ?>
                                        </span>

                                        <!-- SLA Badge -->
                                        <?php if ($sla_status): ?>
                                            <?php if ($sla_status['resolucion_vencido']): ?>
                                                <span class="badge" style="background: #dc3545; color: white;"
                                                    title="Tiempo de resolución excedido">
                                                    <i class="bi bi-exclamation-octagon"></i> SLA Vencido
                                                </span>
                                            <?php elseif ($sla_status['resolucion_tiempo_restante']): ?>
                                                <span class="badge" style="background: #17a2b8; color: white;"
                                                    title="Tiempo restante para resolución">
                                                    <i class="bi bi-hourglass-split"></i>
                                                    Vence en:
                                                    <?= formatearTiempoSLA($sla_status['resolucion_tiempo_restante']) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- Etiquetas Badges -->
                                        <?php foreach ($etiquetas_ticket as $tag): ?>
                                            <span class="badge"
                                                style="background: <?= $tag['color'] ?>; color: white; display: flex; align-items: center; gap: 4px;">
                                                <i class="bi bi-tag-fill" style="font-size: 0.7em;"></i>
                                                <?= htmlspecialchars($tag['nombre']) ?>
                                                <form method="POST" action="" style="display:inline;">
                                                    <input type="hidden" name="eliminar_etiqueta" value="<?= $tag['id'] ?>">
                                                    <button type="submit"
                                                        style="background:none; border:none; color:white; cursor:pointer; padding:0; margin-left:4px; font-size: 10px;">✕</button>
                                                </form>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tarjeta">
                            <div class="tarjeta-header">
                                <h3 class="tarjeta-titulo"><i class="bi bi-card-text"></i> Descripción</h3>
                            </div>
                            <div class="tarjeta-body">
                                <p style="line-height: 1.8; color: var(--gris-700);">
                                    <?= nl2br(htmlspecialchars($ticket['descripcion'])) ?>
                                </p>

                                <?php if ($ticket['ubicacion_direccion']): ?>
                                    <div
                                        style="margin-top: var(--espaciado-lg); padding-top: var(--espaciado-lg); border-top: 1px solid var(--gris-200);">
                                        <strong><i class="bi bi-geo-alt"></i> Ubicación:</strong>
                                        <span
                                            style="color: var(--gris-600);"><?= htmlspecialchars($ticket['ubicacion_direccion']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php 
                                $adjuntos_html = mostrarAdjuntosHTML($ticket['id']);
                                if (!empty($adjuntos_html)):
                                ?>
                                <div style="margin-top: var(--espaciado-lg); padding-top: var(--espaciado-lg); border-top: 1px solid var(--gris-200);">
                                    <?= $adjuntos_html ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="tarjeta">
                            <div class="tarjeta-header">
                                <h3 class="tarjeta-titulo">
                                    <i class="bi bi-chat-dots"></i> Comentarios
                                    <span style="font-weight: 400; font-size: 0.875rem; color: var(--gris-500);">
                                        (<?= count($comentarios) ?>)
                                    </span>
                                </h3>
                            </div>
                            <div class="tarjeta-body">
                                <?php if (empty($comentarios)): ?>
                                    <p style="color: var(--gris-500); text-align: center; padding: var(--espaciado-lg);">
                                        No hay comentarios aún.
                                    </p>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($comentarios as $c): ?>
                                            <div class="timeline-item">
                                                <div class="timeline-fecha"><?= formatearFecha($c['created_at']) ?></div>
                                                <div class="timeline-contenido" <?= $c['es_interno'] ? 'style="background: #fff3cd; border-left: 3px solid var(--color-advertencia);"' : '' ?>>
                                                    <div class="timeline-autor">
                                                        <?= htmlspecialchars($c['autor']) ?>
                                                        <span
                                                            style="font-weight: 400; font-size: 0.75rem; color: var(--gris-500); margin-left: var(--espaciado-sm);">
                                                            (<?= ucfirst($c['rol']) ?>)
                                                        </span>
                                                        <?php if ($c['es_interno']): ?>
                                                            <span class="badge"
                                                                style="background: var(--color-advertencia); color: white; font-size: 0.65rem; margin-left: var(--espaciado-sm);">
                                                                INTERNO
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="timeline-texto"><?= nl2br(htmlspecialchars($c['comentario'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <form method="POST" action=""
                                    style="margin-top: var(--espaciado-xl); padding-top: var(--espaciado-lg); border-top: 1px solid var(--gris-200);">
                                    <div class="form-grupo">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                            <label class="form-label" style="margin:0;">Agregar Comentario</label>
                                            <select id="cannedResponse" class="form-control"
                                                style="width: auto; padding: 2px 8px; font-size: 0.85rem; height: auto;">
                                                <option value="">-- Respuestas Predefinidas --</option>
                                                <?php foreach ($respuestas_predefinidas as $rp): ?>
                                                    <option value="<?= htmlspecialchars($rp['contenido']) ?>">
                                                        <?= htmlspecialchars($rp['titulo']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <textarea id="comentarioText" name="comentario" class="form-control" rows="3"
                                            placeholder="Escriba su comentario aquí..." required></textarea>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <label
                                            style="display: flex; align-items: center; gap: var(--espaciado-sm); cursor: pointer;">
                                            <input type="checkbox" name="es_interno" style="width: auto;">
                                            <span style="font-size: 0.875rem;">Comentario interno (solo visible para
                                                funcionarios)</span>
                                        </label>
                                        <button type="submit" class="btn btn-primario">
                                            <i class="bi bi-send"></i> Enviar
                                        </button>
                                    </div>
                                </form>
                                <script>
                                    document.getElementById('cannedResponse').addEventListener('change', function () {
                                        if (this.value) {
                                            const textarea = document.getElementById('comentarioText');
                                            textarea.value = (textarea.value ? textarea.value + "\n\n" : "") + this.value;
                                            this.value = ""; // Reset
                                        }
                                    });
                                </script>
                            </div>
                        </div>
                    </div>
                    <div class="ticket-sidebar">
                        <div class="sidebar-seccion">
                            <div class="sidebar-seccion-titulo">Información</div>
                            <div class="info-fila">
                                <span class="info-etiqueta">Solicitante</span>
                                <span
                                    class="info-valor"><?= htmlspecialchars($ticket['ciudadano_nombre'] ?? 'Anónimo') ?></span>
                            </div>
                            <div class="info-fila">
                                <span class="info-etiqueta">Email</span>
                                <span class="info-valor"
                                    style="font-size: 0.8rem;"><?= htmlspecialchars($ticket['ciudadano_email'] ?? '-') ?></span>
                            </div>
                            <div class="info-fila">
                                <span class="info-etiqueta">Asignado a</span>
                                <span
                                    class="info-valor"><?= htmlspecialchars($ticket['asignado_nombre'] ?? 'Sin asignar') ?></span>
                            </div>
                            <div class="info-fila">
                                <span class="info-etiqueta">Creado</span>
                                <span class="info-valor"><?= formatearFecha($ticket['created_at']) ?></span>
                            </div>
                            <div class="info-fila">
                                <span class="info-etiqueta">Actualizado</span>
                                <span class="info-valor"><?= tiempoTranscurrido($ticket['updated_at']) ?></span>
                            </div>
                        </div>
                        <div class="sidebar-seccion">
                            <div class="sidebar-seccion-titulo">Gestión del Ticket</div>
                            <form method="POST" action="">
                                <input type="hidden" name="actualizar_ticket" value="1">

                                <div class="form-grupo">
                                    <label class="form-label">Estado</label>
                                    <select name="estado_id" class="form-control">
                                        <?php foreach ($estados as $e): ?>
                                            <option value="<?= $e['id'] ?>" <?= ($ticket['estado_id'] == $e['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($e['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-grupo">
                                    <label class="form-label">Prioridad</label>
                                    <select name="prioridad_id" class="form-control">
                                        <?php foreach ($prioridades as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= ($ticket['prioridad_id'] == $p['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php if (tieneRol(['admin', 'supervisor'])): ?>
                                <div class="form-grupo">
                                    <label class="form-label">Asignar a</label>
                                    <select name="asignado_id" class="form-control">
                                        <option value="">Sin asignar</option>
                                        <?php foreach ($funcionarios as $f): ?>
                                            <option value="<?= $f['id'] ?>" <?= ($ticket['asignado_id'] == $f['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($f['nombres'] . ' ' . $f['apellidos']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-primario btn-bloque">
                                    <i class="bi bi-check-lg"></i> Actualizar
                                </button>
                            </form>
                        </div>
                        <div class="sidebar-seccion">
                            <div class="sidebar-seccion-titulo">Etiquetas</div>
                            <form method="POST" action="" style="display: flex; gap: 4px;">
                                <input type="text" name="etiqueta_nombre" class="form-control"
                                    placeholder="Nueva etiqueta..." required style="margin:0; font-size: 0.85rem;">
                                <input type="color" name="etiqueta_color" value="#17a2b8"
                                    style="width: 30px; padding: 0; border: none; height: 38px;">
                                <button type="submit" name="agregar_etiqueta" value="1" class="btn btn-primario"
                                    style="padding: 0 10px;">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </form>
                            <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 4px;">
                                <?php if (empty($etiquetas_ticket)): ?>
                                    <span style="font-size: 0.8rem; color: var(--gris-500);">Sin etiquetas</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (tieneRol(['admin', 'supervisor'])): ?>
                        <div class="sidebar-seccion">
                            <div class="sidebar-seccion-titulo">Acciones Rápidas</div>
                            <form method="POST" action=""
                                style="display: flex; flex-direction: column; gap: var(--espaciado-sm);">
                                <input type="hidden" name="actualizar_ticket" value="1">
                                <input type="hidden" name="asignado_id" value="<?= $usuario['id'] ?>">
                                <button type="submit" class="btn btn-secundario btn-bloque"
                                    style="justify-content: flex-start;">
                                    <i class="bi bi-person-check"></i> Asignarme este ticket
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>

</html>