<?php
/**
 * Nueva Solicitud de Soporte TI
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/freshdesk_functions.php';


$categorias = obtenerTodasCategorias();
$prioridades = obtenerPrioridades();
$categoria_preseleccionada = $_GET['categoria'] ?? '';

$errores = [];
$exito = false;
$ticket_numero = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiarInput($_POST['nombre'] ?? '');
    $email = limpiarInput($_POST['email'] ?? '');
    $telefono = limpiarInput($_POST['telefono'] ?? '');
    $departamento_usuario = limpiarInput($_POST['departamento_usuario'] ?? '');
    $ubicacion = limpiarInput($_POST['ubicacion'] ?? '');
    $numero_inventario = limpiarInput($_POST['numero_inventario'] ?? '');
    $categoria_id = (int)($_POST['categoria_id'] ?? 0);
    $asunto = limpiarInput($_POST['asunto'] ?? '');
    $descripcion = limpiarInput($_POST['descripcion'] ?? '');
    $prioridad_id = (int)($_POST['prioridad_id'] ?? 2);


    if (empty($nombre)) $errores[] = 'El nombre es requerido';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Email válido requerido';
    if (empty($categoria_id)) $errores[] = 'Seleccione una categoría';
    if (empty($asunto)) $errores[] = 'El asunto es requerido';
    if (empty($descripcion)) $errores[] = 'La descripción es requerida';
    
    if (empty($errores)) {
        $db = Database::getInstance();
        
        $usuario = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$email]);
        
        if (!$usuario) {
            $partes_nombre = explode(' ', $nombre, 2);
            $nombres = $partes_nombre[0];
            $apellidos = $partes_nombre[1] ?? '';
            
            $usuario_id = $db->insert("
                INSERT INTO usuarios (email, nombres, apellidos, rol, password, telefono)
                VALUES (?, ?, ?, 'funcionario', '', ?)
                RETURNING id
            ", [$email, $nombres, $apellidos, $telefono]);
        } else {
            $usuario_id = $usuario['id'];
        }
        
   
        $descripcion_completa = $descripcion;
        if ($ubicacion || $numero_inventario || $departamento_usuario) {
            $descripcion_completa .= "\n\n--- Información del Equipo ---";
            if ($departamento_usuario) $descripcion_completa .= "\nDepartamento: " . $departamento_usuario;
            if ($ubicacion) $descripcion_completa .= "\nUbicación: " . $ubicacion;
            if ($numero_inventario) $descripcion_completa .= "\nNº Inventario: " . $numero_inventario;
        }
        
     
        $ticket_id = crearTicketConSLA($usuario_id, $categoria_id, $asunto, $descripcion_completa, $prioridad_id);
        
        if ($ticket_id) {
            // Procesar archivos adjuntos
            if (!empty($_FILES['adjuntos']['name'][0])) {
                $upload_dir = __DIR__ . '/uploads/attachments/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                foreach ($_FILES['adjuntos']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['adjuntos']['error'][$key] === UPLOAD_ERR_OK) {
                        $nombre_archivo = basename($_FILES['adjuntos']['name'][$key]);
                        $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
                        $nombre_unico = time() . '_' . uniqid() . '.' . $extension;
                        $ruta_destino = $upload_dir . $nombre_unico;
                        
                        if (move_uploaded_file($tmp_name, $ruta_destino)) {
                            // Guardar en base de datos
                            $db->query(
                                "INSERT INTO ticket_adjuntos (ticket_id, nombre_archivo, ruta_archivo, tamano, tipo_mime) 
                                 VALUES (?, ?, ?, ?, ?)",
                                [
                                    $ticket_id,
                                    $nombre_archivo,
                                    'uploads/attachments/' . $nombre_unico,
                                    $_FILES['adjuntos']['size'][$key],
                                    $_FILES['adjuntos']['type'][$key]
                                ]
                            );
                        }
                    }
                }
            }
            
            $ticket = obtenerTicket($ticket_id);
            $ticket_numero = $ticket['numero'];
            $exito = true;
        } else {
            $errores[] = 'Error al crear la solicitud';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Solicitud - Mesa de Ayuda TI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .form-ti {
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
        }
        .seccion-form {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .seccion-titulo {
            font-size: 14px;
            font-weight: 600;
            color: #1a365d;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .categoria-seleccion {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .categoria-opcion {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .categoria-opcion:hover {
            border-color: #3182ce;
            background: #ebf8ff;
        }
        .categoria-opcion.seleccionada {
            border-color: #3182ce;
            background: #ebf8ff;
        }
        .categoria-opcion input { display: none; }
        .categoria-opcion i { font-size: 20px; color: #3182ce; }
        .categoria-opcion span { font-size: 13px; font-weight: 500; color: #2d3748; }
        .exito-mensaje {
            text-align: center;
            padding: 60px 20px;
        }
        
        /* Estilo personalizado para input file */
        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }
        
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            border: none;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .file-upload-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .file-upload-label i {
            font-size: 18px;
        }
        
        .files-selected {
            margin-top: 10px;
            padding: 8px 12px;
            background: #f7fafc;
            border-radius: 6px;
            font-size: 13px;
            color: #4a5568;
            display: none;
        }
        
        .files-selected.show {
            display: block;
        }
        
        .files-selected i {
            color: #48bb78;
            margin-right: 6px;
        }
        .exito-icono {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #48bb78, #38a169);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .ticket-numero {
            background: #f7fafc;
            padding: 16px 24px;
            border-radius: 8px;
            font-size: 24px;
            font-weight: 700;
            color: #1a365d;
            display: inline-block;
            margin-bottom: 24px;
        }
        /* Override para labels en nuevo-ticket */
        .form-ti .form-label {
            color: #1a202c !important;
        }
    </style>
