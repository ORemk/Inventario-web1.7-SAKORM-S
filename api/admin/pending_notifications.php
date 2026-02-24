<?php
// api/admin/pending_notifications.php
require_once __DIR__ . '/../_api_bootstrap.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

if (!isset($_SESSION['user']) || (empty($_SESSION['user']['role']) && empty($_SESSION['user']['is_admin']))) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'No autenticado como administrador']);
    exit;
}

try {
    $logDir = __DIR__ . '/../../logs';
    $logFile = $logDir . '/pending_clients_notifications.log';
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            error_log('pending_notifications.php: failed to create log dir: ' . $logDir);
        }
    }
    $items = [];
    if (is_file($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            $j = json_decode($line, true);
            if (is_array($j)) { $j['_idx'] = $i; $items[] = $j; }
        }
    }
    echo json_encode(['success'=>true,'notifications'=>$items]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno','error'=>$e->getMessage()]);
    exit;
}

?>
