<?php
// SSE endpoint para notificaciones de AI (promociones automáticas)
// Lento pero útil para desarrollo local. Revisa configuración de Apache/PHP si hay buffering.

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');
set_time_limit(0);

$log = __DIR__ . '/../logs/ai_promotions.log';
$lastPos = 0;
if (file_exists($log)) $lastPos = filesize($log);

// Enviar un ping inicial
echo ": connected\n\n";
ob_flush(); flush();

while (!connection_aborted()) {
    clearstatcache(false, $log);
    $size = file_exists($log) ? filesize($log) : 0;
    if ($size > $lastPos) {
        $fp = fopen($log, 'r');
        if ($fp) {
            fseek($fp, $lastPos);
            while (($line = fgets($fp)) !== false) {
                $line = trim($line);
                if ($line === '') continue;
                $payload = json_decode($line, true);
                $data = json_encode(['type' => 'promotion', 'payload' => $payload]);
                echo "event: promotion\n";
                echo "data: " . $data . "\n\n";
                ob_flush(); flush();
            }
            $lastPos = ftell($fp);
            fclose($fp);
        }
    }

    // Keep-alive
    echo ": keepalive\n\n";
    ob_flush(); flush();
    sleep(2);
}

// Cuando el cliente se desconecta, el script terminará
exit;
