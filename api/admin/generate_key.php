<?php
// api/admin/generate_key.php
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
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Payload inválido']);
    exit;
}

$client_id = intval($data['client_id'] ?? 0);
if ($client_id <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'client_id inválido']);
    exit;
}

try {
    require_once __DIR__ . '/../../src/config/Database.php';
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // create keys table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_keys (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id INT UNSIGNED DEFAULT NULL,
        `key` VARCHAR(128) NOT NULL,
        created_by VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        activated_at DATETIME DEFAULT NULL,
        expires_at DATETIME DEFAULT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        used_at DATETIME DEFAULT NULL,
        used_by INT UNSIGNED DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // client_id optional: allow generating unassigned keys
    if ($client_id > 0) {
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE id = ? LIMIT 1');
        $stmt->execute([$client_id]);
        $client = $stmt->fetch();
        if (!$client) {
            http_response_code(404);
            echo json_encode(['success'=>false,'message'=>'Cliente no encontrado']);
            exit;
        }
    }

    // generate token
    $token = bin2hex(random_bytes(16));
    $createdBy = $_SESSION['user']['email'] ?? 'unknown';
    $ins = $pdo->prepare('INSERT INTO client_keys (client_id, `key`, created_by) VALUES (?, ?, ?)');
    $ins->execute([$client_id > 0 ? $client_id : null, $token, $createdBy]);
    $id = $pdo->lastInsertId();
    echo json_encode(['success'=>true,'key'=>$token,'id'=>$id,'message'=>'Clave generada']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno','error'=>$e->getMessage()]);
    exit;
}

?>
