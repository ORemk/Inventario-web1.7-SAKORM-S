<?php
require_once __DIR__ . '/_api_bootstrap.php';
// api/telemetry.php - Endpoint simple para recibir eventos de telemetría anónima
// Guarda la información mínima en logs/telemetry.log

ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

// Leer payload
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid JSON']);
    exit;
}

$allowedEvents = ['ui_shim_used'];
$event = $data['event'] ?? null;
if (!$event || !in_array($event, $allowedEvents, true)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid or missing event']);
    exit;
}

// Construir entrada de log (NO incluir datos personales explícitos)
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
        error_log('telemetry.php: failed to create log dir: ' . $logDir);
    }
}
$logFile = $logDir . '/telemetry.log';
$entry = [
    'ts' => date('c'),
    'event' => $event,
    'path' => $_SERVER['REQUEST_URI'] ?? '',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'payload' => $data
];
if (is_dir($logDir) && is_writable($logDir)) {
    $r = file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    if ($r === false) error_log('telemetry.php: failed to write telemetry log: ' . $logFile);
} else {
    error_log('telemetry.php: Log dir not writable or missing: ' . $logDir);
}

echo json_encode(['success'=>true]);
exit;
