<?php
// api/admin/list_keys.php
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

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

try {
    require_once __DIR__ . '/../../src/config/Database.php';
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    if ($client_id > 0) {
        $stmt = $pdo->prepare('SELECT id, `key`, created_by, created_at, used, used_at, used_by FROM client_keys WHERE client_id = ? ORDER BY created_at DESC');
        $stmt->execute([$client_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query('SELECT id, client_id, `key`, created_by, created_at, used, used_at, used_by FROM client_keys ORDER BY created_at DESC LIMIT 200');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['success'=>true,'keys'=>$rows]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno','error'=>$e->getMessage()]);
    exit;
}

?>