</head>
<body style="background: #f7fafc;">
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
                <a href="/" class="btn btn-secundario btn-sm">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </nav>
        </div>
    </header>

    <main style="padding: 40px 20px;">
        <?php if ($exito): ?>
        <div class="form-ti">
            <div class="seccion-form exito-mensaje">
                <div class="exito-icono">
                    <i class="bi bi-check-lg" style="font-size: 40px; color: white;"></i>
                </div>
                <h2 style="margin-bottom: 16px; color: #1a365d;">¡Solicitud Enviada!</h2>
                <p style="color: #718096; margin-bottom: 16px;">
                    Tu solicitud ha sido recibida y será atendida por el equipo de soporte técnico.
                </p>
                <div class="ticket-numero">
                    <i class="bi bi-ticket"></i> <?= htmlspecialchars($ticket_numero) ?>
                </div>
                <p style="color: #718096; font-size: 14px; margin-bottom: 24px;">
                    Guarda este número para dar seguimiento a tu solicitud.<br>
                    Te notificaremos por correo cuando haya actualizaciones.
                </p>
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <a href="/" class="btn btn-secundario">Volver al Inicio</a>
                    <a href="/nuevo-ticket.php" class="btn btn-primario">Nueva Solicitud</a>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="form-ti">
            <div style="text-align: center; margin-bottom: 24px;">
                <h1 style="font-size: 24px; color: #1a365d; margin-bottom: 8px;">
                    <i class="bi bi-headset"></i> Nueva Solicitud de Soporte
                </h1>
                <p style="color: #718096;">Complete el formulario para reportar un problema o realizar una solicitud</p>
            </div>
            
            <?php if (!empty($errores)): ?>
            <div class="alerta alerta-error" style="margin-bottom: 20px;">
                <i class="bi bi-exclamation-circle"></i>
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errores as $error): ?>
                    <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Datos del Solicitante -->
                <div class="seccion-form">
                    <div class="seccion-titulo">
                        <i class="bi bi-person"></i> Datos del Solicitante
                    </div>
                    
                    <div class="form-fila">
                        <div class="form-grupo">
                            <label class="form-label requerido">Nombre Completo</label>
                            <input type="text" name="nombre" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                        </div>
                        <div class="form-grupo">
                            <label class="form-label requerido">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="tu.nombre@municipalidad.cl"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-fila">
                        <div class="form-grupo">
                            <label class="form-label">Teléfono/Anexo</label>
                            <input type="text" name="telefono" class="form-control" 
                                   placeholder="Ej: Anexo 1234"
                                   value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                        </div>
                        <div class="form-grupo">
                            <label class="form-label">Tu Departamento/Área</label>
                            <input type="text" name="departamento_usuario" class="form-control" 
                                   placeholder="Ej: Secretaría Municipal"
                                   value="<?= htmlspecialchars($_POST['departamento_usuario'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <div class="seccion-form">
                    <div class="seccion-titulo">
                        <i class="bi bi-pc-display"></i> Información del Equipo (si aplica)
                    </div>
                    
                    <div class="form-fila">
                        <div class="form-grupo">
                            <label class="form-label">Ubicación del Equipo</label>
                            <input type="text" name="ubicacion" class="form-control" 
                                   placeholder="Ej: Edificio Central, Piso 2, Oficina 201"
                                   value="<?= htmlspecialchars($_POST['ubicacion'] ?? '') ?>">
                        </div>
                        <div class="form-grupo">
                            <label class="form-label">Nº Inventario</label>
                            <input type="text" name="numero_inventario" class="form-control" 
                                   placeholder="Etiqueta blanca del equipo"
                                   value="<?= htmlspecialchars($_POST['numero_inventario'] ?? '') ?>">
                            <div class="form-ayuda">Número en la etiqueta blanca del equipo</div>
                        </div>
                    </div>
                </div>

                <div class="seccion-form">
                    <div class="seccion-titulo">
                        <i class="bi bi-tag"></i> Tipo de Problema
                    </div>
                    
                    <div class="categoria-seleccion">
                        <?php 
                        $iconos = [
                            'Redes' => 'bi-router', 'Internet' => 'bi-wifi-off', 'Red Lenta' => 'bi-speedometer',
                            'Hardware' => 'bi-pc-display', 'Impresora' => 'bi-printer', 'Teclado' => 'bi-keyboard',
                            'Monitor' => 'bi-display', 'Software' => 'bi-download', 'Office' => 'bi-file-earmark-word',
                            'Navegador' => 'bi-globe', 'Antivirus' => 'bi-shield', 'Sistema' => 'bi-building',
                            'Firma' => 'bi-pen', 'Correo' => 'bi-envelope', 'Accesos' => 'bi-key',
                            'Contraseña' => 'bi-lock', 'Usuario' => 'bi-person-plus', 'Desbloqueo' => 'bi-unlock',
                            'Teléfono' => 'bi-telephone', 'Video' => 'bi-camera-video', 'Soporte' => 'bi-question-circle',
                            'Capacitación' => 'bi-mortarboard', 'Equipamiento' => 'bi-box-seam', 'Otro' => 'bi-three-dots'
                        ];
                        foreach ($categorias as $cat): 
                            $icono = 'bi-question-circle';
                            foreach ($iconos as $key => $icon) {
                                if (stripos($cat['nombre'], $key) !== false) { $icono = $icon; break; }
                            }
                            $seleccionada = ($categoria_preseleccionada == $cat['id']) ? 'seleccionada' : '';
                        ?>
                        <label class="categoria-opcion <?= $seleccionada ?>">
                            <input type="radio" name="categoria_id" value="<?= $cat['id'] ?>" 
                                   <?= $seleccionada ? 'checked' : '' ?> required>
                            <i class="bi <?= $icono ?>"></i>
                            <span><?= htmlspecialchars($cat['nombre']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="seccion-form">
                    <div class="seccion-titulo">
                        <i class="bi bi-chat-left-text"></i> Descripción del Problema
                    </div>
                    
                    <div class="form-grupo">
                        <label class="form-label requerido">Asunto</label>
                        <input type="text" name="asunto" class="form-control" 
                               placeholder="Ej: No puedo imprimir documentos"
                               value="<?= htmlspecialchars($_POST['asunto'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-grupo">
                        <label class="form-label requerido">Descripción Detallada</label>
                        <textarea name="descripcion" class="form-control" rows="5" 
                                  placeholder="Describe el problema con el mayor detalle posible:
- ¿Qué estabas haciendo cuando ocurrió?
- ¿Qué mensaje de error aparece?
- ¿Desde cuándo ocurre el problema?
- ¿Otros equipos tienen el mismo problema?"
                                  required><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-grupo">
                        <label class="form-label">Adjuntar Archivos</label>
                        <input type="file" name="adjuntos[]" class="form-control" multiple 
                               accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                        <small style="color: #6c757d; font-size: 12px;">
                            Puedes adjuntar capturas de pantalla, documentos, etc. (máx. 10MB por archivo)
                        </small>
                    </div>
                    
                    <div class="form-grupo">
                        <label class="form-label">Prioridad</label>
                        <select name="prioridad_id" class="form-control">
                            <?php foreach ($prioridades as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $p['id'] == 2 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nombre']) ?>
                                <?php if ($p['id'] == 5): ?> - Solo si afecta a múltiples usuarios<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-ayuda">
                            Crítica: Afecta a toda la organización | Urgente: No puedo trabajar | Normal: Puedo continuar trabajando
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primario btn-lg btn-bloque">
                    <i class="bi bi-send"></i> Enviar Solicitud
                </button>
            </form>
        </div>
        <?php endif; ?>
    </main>

    <script>
    document.querySelectorAll('.categoria-opcion').forEach(function(el) {
        el.addEventListener('click', function() {
            document.querySelectorAll('.categoria-opcion').forEach(function(c) {
                c.classList.remove('seleccionada');
            });
            this.classList.add('seleccionada');
        });
    });
    </script>
</body>
</html>
