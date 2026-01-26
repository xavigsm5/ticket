<?php
/**
 * Mis Tickets - Portal Ciudadano
 */
require_once __DIR__ . '/../includes/functions.php';

requiereAutenticacion();

$usuario = obtenerUsuarioActual();

// Obtener tickets del ciudadano
$tickets = obtenerTickets(['ciudadano_id' => $usuario['id']], 100);
$stats = [
    'total' => count($tickets),
    'pendientes' => count(array_filter($tickets, fn($t) => $t['estado_id'] == 1)),
    'en_proceso' => count(array_filter($tickets, fn($t) => in_array($t['estado_id'], [2, 3, 4]))),
    'resueltos' => count(array_filter($tickets, fn($t) => in_array($t['estado_id'], [5, 6])))
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Solicitudes - Sistema Municipal</title>
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
                    <a href="/nuevo-ticket.php">Nueva Solicitud</a>
                    <a href="/ciudadano/mis-tickets.php" style="font-weight: 600;">Mis Tickets</a>
                    <div style="display: flex; align-items: center; gap: var(--espaciado-sm); margin-left: var(--espaciado-md); padding-left: var(--espaciado-md); border-left: 1px solid rgba(255,255,255,0.3);">
                        <div style="width:36px;height:36px;background:var(--color-acento);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;">
                            <?= strtoupper(substr($usuario['nombres'], 0, 1)) ?>
                        </div>
                        <span><?= htmlspecialchars($usuario['nombres']) ?></span>
                        <a href="/logout.php" title="Cerrar Sesión" style="color: white; margin-left: var(--espaciado-sm);">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </div>
                </nav>
            </div>
        </div>
    </header>

    <main class="contenedor" style="padding: var(--espaciado-2xl) 0;">
        <div class="pagina-header">
            <div>
                <h1 class="pagina-titulo">
                    <i class="bi bi-ticket-detailed"></i> Mis Solicitudes
                </h1>
                <p class="pagina-descripcion">
                    Bienvenido(a), <?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?>
                </p>
            </div>
            <div class="pagina-acciones">
                <a href="/nuevo-ticket.php" class="btn btn-primario">
                    <i class="bi bi-plus-circle"></i> Nueva Solicitud
                </a>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="estadisticas-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="stat-card">
                <div class="stat-icono primario"><i class="bi bi-ticket"></i></div>
                <div class="stat-info">
                    <div class="stat-valor"><?= $stats['total'] ?></div>
                    <div class="stat-etiqueta">Total</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icono advertencia"><i class="bi bi-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-valor"><?= $stats['pendientes'] ?></div>
                    <div class="stat-etiqueta">Pendientes</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icono info"><i class="bi bi-gear"></i></div>
                <div class="stat-info">
                    <div class="stat-valor"><?= $stats['en_proceso'] ?></div>
                    <div class="stat-etiqueta">En Proceso</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icono exito"><i class="bi bi-check-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-valor"><?= $stats['resueltos'] ?></div>
                    <div class="stat-etiqueta">Resueltos</div>
                </div>
            </div>
        </div>
        
        <!-- Listado de Tickets -->
        <div class="tarjeta">
            <div class="tarjeta-header">
                <h3 class="tarjeta-titulo"><i class="bi bi-list-ul"></i> Historial de Solicitudes</h3>
            </div>
            
            <?php if (empty($tickets)): ?>
            <div class="tarjeta-body" style="text-align: center; padding: var(--espaciado-2xl);">
                <i class="bi bi-inbox" style="font-size: 4rem; color: var(--gris-300); display: block; margin-bottom: var(--espaciado-lg);"></i>
                <h3 style="color: var(--gris-600); margin-bottom: var(--espaciado-md);">No tiene solicitudes aún</h3>
                <p style="color: var(--gris-500); margin-bottom: var(--espaciado-lg);">
                    Comience creando su primera solicitud para recibir atención municipal.
                </p>
                <a href="/nuevo-ticket.php" class="btn btn-primario btn-lg">
                    <i class="bi bi-plus-circle"></i> Crear Primera Solicitud
                </a>
            </div>
            <?php else: ?>
            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Asunto</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td class="ticket-numero"><?= htmlspecialchars($t['numero']) ?></td>
                            <td class="ticket-asunto"><?= htmlspecialchars($t['asunto']) ?></td>
                            <td>
                                <small style="color: var(--gris-400); display: block;"><?= htmlspecialchars($t['departamento'] ?? '') ?></small>
                                <?= htmlspecialchars($t['categoria'] ?? '-') ?>
                            </td>
                            <td>
                                <span class="badge badge-estado" style="background: <?= $t['estado_color'] ?>">
                                    <?= htmlspecialchars($t['estado']) ?>
                                </span>
                            </td>
                            <td class="ticket-fecha">
                                <?= formatearFecha($t['created_at'], 'd/m/Y') ?><br>
                                <small style="color: var(--gris-400);"><?= tiempoTranscurrido($t['created_at']) ?></small>
                            </td>
                            <td>
                                <a href="/ciudadano/ticket.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-secundario">
                                    <i class="bi bi-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="contenedor">
            <div class="footer-contenido">
                <div class="footer-texto">
                    © <?= date('Y') ?> Municipalidad - Sistema de Atención Ciudadana
                </div>
                <div class="footer-links">
                    <a href="#">Ayuda</a>
                    <a href="#">Contacto</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
