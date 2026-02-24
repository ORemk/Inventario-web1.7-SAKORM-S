<?php
// api/admin/register_admin.php
require_once __DIR__ . '/../_api_bootstrap.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// require JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido: se esperaba JSON']);
    exit;
}

$nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
$apellido_paterno = isset($data['apellido_paterno']) ? trim($data['apellido_paterno']) : '';
$apellido_materno = isset($data['apellido_materno']) ? trim($data['apellido_materno']) : '';
$username = isset($data['username']) ? trim($data['username']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) ? $data['password'] : '';
$admin_id = isset($data['admin_id']) ? trim($data['admin_id']) : null;
$registered_at = isset($data['registered_at']) ? $data['registered_at'] : null;
$phone = isset($data['phone']) ? trim($data['phone']) : '';

// Require password and at least 3 non-empty identifying fields among username, nombre, apellidos, email, phone
$identFields = [$username, $nombre, $apellido_paterno, $apellido_materno, $email, $phone];
$nonEmpty = 0;
foreach ($identFields as $f) { if (strlen(trim((string)$f))>0) $nonEmpty++; }
if (!$password || $nonEmpty < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Se requiere contraseña y al menos 3 campos entre nombre, apellidos, usuario, correo o teléfono']);
    exit;
}
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

try {
    $dbFile = __DIR__ . '/../../src/config/Database.php';
    if (!file_exists($dbFile)) throw new Exception('Database configuration missing');
    require_once $dbFile;
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Only a master (is_superadmin=1) may create administrators.
    // Allow if current session user is superadmin OR if a short-lived verified_master flag exists in session.
    $allowed = false;
    $currentEmail = null;
    if (isset($_SESSION['user']['email'])) {
        $currentEmail = $_SESSION['user']['email'];
        $chk = $pdo->prepare('SELECT is_superadmin FROM usuarios WHERE email = ? LIMIT 1');
        $chk->execute([$currentEmail]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if ($row && intval($row['is_superadmin']) === 1) $allowed = true;
    }
    // Check verified_master marker (set by api/admin/verify_master.php) within last 5 minutes
    if (!$allowed && isset($_SESSION['verified_master']) && is_array($_SESSION['verified_master'])) {
        $vm = $_SESSION['verified_master'];
        if (!empty($vm['email']) && !empty($vm['time']) && (time() - intval($vm['time']) <= 300)) {
            // verify that the email in DB still has superadmin flag
            $chk = $pdo->prepare('SELECT is_superadmin FROM usuarios WHERE email = ? LIMIT 1');
            $chk->execute([$vm['email']]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if ($row && intval($row['is_superadmin']) === 1) {
                $allowed = true;
                $currentEmail = $vm['email'];
            }
        }
    }
    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Solo el administrador maestro puede crear administradores']);
        exit;
    }

    // Check duplicates by email or username
    $stmt = $pdo->prepare('SELECT id, email, username FROM usuarios WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([$email, $username]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Usuario o email ya existe']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $registered_at = $registered_at ?: $now;
    $admin_id = $admin_id ?: bin2hex(random_bytes(12));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insertSql = 'INSERT INTO usuarios (admin_id, username, nombre, apellido_paterno, apellido_materno, phone, email, password, role, is_admin, is_superadmin, created_at, registered_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $stmt = $pdo->prepare($insertSql);
    // new admins are not superadmins by default
    $ok = $stmt->execute([$admin_id, $username, $nombre, $apellido_paterno, $apellido_materno, $phone, $email, $passwordHash, 'admin', 1, 0, $now, $registered_at]);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo crear el administrador']);
        exit;
    }

    // Do not overwrite current session (master). Return success.
    // Use a relative redirect so the frontend can resolve it using the current base path.
    echo json_encode(['success' => true, 'message' => 'Administrador creado', 'redirect' => 'index.html']);
    exit;

} catch (Exception $e) {
    error_log('[register_admin] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
    exit;
}

?>
