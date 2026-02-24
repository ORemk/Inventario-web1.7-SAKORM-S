<?php
require_once __DIR__ . '/_api_bootstrap.php';
// api/login.php
// Minimal login endpoint to return JSON for the frontend.

// Allow same-origin credentials; adjust if using different host/origin
// header('Access-Control-Allow-Origin: http://localhost');
// header('Access-Control-Allow-Credentials: true');


session_start();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

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
// Intentar usar Database singleton si está disponible
$dbUsed = false;
try {
    $dbFile1 = __DIR__ . '/../src/config/Database.php';
    if (file_exists($dbFile1)) {
        require_once $dbFile1;
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $dbUsed = true;

        // Buscar usuario por email
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Buscar campo de contraseña en nombres comunes
            $pwFields = ['password','pass','pwd','clave'];
            $stored = null;
            foreach ($pwFields as $f) { if (isset($user[$f])) { $stored = $user[$f]; break; } }

            if ($stored !== null) {
                $ok = false;
                // Si parece ser hash compatible con password_verify
                if (password_verify($password, $stored)) {
                    $ok = true;
                } else {
                    // Fallback: comparación literal (inseguro pero útil en datos legacy)
                    if (hash_equals($stored, $password)) $ok = true;
                }

                if ($ok) {
                    // Establecer sesión mínima
                    $_SESSION['user'] = ['email' => $email];
                    echo json_encode(['success' => true, 'message' => 'Autenticado', 'user' => ['email' => $email]]);
                    exit;
                } else {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
                    exit;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log('[api/login.php] DB access failed: ' . $e->getMessage());
    // Fallthrough to fallback creds
}

// Si no se pudo usar DB o no se encontró usuario, usar credencial local de fallback
if ($email === 'admin@local' && $password === 'admin') {
    $_SESSION['user'] = ['email' => $email, 'role' => 'admin'];
    echo json_encode(['success' => true, 'message' => 'Autenticado (fallback)', 'user' => ['email' => $email]]);
    exit;
}

http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
exit;

?>
