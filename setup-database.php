<?php
/**
 * setup-database.php
 * Script automático para inicializar la base de datos MySQL
 * Uso: Abre en navegador: http://localhost/Sakorms.org/Inventory-web1.5/setup-database.php
 */

// DISABLED FOR SECURITY: This script is disabled to prevent unauthorized database setup.
// If you need to run it, rename to setup-database.php.bak and remove this die statement.
die('Setup database script disabled for security reasons. Contact administrator.');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'inventory';
$port = 3306;

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Setup Base de Datos - Sakorms Inventory</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table th { background: #f8f9fa; }
        .step { margin: 20px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Inicializar Base de Datos MySQL - Sakorms Inventory v1.5</h1>";

// Paso 1: Conectar a MySQL sin seleccionar BD
echo "<div class='step'>";
echo "<h2>Paso 1: Conectando a MySQL...</h2>";

// Conectar temporalmente con PDO sin seleccionar DB para crearla si hace falta
try {
    $tmpDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
    $tmp = new PDO($tmpDsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "<div class='status error'>❌ Error de conexión: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<p>Verifica que:</p>";
    echo "<ul>";
    echo "<li>MySQL está corriendo (XAMPP Control Panel → MySQL → Running)</li>";
    echo "<li>Host: <code>$host</code></li>";
    echo "<li>Usuario: <code>$user</code></li>";
    echo "<li>Puerto: <code>$port</code></li>";
    echo "</ul>";
    echo "</div>";
    exit;
}
echo "<div class='status success'>✅ Conectado a MySQL correctamente</div>";
echo "</div>";

// Paso 2: Crear base de datos
echo "<div class='step'>";
echo "<h2>Paso 2: Crear base de datos 'inventory'...</h2>";

$sql_create_db = "CREATE DATABASE IF NOT EXISTS `" . $database . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
try {
    $tmp->exec($sql_create_db);
    echo "<div class='status success'>✅ Base de datos 'inventory' creada/verificada</div>";
} catch (Exception $e) {
    echo "<div class='status error'>❌ Error al crear base de datos: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}
echo "</div>";

// Paso 3: Seleccionar base de datos
echo "<div class='step'>";
echo "<h2>Paso 3: Seleccionando base de datos...</h2>";

// Inicializar Database singleton apuntando a la base creada
require_once __DIR__ . '/src/config/Database.php';
try {
    $db = Database::getInstance(['host'=>$host,'user'=>$user,'pass'=>$password,'dbname'=>$database,'port'=>$port]);
    echo "<div class='status success'>✅ Base de datos seleccionada</div>";
} catch (Exception $e) {
    echo "<div class='status error'>❌ Error al seleccionar base de datos: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}
echo "</div>";

// Paso 4: Leer y ejecutar schema
echo "<div class='step'>";
echo "<h2>Paso 4: Ejecutando schema de tablas...</h2>";

$schema_file = __DIR__ . '/sql/crear_tablas_inventory.sql';
if (!file_exists($schema_file)) {
    echo "<div class='status error'>❌ Archivo de schema no encontrado: $schema_file</div>";
    $conn->close();
    exit;
}

$schema = file_get_contents($schema_file);

// Eliminar comentarios de uso de BD
$schema = str_replace("USE inventory;", "", $schema);
$schema = str_replace("use inventory;", "", $schema);

// Ejecutar queries del schema (separadas por ;)
$queries = array_filter(array_map('trim', explode(';', $schema)), function($q) {
    return !empty($q) && strpos(trim($q), '--') !== 0;
});

// También aplicar esquema AI (ai_schema.sql) si existe
$ai_schema_file = __DIR__ . '/sql/ai_schema.sql';
if (file_exists($ai_schema_file)) {
    $ai_sql = file_get_contents($ai_schema_file);
    $ai_queries = array_filter(array_map('trim', explode(';', $ai_sql)), function($q){ return !empty($q) && strpos(trim($q),'--') !== 0; });
    $queries = array_merge($queries, $ai_queries);
}


$total = count($queries);
$executed = 0;
$errors_list = [];

foreach ($queries as $idx => $query) {
    $query = trim($query);
    if (empty($query)) continue;
    // Saltar comentarios
    if (strpos($query, '--') === 0) continue;
    try {
        $db->getConnection()->exec($query);
        $executed++;
    } catch (Exception $e) {
        $errors_list[] = "Query " . ($idx + 1) . ": " . htmlspecialchars($e->getMessage());
    }
}

if (count($errors_list) > 0) {
    echo "<div class='status warning'>⚠️ Se ejecutaron $executed/$total queries (con algunos errores)</div>";
    echo "<p><strong>Errores encontrados:</strong></p>";
    echo "<ul>";
    foreach ($errors_list as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<div class='status success'>✅ Se ejecutaron $executed queries correctamente</div>";
}
echo "</div>";

// Paso 5: Verificar tablas
echo "<div class='step'>";
echo "<h2>Paso 5: Verificando tablas creadas...</h2>";

// Obtener tablas mediante PDO
$tables = [];
try {
    $rows = $db->fetchAll('SHOW TABLES');
    foreach ($rows as $r) {
        // tomar primer valor de cada fila
        $vals = array_values($r);
        if (isset($vals[0])) $tables[] = $vals[0];
    }
    sort($tables);
    echo "<div class='status info'>📊 Tablas encontradas: " . count($tables) . " de 7 esperadas</div>";
} catch (Exception $e) {
    echo "<div class='status warning'>⚠️ No se pudo listar tablas: " . htmlspecialchars($e->getMessage()) . "</div>";
}

if (count($tables) > 0) {
    echo "<table>";
    echo "<tr><th>#</th><th>Tabla</th><th>Registros</th><th>Estado</th></tr>";
    
    $expected_tables = ['categorias', 'clientes', 'productos', 'proveedores', 'salidas', 'usuarios', 'ventas'];
    
    foreach ($tables as $idx => $table) {
        try {
            $r = $db->fetch("SELECT COUNT(*) as count FROM `" . str_replace('`','', $table) . "`");
            $count = $r['count'] ?? 0;
        } catch (Exception $e) {
            $count = 'Error';
        }
        
        $is_expected = in_array($table, $expected_tables);
        $status = $is_expected ? '✅' : '⚠️';
        
        echo "<tr>";
        echo "<td>" . ($idx + 1) . "</td>";
        echo "<td><code>$table</code></td>";
        echo "<td>$count</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// Paso 6: Resultado final
echo "<div class='step'>";
if (count($tables) === 7) {
    echo "<div class='status success'>";
    echo "<h2>✅ ¡Base de datos inicializada correctamente!</h2>";
    echo "<p><strong>Acciones completadas:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Conectado a MySQL en <code>localhost:3306</code></li>";
    echo "<li>✅ Creada base de datos <code>inventory</code></li>";
    echo "<li>✅ Creadas 7 tablas (categorias, productos, clientes, proveedores, usuarios, ventas, salidas)</li>";
    echo "<li>✅ Índices y relaciones de claves foráneas configuradas</li>";
    echo "</ul>";
    echo "<p><strong>Próximos pasos:</strong></p>";
    echo "<ol>";
    echo "<li>Abre la aplicación: <a href='http://localhost/Sakorms.org/Inventory-web1.5/' target='_blank'>http://localhost/Sakorms.org/Inventory-web1.5/</a></li>";
    echo "<li>Verifica que todas las secciones funcionan sin errores</li>";
    echo "<li>Abre DevTools (F12) → Console para ver si hay errores</li>";
    echo "</ol>";
    echo "<p style='margin-top: 20px;'><strong>Por seguridad, elimina este archivo (setup-database.php) después de terminar.</strong></p>";
    echo "</div>";
} else {
    echo "<div class='status error'>";
    echo "<h2>❌ Problema: Se encontraron " . count($tables) . " tablas, se esperaban 7</h2>";
    echo "<p>Por favor:</p>";
    echo "<ol>";
    echo "<li>Verifica el archivo <code>sql/crear_tablas_inventory.sql</code></li>";
    echo "<li>Abre phpMyAdmin y verifica manualmente</li>";
    echo "<li>Elimina la BD 'inventory' y vuelve a ejecutar este script</li>";
    echo "</ol>";
    echo "</div>";
}
echo "</div>";

// Cerrar conexión
$conn->close();

echo "
    </div>
</body>
</html>";
?>
