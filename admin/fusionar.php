<?php
/**
 * Fusión de tickets
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/freshdesk_functions.php';

requiereRol(['admin', 'supervisor', 'funcionario']);

$usuario = obtenerUsuarioActual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_principal = (int)$_POST['ticket_principal'];
    $numero_secundario = limpiarInput($_POST['ticket_secundario'] ?? '');
    
    $ticket_secundario = obtenerTicketPorNumero($numero_secundario);
    
    if (!$ticket_secundario) {
        $_SESSION['error'] = 'Ticket no encontrado: ' . $numero_secundario;
        header("Location: /admin/dashboard.php?id=" . $ticket_principal);
        exit;
    }
    
    if ($ticket_secundario['id'] == $ticket_principal) {
        $_SESSION['error'] = 'No puede fusionar un ticket consigo mismo.';
        header("Location: /admin/dashboard.php?id=" . $ticket_principal);
        exit;
    }
    
    fusionarTickets($ticket_principal, $ticket_secundario['id'], $usuario['id']);
    
    $_SESSION['exito'] = 'Ticket #' . $ticket_secundario['numero'] . ' fusionado correctamente.';
    header("Location: /admin/dashboard.php?id=" . $ticket_principal . "&msg=fusionado");
    exit;
}

header("Location: /admin/dashboard.php");
exit;
