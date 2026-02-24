<?php
// api/validate_key.php
header('Content-Type: application/json; charset=utf-8');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$key = trim($data['key'] ?? '');
if (!$key) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Key requerida']);
    exit;
}
try {
    require_once __DIR__ . '/../../src/config/Database.php';
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare('SELECT id, client_id, used, activated_at, expires_at, created_at FROM client_keys WHERE `key` = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['success'=>false,'message'=>'Clave no encontrada']); exit; }
    if (intval($row['used']) === 1) { echo json_encode(['success'=>false,'message'=>'Clave ya utilizada']); exit; }
    if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) { echo json_encode(['success'=>false,'message'=>'Clave expirada']); exit; }
    echo json_encode(['success'=>true,'data'=>$row]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno','error'=>$e->getMessage()]);
    exit;
}
