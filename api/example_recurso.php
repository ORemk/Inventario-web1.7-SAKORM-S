<?php
require_once __DIR__ . '/_api_bootstrap.php';
// api/example_recurso.php - Ejemplo de endpoint siguiendo el patrón del proyecto

// Seguridad y salida limpia
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // cerrar buffer y salir
    if (ob_get_length()) ob_end_clean();
    exit;
}

// Convertir warnings/notice en respuesta JSON inmediata
set_error_handler(function($errno, $errstr, $errfile, $errline){
    http_response_code(500);
    if (ob_get_length()) ob_end_clean();
    echo json_encode(['success' => false, 'error' => "PHP Error: $errstr"]);
    exit;
});
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        if (ob_get_length()) ob_end_clean();
        echo json_encode(['success' => false, 'error' => $err['message']]);
        exit;
    }
});

require_once __DIR__ . '/../db.php';
if (!isset($conn) || !$conn) {
    if (ob_get_length()) ob_end_clean();
    http_response_code(500);
    $errMsg = (isset($dbConnectionError) && !empty($dbConnectionError)) ? 'No hay conexión a la base de datos: ' . substr($dbConnectionError,0,200) : 'No hay conexión a la base de datos';
    echo json_encode(['success' => false, 'error' => $errMsg]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

function send_json($payload, $code = 200) {
    if (ob_get_length()) ob_end_clean();
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// Nota: este archivo es ilustrativo. Use `api/productos.php` o `api/categorias.php` como referencia real.

if ($method === 'GET') {
    try {
        $stmt = $conn->query('SELECT * FROM ejemplo_recurso ORDER BY id DESC');
        $rows = $stmt->fetchAll();
        send_json(['success' => true, 'data' => $rows]);
    } catch (Exception $e) {
        send_json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_json(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
    }
    if (!isset($input['nombre']) || trim($input['nombre']) === '') {
        send_json(['success' => false, 'error' => 'Nombre requerido'], 400);
    }
    try {
        $sql = 'INSERT INTO ejemplo_recurso (nombre, created_at) VALUES (?, NOW())';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$input['nombre']]);
        $input['id'] = $conn->lastInsertId();
        $input['created_at'] = date('Y-m-d H:i:s');
        send_json(['success' => true, 'data' => $input], 201);
    } catch (Exception $e) {
        send_json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($method === 'PUT') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_json(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
    }
    if (!isset($input['id']) || !isset($input['nombre'])) {
        send_json(['success' => false, 'error' => 'ID y nombre requeridos'], 400);
    }
    try {
        $sql = 'UPDATE ejemplo_recurso SET nombre=? WHERE id=?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$input['nombre'], $input['id']]);
        send_json(['success' => true, 'data' => $input]);
    } catch (Exception $e) {
        send_json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        send_json(['success' => false, 'error' => 'ID requerido'], 400);
    }
    try {
        $sql = 'DELETE FROM ejemplo_recurso WHERE id=?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        send_json(['success' => true, 'id' => $id]);
    } catch (Exception $e) {
        send_json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

send_json(['success' => false, 'error' => 'Método no permitido'], 405);
