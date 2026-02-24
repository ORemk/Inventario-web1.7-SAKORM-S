<?php
require_once __DIR__ . '/_api_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../db.php';
if (!isset($conn) || !$conn) {
    http_response_code(500);
    $errMsg = (isset($dbConnectionError) && !empty($dbConnectionError)) ? 'No hay conexión a la base de datos: ' . substr($dbConnectionError,0,200) : 'No hay conexión a la base de datos';
    echo json_encode(['success'=>false,'error'=>$errMsg]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $conn->query('SELECT * FROM clientes ORDER BY id DESC');
        $clientes = $stmt->fetchAll();
        echo json_encode(['success'=>true,'data'=>$clientes]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['nombre']) || trim($input['nombre']) === '') {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'El nombre es obligatorio']);
        exit;
    }
    try {
        // Prevent duplicates: prefer checking email (if provided), otherwise name
        $nombre = trim($input['nombre']);
        $email = isset($input['email']) ? trim($input['email']) : '';
        if ($email !== '') {
            $chk = $conn->prepare('SELECT id FROM clientes WHERE LOWER(email) = LOWER(?)');
            $chk->execute([$email]);
            if ($chk->fetch()) {
                http_response_code(409);
                echo json_encode(['success'=>false,'error'=>'Cliente duplicado: ya existe un cliente con ese email']);
                exit;
            }
        } else {
            $chk = $conn->prepare('SELECT id FROM clientes WHERE LOWER(nombre) = LOWER(?)');
            $chk->execute([$nombre]);
            if ($chk->fetch()) {
                http_response_code(409);
                echo json_encode(['success'=>false,'error'=>'Cliente duplicado: ya existe un cliente con ese nombre']);
                exit;
            }
        }
        $sql = 'INSERT INTO clientes (nombre, email, telefono, direccion, created_at) VALUES (?, ?, ?, ?, NOW())';
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $input['nombre'],
            $input['email'] ?? null,
            $input['telefono'] ?? null,
            $input['direccion'] ?? null
        ]);
        $input['id'] = $conn->lastInsertId();
        $input['created_at'] = date('Y-m-d H:i:s');
        echo json_encode(['success'=>true,'data'=>$input]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    // Compatibilidad: aceptar id desde query param si falta en el cuerpo
    if ((!isset($input['id']) || !$input['id']) && isset($_GET['id'])) {
        $input['id'] = $_GET['id'];
    }
    if (!isset($input['id']) || !isset($input['nombre'])) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'ID y nombre requeridos']);
        exit;
    }
    try {
        $sql = 'UPDATE clientes SET nombre=?, email=?, telefono=?, direccion=? WHERE id=?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $input['nombre'],
            $input['email'] ?? null,
            $input['telefono'] ?? null,
            $input['direccion'] ?? null,
            $input['id']
        ]);
        echo json_encode(['success'=>true,'data'=>$input]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'ID requerido']);
        exit;
    }
    try {
        $sql = 'DELETE FROM clientes WHERE id=?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        echo json_encode(['success'=>true,'id'=>$id]);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($e->getCode(),'23000') !== false || stripos($msg,'foreign key') !== false) {
            http_response_code(409);
            echo json_encode(['success'=>false,'error'=>'No se puede eliminar el cliente: existen registros relacionados (ventas)']);
        } else {
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>$msg]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}
http_response_code(405);
echo json_encode(['success'=>false,'error'=>'Método no permitido']);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$demo = [
    ['id'=>1,'nombre'=>'Juan Pérez','email'=>'juan@example.com','telefono'=>'5551234567','ciudad'=>'Madrid'],
    ['id'=>2,'nombre'=>'María García','email'=>'maria@example.com','telefono'=>'5552345678','ciudad'=>'Barcelona'],
    ['id'=>3,'nombre'=>'Carlos López','email'=>'carlos@example.com','telefono'=>'5553456789','ciudad'=>'Valencia'],
];
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    echo json_encode(['success'=>true,'data'=>$demo,'demo'=>true]);
    exit;
}
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $input['id'] = rand(100,999);
    echo json_encode(['success'=>true,'data'=>$input,'demo'=>true]);
    exit;
}
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    echo json_encode(['success'=>true,'data'=>$input,'demo'=>true]);
    exit;
}
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    echo json_encode(['success'=>true,'id'=>$id,'demo'=>true]);
    exit;
}
http_response_code(405);
echo json_encode(['success'=>false,'error'=>'Método no permitido']);
