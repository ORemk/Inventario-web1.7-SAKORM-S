<?php
require_once __DIR__ . '/_api_bootstrap.php';
// api/upload.php - simple image upload endpoint
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

try {
    if (!isset($_FILES) || !isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No se recibió archivo']);
        exit;
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Error en la subida: ' . $file['error']]);
        exit;
    }

    // Basic mime/type validation
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($mime, $allowed)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido']);
        exit;
    }

    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
            error_log('[upload] failed to create uploads dir: ' . $uploadsDir);
        }
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/','', pathinfo($file['name'], PATHINFO_FILENAME));
    if (!$safeBase) $safeBase = 'img';
    $filename = $safeBase . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . ($ext ?: 'bin');
    $dest = $uploadsDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudo mover el archivo']);
        exit;
    }

    echo json_encode(['success' => true, 'filename' => $filename]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

?>
