<?php
require_once __DIR__ . '/_api_bootstrap.php';
// debug_last_request.php - Endpoint temporal para devolver la última entrada de logs/api_raw.log
// Uso: GET /api/debug_last_request.php

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
// Restringir acceso a localhost por seguridad (temporal)
$allowed = ['127.0.0.1', '::1'];
if (!in_array($remote, $allowed, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access restricted to localhost', 'your_ip' => $remote]);
    exit;
}

$logFile = __DIR__ . '/../logs/api_raw.log';
if (!file_exists($logFile) || !is_readable($logFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Log file not found', 'path' => $logFile]);
    exit;
}

$contents = file_get_contents($logFile);
$contents = trim($contents);
if ($contents === '') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Log file is empty']);
    exit;
}

// Entradas separadas por "----\n" (como lo escribe productos.php)
$entries = preg_split('/\n----\n/', $contents);
$last = array_pop($entries);
if (!$last) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'No log entries found']);
    exit;
}

// Intentar parsear metadatos y headers
$lines = explode("\n", $last);
$firstLine = array_shift($lines);
$meta = [];
if (preg_match('/^(?<ts>[^\t]+)\tIP:(?<ip>[^\t]+)\tURI:(?<uri>[^\t]+)\tLEN:(?<len>\d+)/', $firstLine, $m)) {
    $meta['timestamp'] = $m['ts'];
    $meta['ip'] = $m['ip'];
    $meta['uri'] = $m['uri'];
    $meta['len'] = (int)$m['len'];
} else {
    $meta['first_line'] = $firstLine;
}

$rest = implode("\n", $lines);
$headers = null;
$raw = null;
if (preg_match('/HEADERS:(?<h>\{[\s\S]*?\})\nRAW:(?<r>[\s\S]*)$/', $rest, $m2)) {
    $headers = json_decode($m2['h'], true);
    $raw = $m2['r'];
} else {
    // Fallback: buscar HEADERS y RAW por separado
    if (preg_match('/HEADERS:(?<h>\{[\s\S]*?\})/', $rest, $m3)) {
        $headers = json_decode($m3['h'], true);
        $pos = strpos($rest, 'RAW:');
        if ($pos !== false) $raw = substr($rest, $pos + 4);
    } else {
        $raw = $rest;
    }
}

// Acortar RAW para evitar responses gigantes
$rawPreview = $raw !== null ? (mb_substr($raw, 0, 2000)) : null;

echo json_encode(['success' => true, 'meta' => $meta, 'headers' => $headers, 'raw' => $rawPreview]);
exit;
