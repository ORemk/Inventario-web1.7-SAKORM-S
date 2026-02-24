<?php
// tools/send_smtp_test.php
// Script para enviar correo de prueba usando src/config/smtp.php
require_once __DIR__ . "/../src/config/smtp.php";
$config = include __DIR__ . "/../src/config/smtp.php";
$autoload = __DIR__ . "/../vendor/autoload.php";
$to = $config['admin_email'] ?? null;
if (!$to) {
    echo "No hay admin_email configurado en src/config/smtp.php\n";
    exit(2);
}
$subject = 'Prueba SMTP desde servidor';
$body = "Este es un correo de prueba enviado desde el servidor para verificar la configuración SMTP.\n\nSi lo recibes, la configuración está correcta.";

$sent = false;
$error = null;

if (!empty($config['enabled']) && is_file($autoload)) {
    require_once $autoload;
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = !empty($config['username']);
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        if (!empty($config['encryption'])) $mail->SMTPSecure = $config['encryption'];
        $mail->Port = !empty($config['port']) ? intval($config['port']) : 587;
        $mail->setFrom($config['from_email'] ?? ($config['username'] ?? 'no-reply@localhost'), $config['from_name'] ?? 'Sakorms');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        $mail->send();
        $sent = true;
        echo "PHPMailer: correo enviado correctamente a {$to}\n";
    } catch (Exception $e) {
        $sent = false;
        $error = $e->getMessage();
        echo "PHPMailer error: {$error}\n";
    }
} elseif (!empty($config['enabled'])) {
    // enabled but no composer/vendor
    echo "PHPMailer no disponible (vendor/autoload.php faltante). Intentando mail()...\n";
        if (function_exists('mail')) {
            $headers = 'From: ' . ($config['from_email'] ?? 'no-reply@localhost') . "\r\n";
            $s = mail($to, $subject, $body, $headers);
            if ($s) { echo "mail(): correo enviado a {$to}\n"; $sent = true; }
            else { echo "mail() falló o no está configurado en este entorno\n"; $error = 'mail_failed'; }
        } else {
        echo "Función mail() no disponible. No se puede enviar correo.\n";
        $error = 'no_mail';
    }
} else {
    echo "SMTP desactivado en src/config/smtp.php (enabled = false).\n";
    exit(3);
}

// Log resultado
$logDir = __DIR__ . "/../logs";
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0755, true)) {
        error_log('[send_smtp_test] failed to create log dir: ' . $logDir);
    }
}
$logFile = $logDir . '/email_simulation.log';
$entry = ['time'=>date('c'),'to'=>$to,'sent'=>!!$sent,'error'=>$error];
$res = file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
if ($res === false) error_log('[send_smtp_test] failed to write log to ' . $logFile);

if ($sent) exit(0);
else exit(1);
