<?php
/**
 * Ver Ticket - Portal Ciudadano
 */
require_once __DIR__ . '/../includes/functions.php';

requiereAutenticacion();

$usuario = obtenerUsuarioActual();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /ciudadano/mis-tickets.php');
    exit;
}

$ticket = obtenerTicket($id);

// Verificar que el ticket pertenece al usuario
if (!$ticket || $ticket['ciudadano_id'] != $usuario['id']) {
    header('Location: /ciudadano/mis-tickets.php?error=acceso');
    exit;
}

$comentarios = obtenerComentarios($id);
$exito = '';

// Agregar comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario']) && !empty($_POST['comentario'])) {
    agregarComentario($id, $usuario['id'], limpiarInput($_POST['comentario']), false);
    $exito = 'Comentario enviado correctamente.';
    $comentarios = obtenerComentarios($id);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?= htmlspecialchars($ticket['numero']) ?> - Sistema Municipal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="header-municipalidad">
        <div class="contenedor">
            <div class="header-contenido">
                <div class="header-logo">
                    <div style="width:60px;height:60px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-building" style="font-size:1.8rem;color:var(--color-primario)"></i>
                    </div>
                    <div class="header-titulo">
                        <h1>Municipalidad</h1>
                        <span>Portal Ciudadano</span>
                    </div>
                </div>
                <nav class="header-nav">
                    <a href="/">Inicio</a>
                    <a href="/ciudadano/mis-tickets.php">Mis Tickets</a>
                    <a href="/logout.php" class="btn btn-secundario" style="background:rgba(255,255,255,0.15);border:none;color:white;">
                        <i class="bi bi-box-arrow-right"></i> Salir
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="contenedor" style="padding: var(--espaciado-2xl) 0;">
        <div style="margin-bottom: var(--espaciado-lg);">
            <a href="/ciudadano/mis-tickets.php" style="display: inline-flex; align-items: center; gap: var(--espaciado-sm); color: var(--gris-600);">
                <i class="bi bi-arrow-left"></i> Volver a mis solicitudes
            </a>
        </div>
        
        <?php if ($exito): ?>
        <div class="alerta alerta-exito">
            <i class="bi bi-check-circle"></i>
            <div><?= $exito ?></div>
        </div>
        <?php endif; ?>
        
        <div class="ticket-detalle-grid">
            <!-- Información Principal -->
            <div class="ticket-info-principal">
                <!-- Encabezado -->
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
                                        <i class="bi bi-calendar"></i>
                                        <span><?= formatearFecha($ticket['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php if ($ticket['estado_id'] != 1): ?>
                            <span class="badge badge-estado" style="background: <?= $ticket['estado_color'] ?>; font-size: 0.9rem; padding: 0.5rem 1rem;">
                                <?= htmlspecialchars($ticket['estado']) ?>
                            </span>
                            <?php else: ?>
                            <span class="badge badge-estado" style="background: #6c757d; font-size: 0.9rem; padding: 0.5rem 1rem;">
                                Enviado
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Descripción -->
                <div class="tarjeta">
                    <div class="tarjeta-header">
                        <h3 class="tarjeta-titulo"><i class="bi bi-card-text"></i> Descripción</h3>
                    </div>
                    <div class="tarjeta-body">
                        <p style="line-height: 1.8;"><?= nl2br(htmlspecialchars($ticket['descripcion'])) ?></p>
                        
                        <?php if ($ticket['ubicacion_direccion']): ?>
                        <div style="margin-top: var(--espaciado-lg); padding: var(--espaciado-md); background: var(--gris-50); border-radius: var(--radio-md);">
                            <i class="bi bi-geo-alt"></i> 
                            <strong>Ubicación:</strong> <?= htmlspecialchars($ticket['ubicacion_direccion']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Historial de Comunicación -->
                <div class="tarjeta">
                    <div class="tarjeta-header">
                        <h3 class="tarjeta-titulo"><i class="bi bi-chat-dots"></i> Comunicación</h3>
                    </div>
                    <div class="tarjeta-body">
                        <?php if (empty($comentarios)): ?>
                        <p style="color: var(--gris-500); text-align: center; padding: var(--espaciado-lg);">
                            No hay mensajes aún. Use el formulario de abajo para enviar un mensaje.
                        </p>
                        <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($comentarios as $c): ?>
                            <?php $es_mio = ($c['usuario_id'] == $usuario['id']); ?>
                            <div class="timeline-item">
                                <div class="timeline-fecha"><?= formatearFecha($c['created_at']) ?></div>
                                <div class="timeline-contenido" style="<?= $es_mio ? 'background: #e6f2ff; border-left: 3px solid var(--color-info);' : '' ?>">
                                    <div class="timeline-autor">
                                        <?= $es_mio ? 'Usted' : htmlspecialchars($c['autor']) ?>
                                        <span style="font-weight: 400; font-size: 0.75rem; color: var(--gris-500);">
                                            (<?= $es_mio ? 'Yo' : ucfirst($c['rol']) ?>)
                                        </span>
                                    </div>
                                    <div class="timeline-texto"><?= nl2br(htmlspecialchars($c['comentario'])) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Formulario -->
                        <?php if (!in_array($ticket['estado_id'], [6, 7])): // No cerrado ni rechazado ?>
                        <form method="POST" action="" style="margin-top: var(--espaciado-xl); padding-top: var(--espaciado-lg); border-top: 1px solid var(--gris-200);">
                            <div class="form-grupo">
                                <label class="form-label">Enviar Mensaje</label>
                                <textarea name="comentario" class="form-control" rows="3" 
                                          placeholder="Escriba su mensaje..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primario">
                                <i class="bi bi-send"></i> Enviar
                            </button>
                        </form>
                        <?php else: ?>
                        <div style="margin-top: var(--espaciado-lg); padding: var(--espaciado-md); background: var(--gris-100); border-radius: var(--radio-md); text-align: center; color: var(--gris-600);">
                            <i class="bi bi-lock"></i> Este ticket está cerrado y no acepta nuevos mensajes.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="ticket-sidebar">
                <div class="sidebar-seccion">
                    <div class="sidebar-seccion-titulo">Estado Actual</div>
                    <div style="text-align: center; padding: var(--espaciado-md);">
                        <?php if ($ticket['estado_id'] != 1): ?>
                        <span class="badge badge-estado" style="background: <?= $ticket['estado_color'] ?>; font-size: 1rem; padding: 0.75rem 1.5rem;">
                            <?= htmlspecialchars($ticket['estado']) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge badge-estado" style="background: #6c757d; font-size: 1rem; padding: 0.75rem 1.5rem;">
                            Enviado
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="sidebar-seccion">
                    <div class="sidebar-seccion-titulo">Información</div>
                    <div class="info-fila">
                        <span class="info-etiqueta">Departamento</span>
                        <span class="info-valor"><?= htmlspecialchars($ticket['departamento']) ?></span>
                    </div>
                    <div class="info-fila">
                        <span class="info-etiqueta">Categoría</span>
                        <span class="info-valor"><?= htmlspecialchars($ticket['categoria']) ?></span>
                    </div>
                    <div class="info-fila">
                        <span class="info-etiqueta">Prioridad</span>
                        <span class="badge badge-prioridad" style="border-color: <?= $ticket['prioridad_color'] ?>; color: <?= $ticket['prioridad_color'] ?>">
                            <?= htmlspecialchars($ticket['prioridad']) ?>
                        </span>
                    </div>
                    <div class="info-fila">
                        <span class="info-etiqueta">Atendido por</span>
                        <span class="info-valor"><?= htmlspecialchars($ticket['asignado_nombre'] ?? 'Por asignar') ?></span>
                    </div>
                    <div class="info-fila">
                        <span class="info-etiqueta">Creado</span>
                        <span class="info-valor"><?= formatearFecha($ticket['created_at']) ?></span>
                    </div>
                    <div class="info-fila">
                        <span class="info-etiqueta">Última actualización</span>
                        <span class="info-valor"><?= tiempoTranscurrido($ticket['updated_at']) ?></span>
                    </div>
                </div>
                
                <div class="sidebar-seccion">
                    <div class="sidebar-seccion-titulo">¿Necesita ayuda?</div>
                    <p style="font-size: 0.875rem; color: var(--gris-600); margin-bottom: var(--espaciado-md);">
                        Si tiene problemas con su solicitud, puede contactarnos:
                    </p>
                    <a href="tel:+56212345678" class="btn btn-secundario btn-bloque" style="justify-content: flex-start; margin-bottom: var(--espaciado-sm);">
                        <i class="bi bi-telephone"></i> (02) 1234-5678
                    </a>
                    <a href="mailto:atencion@municipalidad.cl" class="btn btn-secundario btn-bloque" style="justify-content: flex-start;">
                        <i class="bi bi-envelope"></i> atencion@municipalidad.cl
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="contenedor">
            <div class="footer-contenido">
                <div class="footer-texto">© <?= date('Y') ?> Municipalidad</div>
            </div>
        </div>
    </footer>
</body>
</html>
