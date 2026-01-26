<?php
require_once __DIR__ . '/../includes/functions.php';
requiereAutenticacion();

header('Content-Type: application/json');

$accion = $_GET['accion'] ?? '';
$usuario = obtenerUsuarioActual();

if ($accion === 'marcar_leidas') {
    marcarNotificacionesLeidas($usuario['id']);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Acción no válida']);
