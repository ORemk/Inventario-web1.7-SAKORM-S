<?php
require_once __DIR__ . '/_api_bootstrap.php';
/**
 * api/ai_promote.php
 * Promueve una conversación (prompt+reply) a una regla.
 * POST { pattern, response, created_by? }
 * Access: localhost only (admin)
 */
header('Content-Type: application/json; charset=utf-8');
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1','::1','::ffff:127.0.0.1'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso restringido a localhost']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>'JSON inválido']); exit;
}
$pattern = trim($data['pattern'] ?? '');
$response = trim($data['response'] ?? '');
$created_by = trim($data['created_by'] ?? 'admin');
if ($pattern === '' || $response === '') {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>'pattern y response son requeridos']); exit;
}

// Insert into ai_rules using Database PDO
require_once __DIR__ . '/../src/config/Database.php';
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['success'=>false,'error'=>'DB connection failed: ' . $e->getMessage()]); exit;
}

$ok = $db->execute("INSERT INTO ai_rules (pattern, response, created_by) VALUES (?,?,?)", [$pattern, $response, $created_by]);
if (!$ok) {
    http_response_code(500); echo json_encode(['success'=>false,'error'=>'insert_failed']); exit;
}
$insertId = $db->getConnection()->lastInsertId();
echo json_encode(['success'=>true,'id'=>$insertId]);
exit;
?>