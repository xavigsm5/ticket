<?php
/**
 * Reportes y estadísticas
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/freshdesk_functions.php';

requiereRol(['admin', 'supervisor']);

$usuario = obtenerUsuarioActual();
$db = Database::getInstance();


$stats = obtenerEstadisticas();
$csat = obtenerEstadisticasCSAT();


$por_estado = $db->fetchAll("
    SELECT e.nombre, e.color, COUNT(t.id) as total
    FROM estados e
    LEFT JOIN tickets t ON t.estado_id = e.id
    GROUP BY e.id, e.nombre, e.color
    ORDER BY e.orden
");


$por_departamento = $db->fetchAll("
    SELECT d.nombre, COUNT(t.id) as total,
           COUNT(CASE WHEN e.nombre = 'Abierto' THEN 1 END) as abiertos,
           COUNT(CASE WHEN e.nombre = 'Resuelto' OR e.nombre = 'Cerrado' THEN 1 END) as resueltos
    FROM departamentos d
    LEFT JOIN categorias c ON c.departamento_id = d.id
    LEFT JOIN tickets t ON t.categoria_id = c.id
    LEFT JOIN estados e ON t.estado_id = e.id
    WHERE d.activo = TRUE
    GROUP BY d.id, d.nombre
    ORDER BY total DESC
");


$sla_stats = $db->fetch("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN fecha_primera_respuesta IS NOT NULL AND 
                        (sla_respuesta_vencimiento IS NULL OR fecha_primera_respuesta <= sla_respuesta_vencimiento) 
                   THEN 1 END) as respuesta_cumplido,
        COUNT(CASE WHEN fecha_resolucion IS NOT NULL AND 
                        (sla_resolucion_vencimiento IS NULL OR fecha_resolucion <= sla_resolucion_vencimiento) 
                   THEN 1 END) as resolucion_cumplido,
        COUNT(CASE WHEN sla_respuesta_vencimiento < CURRENT_TIMESTAMP AND fecha_primera_respuesta IS NULL THEN 1 END) as respuesta_vencido,
        COUNT(CASE WHEN sla_resolucion_vencimiento < CURRENT_TIMESTAMP AND fecha_resolucion IS NULL THEN 1 END) as resolucion_vencido
    FROM tickets
    WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
");


$por_dia = $db->fetchAll("
    SELECT DATE(created_at) as fecha, COUNT(*) as total
    FROM tickets
    WHERE created_at >= CURRENT_DATE - INTERVAL '7 days'
    GROUP BY DATE(created_at)
    ORDER BY fecha
");


$tiempos = $db->fetch("
    SELECT 
        AVG(EXTRACT(EPOCH FROM (fecha_primera_respuesta - created_at))/3600) as avg_respuesta_horas,
        AVG(EXTRACT(EPOCH FROM (fecha_resolucion - created_at))/3600) as avg_resolucion_horas
    FROM tickets
    WHERE fecha_primera_respuesta IS NOT NULL OR fecha_resolucion IS NOT NULL
");


$top_agentes = $db->fetchAll("
    SELECT CONCAT(u.nombres, ' ', u.apellidos) as nombre,
           COUNT(DISTINCT t.id) as tickets_asignados,
           COUNT(CASE WHEN e.nombre IN ('Resuelto', 'Cerrado') THEN 1 END) as resueltos,
           ROUND(AVG(t.satisfaccion), 1) as satisfaccion_promedio
    FROM usuarios u
    INNER JOIN tickets t ON t.asignado_id = u.id
    LEFT JOIN estados e ON t.estado_id = e.id
    WHERE u.rol IN ('funcionario', 'supervisor')
    GROUP BY u.id, u.nombres, u.apellidos
    ORDER BY resueltos DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Mesa de Ayuda</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .stat-grande { font-size: 36px; font-weight: 700; color: var(--gris-900); }
        .stat-label { font-size: 12px; color: var(--gris-500); text-transform: uppercase; }
        .barra-progreso { height: 8px; background: var(--gris-200); border-radius: 4px; overflow: hidden; }
        .barra-progreso-fill { height: 100%; border-radius: 4px; }
        .chart-simple { display: flex; align-items: flex-end; gap: 8px; height: 120px; padding-top: 20px; }
        .chart-bar { flex: 1; background: var(--color-acento); border-radius: 4px 4px 0 0; min-width: 30px; position: relative; }
        .chart-bar-label { position: absolute; bottom: -20px; left: 50%; transform: translateX(-50%); font-size: 10px; color: var(--gris-500); white-space: nowrap; }
        .chart-bar-value { position: absolute; top: -18px; left: 50%; transform: translateX(-50%); font-size: 11px; font-weight: 600; }
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
                <a href="/admin/automatizaciones.php" class="menu-item">
                    <i class="bi bi-lightning"></i>
                    <span>Automatizaciones</span>
                </a>
                <a href="/admin/sla.php" class="menu-item">
                    <i class="bi bi-clock-history"></i>
                    <span>Políticas SLA</span>
                </a>
                <a href="/admin/reportes.php" class="menu-item activo">
                    <i class="bi bi-bar-chart"></i>
                    <span>Reportes</span>
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
                        <span>Reportes</span>
                    </nav>
                </div>
                <div class="topbar-derecha">
                    <select class="form-control" style="width: auto;">
                        <option>Últimos 7 días</option>
                        <option>Últimos 30 días</option>
                        <option>Este mes</option>
                        <option>Este año</option>
                    </select>
                </div>
            </header>
            
            <main class="pagina-contenido">
                <div style="margin-bottom: 24px;">
                    <h1 style="font-size: 24px; font-weight: 600; color: var(--gris-900); margin-bottom: 8px;">
                        <i class="bi bi-bar-chart"></i> Dashboard de Reportes
                    </h1>
                    <p style="color: var(--gris-600);">Métricas y estadísticas del sistema de tickets</p>
                </div>
                <div class="stats-grid" style="margin-bottom: 24px;">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-titulo">Total Tickets</span>
                            <div class="stat-card-icono" style="background: #e8f4fc; color: var(--color-acento);">
                                <i class="bi bi-ticket"></i>
                            </div>
                        </div>
                        <div class="stat-card-valor"><?= $stats['total_tickets'] ?? 0 ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-titulo">Abiertos</span>
                            <div class="stat-card-icono" style="background: #fdecea; color: var(--estado-abierto);">
                                <i class="bi bi-exclamation-circle"></i>
                            </div>
                        </div>
                        <div class="stat-card-valor"><?= $stats['pendientes'] ?? 0 ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-titulo">Satisfacción</span>
                            <div class="stat-card-icono" style="background: #d4edda; color: var(--estado-resuelto);">
                                <i class="bi bi-emoji-smile"></i>
                            </div>
                        </div>
                        <div class="stat-card-valor">
                            <?= $csat['promedio'] ?? '-' ?>
                            <span style="font-size: 14px; color: var(--gris-500);">/ 5</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-titulo">SLA Vencidos</span>
                            <div class="stat-card-icono" style="background: #fff3cd; color: #856404;">
                                <i class="bi bi-clock"></i>
                            </div>
                        </div>
                        <div class="stat-card-valor" style="color: <?= ($sla_stats['respuesta_vencido'] ?? 0) > 0 ? '#e74c3c' : 'inherit' ?>">
                            <?= ($sla_stats['respuesta_vencido'] ?? 0) + ($sla_stats['resolucion_vencido'] ?? 0) ?>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
                    <div class="tarjeta">
                        <div class="tarjeta-header">
                            <h3 class="tarjeta-titulo">Tickets - Últimos 7 días</h3>
                        </div>
                        <div class="tarjeta-body">
                            <?php 
                            $max = max(array_column($por_dia, 'total') ?: [1]);
                            ?>
                            <div class="chart-simple">
                                <?php foreach ($por_dia as $dia): ?>
                                <div class="chart-bar" style="height: <?= ($dia['total'] / $max) * 100 ?>%;">
                                    <span class="chart-bar-value"><?= $dia['total'] ?></span>
                                    <span class="chart-bar-label"><?= date('d/m', strtotime($dia['fecha'])) ?></span>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($por_dia)): ?>
                                <div style="flex: 1; text-align: center; color: var(--gris-500);">Sin datos</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tarjeta">
                        <div class="tarjeta-header">
                            <h3 class="tarjeta-titulo">Tiempos Promedio</h3>
                        </div>
                        <div class="tarjeta-body">
                            <div style="margin-bottom: 24px;">
                                <div class="stat-label">Primera Respuesta</div>
                                <div class="stat-grande" style="font-size: 24px;">
                                    <?= $tiempos['avg_respuesta_horas'] ? round($tiempos['avg_respuesta_horas'], 1) . 'h' : '-' ?>
                                </div>
                            </div>
                            <div>
                                <div class="stat-label">Resolución</div>
                                <div class="stat-grande" style="font-size: 24px;">
                                    <?= $tiempos['avg_resolucion_horas'] ? round($tiempos['avg_resolucion_horas'], 1) . 'h' : '-' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                    <div class="tarjeta">
                        <div class="tarjeta-header">
                            <h3 class="tarjeta-titulo">Por Departamento</h3>
                        </div>
                        <div class="tarjeta-body">
                            <?php foreach ($por_departamento as $dep): ?>
                            <?php if ($dep['total'] > 0): ?>
                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="font-size: 13px; font-weight: 500;"><?= htmlspecialchars($dep['nombre']) ?></span>
                                    <span style="font-size: 13px; color: var(--gris-500);"><?= $dep['total'] ?> tickets</span>
                                </div>
                                <div class="barra-progreso">
                                    <?php $pct = $dep['total'] > 0 ? ($dep['resueltos'] / $dep['total']) * 100 : 0; ?>
                                    <div class="barra-progreso-fill" style="width: <?= $pct ?>%; background: var(--estado-resuelto);"></div>
                                </div>
                                <div style="font-size: 11px; color: var(--gris-500); margin-top: 2px;">
                                    <?= $dep['resueltos'] ?> resueltos (<?= round($pct) ?>%)
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="tarjeta">
                        <div class="tarjeta-header">
                            <h3 class="tarjeta-titulo">Por Estado</h3>
                        </div>
                        <div class="tarjeta-body">
                            <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                                <?php foreach ($por_estado as $est): ?>
                                <div style="flex: 1; min-width: 100px; text-align: center; padding: 16px; background: var(--gris-50); border-radius: 8px;">
                                    <div style="width: 12px; height: 12px; background: <?= $est['color'] ?>; border-radius: 50%; margin: 0 auto 8px;"></div>
                                    <div style="font-size: 24px; font-weight: 700;"><?= $est['total'] ?></div>
                                    <div style="font-size: 11px; color: var(--gris-500); text-transform: uppercase;"><?= htmlspecialchars($est['nombre']) ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tarjeta">
                    <div class="tarjeta-header">
                        <h3 class="tarjeta-titulo">Top Agentes</h3>
                    </div>
                    <div class="tabla-contenedor">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>Agente</th>
                                    <th>Tickets Asignados</th>
                                    <th>Resueltos</th>
                                    <th>Satisfacción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_agentes as $agente): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($agente['nombre']) ?></strong></td>
                                    <td><?= $agente['tickets_asignados'] ?></td>
                                    <td>
                                        <span style="color: var(--estado-resuelto);"><?= $agente['resueltos'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($agente['satisfaccion_promedio']): ?>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?= $i <= round($agente['satisfaccion_promedio']) ? '-fill' : '' ?>" style="color: #f39c12; font-size: 12px;"></i>
                                        <?php endfor; ?>
                                        (<?= $agente['satisfaccion_promedio'] ?>)
                                        <?php else: ?>
                                        <span style="color: var(--gris-400);">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($top_agentes)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--gris-500);">Sin datos de agentes</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
