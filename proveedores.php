<?php
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
/**
 * proveedores.php - proxy
 * Implementación canónica en `api/proveedores.php`.
 * Este archivo se mantiene como proxy por compatibilidad hacia atrás.
 */
require_once __DIR__ . '/api/proveedores.php';
exit;

// Proxy-only: la implementación canónica está en `api/proveedores.php`.
// EOF - proveedores.php


switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        try {
            $query = "SELECT id, nombre, email, telefono, created_at FROM proveedores ORDER BY nombre ASC";
            $result = sqlsrv_query($conn, $query);
            
            if (!$result) {
                throw new Exception('Error en la consulta: ' . print_r(sqlsrv_errors(), true));
            }
            
            $proveedores = [];
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $proveedores[] = $row;
            }
            sqlsrv_free_stmt($result);
            
            echo json_encode(['success' => true, 'data' => $proveedores]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'POST':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $errors = validarProveedor($data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                break;
            }
            
            $email = $data['email'] ?? '';
            $telefono = $data['telefono'] ?? '';
            
            $params = array($data['nombre'], $email, $telefono);
            $stmt = sqlsrv_prepare($conn, "INSERT INTO proveedores (nombre, email, telefono) VALUES (?, ?, ?)", $params);
            if (!$stmt) {
                throw new Exception('Error al preparar: ' . print_r(sqlsrv_errors(), true));
            }
            
            if (!sqlsrv_execute($stmt)) {
                throw new Exception('Error al insertar: ' . print_r(sqlsrv_errors(), true));
            }
            
            $result_id = sqlsrv_query($conn, "SELECT @@IDENTITY as id");
            $row = sqlsrv_fetch_array($result_id, SQLSRV_FETCH_ASSOC);
            $last_id = $row['id'];
            sqlsrv_free_stmt($stmt);
            sqlsrv_free_stmt($result_id);
            
            echo json_encode([
                'success' => true,
                'id' => $last_id,
                'message' => 'Proveedor creado exitosamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'PUT':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            
            $errors = validarProveedor($data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                break;
            }
            
            $id = (int)$data['id'];
            $email = $data['email'] ?? '';
            $telefono = $data['telefono'] ?? '';
            
            $params = array($data['nombre'], $email, $telefono, $id);
            $stmt = sqlsrv_prepare($conn, "UPDATE proveedores SET nombre = ?, email = ?, telefono = ? WHERE id = ?", $params);
            if (!$stmt) {
                throw new Exception('Error al preparar: ' . print_r(sqlsrv_errors(), true));
            }
            
            if (!sqlsrv_execute($stmt)) {
                throw new Exception('Error al actualizar: ' . print_r(sqlsrv_errors(), true));
            }
            
            sqlsrv_free_stmt($stmt);
            
            echo json_encode(['success' => true, 'message' => 'Proveedor actualizado']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            
            $id = (int)$data['id'];
            $params = array($id);
            $stmt = sqlsrv_prepare($conn, "DELETE FROM proveedores WHERE id = ?", $params);
            if (!$stmt) {
                throw new Exception('Error al preparar: ' . print_r(sqlsrv_errors(), true));
            }
            
            if (!sqlsrv_execute($stmt)) {
                throw new Exception('Error al eliminar: ' . print_r(sqlsrv_errors(), true));
            }
            
            sqlsrv_free_stmt($stmt);
            
            echo json_encode(['success' => true, 'message' => 'Proveedor eliminado']);
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
