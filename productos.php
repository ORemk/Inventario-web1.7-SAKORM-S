<?php
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
/**
 * productos.php - proxy
 * Implementación canónica en `api/productos.php`.
 * Este archivo se mantiene como proxy por compatibilidad hacia atrás.
 */
require_once __DIR__ . '/api/productos.php';
exit;

// Proxy-only: la implementación canónica está en `api/productos.php`.
// EOF - productos.php


function validarProducto($data, $esActualizacion = false) {
    $errors = [];
    
    if (empty($data['nombre'])) {
        $errors[] = 'El nombre del producto es requerido';
    } elseif (strlen($data['nombre']) > 150) {
        $errors[] = 'El nombre no puede exceder 150 caracteres';
    }
    
    if (empty($data['categoria_id']) && !$esActualizacion) {
        $errors[] = 'La categoría es requerida';
    }
    
    if (isset($data['cantidad'])) {
        if (!is_numeric($data['cantidad']) || (int)$data['cantidad'] < 0) {
            $errors[] = 'La cantidad debe ser un número no negativo';
        }
    }
    
    if (isset($data['precio'])) {
        if (!is_numeric($data['precio']) || (float)$data['precio'] < 0) {
            $errors[] = 'El precio debe ser un número no negativo';
        }
    }

    if (isset($data['costo'])) {
        if (!is_numeric($data['costo']) || (float)$data['costo'] < 0) {
            $errors[] = 'El costo debe ser un número no negativo';
        }
    }
    
    return $errors;
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Obtener todos los productos
        try {
            $query = "SELECT id, codigo, nombre, categoria_id, cantidad, costo, precio, fecha_caducidad, created_at, imagen FROM productos ORDER BY nombre ASC";
            $result = sqlQuery($query);
            
            $productos = [];
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $productos[] = $row;
            }
            sqlsrv_free_stmt($result);
            
            echo json_encode(['success' => true, 'data' => $productos]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'POST':
        // Crear producto
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar entrada
            $errors = validarProducto($data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                break;
            }
            
            $codigo = $data['codigo'] ?? '';
            $nombre = $data['nombre'];
            $categoria_id = (int)($data['categoria_id'] ?? 0);
            $cantidad = (int)($data['cantidad'] ?? 0);
            $costo = (float)($data['costo'] ?? 0);
            $precio = (float)($data['precio'] ?? 0);
            $fecha_caducidad = $data['fecha_caducidad'] ?? null;
            $imagen = $data['imagen'] ?? null;

            $query = "INSERT INTO productos (codigo, nombre, categoria_id, cantidad, costo, precio, fecha_caducidad, imagen) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?); SELECT @@IDENTITY as id";
            
            $params = array(&$codigo, &$nombre, &$categoria_id, &$cantidad, &$costo, &$precio, &$fecha_caducidad, &$imagen);
            $stmt = sqlQuery($query, $params);
            
            $last_id = null;
            while (sqlsrv_next_result($stmt)) {
                if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $last_id = $row['id'];
                }
            }
            sqlsrv_free_stmt($stmt);
            
            echo json_encode([
                'success' => true,
                'id' => $last_id,
                'message' => 'Producto creado exitosamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Actualizar producto
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            
            // Validar entrada
            $errors = validarProducto($data, true);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                break;
            }
            
            $id = (int)$data['id'];
            $codigo = $data['codigo'] ?? '';
            $nombre = $data['nombre'] ?? '';
            $categoria_id = (int)($data['categoria_id'] ?? 0);
            $cantidad = (int)($data['cantidad'] ?? 0);
            $costo = (float)($data['costo'] ?? 0);
            $precio = (float)($data['precio'] ?? 0);
            $fecha_caducidad = $data['fecha_caducidad'] ?? null;
            $imagen = $data['imagen'] ?? null;

            $query = "UPDATE productos SET codigo = ?, nombre = ?, categoria_id = ?, cantidad = ?, costo = ?, precio = ?, fecha_caducidad = ?, imagen = ? WHERE id = ?";
            
            $params = array(&$codigo, &$nombre, &$categoria_id, &$cantidad, &$costo, &$precio, &$fecha_caducidad, &$imagen, &$id);
            $stmt = sqlQuery($query, $params);
            sqlsrv_free_stmt($stmt);
            
            echo json_encode(['success' => true, 'message' => 'Producto actualizado']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Eliminar producto(s)
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['all']) && $data['all'] === true) {
                // Eliminar todos
                $stmt = sqlQuery("DELETE FROM productos");
                sqlsrv_free_stmt($stmt);
                
                echo json_encode([
                    'success' => true,
                    'all_deleted' => true,
                    'message' => 'Todos los productos fueron eliminados'
                ]);
            } else {
                // Eliminar uno
                if (empty($data['id'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID requerido']);
                    break;
                }
                
                $id = (int)$data['id'];
                $query = "DELETE FROM productos WHERE id = ?";
                $params = array(&$id);
                $stmt = sqlQuery($query, $params);
                sqlsrv_free_stmt($stmt);
                
                echo json_encode(['success' => true, 'message' => 'Producto eliminado']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

?>

