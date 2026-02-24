<?php
require_once __DIR__ . '/_api_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../db.php';
if (!isset($conn) || !$conn) {
    http_response_code(500);
    $errMsg = (isset($dbConnectionError) && !empty($dbConnectionError)) ? 'No hay conexión a la base de datos: ' . substr($dbConnectionError,0,200) : 'No hay conexión a la base de datos';
    echo json_encode(['success'=>false,'error'=>$errMsg]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Return recent rules (PDO)
        try {
            $stmt = $conn->query('SELECT id, pattern, response, created_by, created_at FROM ai_rules ORDER BY id DESC LIMIT 200');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'data'=>$rows]);
            exit;
        } catch (PDOException $e) {
            // Table might not exist yet
            echo json_encode(['success'=>true,'data'=>[], 'message'=>'ai_rules table not found or empty']);
            exit;
        }
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input) || empty($input['pattern']) || empty($input['response'])) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'pattern and response required']);
            exit;
        }
        try {
            $stmt = $conn->prepare('INSERT INTO ai_rules (pattern, response, created_by) VALUES (:pattern, :response, :created_by)');
            $stmt->execute([
                ':pattern' => $input['pattern'],
                ':response' => $input['response'],
                ':created_by' => $input['created_by'] ?? null
            ]);
            echo json_encode(['success'=>true,'id'=>$conn->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>'ai_rules table missing or insert failed: '.$e->getMessage()]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Metodo no permitido']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
