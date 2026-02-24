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
        $stmt = $conn->query('SELECT * FROM ventas ORDER BY id DESC');
        $ventas = $stmt->fetchAll();
        echo json_encode(['success'=>true,'data'=>$ventas]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['cliente_id']) || !isset($input['total'])) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'cliente_id y total son obligatorios']);
        exit;
    }
    try {
        $sql = 'INSERT INTO ventas (cliente_id, fecha, total) VALUES (?, NOW(), ?)';
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $input['cliente_id'],
            $input['total']
        ]);
        $input['id'] = $conn->lastInsertId();
        $input['fecha'] = date('Y-m-d H:i:s');
        echo json_encode(['success'=>true,'data'=>$input]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ((!isset($input['id']) || !$input['id']) && isset($_GET['id'])) { $input['id'] = $_GET['id']; }
    if (!isset($input['id']) || !isset($input['total'])) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'ID y total requeridos']);
        exit;
    }
    try {
        $sql = 'UPDATE ventas SET cliente_id=?, total=? WHERE id=?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $input['cliente_id'],
            $input['total'],
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
        $sql = 'DELETE FROM ventas WHERE id=?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        echo json_encode(['success'=>true,'id'=>$id]);    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($e->getCode(),'23000') !== false || stripos($msg,'foreign key') !== false) {
            http_response_code(409);
            echo json_encode(['success'=>false,'error'=>'No se puede eliminar la venta: existe integridad referencial que lo impide']);
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
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Demo: siempre retorna un array
    $demo = [
        ["id"=>1, "cliente"=>"Juan Pérez", "total"=>1200, "fecha"=>"2026-01-20"],
        ["id"=>2, "cliente"=>"Ana López", "total"=>350, "fecha"=>"2026-01-22"]
    ];
    if (!is_array($demo)) $demo = [];
    echo json_encode(["success"=>true, "data"=>$demo]);
    exit;
}
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $input['id'] = rand(100,999);
    echo json_encode(["success"=>true, "data"=>$input]);
    exit;
}
if ($method === 'DELETE') {
    echo json_encode(["success"=>true, "deleted"=>true]);
    exit;
}
echo json_encode(["success"=>false, "error"=>"Método no permitido"]);