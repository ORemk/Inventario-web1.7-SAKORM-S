<?php
/**
 * test-api.php
 * Prueba directa de los endpoints de API
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mostrar información del servidor
echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Prueba de API</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .test { padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        .success { background: #d4edda; border-color: #28a745; }
        .error { background: #f8d7da; border-color: #dc3545; }
        .info { background: #e2e3e5; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🧪 Prueba de API</h1>";

// Test 1: Cargar db.php y verificar conexión
echo "<div class='test info'>";
echo "<h2>1. Cargando db.php</h2>";

// Capturar cualquier output de db.php
ob_start();
require_once __DIR__ . '/db.php';
$output = ob_get_clean();

if (!empty($output)) {
    echo "<p>Output de db.php:</p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
}

global $conn, $db_error;

if ($db_error) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($db_error) . "</div>";
} else if ($conn) {
    echo "<div class='success'>✅ Conexión a SQL Server establecida</div>";
} else {
    echo "<div class='error'>❌ No hay conexión y no hay error reportado</div>";
}
echo "</div>";

// Test 2: Probar categorías GET
echo "<div class='test'>";
echo "<h2>2. GET /categorias.php</h2>";

if ($conn) {
    try {
        $query = "SELECT id, nombre, created_at FROM categorias ORDER BY nombre ASC";
        $result = executeQuery($query);
        $categorias = fetchAll($result);
        
        echo "<div class='success'>✅ Query ejecutado</div>";
        echo "<p>Categorías encontradas: " . count($categorias) . "</p>";
        
        if (count($categorias) > 0) {
            echo "<pre>" . json_encode($categorias, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<p><em>No hay categorías en la base de datos</em></p>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='error'>❌ Sin conexión a BD</div>";
}
echo "</div>";

// Test 3: Probar productos GET
echo "<div class='test'>";
echo "<h2>3. GET /productos.php</h2>";

if ($conn) {
    try {
        $query = "SELECT id, nombre, precio, cantidad FROM productos ORDER BY nombre ASC";
        $result = executeQuery($query);
        $productos = fetchAll($result);
        
        echo "<div class='success'>✅ Query ejecutado</div>";
        echo "<p>Productos encontrados: " . count($productos) . "</p>";
        
        if (count($productos) > 0) {
            echo "<pre>" . json_encode($productos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<p><em>No hay productos en la base de datos</em></p>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='error'>❌ Sin conexión a BD</div>";
}
echo "</div>";

// Test 4: Verificar estructura de tablas
echo "<div class='test'>";
echo "<h2>4. Estructura de Tablas</h2>";

if ($conn) {
    try {
        $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' ORDER BY TABLE_NAME";
        $result = executeQuery($query);
        $tables = fetchAll($result);
        
        echo "<div class='success'>✅ Tablas encontradas: " . count($tables) . "</div>";
        
        foreach ($tables as $table) {
            $tableName = $table['TABLE_NAME'];
            
            // Contar registros
            try {
                $countQuery = "SELECT COUNT(*) as cnt FROM " . $tableName;
                $countResult = executeQuery($countQuery);
                $countRow = fetchOne($countResult);
                $count = $countRow ? $countRow['cnt'] : 0;
                
                echo "<p>✅ <strong>$tableName</strong>: $count registros</p>";
            } catch (Exception $e) {
                echo "<p>⚠️ <strong>$tableName</strong>: No se pudo contar registros</p>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='error'>❌ Sin conexión a BD</div>";
}
echo "</div>";

// Test 5: Información de servidor
echo "<div class='test info'>";
echo "<h2>5. Información del Servidor</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>sqlsrv Extension: " . (extension_loaded('sqlsrv') ? '✅ Sí' : '❌ No') . "</p>";
echo "<p>ODBC Extension: " . (extension_loaded('odbc') ? '✅ Sí' : '❌ No') . "</p>";
echo "<p>Current File: " . __FILE__ . "</p>";
echo "</div>";

echo "</body></html>";
?>
