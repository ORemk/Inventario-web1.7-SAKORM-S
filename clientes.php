<?php
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
/**
 * clientes.php - proxy
 * Implementación canónica en `api/clientes.php`.
 * Este archivo se mantiene como proxy por compatibilidad hacia atrás.
 */
require_once __DIR__ . '/api/clientes.php';
exit;

// Proxy-only: la implementación canónica está en `api/clientes.php`.
// EOF - clientes.php


switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Obtener todos los clientes
        try {
            $query = "SELECT id, nombre, email, telefono, created_at FROM clientes ORDER BY nombre ASC";
            $result = executeQuery($query);
            
            $clientes = fetchAll($result);
            
            echo json_encode(['success' => true, 'data' => $clientes]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'POST':
        // Crear cliente
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar entrada
            $errors = validarCliente($data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                break;
            }
            
            $nombre = $data['nombre'];
            $email = $data['email'] ?? '';
            $telefono = $data['telefono'] ?? '';
            
            $query = "INSERT INTO clientes (nombre, email, telefono) VALUES (?, ?, ?); SELECT @@IDENTITY as id";
            $params = array(&$nombre, &$email, &$telefono);
            $stmt = executeQuery($query, $params);
            
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
                'message' => 'Cliente creado exitosamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Actualizar cliente
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            
            // Validar entrada
            $errors = validarCliente($data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                break;
            }
            
            $nombre = $data['nombre'];
            $email = $data['email'] ?? '';
            $telefono = $data['telefono'] ?? '';
            $id = (int)$data['id'];
            
            $query = "UPDATE clientes SET nombre = ?, email = ?, telefono = ? WHERE id = ?";
            $params = array(&$nombre, &$email, &$telefono, &$id);
            $stmt = executeQuery($query, $params);
            sqlsrv_free_stmt($stmt);
            
            echo json_encode(['success' => true, 'message' => 'Cliente actualizado']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Eliminar cliente
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            
            $id = (int)$data['id'];
            $query = "DELETE FROM clientes WHERE id = ?";
            $params = array(&$id);
            $stmt = executeQuery($query, $params);
            sqlsrv_free_stmt($stmt);
            
            echo json_encode(['success' => true, 'message' => 'Cliente eliminado']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        break;
}
?>
