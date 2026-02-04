<?php
/**
 * Gestión de SLA
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/freshdesk_functions.php';

requiereRol(['admin']);

$usuario = obtenerUsuarioActual();
$prioridades = obtenerPrioridades();
$departamentos = obtenerDepartamentos();
$exito = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear'])) {
        $nombre = limpiarInput($_POST['nombre']);
        $tiempo_respuesta = (int)$_POST['tiempo_respuesta'];
        $tiempo_resolucion = (int)$_POST['tiempo_resolucion'];
        $prioridad_id = $_POST['prioridad_id'] ?: null;
        $departamento_id = $_POST['departamento_id'] ?: null;
        
        if (!empty($nombre) && $tiempo_respuesta > 0 && $tiempo_resolucion > 0) {
            $db = Database::getInstance();
            $db->query("
                INSERT INTO sla_politicas (nombre, tiempo_primera_respuesta_horas, tiempo_resolucion_horas, prioridad_id, departamento_id)
                VALUES (?, ?, ?, ?, ?)
            ", [$nombre, $tiempo_respuesta, $tiempo_resolucion, $prioridad_id, $departamento_id]);
            $exito = 'Política SLA creada correctamente.';
        } else {
            $error = 'Complete todos los campos requeridos.';
        }
    }
    
    if (isset($_POST['actualizar'])) {
        $id = (int)$_POST['id'];
        $tiempo_respuesta = (int)$_POST['tiempo_respuesta'];
        $tiempo_resolucion = (int)$_POST['tiempo_resolucion'];
        
        $db = Database::getInstance();
        $db->query("
            UPDATE sla_politicas 
            SET tiempo_primera_respuesta_horas = ?, tiempo_resolucion_horas = ?
            WHERE id = ?
        ", [$tiempo_respuesta, $tiempo_resolucion, $id]);
        $exito = 'Política actualizada.';
    }
    
    if (isset($_POST['eliminar'])) {
        $id = (int)$_POST['id'];
        $db = Database::getInstance();
        $db->query("DELETE FROM sla_politicas WHERE id = ?", [$id]);
        $exito = 'Política eliminada.';
    }
}

$politicas = obtenerPoliticasSLA();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Políticas SLA - Mesa de Ayuda</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
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
                <a href="/admin/automatizaciones.php" class="menu-item">
                    <i class="bi bi-lightning"></i>
                    <span>Automatizaciones</span>
                </a>
                <a href="/admin/sla.php" class="menu-item activo">
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
                        <span>Políticas SLA</span>
                    </nav>
                </div>
            </header>
            
            <main class="pagina-contenido">
                <div style="margin-bottom: 24px;">
                    <h1 style="font-size: 24px; font-weight: 600; color: var(--gris-900); margin-bottom: 8px;">
                        <i class="bi bi-clock-history"></i> Acuerdos de Nivel de Servicio (SLA)
                    </h1>
                    <p style="color: var(--gris-600);">Configure los tiempos de respuesta y resolución para cada prioridad</p>
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
                            <h3 class="tarjeta-titulo">Políticas Configuradas</h3>
                        </div>
                        <div class="tabla-contenedor">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Prioridad</th>
                                        <th>Primera Respuesta</th>
                                        <th>Resolución</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($politicas as $p): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                                        <td>
                                            <?php 
                                            $prio = array_filter($prioridades, fn($pr) => $pr['id'] == $p['prioridad_id']);
                                            echo $prio ? htmlspecialchars(reset($prio)['nombre']) : 'General';
                                            ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline-flex; align-items: center; gap: 4px;">
                                                <input type="hidden" name="actualizar" value="1">
                                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                <input type="number" name="tiempo_respuesta" value="<?= $p['tiempo_primera_respuesta_horas'] ?>" 
                                                       class="form-control" style="width: 60px; padding: 4px 8px;">
                                                <span style="font-size: 12px;">horas</span>
                                        </td>
                                        <td>
                                                <input type="number" name="tiempo_resolucion" value="<?= $p['tiempo_resolucion_horas'] ?>" 
                                                       class="form-control" style="width: 60px; padding: 4px 8px;">
                                                <span style="font-size: 12px;">horas</span>
                                        </td>
                                        <td>
                                                <button type="submit" class="btn btn-sm btn-secundario" title="Guardar">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar?')">
                                                <input type="hidden" name="eliminar" value="1">
                                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-secundario" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Crear nueva -->
                    <div class="tarjeta">
                        <div class="tarjeta-header">
                            <h3 class="tarjeta-titulo">Nueva Política</h3>
                        </div>
                        <div class="tarjeta-body">
                            <form method="POST" action="">
                                <input type="hidden" name="crear" value="1">
                                
                                <div class="form-grupo">
                                    <label class="form-label requerido">Nombre</label>
                                    <input type="text" name="nombre" class="form-control" placeholder="Ej: SLA Urgente" required>
                                </div>
                                
                                <div class="form-grupo">
                                    <label class="form-label">Prioridad</label>
                                    <select name="prioridad_id" class="form-control">
                                        <option value="">-- General --</option>
                                        <?php foreach ($prioridades as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-fila">
                                    <div class="form-grupo">
                                        <label class="form-label requerido">Primera Respuesta</label>
                                        <input type="number" name="tiempo_respuesta" class="form-control" placeholder="24" required>
                                        <div class="form-ayuda">Horas</div>
                                    </div>
                                    
                                    <div class="form-grupo">
                                        <label class="form-label requerido">Resolución</label>
                                        <input type="number" name="tiempo_resolucion" class="form-control" placeholder="72" required>
                                        <div class="form-ayuda">Horas</div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primario btn-bloque">
                                    <i class="bi bi-plus"></i> Crear Política
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
