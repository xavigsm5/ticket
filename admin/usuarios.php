<?php
/**
 * Gestión de Usuarios - Panel Administrativo
 */
require_once __DIR__ . '/../includes/functions.php';

requiereRol(['admin', 'supervisor']);

$usuario_actual = obtenerUsuarioActual();
$db = Database::getInstance();

$exito = '';
$error = '';
$accion = $_GET['accion'] ?? 'listar';
$id_editar = (int) ($_GET['id'] ?? 0);

// ==========================================
// ELIMINAR USUARIO
// ==========================================
if (isset($_POST['eliminar_id'])) {
    $id_borrar = (int) $_POST['eliminar_id'];
    if ($id_borrar !== $usuario_actual['id']) { // No auto-borrarse
        $db->query("UPDATE usuarios SET activo = FALSE WHERE id = ?", [$id_borrar]);
        $exito = 'Usuario desactivado correctamente.';
    } else {
        $error = 'No puedes eliminar tu propia cuenta.';
    }
}

// ==========================================
// GUARDAR / ACTUALIZAR USUARIO
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_usuario'])) {
    $nombres = limpiarInput($_POST['nombres']);
    $apellidos = limpiarInput($_POST['apellidos']);
    $email = trim($_POST['email']);
    $rut = limpiarInput($_POST['rut']);
    $rol = $_POST['rol'];
    $departamento_id = !empty($_POST['departamento_id']) ? (int) $_POST['departamento_id'] : null;
    $password = $_POST['password'];

    // Validar duplicados mail
    $existe = $db->fetch("SELECT id FROM usuarios WHERE email = ? AND id != ?", [$email, $id_editar]);

    if ($existe) {
        $error = 'El correo electrónico ya está registrado por otro usuario.';
    } else {
        if ($id_editar > 0) {
            // ACTUALIZAR
            $sql = "UPDATE usuarios SET nombres = ?, apellidos = ?, email = ?, rut = ?, rol = ?, departamento_id = ? WHERE id = ?";
            $params = [$nombres, $apellidos, $email, $rut, $rol, $departamento_id, $id_editar];
            $db->query($sql, $params);

            // Password solo si se escribe algo
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->query("UPDATE usuarios SET password = ? WHERE id = ?", [$hash, $id_editar]);
            }

            $exito = 'Usuario actualizado correctamente.';
            $accion = 'listar'; // Volver a lista
        } else {
            // CREAR (Si pass vacía, poner por defecto 123456)
            $pass_final = !empty($password) ? $password : '123456';
            $hash = password_hash($pass_final, PASSWORD_DEFAULT);

            $db->query("
                INSERT INTO usuarios (rut, nombres, apellidos, email, password, rol, departamento_id, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
            ", [$rut, $nombres, $apellidos, $email, $hash, $rol, $departamento_id]);

            $exito = 'Usuario creado correctamente.';
            $accion = 'listar';
        }
    }
}

// Datos para Selects
$departamentos = obtenerDepartamentos();
$roles_disponibles = [
    'ciudadano' => 'Funcionario (Solicitante)',
    'funcionario' => 'Soporte TI (Técnico)',
    'supervisor' => 'Supervisor TI',
    'admin' => 'Administrador Sistema'
];

// Obtener Usuario a Editar si aplica
$usuario_editar = null;
if ($accion === 'editar' && $id_editar) {
    $usuario_editar = $db->fetch("SELECT * FROM usuarios WHERE id = ?", [$id_editar]);
}

// Listar Usuarios
$filtro_busqueda = $_GET['q'] ?? '';
$sql_lista = "
    SELECT u.*, d.nombre as depto 
    FROM usuarios u 
    LEFT JOIN departamentos d ON u.departamento_id = d.id 
    WHERE u.activo = TRUE 
";
$params_lista = [];

if ($filtro_busqueda) {
    $sql_lista .= " AND (u.nombres ILIKE ? OR u.apellidos ILIKE ? OR u.email ILIKE ?)";
    $params_lista = ["%$filtro_busqueda%", "%$filtro_busqueda%", "%$filtro_busqueda%"];
}

