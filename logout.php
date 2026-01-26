<?php
/**
 * Cerrar Sesión
 */
require_once __DIR__ . '/includes/functions.php';

iniciarSesionSegura();

// Destruir sesión
$_SESSION = [];
session_destroy();

// Redirigir al inicio
header('Location: /login.php?logout=1');
exit;
