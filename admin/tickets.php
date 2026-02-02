<?php
/**
 * Listado de Tickets - Panel Administrativo
 */
require_once __DIR__ . '/../includes/functions.php';

requiereRol(['admin', 'supervisor', 'funcionario']);

$usuario = obtenerUsuarioActual();
$estados = obtenerEstados();
$prioridades = obtenerPrioridades();
$departamentos = obtenerDepartamentos();

// Filtros
$filtros = [];
if (!empty($_GET['estado']))
    $filtros['estado_id'] = (int) $_GET['estado'];
if (!empty($_GET['prioridad']))
    $filtros['prioridad_id'] = (int) $_GET['prioridad'];
if (!empty($_GET['departamento']))
    $filtros['departamento_id'] = (int) $_GET['departamento'];
if (!empty($_GET['busqueda']))
    $filtros['busqueda'] = $_GET['busqueda'];

// Comentado para permitir que funcionarios vean todos los tickets (testing)
// if ($usuario['rol'] === 'funcionario') {
//     $filtros['asignado_id'] = $usuario['id'];
// }

$tickets = obtenerTickets($filtros, 100);

// Procesar acciones masivas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_masiva'])) {
    $ids = $_POST['tickets_seleccionados'] ?? [];
    $accion = $_POST['accion_masiva'];

    if (!empty($ids)) {
        $db = Database::getInstance();
        foreach ($ids as $id) {
            if ($accion === 'asignarme') {
                $db->query("UPDATE tickets SET asignado_id = ?, updated_at = NOW() WHERE id = ?", [$usuario['id'], $id]);
            } elseif (is_numeric($accion)) {
                $db->query("UPDATE tickets SET estado_id = ?, updated_at = NOW() WHERE id = ?", [(int) $accion, $id]);
            }
        }
        header('Location: /admin/tickets.php?exito=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tickets - Sistema Municipal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
    <div class="layout-app">
        <!-- Sidebar -->
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

                <?php if (tieneRol(['admin', 'supervisor'])): ?>
                    <div class="menu-seccion">Administración</div>
                    <a href="/admin/usuarios.php" class="menu-item">
                        <i class="bi bi-people"></i>
                        <span>Usuarios</span>
                    </a>
                    <a href="/admin/departamentos.php" class="menu-item">
                        <i class="bi bi-diagram-3"></i>
                        <span>Departamentos</span>
                    </a>
                <?php endif; ?>

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

        <!-- Contenido Principal -->
        <div class="contenido-principal">
            <header class="topbar">
                <div class="topbar-izquierda">
                    <nav class="breadcrumb">
                        <a href="/admin/dashboard.php">Inicio</a>
                        <span class="breadcrumb-separador">/</span>
                        <span>Tickets</span>
                    </nav>
                </div>
                <div class="topbar-derecha">
                    <button class="btn-icono"><i class="bi bi-bell"></i></button>
                </div>
            </header>

            <main class="pagina-contenido">
                <div class="pagina-header">
                    <div>
                        <h1 class="pagina-titulo">
                            <i class="bi bi-ticket-detailed"></i> Gestión de Tickets
                        </h1>
                        <p class="pagina-descripcion">Administre las solicitudes ciudadanas</p>
                    </div>
                </div>

                <?php if (isset($_GET['exito'])): ?>
                    <div class="alerta alerta-exito">
                        <i class="bi bi-check-circle"></i>
                        <div>Acción realizada correctamente.</div>
                    </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="tarjeta" style="margin-bottom: var(--espaciado-lg);">
                    <div class="tarjeta-body">
                        <form method="GET" action=""
                            style="display: flex; gap: var(--espaciado-md); flex-wrap: wrap; align-items: flex-end;">
                            <div class="form-grupo" style="margin: 0; flex: 1; min-width: 200px;">
                                <label class="form-label">Buscar</label>
                                <input type="text" name="busqueda" class="form-control" placeholder="Número o asunto..."
                                    value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>">
                            </div>
                            <div class="form-grupo" style="margin: 0; min-width: 150px;">
                                <label class="form-label">Estado</label>
                                <select name="estado" class="form-control">
                                    <option value="">Todos</option>
                                    <?php foreach ($estados as $e): ?>
                                        <option value="<?= $e['id'] ?>" <?= (($_GET['estado'] ?? '') == $e['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($e['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grupo" style="margin: 0; min-width: 150px;">
                                <label class="form-label">Prioridad</label>
                                <select name="prioridad" class="form-control">
                                    <option value="">Todas</option>
                                    <?php foreach ($prioridades as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= (($_GET['prioridad'] ?? '') == $p['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($usuario['rol'] !== 'funcionario'): ?>
                                <div class="form-grupo" style="margin: 0; min-width: 180px;">
                                    <label class="form-label">Departamento</label>
                                    <select name="departamento" class="form-control">
                                        <option value="">Todos</option>
                                        <?php foreach ($departamentos as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?= (($_GET['departamento'] ?? '') == $d['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primario">
                                <i class="bi bi-search"></i> Filtrar
                            </button>
                            <a href="/admin/tickets.php" class="btn btn-secundario">Limpiar</a>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Tickets -->
                <div class="tarjeta">
                    <div class="tarjeta-header">
                        <h3 class="tarjeta-titulo">
                            <i class="bi bi-list-ul"></i> Listado de Tickets
                            <span style="font-weight: 400; font-size: 0.875rem; color: var(--gris-500);">
                                (<?= count($tickets) ?> resultados)
                            </span>
                        </h3>
                    </div>
                    <form method="POST" action="">
                        <div class="tabla-contenedor">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="seleccionarTodos">
                                        </th>
                                        <th>Número</th>
                                        <th>Asunto</th>
                                        <th>Categoría</th>
                                        <th>Ciudadano</th>
                                        <th>Estado</th>
                                        <th>Prioridad</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tickets)): ?>
                                        <tr>
                                            <td colspan="9"
                                                style="text-align: center; padding: var(--espaciado-2xl); color: var(--gris-500);">
                                                <i class="bi bi-inbox"
                                                    style="font-size: 3rem; display: block; margin-bottom: var(--espaciado-md);"></i>
                                                No se encontraron tickets con los filtros seleccionados
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tickets as $t): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="tickets_seleccionados[]"
                                                        value="<?= $t['id'] ?>" class="ticket-check">
                                                </td>
                                                <td>
                                                    <a href="/admin/ticket-detalle.php?id=<?= $t['id'] ?>"
                                                        class="ticket-numero">
                                                        <?= htmlspecialchars($t['numero']) ?>
                                                    </a>
                                                </td>
                                                <td class="ticket-asunto" title="<?= htmlspecialchars($t['asunto']) ?>">
                                                    <?= htmlspecialchars($t['asunto']) ?>
                                                </td>
                                                <td class="ticket-categoria">
                                                    <small
                                                        style="display: block; color: var(--gris-400);"><?= htmlspecialchars($t['departamento'] ?? '-') ?></small>
                                                    <?= htmlspecialchars($t['categoria'] ?? '-') ?>
                                                </td>
                                                <td style="font-size: 0.875rem;">
                                                    <?= htmlspecialchars($t['ciudadano_nombre'] ?? 'Anónimo') ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-estado"
                                                        style="background: <?= $t['estado_color'] ?>">
                                                        <?= htmlspecialchars($t['estado']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-prioridad"
                                                        style="border-color: <?= $t['prioridad_color'] ?>; color: <?= $t['prioridad_color'] ?>">
                                                        <?= htmlspecialchars($t['prioridad']) ?>
                                                    </span>
                                                </td>
                                                <td class="ticket-fecha">
                                                    <?= formatearFecha($t['created_at'], 'd/m/Y') ?><br>
                                                    <small
                                                        style="color: var(--gris-400);"><?= tiempoTranscurrido($t['created_at']) ?></small>

                                                    <?php
                                                    // Verificar SLA vencido visualmente
                                                    if ($t['sla_resolucion_vencimiento'] && !in_array($t['estado_id'], [5, 6])) {
                                                        $vence = new DateTime($t['sla_resolucion_vencimiento']);
                                                        if (new DateTime() > $vence) {
                                                            echo '<div style="margin-top:4px;"><span class="badge" style="background:#dc3545; color:white; font-size:10px;">SLA Vencido</span></div>';
                                                        }
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: var(--espaciado-xs);">
                                                        <a href="/admin/ticket-detalle.php?id=<?= $t['id'] ?>"
                                                            class="btn btn-sm btn-secundario" title="Ver">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($tickets)): ?>
                            <div class="tarjeta-footer"
                                style="display: flex; align-items: center; gap: var(--espaciado-md);">
                                <span style="font-size: 0.875rem; color: var(--gris-600);">Con seleccionados:</span>
                                <select name="accion_masiva" class="form-control" style="width: auto;">
                                    <option value="">Seleccionar acción</option>
                                    <option value="asignarme">Asignarme</option>
                                    <optgroup label="Cambiar estado a:">
                                        <?php foreach ($estados as $e): ?>
                                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primario">Aplicar</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Seleccionar/deseleccionar todos
        document.getElementById('seleccionarTodos')?.addEventListener('change', function () {
            document.querySelectorAll('.ticket-check').forEach(cb => cb.checked = this.checked);
        });
    </script>
</body>

</html>