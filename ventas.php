<?php
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
/**
 * ventas.php - proxy
 * Implementación canónica en `api/ventas.php`.
 * Este archivo se mantiene como proxy por compatibilidad hacia atrás.
 */
require_once __DIR__ . '/api/ventas.php';
exit;

// Proxy-only: la implementación canónica está en `api/ventas.php`.
// EOF - ventas.php


switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        try {
            $query = "SELECT id, cliente_id, total, fecha, created_at FROM ventas ORDER BY fecha DESC";
            $result = sqlsrv_query($conn, $query);
            
            if (!$result) {
                throw new Exception('Error en la consulta: ' . print_r(sqlsrv_errors(), true));
            }
            
            $ventas = [];
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $row['total'] = (float)$row['total'];
                $ventas[] = $row;
            }
            sqlsrv_free_stmt($result);
            
            echo json_encode(['success' => true, 'data' => $ventas]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'POST':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $errors = validarVenta($data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                break;
            }
            
            $cliente_id = (int)$data['cliente_id'];
            $total = (float)($data['total'] ?? 0);
            $fecha = $data['fecha'] ?? date('Y-m-d');
            
            $params = array($cliente_id, $total, $fecha);
            $stmt = sqlsrv_prepare($conn, "INSERT INTO ventas (cliente_id, total, fecha) VALUES (?, ?, ?)", $params);
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
                'message' => 'Venta registrada exitosamente'
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
            
            $errors = validarVenta($data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                break;
            }
            
            $id = (int)$data['id'];
            $cliente_id = (int)$data['cliente_id'];
            $total = (float)($data['total'] ?? 0);
            $fecha = $data['fecha'] ?? date('Y-m-d');
            
            $params = array($cliente_id, $total, $fecha, $id);
            $stmt = sqlsrv_prepare($conn, "UPDATE ventas SET cliente_id = ?, total = ?, fecha = ? WHERE id = ?", $params);
            if (!$stmt) {
                throw new Exception('Error al preparar: ' . print_r(sqlsrv_errors(), true));
            }
            
            if (!sqlsrv_execute($stmt)) {
                throw new Exception('Error al actualizar: ' . print_r(sqlsrv_errors(), true));
            }
            
            sqlsrv_free_stmt($stmt);
            
            echo json_encode(['success' => true, 'message' => 'Venta actualizada']);
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
            $stmt = sqlsrv_prepare($conn, "DELETE FROM ventas WHERE id = ?", $params);
            if (!$stmt) {
                throw new Exception('Error al preparar: ' . print_r(sqlsrv_errors(), true));
            }
            
            if (!sqlsrv_execute($stmt)) {
                throw new Exception('Error al eliminar: ' . print_r(sqlsrv_errors(), true));
            }
            
            sqlsrv_free_stmt($stmt);
            
            echo json_encode(['success' => true, 'message' => 'Venta eliminada']);
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
