<?php
// api/admin/send_notification_email.php
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
$idx = isset($data['idx']) ? intval($data['idx']) : null;
if ($idx === null) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'idx requerido']); exit; }

try {
    $logDir = __DIR__ . '/../../logs';
    $logFile = $logDir . '/pending_clients_notifications.log';
    $emailLog = $logDir . '/email_simulation.log';
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            error_log('send_notification_email.php: failed to create log dir: ' . $logDir);
        }
    }
    if (!is_file($logFile)) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'No hay notificaciones']); exit; }

    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!isset($lines[$idx])) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Notificación no encontrada']); exit; }

    $note = json_decode($lines[$idx], true);
    $adminEmail = ($data['admin_email'] ?? null);
    $subject = 'Nueva solicitud de cliente pendiente: ' . ($note['name'] ?? 'Sin nombre');
    $body = "Se ha registrado un nuevo cliente pendiente:\n\n" . print_r($note, true) . "\n";

    $sent = false;
    $sendError = null;

    // Attempt to use PHPMailer via Composer if available and SMTP configured
    try {
        $smtpCfg = null;
        $cfgFile = __DIR__ . '/../../src/config/smtp.php';
        if (is_file($cfgFile)) {
            $smtpCfg = include $cfgFile;
        }

        if ($smtpCfg && !empty($smtpCfg['enabled']) && class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtpCfg['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtpCfg['username'];
            $mail->Password = $smtpCfg['password'];
            $mail->SMTPSecure = $smtpCfg['encryption'] ?? 'tls';
            $mail->Port = $smtpCfg['port'] ?? 587;
            $mail->setFrom($smtpCfg['from_email'] ?? $smtpCfg['username'], $smtpCfg['from_name'] ?? 'No Reply');
            $to = $adminEmail ?: ($smtpCfg['admin_email'] ?? $smtpCfg['username']);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            $mail->send();
            $sent = true;
        } else {
            // Fallback to PHP mail() if available
            $to = $adminEmail ?: ($smtpCfg['admin_email'] ?? 'admin@localhost');
                if (function_exists('mail')) {
                    $headers = 'From: ' . ($smtpCfg['from_email'] ?? 'no-reply@localhost') . "\r\n";
                    $sent = mail($to, $subject, $body, $headers);
                    if ($sent === false) error_log('send_notification_email.php: mail() failed to send to ' . $to);
                }
        }
    } catch (Exception $e) {
        $sendError = $e->getMessage();
    }

    // Log the email attempt/result
    $entry = ['time'=>date('c'),'to'=>$adminEmail ?: null,'subject'=>$subject,'body'=>$note,'sent'=>$sent,'error'=>$sendError];
    if (is_dir($logDir) && is_writable($logDir)) {
        $r = file_put_contents($emailLog, json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($r === false) error_log('send_notification_email.php: failed to write email log: ' . $emailLog);
    } else {
        error_log('send_notification_email.php: Log dir not writable or missing: ' . $logDir);
    }

    echo json_encode(['success'=>true,'sent'=>$sent,'error'=>$sendError]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno','error'=>$e->getMessage()]);
    exit;
}

?>
