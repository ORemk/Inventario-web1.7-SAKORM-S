<?php
// api/admin/login_admin.php
// Login para administradores. Usa `src/config/Database.php` si está disponible.

require_once __DIR__ . '/../_api_bootstrap.php';
session_start();

// Allow GET to inspect current session (useful for UI to know if current user is superadmin)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
        exit;
    }
    // Return 200 with success:false for unauthenticated GET requests to avoid browser 401 console noise
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

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
// allow optional debug flag in JSON body for local debugging
$debugRequested = false;
if (isset($data['debug']) && ($data['debug'] === true || $data['debug'] === '1' || $data['debug'] === 1)) {
    $debugRequested = true;
}

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email y password son requeridos']);
    exit;
}

try {
    $dbFile = __DIR__ . '/../../src/config/Database.php';
    if (file_exists($dbFile)) {
        require_once $dbFile;
        $db = Database::getInstance();
        $pdo = $db->getConnection();

        // Permitir identificar por email o por username (el campo de entrada acepta "usuario o correo")
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // contraseña
            $pwFields = ['password','pass','pwd','clave'];
            $stored = null;
            foreach ($pwFields as $f) { if (isset($user[$f])) { $stored = $user[$f]; break; } }

            $ok = false;
            if ($stored !== null) {
                if (password_verify($password, $stored)) $ok = true;
                if (!$ok && hash_equals($stored, $password)) $ok = true;
            }

            if (!$ok) {
                // If debug requested from localhost, reveal reason
                $reason = 'invalid_credentials';
                if ($debugRequested && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1','::1','0.0.0.0'])) {
                    $reason = 'bad_password';
                }
                http_response_code(401);
                $resp = ['success' => false, 'message' => 'Credenciales inválidas'];
                if ($debugRequested && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1','::1','0.0.0.0'])) $resp['debug'] = $reason;
                echo json_encode($resp);
                exit;
            }

            // Verificar rol admin en varios nombres comunes
            $isAdmin = false;
            $adminFields = ['role','rol','is_admin','admin'];
            foreach ($adminFields as $af) {
                if (isset($user[$af])) {
                    $v = $user[$af];
                    if (is_numeric($v) && intval($v) === 1) { $isAdmin = true; break; }
                    if (is_string($v) && strtolower(trim($v)) === 'admin') { $isAdmin = true; break; }
                }
            }

            if (!$isAdmin) {
                // debug info only for localhost when requested
                $resp = ['success' => false, 'message' => 'Acceso denegado: no es administrador'];
                if ($debugRequested && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1','::1','0.0.0.0'])) $resp['debug'] = 'not_admin';
                http_response_code(403);
                echo json_encode($resp);
                exit;
            }

            // Éxito
            // incluir bandera is_superadmin en sesión (si existe en fila)
            $sess = ['email' => $email, 'role' => 'admin'];
            if (isset($user['is_superadmin']) && intval($user['is_superadmin']) === 1) $sess['is_superadmin'] = 1;
            $_SESSION['user'] = $sess;
            $userResp = ['email' => $email];
            if (isset($sess['is_superadmin']) && intval($sess['is_superadmin']) === 1) $userResp['is_superadmin'] = 1;
            echo json_encode(['success' => true, 'message' => 'Autenticado como administrador', 'user' => $userResp]);
            exit;
        } else {
            // usuario no encontrado
            if ($debugRequested && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1','::1','0.0.0.0'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado', 'debug' => 'no_user']);
                exit;
            }
        }
    }
} catch (Exception $e) {
    // error de BD/ejecución - solo dar detalles en debug desde localhost
    if ($debugRequested && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1','::1','0.0.0.0'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error interno en login_admin', 'debug' => 'db_error', 'error' => $e->getMessage()]);
        exit;
    }
    // seguir a fallback silencioso
}

// Fallback local
if ($email === 'admin@local' && $password === 'admin') {
    $_SESSION['user'] = ['email' => $email, 'role' => 'admin'];
    echo json_encode(['success' => true, 'message' => 'Autenticado (fallback)', 'user' => ['email' => $email]]);
    exit;
}

http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Credenciales inválidas o no es administrador']);
exit;

?>
