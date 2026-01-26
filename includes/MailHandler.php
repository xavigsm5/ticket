<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

use PhpImap\Mailbox;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHandler
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // Obtener configuración activa
    private function getConfig()
    {
        return $this->pdo->fetch("SELECT * FROM configuracion_correo WHERE activo = TRUE LIMIT 1");
    }

    // PROCESAR CORREOS ENTRANTES (IMAP)
    public function procesarCorreos()
    {
        $config = $this->getConfig();
        if (!$config) {
            echo "No hay configuración de correo activa.\n";
            return;
        }

        $path = "{" . $config['host'] . ":" . $config['port'] . "/" . $config['protocolo'] . "/" . $config['encryption'] . "}" . $config['carpeta'];

        try {
            $mailbox = new Mailbox(
                $path,
                $config['usuario'],
                $config['password'],
                __DIR__ . '/../uploads/attachments'
            );

            // Buscar correos no leídos
            $mailsIds = $mailbox->searchMailbox('UNSEEN');

            if (!$mailsIds) {
                echo "No hay correos nuevos.\n";
                return;
            }

            foreach ($mailsIds as $mailId) {
                $mail = $mailbox->getMail($mailId);

                // Procesar el correo
                $this->convertirCorreoATicket($mail);

                // Marcar como leído (opcional, IMAP lo hace al leer a veces)
                // $mailbox->markMailAsRead($mailId);
            }

            // Actualizar timestamp
            $this->pdo->query("UPDATE configuracion_correo SET ultimo_chequeo = CURRENT_TIMESTAMP WHERE id = ?", [$config['id']]);

        } catch (Exception $e) {
            echo "Error IMAP: " . $e->getMessage() . "\n";
        }
    }

    private function convertirCorreoATicket($mail)
    {
        $fromEmail = $mail->fromAddress;
        $fromName = $mail->fromName;
        $subject = $mail->subject;
        $body = $mail->textPlain ?: $mail->textHtml;

        echo "Procesando correo de: $fromEmail - Asunto: $subject\n";

        // 1. Verificar si es respuesta a un ticket existente [TKT-YYYYMMDD-ID]
        if (preg_match('/\[(TKT-\d+-\d+)\]/', $subject, $matches)) {
            $ticketNumero = $matches[1];
            $ticket = obtenerTicketPorNumero($ticketNumero);

            if ($ticket) {
                echo " -> Es respuesta al ticket $ticketNumero\n";
                $usuario = $this->obtenerOCrearUsuario($fromEmail, $fromName);
                agregarComentario($ticket['id'], $usuario['id'], $body, false);
                return;
            }
        }

        // 2. Crear nuevo ticket
        echo " -> Creando nuevo ticket\n";
        $usuario = $this->obtenerOCrearUsuario($fromEmail, $fromName);

        // Categoría por defecto (puedes cambiar logicamente)
        $categoriaId = 16; // 'Información General' o una por defecto

        $datosTicket = [
            'ciudadano_id' => $usuario['id'],
            'categoria_id' => $categoriaId,
            'prioridad_id' => 2, // Normal
            'asunto' => $subject,
            'descripcion' => $body,
            'ubicacion_direccion' => 'Vía Correo Electrónico'
        ];

        $nuevoTicket = crearTicket($datosTicket);

        // Auto-responder confirmación
        $this->enviarNotificacionNuevoTicket($usuario['email'], $nuevoTicket['numero'], $subject);
    }

    private function obtenerOCrearUsuario($email, $nombre)
    {
        $user = $this->pdo->fetch("SELECT * FROM usuarios WHERE email = ?", [$email]);
        if ($user)
            return $user;

        // Crear usuario temporal
        $pass = bin2hex(random_bytes(8)); // Contraseña aleatoria
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO usuarios (email, nombres, apellidos, password, rol, rut) VALUES (?, ?, ?, ?, 'ciudadano', ?)",
            [$email, $nombre ?: 'Usuario', 'Email', password_hash($pass, PASSWORD_DEFAULT), 'SC-' . time()]
        );

        return $this->pdo->fetch("SELECT * FROM usuarios WHERE email = ?", [$email]);
    }

    // ENVIAR CORREOS (SMTP)
    public function enviarCorreo($destinatario, $asunto, $cuerpo)
    {
        $config = $this->getConfig();
        if (!$config)
            return false;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();

            // Lógica para detectar el host SMTP
            $smtpHost = !empty($config['smtp_host']) ? $config['smtp_host'] : null;

            // Si no hay SMTP explícito, intentar deducirlo del IMAP
            if (!$smtpHost) {
                // Si el host IMAP empieza con 'imap.', lo cambiamos a 'smtp.'
                if (strpos($config['host'], 'imap.') === 0) {
                    $smtpHost = str_replace('imap.', 'smtp.', $config['host']);
                } else {
                    // Si no, asumimos que es el mismo host (común en Exchange/cPanel)
                    $smtpHost = $config['host'];
                }
            }

            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $config['usuario'];
            $mail->Password = $config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            // Usar puerto SMTP específico o 587 por defecto
            $mail->Port = !empty($config['smtp_port']) ? $config['smtp_port'] : 587;

            $mail->setFrom($config['usuario'], 'Mesa de Ayuda Municipal');
            $mail->addAddress($destinatario);

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $cuerpo;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error enviando correo: {$mail->ErrorInfo}");
            return false;
        }
    }

    private function enviarNotificacionNuevoTicket($email, $numero, $asunto)
    {
        $body = "
            <h2>Ticket Recibido</h2>
            <p>Hemos recibido su solicitud y se ha creado el ticket <strong>[$numero]</strong>.</p>
            <p>Asunto: $asunto</p>
            <p>Puede responder a este correo para agregar más información.</p>
        ";
        $this->enviarCorreo($email, "Ticket Creado [$numero]", $body);
    }
}
