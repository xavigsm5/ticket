<?php
/**
 * Cron para procesar correos entrantes
 * Ejecutar cada 5 min: php cron/fetch_emails.php
 */

require_once __DIR__ . '/../includes/MailHandler.php';

echo "Iniciando sincronización de correos...\n";

$mailHandler = new MailHandler();
$mailHandler->procesarCorreos();

echo "Sincronización finalizada.\n";
