<?php
// api/admin/list_clients.php
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
    require_once __DIR__ . '/../../src/config/Database.php';
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Ensure `approved` column exists (MySQL 8+ supports ADD COLUMN IF NOT EXISTS)
    try { $pdo->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS approved TINYINT(1) NOT NULL DEFAULT 0"); } catch(Exception $e) { error_log('[api/admin/list_clients.php] ALTER TABLE approved failed: ' . $e->getMessage()); }
    try { $pdo->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS approved_at DATETIME DEFAULT NULL"); } catch(Exception $e) { error_log('[api/admin/list_clients.php] ALTER TABLE approved_at failed: ' . $e->getMessage()); }

    $stmt = $pdo->query('SELECT id, name, email, phone, created_at, COALESCE(approved,0) AS approved, approved_at FROM clients ORDER BY id DESC LIMIT 500');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success'=>true,'clients'=>$rows]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno','error'=>$e->getMessage()]);
    exit;
}

?>
