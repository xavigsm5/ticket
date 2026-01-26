<?php
/**
 * Gestión de Respuestas Predefinidas (Canned Responses)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/freshdesk_functions.php';

requiereRol(['admin', 'supervisor']);

$usuario = obtenerUsuarioActual();
$departamentos = obtenerDepartamentos();
$exito = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear'])) {
        $titulo = limpiarInput($_POST['titulo']);
        $contenido = $_POST['contenido']; 
        $departamento_id = $_POST['departamento_id'] ?: null;
        $es_global = isset($_POST['es_global']);
        
        if (!empty($titulo) && !empty($contenido)) {
            crearRespuestaPredefinida($titulo, $contenido, $departamento_id, $usuario['id'], $es_global);
            $exito = 'Respuesta predefinida creada correctamente.';
        } else {
            $error = 'Complete todos los campos requeridos.';
        }
    }
    
    if (isset($_POST['eliminar'])) {
        $id = (int)$_POST['id'];
        $db = Database::getInstance();
        $db->query("DELETE FROM respuestas_predefinidas WHERE id = ?", [$id]);
        $exito = 'Respuesta eliminada.';
    }
}

$respuestas = obtenerRespuestasPredefinidas(null, null);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respuestas Predefinidas - Mesa de Ayuda</title>
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
                <a href="/admin/respuestas-predefinidas.php" class="menu-item activo">
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
                        <span>Respuestas Predefinidas</span>
                    </nav>
                </div>
            </header>
            
            <main class="pagina-contenido">
                <div style="display: grid; grid-template-columns: 1fr 400px; gap: 24px;">
                    <div>
                        <div class="tarjeta">
                            <div class="tarjeta-header">
                                <h3 class="tarjeta-titulo">
                                    <i class="bi bi-chat-square-text"></i> Respuestas Predefinidas
                                </h3>
                            </div>
                            
                            <?php if ($exito): ?>
                            <div class="alerta alerta-exito" style="margin: 16px;">
                                <i class="bi bi-check-circle"></i> <?= $exito ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="tabla-contenedor">
                                <table class="tabla">
                                    <thead>
                                        <tr>
                                            <th>Título</th>
                                            <th>Departamento</th>
                                            <th>Usos</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($respuestas as $r): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($r['titulo']) ?></strong>
                                                <?php if ($r['es_global']): ?>
                                                <span class="badge" style="background: var(--color-acento); color: white; font-size: 10px;">Global</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 13px; color: var(--gris-600);">
                                                <?= $r['departamento_id'] ? htmlspecialchars($departamentos[array_search($r['departamento_id'], array_column($departamentos, 'id'))]['nombre'] ?? '-') : 'Todos' ?>
                                            </td>
                                            <td><?= $r['uso_count'] ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta respuesta?')">
                                                    <input type="hidden" name="eliminar" value="1">
                                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-secundario">
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
                    </div>
                    <div>
                        <div class="tarjeta">
                            <div class="tarjeta-header">
                                <h3 class="tarjeta-titulo">
                                    <i class="bi bi-plus-circle"></i> Nueva Respuesta
                                </h3>
                            </div>
                            <div class="tarjeta-body">
                                <?php if ($error): ?>
                                <div class="alerta alerta-error">
                                    <i class="bi bi-exclamation-circle"></i> <?= $error ?>
                                </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="crear" value="1">
                                    
                                    <div class="form-grupo">
                                        <label class="form-label requerido">Título</label>
                                        <input type="text" name="titulo" class="form-control" placeholder="Ej: Saludo inicial" required>
                                    </div>
                                    
                                    <div class="form-grupo">
                                        <label class="form-label requerido">Contenido</label>
                                        <textarea name="contenido" class="form-control" rows="6" 
                                                  placeholder="Escriba el contenido de la respuesta..." required></textarea>
                                        <div class="form-ayuda">Puede usar [NOMBRE] para insertar el nombre del ciudadano</div>
                                    </div>
                                    
                                    <div class="form-grupo">
                                        <label class="form-label">Departamento</label>
                                        <select name="departamento_id" class="form-control">
                                            <option value="">Todos los departamentos</option>
                                            <?php foreach ($departamentos as $d): ?>
                                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-grupo">
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="es_global" value="1">
                                            <span>Disponible para todos los agentes</span>
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primario btn-bloque">
                                        <i class="bi bi-plus"></i> Crear Respuesta
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
