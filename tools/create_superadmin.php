<?php
// tools/create_superadmin.php
// Creates or updates a superadmin user for testing. Adjust $email and $password below.
require_once __DIR__ . '/../src/config/Database.php';

$email = 'admin@local';
$password = 'admin';
$username = 'admin';
$nombre = 'Admin';
$admin_id = 'master-admin-001';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Check if user exists by email or username
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([$email, $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && isset($row['id'])) {
        $id = $row['id'];
        $upd = $pdo->prepare('UPDATE usuarios SET password = ?, is_admin = 1, is_superadmin = 1, role = ?, admin_id = ?, updated_at = NOW() WHERE id = ?');
        $upd->execute([$hash, 'admin', $admin_id, $id]);
        echo "Updated existing user id={$id}\n";
    } else {
        $ins = $pdo->prepare('INSERT INTO usuarios (admin_id, username, nombre, email, password, role, is_admin, is_superadmin, registered_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())');
        $ins->execute([$admin_id, $username, $nombre, $email, $hash, 'admin']);
        $id = $pdo->lastInsertId();
        echo "Inserted superadmin id={$id}\n";
    }

    echo "Superadmin ready: {$email} / {$password}\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
