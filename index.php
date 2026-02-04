<?php
/**
 * Página principal - Mesa de Ayuda TI
 */
require_once __DIR__ . '/includes/functions.php';


if (estaAutenticado()) {
    $usuario = obtenerUsuarioActual();
    if (in_array($usuario['rol'], ['admin', 'soporte_ti'])) {
        $baseUrl = getBaseUrl();
        header('Location: ' . $baseUrl . '/admin/dashboard.php');
        exit;
    }
}

$categorias = obtenerTodasCategorias();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesa de Ayuda TI - Municipalidad</title>
    <meta name="description" content="Sistema de soporte técnico del Área de Informática Municipal">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .hero-ti {
            background: linear-gradient(135deg, #1a365d 0%, #2c5282 50%, #3182ce 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }

        .hero-ti h1 {
            font-size: 2.5rem;
            margin-bottom: 16px;
        }

        .hero-ti p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto 32px;
        }

        .categorias-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .categoria-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .categoria-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .categoria-icono {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #3182ce, #2c5282);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
        }

        .categoria-info h3 {
            margin: 0 0 4px 0;
            font-size: 15px;
            color: #1a365d;
        }

        .categoria-info p {
            margin: 0;
            font-size: 13px;
            color: #718096;
        }

        .acciones-rapidas {
            background: #f7fafc;
            padding: 60px 20px;
        }

        .acciones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .accion-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .accion-icono {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #3182ce, #2c5282);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            margin: 0 auto 16px;
        }

        .contacto-info {
            background: #1a365d;
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .contacto-grid {
            display: flex;
            justify-content: center;
            gap: 48px;
            flex-wrap: wrap;
            max-width: 800px;
            margin: 0 auto;
        }

        .contacto-item {
            text-align: center;
        }

        .contacto-item i {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
            opacity: 0.8;
        }

        .contacto-item span {
            font-size: 14px;
            opacity: 0.9;
        }
    </style>
</head>

<body>
    <header class="header-publico">
        <div class="header-contenido">
            <a href="/" style="text-decoration: none; display: flex; align-items: center; gap: 12px;">
                <div class="header-logo-icon" style="background: #3182ce;">
                    <i class="bi bi-headset"></i>
                </div>
                <div>
                    <span class="header-titulo">Mesa de Ayuda TI</span>
                    <span style="display: block; font-size: 11px; color: #718096;">Área de Informática</span>
                </div>
            </a>
            <nav class="header-nav">
                <?php
                if (estaAutenticado()):
                    $u = obtenerUsuarioActual();
                    $notis = contarNotificacionesNoLeidas($u['id']);
                    ?>
                    <a href="/funcionario/mis-tickets.php"
                        class="btn btn-secundario btn-sm <?php echo strpos($_SERVER['PHP_SELF'], 'mis-tickets.php') !== false ? 'activo' : ''; ?>"
                        style="position:relative;">
                        Mis Tickets
                        <?php if ($notis > 0): ?>
                            <span
                                style="position: absolute; top: -5px; right: -5px; background: #e53e3e; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center;"><?= $notis ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/logout.php" class="btn btn-secundario btn-sm">Salir</a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-primario btn-sm">Acceso Funcionarios</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <section class="hero-ti">
        <div class="contenedor">
            <h1><i class="bi bi-headset"></i> Mesa de Ayuda TI</h1>
            <p>Soporte técnico para funcionarios municipales. Reporta problemas con equipos, redes, software o solicita
                asistencia del Área de Informática.</p>
            <a href="/nuevo-ticket.php" class="btn btn-primario btn-lg" style="background: white; color: #1a365d;">
                <i class="bi bi-plus-circle"></i> Crear Nueva Solicitud
            </a>
        </div>
    </section>
    <section class="acciones-rapidas">
        <div class="contenedor">
            <h2 style="text-align: center; margin-bottom: 32px; color: #1a365d;">¿Qué necesitas?</h2>
            <div class="acciones-grid">
                <div class="accion-card">
                    <div class="accion-icono"><i class="bi bi-plus-lg"></i></div>
                    <h3 style="margin-bottom: 8px; color: #1a365d;">Nueva Solicitud</h3>
                    <p style="color: #718096; margin-bottom: 16px;">Reporta un problema o realiza una solicitud al área
                        de informática</p>
                    <a href="/nuevo-ticket.php" class="btn btn-primario btn-bloque">Crear Solicitud</a>
                </div>
                <div class="accion-card">
                    <div class="accion-icono"><i class="bi bi-search"></i></div>
                    <h3 style="margin-bottom: 8px; color: #1a365d;">Consultar Estado</h3>
                    <p style="color: #718096; margin-bottom: 16px;">Revisa el estado de tus solicitudes anteriores</p>
                    <a href="/login.php" class="btn btn-secundario btn-bloque">Ver Mis Solicitudes</a>
                </div>
                <div class="accion-card">
                    <div class="accion-icono"><i class="bi bi-telephone"></i></div>
                    <h3 style="margin-bottom: 8px; color: #1a365d;">Contacto Directo</h3>
                    <p style="color: #718096; margin-bottom: 16px;">Para urgencias, contacta directamente al área</p>
                    <a href="tel:+56221234567" class="btn btn-secundario btn-bloque">Llamar a Soporte</a>
                </div>
            </div>
        </div>
    </section>
    <section style="padding: 60px 20px; background: white;">
        <div class="contenedor">
            <h2 style="text-align: center; margin-bottom: 8px; color: #1a365d;">Categorías de Soporte</h2>
            <p style="text-align: center; color: #718096; margin-bottom: 32px;">Selecciona la categoría que mejor
                describe tu problema</p>

            <div class="categorias-grid">
                <?php
                $iconos_cat = [
                    'Redes' => 'bi-router',
                    'Internet' => 'bi-wifi-off',
                    'Red Lenta' => 'bi-speedometer',
                    'Hardware' => 'bi-pc-display',
                    'Impresora' => 'bi-printer',
                    'Software' => 'bi-download',
                    'Office' => 'bi-file-earmark-word',
                    'Correo' => 'bi-envelope',
                    'Contraseña' => 'bi-lock',
                    'Usuario' => 'bi-person-plus',
                    'Teléfono' => 'bi-telephone',
                    'Sistema' => 'bi-building'
                ];

                foreach ($categorias as $cat):
                    $icono = 'bi-question-circle';
                    foreach ($iconos_cat as $key => $icon) {
                        if (stripos($cat['nombre'], $key) !== false) {
                            $icono = $icon;
                            break;
                        }
                    }
                    ?>
                    <a href="/nuevo-ticket.php?categoria=<?= $cat['id'] ?>" class="categoria-card">
                        <div class="categoria-icono">
                            <i class="bi <?= $icono ?>"></i>
                        </div>
                        <div class="categoria-info">
                            <h3><?= htmlspecialchars($cat['nombre']) ?></h3>
                            <p><?= htmlspecialchars($cat['descripcion'] ?? 'Solicitar asistencia') ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <section class="contacto-info">
        <h3 style="margin-bottom: 24px;">Área de Informática Municipal</h3>
        <div class="contacto-grid">
            <div class="contacto-item">
                <i class="bi bi-telephone"></i>
                <span>Anexo 1234</span>
            </div>
            <div class="contacto-item">
                <i class="bi bi-envelope"></i>
                <span>informatica@municipalidad.cl</span>
            </div>
            <div class="contacto-item">
                <i class="bi bi-geo-alt"></i>
                <span>Oficina 201, 2do Piso</span>
            </div>
            <div class="contacto-item">
                <i class="bi bi-clock"></i>
                <span>Lun-Vie 8:30 - 17:30</span>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="contenedor">
            <div class="footer-contenido">
                <div>© <?= date('Y') ?> Municipalidad - Área de Informática</div>
                <div class="footer-links">
                    <a href="/login.php">Acceso Técnicos</a>
                </div>
            </div>
        </div>
    </footer>
</body>

</html>