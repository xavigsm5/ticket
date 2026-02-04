<?php
/**
 * Cerrar sesión
 */
require_once __DIR__ . '/includes/functions.php';

iniciarSesionSegura();

// Destruir sesión
$_SESSION = [];
session_destroy();

// Redirigir al inicio
$baseUrl = getBaseUrl();
header('Location: ' . $baseUrl . '/login.php?logout=1');
exit;
