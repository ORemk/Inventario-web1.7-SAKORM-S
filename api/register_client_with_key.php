<?php
// api/register_client_with_key.php
require_once __DIR__ . '/_api_bootstrap.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Payload inválido']);
    exit;
}

$accessKey = trim($data['access_key'] ?? '');
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$company = trim($data['company'] ?? '');
$contact = trim($data['contact_person'] ?? '');
$tax_id = trim($data['tax_id'] ?? '');
$address = trim($data['address'] ?? '');
$city = trim($data['city'] ?? '');
$state = trim($data['state'] ?? '');
$postal = trim($data['postal'] ?? '');
$country = trim($data['country'] ?? '');
$website = trim($data['website'] ?? '');
$notes = trim($data['notes'] ?? '');
$deviceId = trim($data['device_id'] ?? '');

if (!$accessKey) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'La clave de acceso es requerida']);
    exit;
}
if (!$name) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Nombre requerido']);
    exit;
}

try {
    require_once __DIR__ . '/../src/config/Database.php';
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // ensure tables (include approval columns)
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        company VARCHAR(255) DEFAULT NULL,
        contact_person VARCHAR(255) DEFAULT NULL,
        tax_id VARCHAR(128) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(60) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        city VARCHAR(120) DEFAULT NULL,
        state VARCHAR(120) DEFAULT NULL,
        postal VARCHAR(30) DEFAULT NULL,
        country VARCHAR(100) DEFAULT NULL,
        website VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        approved TINYINT(1) NOT NULL DEFAULT 0,
        approved_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

    $pdo->exec("CREATE TABLE IF NOT EXISTS client_sessions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id INT UNSIGNED NOT NULL,
        device_id VARCHAR(255) DEFAULT NULL,
        session_token VARCHAR(128) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // lookup key
    $stmt = $pdo->prepare('SELECT * FROM client_keys WHERE `key` = ? LIMIT 1');
    $stmt->execute([$accessKey]);
    $keyRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$keyRow) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Clave inválida o no encontrada']);
        exit;
    }
    if (intval($keyRow['used']) === 1) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Clave ya fue utilizada']);
        exit;
    }

    // create client (auto-approved because registration uses a valid key)
    $ins = $pdo->prepare('INSERT INTO clients (name,company,contact_person,tax_id,email,phone,address,city,state,postal,country,website,notes,approved,approved_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())');
    $ins->execute([$name,$company,$contact,$tax_id,$email,$phone,$address,$city,$state,$postal,$country,$website,$notes]);
    $clientId = $pdo->lastInsertId();

    // activate key: mark used, set used_by, used_at, activated_at and expires_at = +30 days
    $now = date('Y-m-d H:i:s');
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    $up = $pdo->prepare('UPDATE client_keys SET used = 1, used_at = ?, used_by = ?, activated_at = ?, expires_at = ?, client_id = ? WHERE id = ?');
    $up->execute([$now, $clientId, $now, $expires, $clientId, $keyRow['id']]);

    // create session token and enforce one session per device
    $sessionToken = bin2hex(random_bytes(16));
    // derive device id fallback
    if (!$deviceId) {
        $deviceId = substr(hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? '')),0,64);
    }
    // remove any existing session for same client & device
    $del = $pdo->prepare('DELETE FROM client_sessions WHERE client_id = ? AND device_id = ?');
    $del->execute([$clientId, $deviceId]);
    $sessExp = date('Y-m-d H:i:s', strtotime('+30 days'));
    $insS = $pdo->prepare('INSERT INTO client_sessions (client_id, device_id, session_token, expires_at) VALUES (?,?,?,?)');
    $insS->execute([$clientId, $deviceId, $sessionToken, $sessExp]);

    // set PHP session for convenience
    $_SESSION['client'] = ['id'=>$clientId,'name'=>$name,'email'=>$email];

    // return session token and client id
    echo json_encode(['success'=>true,'client_id'=>$clientId,'session_token'=>$sessionToken,'expires_at'=>strtotime($sessExp),'message'=>'Registro exitoso y clave activada']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno','error'=>$e->getMessage()]);
    exit;
}
