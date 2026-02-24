<?php
// api/admin/save_smtp_settings.php
require_once __DIR__ . '/../_api_bootstrap.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

if (!isset($_SESSION['user']) || (empty($_SESSION['user']['role']) && empty($_SESSION['user']['is_admin']))) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'No autenticado como administrador']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Payload inválido']); exit; }

$cfgFile = __DIR__ . '/../../src/config/smtp.php';
$backup = $cfgFile . '.bak.' . time();
try {
    if (is_file($cfgFile)) {
        copy($cfgFile, $backup);
    }
    // sanitize and build config array
    $enabled = (!empty($data['enabled']) && intval($data['enabled'])===1) ? true : false;
    $cfg = [
        'enabled' => $enabled,
        'host' => trim($data['host'] ?? ''),
        'port' => intval($data['port'] ?? 0),
        'username' => trim($data['username'] ?? ''),
        'password' => trim($data['password'] ?? ''),
        'encryption' => trim($data['encryption'] ?? ''),
        'from_email' => trim($data['from_email'] ?? ''),
        'from_name' => trim($data['from_name'] ?? ''),
        'admin_email' => trim($data['admin_email'] ?? '')
    ];
    // write PHP file
    $out = "<?php\n// src/config/smtp.php - written by admin UI\nreturn ".var_export($cfg, true).";\n";
    file_put_contents($cfgFile, $out, LOCK_EX);
    $response = ['success'=>true,'message'=>'Configuración guardada','backup'=>$backup];

    // If enabled, attempt an SMTP connection test using PHPMailer if available,
    // otherwise try a basic TCP connection to the host:port.
    if (!empty($cfg['enabled'])) {
        $smtpTest = null;
        $smtpError = null;
        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $cfg['host'];
                $mail->SMTPAuth = !empty($cfg['username']);
                $mail->Username = $cfg['username'];
                $mail->Password = $cfg['password'];
                if (!empty($cfg['encryption'])) $mail->SMTPSecure = $cfg['encryption'];
                $mail->Port = !empty($cfg['port']) ? intval($cfg['port']) : 587;
                $mail->SMTPAutoTLS = false;
                $mail->Timeout = 10;
                // Try to establish SMTP connection
                try {
                    $connected = $mail->smtpConnect();
                    if ($connected) {
                        $smtpTest = true;
                        $mail->smtpClose();
                    } else {
                        $smtpTest = false;
                        $smtpError = $mail->ErrorInfo ?: 'No se pudo conectar al servidor SMTP';
                    }
                } catch (Exception $e) {
                    $smtpTest = false;
                    $smtpError = $e->getMessage();
                }
            } catch (Exception $e) {
                $smtpTest = false;
                $smtpError = $e->getMessage();
            }
        } else {
            // PHPMailer not installed — try simple TCP connection as fallback
            $host = $cfg['host'] ?? '';
            $port = !empty($cfg['port']) ? intval($cfg['port']) : 25;
            $fp = fsockopen($host, $port, $errno, $errstr, 5);
            if ($fp) {
                $smtpTest = true;
                fclose($fp);
            } else {
                $smtpTest = false;
                $smtpError = 'No se pudo conectar al host ' . $host . ':' . $port . ' (' . ($errstr ?? '') . ')';
            }
        }
        $response['smtp_test'] = $smtpTest;
        $response['smtp_error'] = $smtpError;
        // Attempt to send a test email to admin_email and report result
        $smtpSend = null;
        $smtpSendError = null;
        try {
            $to = $cfg['admin_email'] ?: ($cfg['username'] ?? null);
            $subject = 'Prueba de configuración SMTP';
            $body = "Este es un correo de prueba enviado tras guardar la configuración SMTP.\n\nSi recibes este correo, la configuración es correcta.";
            if (is_file($autoload) && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                try {
                    $pm = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $pm->isSMTP();
                    $pm->Host = $cfg['host'];
                    $pm->SMTPAuth = !empty($cfg['username']);
                    $pm->Username = $cfg['username'];
                    $pm->Password = $cfg['password'];
                    if (!empty($cfg['encryption'])) $pm->SMTPSecure = $cfg['encryption'];
                    $pm->Port = !empty($cfg['port']) ? intval($cfg['port']) : 587;
                    $pm->setFrom($cfg['from_email'] ?? $cfg['username'] ?? 'no-reply@localhost', $cfg['from_name'] ?? 'Sakorms');
                    $pm->addAddress($to);
                    $pm->Subject = $subject;
                    $pm->Body = $body;
                    $pm->AltBody = strip_tags($body);
                    $pm->send();
                    $smtpSend = true;
                } catch (Exception $e) {
                    $smtpSend = false;
                    $smtpSendError = $e->getMessage();
                }
            } else {
                // Fallback to mail()
                if (function_exists('mail')) {
                    $headers = 'From: ' . ($cfg['from_email'] ?? 'no-reply@localhost') . "\r\n";
                    $smtpSend = mail($to, $subject, $body, $headers);
                    if ($smtpSend === false) $smtpSendError = 'mail() falló o no está configurado';
                } else {
                    $smtpSend = null;
                    $smtpSendError = 'PHPMailer no disponible y mail() no está disponible';
                }
            }
        } catch (Exception $e) {
            $smtpSend = false;
            $smtpSendError = $e->getMessage();
        }
        $response['smtp_send_test'] = $smtpSend;
        $response['smtp_send_error'] = $smtpSendError;
    }

    echo json_encode($response);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error al guardar','error'=>$e->getMessage()]);
    exit;
}

?>
