<?php
// ui-check.php - Diagnostic helper for js/ui.js truncation issues
// Returns JSON with disk and served versions (length, sha256, EOF marker, tail)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$diskPath = __DIR__ . '/js/ui.js';
$diskExists = file_exists($diskPath);
$diskContent = $diskExists ? file_get_contents($diskPath) : null;
$diskLength = $diskExists ? strlen($diskContent) : 0;
$diskSha256 = $diskExists ? hash('sha256', $diskContent) : null;
$diskHasEOF = $diskExists ? (substr(trim($diskContent), -strlen('// EOF - end of js/ui.js')) === '// EOF - end of js/ui.js') : false;
$diskTail = $diskExists ? implode("\n", array_slice(explode("\n", $diskContent), -20)) : null;

// Construct served URL (same host/path where this script runs)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
$servedUrl = $scheme . '://' . $host . $base . '/js/ui.js';

$served = file_get_contents($servedUrl);
if ($served === false) {
    error_log('[ui-check] failed to fetch ' . $servedUrl);
}
$servedLength = $served !== false ? strlen($served) : null;
$servedSha256 = $served !== false ? hash('sha256', $served) : null;
$servedHasEOF = $served !== false ? (substr(trim($served), -strlen('// EOF - end of js/ui.js')) === '// EOF - end of js/ui.js') : null;
$servedTail = $served !== false ? implode("\n", array_slice(explode("\n", $served), -20)) : null;

echo json_encode([
    'success' => true,
    'disk' => [
        'path' => str_replace('\\', '/', $diskPath),
        'exists' => $diskExists,
        'length' => $diskLength,
        'sha256' => $diskSha256,
        'has_eof_marker' => $diskHasEOF,
        'tail' => $diskTail
    ],
    'served' => [
        'url' => $servedUrl,
        'fetched' => ($served !== false),
        'length' => $servedLength,
        'sha256' => $servedSha256,
        'has_eof_marker' => $servedHasEOF,
        'tail' => $servedTail
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
