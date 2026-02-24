<?php
require_once __DIR__ . '/_api_bootstrap.php';
// API REST productos.php

// Salida limpia para asegurar JSON válido incluso en caso de warnings/notices
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ob_get_length()) ob_end_clean();
    exit;
}

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
    http_response_code(500);
    $errMsg = (isset($dbConnectionError) && !empty($dbConnectionError)) ? 'No hay conexión a la base de datos: ' . substr($dbConnectionError,0,200) : 'No hay conexión a la base de datos';
    echo json_encode(['success'=>false,'error'=>$errMsg]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        // Soporte de búsqueda en tiempo real: ?q=termino
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql = 'SELECT p.*, c.nombre AS categoria_nombre FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.nombre LIKE ? OR p.codigo LIKE ? ORDER BY p.nombre ASC';
            $stmt = $conn->prepare($sql);
            $stmt->execute([$like, $like]);
            $productos = $stmt->fetchAll();
        } else {
            $sql = 'SELECT p.*, c.nombre AS categoria_nombre FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id ORDER BY p.nombre ASC';
            $stmt = $conn->query($sql);
            $productos = $stmt->fetchAll();
        }
        echo json_encode(['success'=>true,'data'=>$productos]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        // Registrar el payload y cabeceras para ayudar debugging
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                error_log('productos.php: Failed to create log dir: ' . $logDir);
            }
        }
        $logFile = $logDir . '/api_raw.log';
        $entry = date('Y-m-d H:i:s') . "\tIP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\tURI:" . ($_SERVER['REQUEST_URI'] ?? '') . "\tLEN:" . strlen($raw) . "\nHEADERS:" . json_encode(getallheaders()) . "\nRAW:" . substr($raw,0,2000) . "\n----\n";
        if (is_dir($logDir) && is_writable($logDir)) {
            $res = file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            if ($res === false) {
                error_log('productos.php: Failed to write API raw log to ' . $logFile . ' EntryLen:' . strlen($entry));
            }
        } else {
            error_log('productos.php: Log dir not writable or missing: ' . $logDir);
        }

        if (ob_get_length()) ob_end_clean();
        echo json_encode(['success'=>false,'error'=>'Invalid JSON: '.json_last_error_msg(),'raw'=>substr($raw,0,1000)]);
        exit;
    }
    if (!isset($input['nombre'],$input['precio'],$input['cantidad'])) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Faltan campos obligatorios','raw'=>substr($raw,0,500)]);
        exit;
    }
    try {
        // Prevent duplicates: same nombre or same codigo
        $checkSql = 'SELECT id FROM productos WHERE LOWER(nombre) = LOWER(?)';
        $checkParams = [$input['nombre']];
        if (!empty($input['codigo'])) {
            $checkSql .= ' OR codigo = ?';
            $checkParams[] = $input['codigo'];
        }
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute($checkParams);
        $exists = $checkStmt->fetch();
        if ($exists) {
            http_response_code(409);
            echo json_encode(['success'=>false,'error'=>'Producto duplicado: ya existe un producto con ese nombre o código']);
            exit;
        }
        $sql = 'INSERT INTO productos (nombre, precio, cantidad, categoria_id) VALUES (?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $input['nombre'],
            $input['precio'],
            $input['cantidad'],
            $input['categoria_id'] ?? null
        ]);
        $input['id'] = $conn->lastInsertId();
        echo json_encode(['success'=>true,'data'=>$input]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    // Allow id to be provided via query parameter for compatibility
    if ((!isset($input['id']) || !$input['id']) && isset($_GET['id'])) {
        $input['id'] = $_GET['id'];
    }
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'ID requerido']);
        exit;
    }

    // Basic validation: require at least nombre and categoria_id for updates
    if (!isset($input['nombre']) || !isset($input['categoria_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Campos obligatorios faltantes: nombre y categoria_id']);
        exit;
    }

    try {
        // Update all relevant fields so the frontend can edit codigo, costo, fecha_caducidad, imagen, etc.
        $sql = 'UPDATE productos SET codigo = ?, nombre = ?, categoria_id = ?, cantidad = ?, costo = ?, precio = ?, fecha_caducidad = ?, imagen = ? WHERE id = ?';
        $stmt = $conn->prepare($sql);

        $params = [
            $input['codigo'] ?? null,
            $input['nombre'] ?? null,
            $input['categoria_id'] ?? null,
            $input['cantidad'] ?? null,
            $input['costo'] ?? null,
            $input['precio'] ?? null,
            $input['fecha_caducidad'] ?? null,
            $input['imagen'] ?? null,
            $input['id']
        ];

        // Retry loop to handle transient lock wait timeouts (MySQL 1205 / InnoDB locks)
        $maxRetries = 3;
        $attempt = 0;
        while (true) {
            try {
                $stmt->execute($params);
                break; // Success
            } catch (PDOException $e) {
                $attempt++;
                $msg = $e->getMessage();
                $codeNum = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : null;
                // Detect Lock wait timeout or deadlock patterns
                if ($e->getCode() === 'HY000' && ($codeNum === 1205 || stripos($msg, 'Lock wait timeout') !== false || stripos($msg, 'deadlock') !== false)) {
                    if ($attempt >= $maxRetries) {
                        // Log details for debugging
                        $logDir = __DIR__ . '/../logs';
                        if (!is_dir($logDir)) {
                            if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                                error_log('productos.php: Failed to create log dir: ' . $logDir);
                            }
                        }
                        $logFile = $logDir . '/db_locks.log';
                        $entry = date('Y-m-d H:i:s') . "\tUPDATE_LOCK_TIMEOUT\tID:" . ($input['id'] ?? '') . "\tTRY:$attempt\tMSG:" . substr($msg,0,2000) . "\n";
                        if (is_dir($logDir) && is_writable($logDir)) {
                            $res2 = file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
                            if ($res2 === false) {
                                error_log('productos.php: Failed to write DB lock log to ' . $logFile . ' EntryLen:' . strlen($entry));
                            }
                        } else {
                            error_log('productos.php: Log dir not writable or missing: ' . $logDir);
                        }
                        throw $e; // rethrow after exhausting retries
                    }
                    // short exponential backoff
                    usleep(200000 * $attempt);
                    continue;
                }
                throw $e; // non-lock error
            }
        }

        echo json_encode(['success'=>true,'data'=>$input]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
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

    // Special case: delete ALL products (and dependent salidas)
    if ($id === 'all') {
        try {
            $conn->beginTransaction();
            // First remove dependent records that reference productos
            $conn->exec('DELETE FROM salidas');
            // Then remove products
            $conn->exec('DELETE FROM productos');
            $conn->commit();
            echo json_encode(['success'=>true,'deleted'=>'all']);
        } catch (PDOException $e) {
            $conn->rollBack();
            http_response_code(500);
            $msg = $e->getMessage();
            if (strpos($e->getCode(),'23000') !== false || stripos($msg,'foreign key') !== false) {
                echo json_encode(['success'=>false,'error'=>'No se puede eliminar todo: existen registros relacionados en otras tablas.']);
            } else {
                echo json_encode(['success'=>false,'error'=>$msg]);
            }
        } catch (Exception $e) {
            $conn->rollBack();
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
        exit;
    }

    try {
        $sql = 'DELETE FROM productos WHERE id=?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        echo json_encode(['success'=>true,'id'=>$id]);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Detect FK violation (SQLSTATE 23000) and return friendlier message
        if (strpos($e->getCode(),'23000') !== false || stripos($msg,'foreign key') !== false) {
            http_response_code(409);
            echo json_encode(['success'=>false,'error'=>'No se puede eliminar el producto: existen registros relacionados en otras tablas (ventas/salidas).']);
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
