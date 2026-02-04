<?php
/**
 * Automatizaciones de tickets
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/freshdesk_functions.php';

requiereRol(['admin']);

$usuario = obtenerUsuarioActual();
$estados = obtenerEstados();
$prioridades = obtenerPrioridades();
$exito = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    
    if (isset($_POST['toggle'])) {
        $id = (int)$_POST['id'];
        $db->query("UPDATE automatizaciones SET activo = NOT activo WHERE id = ?", [$id]);
        $exito = 'Estado actualizado.';
    }
    
    if (isset($_POST['eliminar'])) {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM automatizaciones WHERE id = ?", [$id]);
        $exito = 'Automatización eliminada.';
    }
    
    if (isset($_POST['crear'])) {
        $nombre = limpiarInput($_POST['nombre']);
        $descripcion = limpiarInput($_POST['descripcion'] ?? '');
        $evento = $_POST['evento'];
        
        $condiciones = [];
        if (!empty($_POST['condicion_campo']) && !empty($_POST['condicion_valor'])) {
            $condiciones[] = [
                'campo' => $_POST['condicion_campo'],
                'operador' => $_POST['condicion_operador'] ?? 'igual',
                'valor' => $_POST['condicion_valor']
            ];
        }
 
        $acciones = [];
        if (!empty($_POST['accion_tipo'])) {
            $acciones[] = [
                'tipo' => $_POST['accion_tipo'],
                'valor' => $_POST['accion_valor'] ?? ''
            ];
        }
        
        if (!empty($nombre) && !empty($evento) && !empty($acciones)) {
            $db->query("
                INSERT INTO automatizaciones (nombre, descripcion, evento, condiciones, acciones)
                VALUES (?, ?, ?, ?, ?)
            ", [$nombre, $descripcion, $evento, json_encode($condiciones), json_encode($acciones)]);
            $exito = 'Automatización creada.';
        } else {
            $error = 'Complete los campos requeridos.';
        }
    }
}

$db = Database::getInstance();
$automatizaciones = $db->fetchAll("SELECT * FROM automatizaciones ORDER BY activo DESC, orden");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automatizaciones - Mesa de Ayuda</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .form-label {
            color: #1f2937 !important;
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
                <a href="/admin/dashboard.php" class="menu-item">
                    <i class="bi bi-inbox"></i>
                    <span>Tickets</span>
                </a>
                
                <div class="menu-seccion">Administración</div>
                <a href="/admin/respuestas-predefinidas.php" class="menu-item">
                    <i class="bi bi-chat-square-text"></i>
                    <span>Respuestas</span>
                </a>
                <a href="/admin/automatizaciones.php" class="menu-item activo">
                    <i class="bi bi-lightning"></i>
                    <span>Automatizaciones</span>
                </a>
                <a href="/admin/sla.php" class="menu-item">
                    <i class="bi bi-clock-history"></i>
                    <span>Políticas SLA</span>
                </a>
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
                    <nav class="breadcrumb">
                        <a href="/admin/dashboard.php">Inicio</a>
                        <span class="breadcrumb-separador">/</span>
                        <span>Automatizaciones</span>
                    </nav>
                </div>
            </header>
            
            <main class="pagina-contenido">
                <div style="margin-bottom: 24px;">
                    <h1 style="font-size: 24px; font-weight: 600; color: var(--gris-900); margin-bottom: 8px;">
                        <i class="bi bi-lightning"></i> Automatizaciones
                    </h1>
                    <p style="color: var(--gris-600);">Configure reglas automáticas para gestionar tickets</p>
                </div>
                
                <?php if ($exito): ?>
                <div class="alerta alerta-exito">
                    <i class="bi bi-check-circle"></i> <?= $exito ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alerta alerta-error">
                    <i class="bi bi-exclamation-circle"></i> <?= $error ?>
                </div>
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                    <div class="tarjeta">
                        <div class="tarjeta-header">
                            <h3 class="tarjeta-titulo">Reglas Configuradas</h3>
                        </div>
                        
                        <?php if (empty($automatizaciones)): ?>
                        <div style="padding: 40px; text-align: center; color: var(--gris-500);">
                            <i class="bi bi-lightning" style="font-size: 48px; display: block; margin-bottom: 16px;"></i>
                            No hay automatizaciones configuradas
                        </div>
                        <?php else: ?>
                        <div style="padding: 16px;">
                            <?php foreach ($automatizaciones as $auto): ?>
                            <div style="padding: 16px; border: 1px solid var(--gris-200); border-radius: 8px; margin-bottom: 12px; 
                                        <?= !$auto['activo'] ? 'opacity: 0.6;' : '' ?>">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                    <div>
                                        <h4 style="font-size: 15px; font-weight: 600; color: var(--gris-900);">
                                            <?= htmlspecialchars($auto['nombre']) ?>
                                        </h4>
                                        <?php if ($auto['descripcion']): ?>
                                        <p style="font-size: 13px; color: var(--gris-600); margin-top: 4px;">
                                            <?= htmlspecialchars($auto['descripcion']) ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <span style="font-size: 11px; color: var(--gris-500);">
                                            <?= $auto['ejecuciones'] ?> ejecuciones
                                        </span>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="toggle" value="1">
                                            <input type="hidden" name="id" value="<?= $auto['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $auto['activo'] ? 'btn-exito' : 'btn-secundario' ?>">
                                                <?= $auto['activo'] ? 'Activa' : 'Inactiva' ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar?')">
                                            <input type="hidden" name="eliminar" value="1">
                                            <input type="hidden" name="id" value="<?= $auto['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-secundario">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 16px; font-size: 12px;">
                                    <span style="background: var(--gris-100); padding: 4px 8px; border-radius: 4px;">
                                        <i class="bi bi-play-circle"></i> 
                                        <?php
                                        $eventos = [
                                            'ticket_creado' => 'Al crear ticket',
                                            'ticket_actualizado' => 'Al actualizar ticket',
                                            'tiempo' => 'Basado en tiempo',
                                            'respuesta_ciudadano' => 'Respuesta del ciudadano'
                                        ];
                                        echo $eventos[$auto['evento']] ?? $auto['evento'];
                                        ?>
                                    </span>
                                    <?php 
                                    $acciones = json_decode($auto['acciones'], true) ?: [];
                                    foreach ($acciones as $acc): 
                                    ?>
                                    <span style="background: #e8f4fc; padding: 4px 8px; border-radius: 4px; color: var(--color-acento);">
                                        <i class="bi bi-arrow-right"></i> <?= htmlspecialchars($acc['tipo']) ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="tarjeta">
                        <div class="tarjeta-header">
                            <h3 class="tarjeta-titulo">Nueva Regla</h3>
                        </div>
                        <div class="tarjeta-body">
                            <form method="POST" action="">
                                <input type="hidden" name="crear" value="1">
                                
                                <div class="form-grupo">
                                    <label class="form-label requerido">Nombre</label>
                                    <input type="text" name="nombre" class="form-control" placeholder="Ej: Auto-asignar tickets" required>
                                </div>
                                
                                <div class="form-grupo">
                                    <label class="form-label">Descripción</label>
                                    <input type="text" name="descripcion" class="form-control" placeholder="Descripción opcional">
                                </div>
                                
                                <div class="form-grupo">
                                    <label class="form-label requerido">Evento disparador</label>
                                    <select name="evento" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        <option value="ticket_creado">Cuando se crea un ticket</option>
                                        <option value="ticket_actualizado">Cuando se actualiza un ticket</option>
                                        <option value="respuesta_ciudadano">Cuando el ciudadano responde</option>
                                    </select>
                                </div>
                                
                                <div style="background: var(--gris-50); padding: 12px; border-radius: 6px; margin-bottom: 16px;">
                                    <div style="font-size: 12px; font-weight: 600; color: var(--gris-600); margin-bottom: 8px;">
                                        CONDICIÓN (opcional)
                                    </div>
                                    <div class="form-fila">
                                        <select name="condicion_campo" class="form-control">
                                            <option value="">Campo</option>
                                            <option value="prioridad_id">Prioridad</option>
                                            <option value="estado_id">Estado</option>
                                            <option value="categoria">Categoría</option>
                                        </select>
                                        <select name="condicion_operador" class="form-control">
                                            <option value="igual">es igual a</option>
                                            <option value="no_igual">no es igual a</option>
                                            <option value="contiene">contiene</option>
                                        </select>
                                    </div>
                                    <input type="text" name="condicion_valor" class="form-control mt-1" placeholder="Valor">
                                </div>
                                
                                <div style="background: #e8f4fc; padding: 12px; border-radius: 6px; margin-bottom: 16px;">
                                    <div style="font-size: 12px; font-weight: 600; color: var(--color-acento); margin-bottom: 8px;">
                                        ACCIÓN A EJECUTAR
                                    </div>
                                    <select name="accion_tipo" class="form-control" required>
                                        <option value="">Seleccione acción...</option>
                                        <option value="cambiar_estado">Cambiar estado</option>
                                        <option value="cambiar_prioridad">Cambiar prioridad</option>
                                        <option value="asignar">Asignar a supervisor</option>
                                        <option value="agregar_etiqueta">Agregar etiqueta</option>
                                        <option value="notificar">Enviar notificación</option>
                                    </select>
                                    <select name="accion_valor" class="form-control mt-1">
                                        <option value="">Valor de la acción</option>
                                        <option value="supervisor_departamento">Supervisor del departamento</option>
                                        <?php foreach ($estados as $e): ?>
                                        <option value="<?= $e['id'] ?>">Estado: <?= htmlspecialchars($e['nombre']) ?></option>
                                        <?php endforeach; ?>
                                        <?php foreach ($prioridades as $p): ?>
                                        <option value="<?= $p['id'] ?>">Prioridad: <?= htmlspecialchars($p['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primario btn-bloque">
                                    <i class="bi bi-plus"></i> Crear Automatización
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
