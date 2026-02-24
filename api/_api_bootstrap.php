<?php
// api/_api_bootstrap.php
// Global initializer for API endpoints. Ensures JSON responses and converts
// PHP warnings/notices/fatals into JSON error responses to avoid HTML injection.

// Environment detection: allow APP_ENV=development or API_DEV=1 to enable verbose output
$env = getenv('APP_ENV') ?: getenv('API_ENV');
$devFlag = getenv('API_DEV');
$isDev = false;
if ($env && strtolower($env) === 'development') $isDev = true;
if ($devFlag && ($devFlag === '1' || strtolower($devFlag) === 'true')) $isDev = true;

// Disable direct display of PHP errors by default; in dev we still keep them off
// but include error details in JSON responses via handlers below.
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Ensure JSON Content-Type unless the script overrides deliberately
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Convert PHP warnings/notices to ErrorException so they become catchable
set_error_handler(function($severity, $message, $file, $line) {
    // Respect @ operator: if error_reporting() is 0, ignore
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($ex) use ($isDev) {
    if (!headers_sent()) http_response_code(500);
    $payload = ['success' => false, 'message' => 'Internal server error'];
    if ($isDev) {
        $payload['error'] = $ex->getMessage();
        $payload['type'] = get_class($ex);
        $payload['file'] = $ex->getFile();
        $payload['line'] = $ex->getLine();
        $payload['trace'] = $ex->getTraceAsString();
    }
    try {
        echo json_encode($payload);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>'Internal server error']);
    }
    exit;
});

register_shutdown_function(function() use ($isDev) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) http_response_code(500);
        $payload = ['success' => false, 'message' => 'Fatal error'];
        if ($isDev) {
            $payload['error'] = $err['message'];
            $payload['file'] = $err['file'];
            $payload['line'] = $err['line'];
        }
        echo json_encode($payload);
        exit;
    }
});

// Small helper to send a JSON error and exit
function api_json_error($message, $code = 400, $details = null) {
    if (!headers_sent()) http_response_code($code);
    $payload = ['success' => false, 'message' => $message];
    if ($details) $payload['details'] = $details;
    echo json_encode($payload);
    exit;
}

// End bootstrap
?>
