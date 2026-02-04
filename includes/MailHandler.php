<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

use PhpImap\Mailbox;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use TheNetworg\OAuth2\Client\Provider\Azure;

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
        $mail = new PHPMailer(true);
        try {
            // Cargar configuración SMTP desde variables de entorno
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
            
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.office365.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER'] ?? '';
            $mail->Password = $_ENV['SMTP_PASS'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
            
            // Configuración de caracteres UTF-8
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // Remitente y destinatario
            $smtpUser = $_ENV['SMTP_USER'] ?? 'testing@quintanormal.cl';
            $smtpFromName = $_ENV['SMTP_FROM_NAME'] ?? 'Mesa de Ayuda Municipal';
            $mail->setFrom($smtpUser, $smtpFromName);
            $mail->addAddress($destinatario);

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $cuerpo;
            $mail->AltBody = strip_tags($cuerpo); // Versión texto plano

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error enviando correo SMTP: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Enviar correo con copia (CC) y/o copia oculta (BCC)
     */
    public function enviarCorreoAvanzado($destinatario, $asunto, $cuerpo, $cc = [], $bcc = [], $adjuntos = [])
    {
        $mail = new PHPMailer(true);
        try {
            // Cargar configuración SMTP desde variables de entorno
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
            
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.office365.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER'] ?? '';
            $mail->Password = $_ENV['SMTP_PASS'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
            
            // Configuración de caracteres UTF-8
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // Remitente
            $smtpUser = $_ENV['SMTP_USER'] ?? 'testing@quintanormal.cl';
            $smtpFromName = $_ENV['SMTP_FROM_NAME'] ?? 'Mesa de Ayuda Municipal';
            $mail->setFrom($smtpUser, $smtpFromName);
            
            // Destinatario principal
            $mail->addAddress($destinatario);
            
            // Copias (CC)
            foreach ($cc as $ccEmail) {
                $mail->addCC($ccEmail);
            }
            
            // Copias ocultas (BCC)
            foreach ($bcc as $bccEmail) {
                $mail->addBCC($bccEmail);
            }
            
            // Adjuntos
            foreach ($adjuntos as $adjunto) {
                if (file_exists($adjunto)) {
                    $mail->addAttachment($adjunto);
                }
            }

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $cuerpo;
            $mail->AltBody = strip_tags($cuerpo);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error enviando correo avanzado SMTP: {$mail->ErrorInfo}");
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
    
    /**
     * Enviar correo usando Microsoft Graph API con el token del usuario
     * Esto permite enviar desde la cuenta del usuario con su firma de Outlook
     * 
     * @param int $usuario_id ID del usuario que enviará el correo
     * @param string $destinatario Email del destinatario
     * @param string $asunto Asunto del correo
     * @param string $cuerpo Cuerpo del correo en HTML
     * @param array $cc Copias (opcional)
     * @return bool True si se envió correctamente
     */
    public function enviarCorreoDesdeUsuario($usuario_id, $destinatario, $asunto, $cuerpo, $cc = [])
    {
        try {
            // 1. Obtener el token del usuario desde la base de datos
            $tokenData = $this->pdo->fetch(
                "SELECT access_token, refresh_token, expires_at FROM oauth_tokens WHERE usuario_id = ?",
                [$usuario_id]
            );
            
            if (!$tokenData) {
                error_log("No hay tokens OAuth2 para el usuario ID: $usuario_id");
                return false;
            }
            
            // 2. Verificar si el token ha expirado y refrescarlo si es necesario
            $expiresAt = strtotime($tokenData['expires_at']);
            $ahora = time();
            
            if ($ahora >= $expiresAt) {
                // Token expirado, intentar refrescarlo
                $accessToken = $this->refrescarTokenOAuth($usuario_id, $tokenData['refresh_token']);
                if (!$accessToken) {
                    error_log("No se pudo refrescar el token para el usuario ID: $usuario_id");
                    return false;
                }
            } else {
                $accessToken = $tokenData['access_token'];
            }
            
            // 3. Obtener la firma del usuario
            $firma = $this->generarFirmaUsuario($usuario_id);
            
            // Agregar firma al cuerpo del mensaje
            if ($firma) {
                $cuerpo = $cuerpo . '<br><br>' . $firma;
            }
            
            // 4. Construir el mensaje para Microsoft Graph API
            $message = [
                'message' => [
                    'subject' => $asunto,
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => $cuerpo
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => $destinatario
                            ]
                        ]
                    ]
                ],
                'saveToSentItems' => 'true' // Guardar en "Enviados"
            ];
            
            // Agregar copias (CC) si existen
            if (!empty($cc)) {
                $ccRecipients = [];
                foreach ($cc as $ccEmail) {
                    $ccRecipients[] = [
                        'emailAddress' => [
                            'address' => $ccEmail
                        ]
                    ];
                }
                $message['message']['ccRecipients'] = $ccRecipients;
            }
            
            // 5. Enviar el correo usando Microsoft Graph API
            $url = 'https://graph.microsoft.com/v1.0/me/sendMail';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // 6. Verificar respuesta
            if ($httpCode === 202 || $httpCode === 200) {
                // Éxito: Microsoft Graph devuelve 202 Accepted para sendMail
                return true;
            } else {
                error_log("Error al enviar correo via Graph API. HTTP Code: $httpCode. Response: $response");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Excepción al enviar correo via Graph API: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generar firma HTML para el usuario basada en sus datos
     * 
     * @param int $usuario_id ID del usuario
     * @return string Firma HTML
     */
    private function generarFirmaUsuario($usuario_id)
    {
        try {
            $usuario = $this->pdo->fetch(
                "SELECT u.nombres, u.apellidos, u.email, u.telefono, u.firma_imagen, d.nombre as departamento 
                 FROM usuarios u 
                 LEFT JOIN departamentos d ON u.departamento_id = d.id 
                 WHERE u.id = ?",
                [$usuario_id]
            );
            
            if (!$usuario) {
                return '';
            }
            
            // Si tiene firma en imagen, usarla
            if ($usuario['firma_imagen'] && file_exists(__DIR__ . '/../' . $usuario['firma_imagen'])) {
                $firmaUrl = 'http://localhost:8080/' . $usuario['firma_imagen'];
                return "<br><br><img src='{$firmaUrl}' alt='Firma' style='max-width: 400px;'>";
            }
            
            // Si no, generar firma HTML con datos del usuario
            $nombre = $usuario['nombres'] . ' ' . $usuario['apellidos'];
            $email = $usuario['email'];
            $telefono = $usuario['telefono'] ? $usuario['telefono'] : '';
            $departamento = $usuario['departamento'] ? $usuario['departamento'] : 'Municipalidad de Quinta Normal';
            
            $firma = "
            <div style='font-family: Arial, sans-serif; font-size: 13px; color: #333; margin-top: 20px; padding-top: 15px; border-top: 2px solid #0d6efd;'>
                <p style='margin: 5px 0;'><strong style='color: #0d6efd;'>{$nombre}</strong></p>
                <p style='margin: 5px 0; color: #666;'>{$departamento}</p>
                " . ($telefono ? "<p style='margin: 5px 0;'><strong>Tel:</strong> {$telefono}</p>" : "") . "
                <p style='margin: 5px 0;'><strong>Email:</strong> <a href='mailto:{$email}' style='color: #0d6efd;'>{$email}</a></p>
                <p style='margin: 5px 0; color: #888; font-size: 11px;'>Municipalidad de Quinta Normal</p>
            </div>
            ";
            
            return $firma;
            
        } catch (Exception $e) {
            error_log("Error generando firma de usuario: " . $e->getMessage());
            return '';
        }
    }

    /**
     * 
     * 
     * @param string 
     * @return string|false 
     */
    private function obtenerFirmaOutlook($accessToken)
    {
        try {
            
            $url = 'https://graph.microsoft.com/v1.0/me/mailboxSettings';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                
                return false; 
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error obteniendo firma de Outlook: " . $e->getMessage());
            return false;
        }
    }

    /**
     *
     * 
     * @param int 
     * @param string 
     * @return string|false 
     */
    private function refrescarTokenOAuth($usuario_id, $refreshToken)
    {
        try {
            
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
            
            $provider = new Azure([
                'clientId'                => $_ENV['AZURE_CLIENT_ID'],
                'clientSecret'            => $_ENV['AZURE_CLIENT_SECRET'],
                'redirectUri'             => $_ENV['AZURE_REDIRECT_URI'],
                'tenant'                  => 'common',
                'urlAuthorize'            => "https://login.microsoftonline.com/common/oauth2/v2.0/authorize",
                'urlAccessToken'          => "https://login.microsoftonline.com/common/oauth2/v2.0/token",
                'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
                'scopes'                  => ['openid', 'profile', 'email', 'User.Read', 'Mail.Send'],
                'defaultEndPointVersion'  => '2.0',
            ]);
            
           
            $newAccessToken = $provider->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken
            ]);
            
            
            $tokenValue = $newAccessToken->getToken();
            $newRefreshToken = $newAccessToken->getRefreshToken() ?: $refreshToken; 
            $expiresAt = date('Y-m-d H:i:s', $newAccessToken->getExpires());
            
            
            $this->pdo->query(
                "UPDATE oauth_tokens 
                 SET access_token = ?, refresh_token = ?, expires_at = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE usuario_id = ?",
                [$tokenValue, $newRefreshToken, $expiresAt, $usuario_id]
            );
            
            return $tokenValue;
            
        } catch (Exception $e) {
            error_log("Error al refrescar token OAuth: " . $e->getMessage());
            return false;
        }
    }}