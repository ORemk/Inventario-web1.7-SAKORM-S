<?php
// api/admin/register_client.php
require_once __DIR__ . '/../_api_bootstrap.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

// require admin
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

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');

if (!$name) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Nombre requerido']);
    exit;
}

try {
    require_once __DIR__ . '/../../src/config/Database.php';
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(60) DEFAULT NULL,
        created_by VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $createdBy = $_SESSION['user']['email'] ?? 'unknown';
    $stmt = $pdo->prepare('INSERT INTO clients (name,email,phone,created_by) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name,$email,$phone,$createdBy]);
    $id = $pdo->lastInsertId();
    echo json_encode(['success'=>true,'client_id'=>$id,'message'=>'Cliente creado']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno','error'=>$e->getMessage()]);
    exit;
}

?>
