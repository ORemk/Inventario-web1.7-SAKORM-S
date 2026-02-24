<?php
// api/admin/approve_client.php
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
$clientId = intval($data['client_id'] ?? 0);
if ($clientId <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'client_id inválido']);
    exit;
}

try {
    require_once __DIR__ . '/../../src/config/Database.php';
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // ensure approved columns exist
    try {
        $pdo->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS approved TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Exception $e) {
        error_log('[approve_client] ensure column approved failed: ' . $e->getMessage());
    }
    try {
        $pdo->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS approved_at DATETIME DEFAULT NULL");
    } catch (Exception $e) {
        error_log('[approve_client] ensure column approved_at failed: ' . $e->getMessage());
    }

    $stmt = $pdo->prepare('SELECT id, name, email, COALESCE(approved,0) AS approved FROM clients WHERE id = ? LIMIT 1');
    $stmt->execute([$clientId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Cliente no encontrado']);
        exit;
    }

    if (intval($row['approved']) === 1) {
        echo json_encode(['success'=>true,'message'=>'Cliente ya aprobado']);
        exit;
    }

    $up = $pdo->prepare('UPDATE clients SET approved = 1, approved_at = NOW() WHERE id = ?');
    $up->execute([$clientId]);

    echo json_encode(['success'=>true,'message'=>'Cliente aprobado correctamente','client_id'=>$clientId]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno','error'=>$e->getMessage()]);
    exit;
}

?>
