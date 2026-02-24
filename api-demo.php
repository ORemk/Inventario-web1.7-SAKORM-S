<?php
/**
 * api-demo.php
 * APIs demo que retornan datos de prueba sin necesidad de SQL Server
 * Útil para diagnosticar problemas del frontend vs. backend
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// CORS Headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Detectar cuál API está siendo llamada basándose en el archivo
$script = basename($_SERVER['PHP_SELF']);

// Datos de demostración
$demo_data = [
    'categorias.php' => [
        ['id' => 1, 'nombre' => 'Electrónica', 'created_at' => date('Y-m-d H:i:s')],
        ['id' => 2, 'nombre' => 'Ropa', 'created_at' => date('Y-m-d H:i:s')],
        ['id' => 3, 'nombre' => 'Alimentos', 'created_at' => date('Y-m-d H:i:s')],
        ['id' => 4, 'nombre' => 'Libros', 'created_at' => date('Y-m-d H:i:s')],
    ],
    'productos.php' => [
        ['id' => 1, 'nombre' => 'Laptop Dell', 'precio' => 899.99, 'cantidad' => 5, 'categoria_id' => 1],
        ['id' => 2, 'nombre' => 'Mouse Inalámbrico', 'precio' => 29.99, 'cantidad' => 50, 'categoria_id' => 1],
        ['id' => 3, 'nombre' => 'Camiseta', 'precio' => 19.99, 'cantidad' => 100, 'categoria_id' => 2],
        ['id' => 4, 'nombre' => 'Pantalón', 'precio' => 49.99, 'cantidad' => 80, 'categoria_id' => 2],
        ['id' => 5, 'nombre' => 'Arroz 5kg', 'precio' => 15.99, 'cantidad' => 200, 'categoria_id' => 3],
    ],
    'clientes.php' => [
        ['id' => 1, 'nombre' => 'Juan Pérez', 'email' => 'juan@example.com', 'telefono' => '5551234567', 'ciudad' => 'Madrid'],
        ['id' => 2, 'nombre' => 'María García', 'email' => 'maria@example.com', 'telefono' => '5552345678', 'ciudad' => 'Barcelona'],
        ['id' => 3, 'nombre' => 'Carlos López', 'email' => 'carlos@example.com', 'telefono' => '5553456789', 'ciudad' => 'Valencia'],
    ],
    'proveedores.php' => [
        ['id' => 1, 'nombre' => 'Distribuidor ABC', 'email' => 'info@abc.com', 'telefono' => '5551111111', 'ciudad' => 'Barcelona'],
        ['id' => 2, 'nombre' => 'Supplier XYZ', 'email' => 'contact@xyz.com', 'telefono' => '5552222222', 'ciudad' => 'Madrid'],
    ],
];

// Detectar método
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

// GET - Retornar datos
if ($method === 'GET') {
    $data = $demo_data[$script] ?? [];
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => 'Datos de demostración (sin BD)',
        'demo' => true
    ]);
    exit;
}

// POST - Crear (agregar a datos)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        exit;
    }
    
    // Simular creación
    $new_id = rand(100, 999);
    $new_item = array_merge(['id' => $new_id], $input);
    
    echo json_encode([
        'success' => true,
        'id' => $new_id,
        'data' => $new_item,
        'message' => 'Creado exitosamente (simulado)',
        'demo' => true
    ]);
    exit;
}

// PUT - Actualizar
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    echo json_encode([
        'success' => true,
        'data' => $input,
        'message' => 'Actualizado exitosamente (simulado)',
        'demo' => true
    ]);
    exit;
}

// DELETE - Eliminar
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    echo json_encode([
        'success' => true,
        'message' => 'Eliminado exitosamente (simulado)',
        'id' => $id,
        'demo' => true
    ]);
    exit;
}

// Por defecto
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
?>
