<?php
// api/register_client.php
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

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$company = trim($data['company'] ?? '');
$contact = trim($data['contact_person'] ?? '');
$notes = trim($data['notes'] ?? '');
$deviceId = trim($data['device_id'] ?? '');

if (!$name) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Nombre requerido']);
    exit;
}

try {
    require_once __DIR__ . '/../src/config/Database.php';
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // ensure tables exist (include approval columns)
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

    // Ensure expected columns exist (for upgrades from older schemas)
    $expectedColumns = [
        'company' => "VARCHAR(255) DEFAULT NULL",
        'contact_person' => "VARCHAR(255) DEFAULT NULL",
        'tax_id' => "VARCHAR(128) DEFAULT NULL",
        'notes' => "TEXT DEFAULT NULL",
        'approved' => "TINYINT(1) NOT NULL DEFAULT 0",
        'approved_at' => "DATETIME DEFAULT NULL",
        'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP"
    ];
    foreach ($expectedColumns as $col => $ddl) {
        $q = "SHOW COLUMNS FROM clients LIKE " . $pdo->quote($col);
        $chk = $pdo->query($q);
        if (!$chk || !$chk->fetch(PDO::FETCH_ASSOC)) {
            try {
                $pdo->exec("ALTER TABLE clients ADD COLUMN {$col} {$ddl}");
            } catch (Throwable $t) {
                error_log('[api/register_client.php] ALTER TABLE add column failed: ' . $t->getMessage());
            }
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS client_sessions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id INT UNSIGNED NOT NULL,
        device_id VARCHAR(255) DEFAULT NULL,
        session_token VARCHAR(128) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // If email provided, avoid duplicate clients
    if ($email) {
        $chk = $pdo->prepare('SELECT id FROM clients WHERE email = ? LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(409);
            echo json_encode(['success'=>false,'message'=>'Ya existe un cliente con ese email']);
            exit;
        }
    }

    // insert client (pending approval)
    $ins = $pdo->prepare('INSERT INTO clients (name,company,contact_person,email,phone,notes,approved) VALUES (?,?,?,?,?,?,0)');
    $ins->execute([$name,$company,$contact,$email,$phone,$notes]);
    $clientId = $pdo->lastInsertId();

    // create session token
    $sessionToken = bin2hex(random_bytes(16));

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

    // Simulate notification to admins: append a JSON line to logs/pending_clients_notifications.log
    try {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                error_log('[register_client] failed to create log dir: ' . $logDir);
            }
        }
        $logFile = $logDir . '/pending_clients_notifications.log';
        $note = [
            'client_id' => $clientId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'created_at' => date('c')
        ];
        if (is_dir($logDir) && is_writable($logDir)) {
            $w = file_put_contents($logFile, json_encode($note, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($w === false) error_log('[register_client] failed to write pending_clients_notifications.log');
        } else {
            error_log('[register_client] Log dir not writable or missing: ' . $logDir);
        }
    } catch (Throwable $t) {
        error_log('[register_client] logging failure: ' . $t->getMessage());
    }

    http_response_code(201);
    echo json_encode(['success'=>true,'client_id'=>$clientId,'session_token'=>$sessionToken,'expires_at'=>strtotime($sessExp),'message'=>'Registro creado, pendiente de aprobación por un administrador']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno','error'=>$e->getMessage()]);
    exit;
}

?>
