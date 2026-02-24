<?php
require_once __DIR__ . '/_api_bootstrap.php';
/**
 * api/ai_import_docs.php
 * Ejecuta la indexación de docs/*.md desde el navegador (localhost only)
 */
header('Content-Type: application/json; charset=utf-8');
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1','::1','::ffff:127.0.0.1'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso restringido a localhost']);
    exit;
}

$base = __DIR__ . '/../';
$docsDir = realpath($base . 'docs');
if (!$docsDir) {
    echo json_encode(['success' => false, 'error' => 'docs/ no encontrada']); exit;
}

$files = glob($docsDir . '/*.md');
require_once __DIR__ . '/../src/config/Database.php';
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>'DB connect failed: ' . $e->getMessage()]); exit;
}

$processed = 0; $errors = [];
foreach ($files as $f) {
    $content = file_get_contents($f);
    $title = basename($f);
    if (preg_match('/^#\s+(.+)$/m', $content, $m)) $title = trim($m[1]);
    $excerpt = substr(strip_tags($content), 0, 800);
    $path = str_replace($base, '', $f);

    $existing = $db->fetch("SELECT id FROM ai_docs WHERE path = ? LIMIT 1", [$path]);
    if (!empty($existing) && isset($existing['id'])) {
        $id = $existing['id'];
        $ok = $db->execute("UPDATE ai_docs SET title=?, content=?, excerpt=?, updated_at=NOW() WHERE id=?", [$title, $content, $excerpt, $id]);
        if ($ok) $processed++; else $errors[] = 'update_failed';
    } else {
        $ok = $db->execute("INSERT INTO ai_docs (title, path, content, excerpt) VALUES (?,?,?,?)", [$title, $path, $content, $excerpt]);
        if ($ok) $processed++; else $errors[] = 'insert_failed';
    }
}

echo json_encode(['success'=>true,'processed'=>$processed,'errors'=>$errors]);
exit;
?>