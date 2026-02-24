<?php
// api/admin/verify_master.php
// Verifica credenciales de administrador "master" para autorizar acciones sensibles.
require_once __DIR__ . '/../_api_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
// start session so we can store a short-lived verified master marker
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido: se esperaba JSON']);
    exit;
}

$email = isset($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) ? $data['password'] : '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email y password son requeridos']);
    exit;
}

$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
        error_log('verify_master.php: failed to create log dir: ' . $logDir);
    }
}
$logFile = $logDir . '/verify_master.log';

try {
    $dbFile = __DIR__ . '/../../src/config/Database.php';
    if (file_exists($dbFile)) {
        require_once $dbFile;
        $db = Database::getInstance();
        $pdo = $db->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $pwFields = ['password','pass','pwd','clave'];
            $stored = null;
            foreach ($pwFields as $f) { if (isset($user[$f])) { $stored = $user[$f]; break; } }

            $ok = false;
            if ($stored !== null) {
                if (password_verify($password, $stored)) $ok = true;
                if (!$ok && hash_equals($stored, $password)) $ok = true;
            }

            // check superadmin flag
            $isSuper = false;
            if (isset($user['is_superadmin']) && intval($user['is_superadmin']) === 1) $isSuper = true;

            // Log attempt
            $entry = date('c') . "\t" . $clientIp . "\t" . $email . "\t" . ($ok ? 'PASSWORD_OK' : 'PASSWORD_FAIL') . "\t" . ($isSuper ? 'IS_SUPER' : 'NOT_SUPER') . "\n";
            if (is_dir($logDir) && is_writable($logDir)) {
                $wr = file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
                if ($wr === false) error_log('verify_master.php: failed to write verify log: ' . $logFile);
            } else {
                error_log('verify_master.php: Log dir not writable or missing: ' . $logDir);
            }

            if ($ok && $isSuper) {
                // mark verified master in session for a short period (5 minutes)
                $expires = time() + 300; // 5 minutes
                $_SESSION['verified_master'] = ['email' => $email, 'time' => time(), 'expires' => $expires];
                echo json_encode(['success' => true, 'message' => 'Credenciales master válidas', 'user' => ['email' => $email, 'is_superadmin' => 1], 'expires_at' => $expires]);
                exit;
            }

            // unauthorized
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No autorizado: se requieren privilegios de administrador master']);
            exit;
        }
    }
} catch (Exception $e) {
    $exEntry = date('c') . "\t" . $clientIp . "\t" . $email . "\tEXCEPTION\t" . $e->getMessage() . "\n";
    if (is_dir($logDir) && is_writable($logDir)) {
        $wr2 = file_put_contents($logFile, $exEntry, FILE_APPEND | LOCK_EX);
        if ($wr2 === false) error_log('verify_master.php: failed to write exception to log: ' . $logFile);
    } else {
        error_log('verify_master.php: Log dir not writable or missing: ' . $logDir);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
    exit;
}

// Fallback local credentials (use only for development)
    if ($email === 'admin@local' && $password === 'admin') {
    $fb = date('c') . "\t" . $clientIp . "\t" . $email . "\tFALLBACK_OK\n";
    if (is_dir($logDir) && is_writable($logDir)) {
        $wr3 = file_put_contents($logFile, $fb, FILE_APPEND | LOCK_EX);
        if ($wr3 === false) error_log('verify_master.php: failed to write fallback ok to log: ' . $logFile);
    } else {
        error_log('verify_master.php: Log dir not writable or missing: ' . $logDir);
    }
    $expires = time() + 300;
    $_SESSION['verified_master'] = ['email' => $email, 'time' => time(), 'expires' => $expires];
    echo json_encode(['success' => true, 'message' => 'Credenciales master válidas (fallback)', 'user' => ['email' => $email, 'is_superadmin' => 1], 'expires_at' => $expires]);
    exit;
}

$nf = date('c') . "\t" . $clientIp . "\t" . $email . "\tNOT_FOUND\n";
if (is_dir($logDir) && is_writable($logDir)) {
    $wr4 = file_put_contents($logFile, $nf, FILE_APPEND | LOCK_EX);
    if ($wr4 === false) error_log('verify_master.php: failed to write not_found to log: ' . $logFile);
} else {
    error_log('verify_master.php: Log dir not writable or missing: ' . $logDir);
}
http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
exit;

?>
