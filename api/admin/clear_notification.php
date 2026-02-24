<?php
// api/admin/clear_notification.php
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
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            error_log('clear_notification.php: failed to create log dir: ' . $logDir);
        }
    }
    if (!is_file($logFile)) { echo json_encode(['success'=>true,'message'=>'No hay notificaciones']); exit; }

    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!isset($lines[$idx])) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Notificación no encontrada']); exit; }

    // Remove the line at index and rewrite file
    array_splice($lines, $idx, 1);
    file_put_contents($logFile, implode(PHP_EOL, $lines) . (count($lines)?PHP_EOL:''), LOCK_EX);

    echo json_encode(['success'=>true,'message'=>'Notificación eliminada']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno','error'=>$e->getMessage()]);
    exit;
}

?>
