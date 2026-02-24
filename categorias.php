<?php
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
/**
 * categorias.php - proxy
 * Implementación canónica en `api/categorias.php`.
 * Este archivo se mantiene como proxy por compatibilidad hacia atrás.
 */
require_once __DIR__ . '/api/categorias.php';
exit;

// Proxy-only: la implementación canónica está en `api/categorias.php`.
// EOF - categorias.php


switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Obtener todas las categorías
        try {
            $query = "SELECT id, nombre, created_at FROM categorias ORDER BY nombre ASC";
            $result = executeQuery($query);
            
            $categorias = fetchAll($result);
            
            echo json_encode(['success' => true, 'data' => $categorias]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'POST':
        // Crear categoría
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar entrada
            $errors = validarCategoria($data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                break;
            }
            
            $nombre = $data['nombre'];
            $query = "INSERT INTO categorias (nombre) VALUES (?); SELECT @@IDENTITY as id";
            $params = array(&$nombre);
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
                'message' => 'Categoría creada exitosamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Actualizar categoría
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            
            // Validar entrada
            $errors = validarCategoria($data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                break;
            }
            
            $nombre = $data['nombre'];
            $id = (int)$data['id'];
            $query = "UPDATE categorias SET nombre = ? WHERE id = ?";
            $params = array(&$nombre, &$id);
            $stmt = executeQuery($query, $params);
            sqlsrv_free_stmt($stmt);
            
            echo json_encode(['success' => true, 'message' => 'Categoría actualizada']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Eliminar categoría
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            
            $id = (int)$data['id'];
            $query = "DELETE FROM categorias WHERE id = ?";
            $params = array(&$id);
            $stmt = executeQuery($query, $params);
            sqlsrv_free_stmt($stmt);
            
            echo json_encode(['success' => true, 'message' => 'Categoría eliminada']);
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