$sql_lista .= " ORDER BY u.id DESC LIMIT 100";
$usuarios_lista = $db->fetchAll($sql_lista, $params_lista);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Municipalidad</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
    <div class="layout-app">
        <!-- Sidebar aquí (simplificado para no repetir todo el código, ideal sería un include) -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo"><i class="bi bi-building"></i> <span
                        class="sidebar-titulo">Municipalidad</span></div>
            </div>
            <nav class="sidebar-menu">
                <a href="/admin/dashboard.php" class="menu-item"><i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span></a>
                <a href="/admin/usuarios.php" class="menu-item activo"><i class="bi bi-people"></i>
                    <span>Usuarios</span></a>
                <a href="/logout.php" class="menu-item"><i class="bi bi-box-arrow-left"></i> <span>Salir</span></a>
            </nav>
        </aside>

        <div class="contenido-principal">
            <header class="topbar">
                <div class="topbar-izq">
                    <h2>Gestión de Usuarios</h2>
                </div>
            </header>

            <main class="pagina-contenido">
                <?php if ($exito): ?>
                    <div class="alerta alerta-exito">
                        <?= $exito ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alerta alerta-error">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <?php if ($accion === 'listar'): ?>
                    <!-- VISTA LISTA -->
                    <div class="tarjeta">
                        <div class="tarjeta-header"
                            style="display:flex; justify-content:space-between; align-items:center;">
                            <form action="" method="GET" style="display:flex; gap:10px;">
                                <input type="text" name="q" class="form-control" placeholder="Buscar usuario..."
                                    value="<?= htmlspecialchars($filtro_busqueda) ?>">
                                <button type="submit" class="btn btn-secundario"><i class="bi bi-search"></i></button>
                            </form>
                            <a href="?accion=crear" class="btn btn-primario"><i class="bi bi-person-plus"></i> Nuevo
                                Usuario</a>
                        </div>
                        <div class="tabla-contenedor">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Rol</th>
                                        <th>Departamento</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios_lista as $u): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight:600;">
                                                    <?= htmlspecialchars($u['nombres'] . ' ' . $u['apellidos']) ?>
                                                </div>
                                                <div style="font-size:11px; color:#777;">
                                                    <?= htmlspecialchars($u['rut']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($u['email']) ?>
                                            </td>
                                            <td>
                                                <span class="badge" style="background:var(--color-primario); color:white;">
                                                    <?= ucfirst($u['rol']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($u['depto'] ?? '-') ?>
                                            </td>
                                            <td>
                                                <a href="?accion=editar&id=<?= $u['id'] ?>" class="btn btn-sm btn-secundario"><i
                                                        class="bi bi-pencil"></i></a>
                                                <form method="POST" style="display:inline;"
                                                    onsubmit="return confirm('¿Desactivar usuario?');">
                                                    <input type="hidden" name="eliminar_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-peligro"><i
                                                            class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- VISTA FORMULARIO (CREAR/EDITAR) -->
                    <div class="tarjeta" style="max-width: 800px; margin: 0 auto;">
                        <div class="tarjeta-header">
                            <h3>
                                <?= $id_editar ? 'Editar Usuario' : 'Nuevo Usuario' ?>
                            </h3>
                        </div>
                        <div class="tarjeta-body">
                            <form method="POST" action="?accion=<?= $accion ?>&id=<?= $id_editar ?>">
                                <input type="hidden" name="guardar_usuario" value="1">

                                <div class="form-fila">
                                    <div class="form-grupo">
                                        <label class="form-label">RUT</label>
                                        <input type="text" name="rut" class="form-control" required
                                            value="<?= htmlspecialchars($usuario_editar['rut'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-fila">
                                    <div class="form-grupo">
                                        <label class="form-label">Nombres</label>
                                        <input type="text" name="nombres" class="form-control" required
                                            value="<?= htmlspecialchars($usuario_editar['nombres'] ?? '') ?>">
                                    </div>
                                    <div class="form-grupo">
                                        <label class="form-label">Apellidos</label>
                                        <input type="text" name="apellidos" class="form-control" required
                                            value="<?= htmlspecialchars($usuario_editar['apellidos'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-grupo">
                                    <label class="form-label">Email Corporativo</label>
                                    <input type="email" name="email" class="form-control" required
                                        value="<?= htmlspecialchars($usuario_editar['email'] ?? '') ?>">
                                </div>

                                <div class="form-fila">
                                    <div class="form-grupo">
                                        <label class="form-label">Rol en el Sistema</label>
                                        <select name="rol" class="form-control" required>
                                            <?php foreach ($roles_disponibles as $key => $label): ?>
                                                <option value="<?= $key ?>" <?= ($usuario_editar['rol'] ?? '') === $key ? 'selected' : '' ?>>
                                                    <?= $label ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-grupo">
                                        <label class="form-label">Departamento (Opcional)</label>
                                        <select name="departamento_id" class="form-control">
                                            <option value="">-- Ninguno --</option>
                                            <?php foreach ($departamentos as $d): ?>
                                                <option value="<?= $d['id'] ?>" <?= ($usuario_editar['departamento_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($d['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-ayuda">Requerido si es Técnico o Jefatura.</div>
                                    </div>
                                </div>

                                <div class="form-grupo" style="background:#f8f9fa; padding:15px; border-radius:5px;">
                                    <label class="form-label">
                                        <?= $id_editar ? 'Cambiar Contraseña (dejar en blanco para mantener)' : 'Contraseña' ?>
                                    </label>
                                    <input type="password" name="password" class="form-control"
                                        placeholder="Mínimo 6 caracteres">
                                </div>

                                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                                    <a href="?accion=listar" class="btn btn-secundario">Cancelar</a>
                                    <button type="submit" class="btn btn-primario">Guardar Usuario</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>

</html>