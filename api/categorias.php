<?php
require_once __DIR__ . '/_api_bootstrap.php';
// API REST categorias.php

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
        $stmt = $conn->query('SELECT * FROM categorias ORDER BY id DESC');
        $categorias = $stmt->fetchAll();
        echo json_encode(['success'=>true,'data'=>$categorias]);
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
        echo json_encode(['success'=>false,'error'=>'El nombre de la categoría es obligatorio']);
        exit;
    }
    try {
        // Prevent duplicate category names (case-insensitive)
        $name = trim($input['nombre']);
        $chk = $conn->prepare('SELECT id FROM categorias WHERE LOWER(nombre) = LOWER(?)');
        $chk->execute([$name]);
        if ($chk->fetch()) {
            http_response_code(409);
            echo json_encode(['success'=>false,'error'=>'Categoría duplicada: ya existe una categoría con ese nombre']);
            exit;
        }
        $sql = 'INSERT INTO categorias (nombre, created_at) VALUES (?, NOW())';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$input['nombre']]);
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
    if ((!isset($input['id']) || !$input['id']) && isset($_GET['id'])) { $input['id'] = $_GET['id']; }
    if (!isset($input['id']) || !isset($input['nombre'])) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'ID y nombre requeridos']);
        exit;
    }
    try {
        $sql = 'UPDATE categorias SET nombre=? WHERE id=?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$input['nombre'], $input['id']]);
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
        $sql = 'DELETE FROM categorias WHERE id=?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        echo json_encode(['success'=>true,'id'=>$id]);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($e->getCode(),'23000') !== false || stripos($msg,'foreign key') !== false) {
            http_response_code(409);
            echo json_encode(['success'=>false,'error'=>'No se puede eliminar la categoría: existen productos asociados']);
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
