<?php
/**
 * test_bd.php
 * Página de diagnóstico para comprobar la conexión a SQL Server y mostrar errores detallados.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';

// Cabecera amigable
$wantJson = (isset($_GET['json']) && ($_GET['json'] === '1' || strtolower($_GET['json']) === 'true')) || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
header('Content-Type: ' . ($wantJson ? 'application/json; charset=utf-8' : 'text/html; charset=utf-8'));

// Si se solicita JSON, devolver un resumen estructurado para consumo AJAX
if ($wantJson) {
    $response = [];

    $response['php_version'] = PHP_VERSION;
    $response['extensions'] = get_loaded_extensions();

    // Reutilizar variables de db.php si están disponibles
    $response['server'] = $serverName ?? null;
    $response['connection_options_masked'] = null;
    if (isset($connectionOptions) && is_array($connectionOptions)) {
        $masked = $connectionOptions;
        if (isset($masked['Pwd'])) $masked['Pwd'] = '***';
        if (isset($masked['PWD'])) $masked['PWD'] = '***';
        if (isset($masked['Uid'])) $masked['Uid'] = ($masked['Uid'] ? '***' : '(empty)');
        $response['connection_options_masked'] = $masked;
    }

    // Extensiones requeridas
    $required = ['sqlsrv', 'json'];
    $reqStatus = [];
    foreach ($required as $ext) {
        $reqStatus[$ext] = [
            'loaded' => extension_loaded($ext),
            'version' => extension_loaded($ext) ? phpversion($ext) : null
        ];
    }
    $response['required_extensions'] = $reqStatus;
    $response['odbc_loaded'] = extension_loaded('odbc');

    // Estado de conexión y versión SQL
    if ($conn) {
        $response['connected'] = true;
        $response['connection_method'] = $connection_method ?? null;
        try {
            $res = db_query('SELECT @@VERSION as version');
            $row = db_fetch_array($res);
            $response['sql_server_version'] = $row['version'] ?? null;
        } catch (Exception $e) {
            $response['sql_server_version'] = null;
            $response['db_errors'] = $e->getMessage();
        }

        // Listar tablas y conteos
        $tables = [];
        $expected_tables = ['categorias', 'clientes', 'productos', 'proveedores', 'salidas', 'usuarios', 'ventas'];
        $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' ORDER BY TABLE_NAME";
        try {
            $result = db_query($query);
            while ($row = db_fetch_array($result)) {
                $tables[] = $row['TABLE_NAME'];
            }
            db_free_stmt($result);
        } catch (Exception $e) {
            error_log('[test_bd.php] failed to list tables: ' . $e->getMessage());
            // tables remains empty
        }
        $response['tables_found'] = $tables;
        $tableDetails = [];
        foreach ($tables as $t) {
            $countQ = sqlsrv_query($conn, "SELECT COUNT(*) as cnt FROM [dbo].[" . $t . "]");
            $cnt = null;
            if ($countQ !== false) {
                $crow = sqlsrv_fetch_array($countQ, SQLSRV_FETCH_ASSOC);
                $cnt = $crow['cnt'];
                sqlsrv_free_stmt($countQ);
            }
            $tableDetails[] = ['name' => $t, 'count' => $cnt, 'expected' => in_array($t, $expected_tables)];
        }
        $response['table_details'] = $tableDetails;
    } else {
        $response['connected'] = false;
        $response['db_error'] = $db_error ?? 'No se pudo establecer conexión';
        $response['connection_method'] = $connection_method ?? null;
        $response['sqlsrv_errors'] = function_exists('sqlsrv_errors') ? sqlsrv_errors() : null;
    }

    // Funciones auxiliares
    $functions = ['executeQuery', 'fetchAll', 'fetchOne'];
    $funcStatus = [];
    foreach ($functions as $f) {
        $funcStatus[$f] = function_exists($f);
    }
    $response['helpers'] = $funcStatus;

    // Calcular resumen
    $checks_total = 0; $checks_passed = 0;
    // ext checks
    foreach ($reqStatus as $ext => $info) { $checks_total++; if ($info['loaded']) $checks_passed++; }
    $checks_total++; if (extension_loaded('odbc')) $checks_passed++;
    $checks_total++; if ($response['connected']) $checks_passed++;
    foreach ($funcStatus as $ok) { $checks_total++; if ($ok) $checks_passed++; }
    $response['summary'] = ['passed' => $checks_passed, 'total' => $checks_total, 'percent' => ($checks_total ? round(($checks_passed / $checks_total) * 100) : 0)];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Diagnóstico Base de Datos - Sakorms</title>
    <style>body{font-family:Segoe UI,Arial; padding:20px; color:#222} pre{background:#f4f4f4;padding:10px;border-radius:6px}</style>
</head>
<body>
    <h1>🔧 Diagnóstico de Conexión a SQL Server</h1>

    <h2>Entorno PHP</h2>
    <pre><?php echo 'PHP Version: ' . PHP_VERSION . "\n"; echo 'Extensiones cargadas: ' . implode(', ', get_loaded_extensions()); ?></pre>

    <h2>Parámetros de Conexión</h2>
    <pre><?php echo 'Server: ' . (isset($serverName) ? $serverName : '(no definido)') . "\n"; 

    // Mostrar opciones enmascaradas para evitar exponer credenciales
    if (isset($connectionOptions) && is_array($connectionOptions)) {
        $masked = $connectionOptions;
        if (isset($masked['Pwd'])) $masked['Pwd'] = '***';
        if (isset($masked['PWD'])) $masked['PWD'] = '***';
        if (isset($masked['Uid'])) $masked['Uid'] = ($masked['Uid'] ? '***' : '(empty)');
        echo 'Connection Options (masked): ' . PHP_EOL;
        var_export($masked);
    } else {
        echo 'Connection Options: (no disponibles)';
    }

    ?></pre>

    <h2>Estado de Conexión</h2>
    <pre>
<?php
if ($conn) {
    echo "Conexión establecida correctamente.\n";
    echo 'Método: ' . ($connection_method ?? 'desconocido') . "\n";
    // Prueba de consulta simple
    $res = sqlsrv_query($conn, 'SELECT @@VERSION as version');
    if ($res === false) {
        echo "Error al ejecutar consulta de prueba: \n" . print_r(sqlsrv_errors(), true);
    } else {
        $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        echo 'SQL Server Version: ' . ($row['version'] ?? '(desconocida)') . "\n";
        sqlsrv_free_stmt($res);
    }
} else {
    echo "No hay conexión.\n";
    echo 'db_error: ' . (isset($db_error) ? $db_error : '(no disponible)') . "\n";
    echo 'Método intento: ' . (isset($connection_method) ? $connection_method : '(no establecido)') . "\n";
    echo "sqlsrv_errors (si aplica): \n";
    if (function_exists('sqlsrv_errors')) {
        print_r(sqlsrv_errors());
    } else {
        echo "Función sqlsrv_errors() no disponible.\n";
    }
}
?>
    </pre>

    <h2>Recomendaciones</h2>
    <ul>
        <li>Verifica que SQL Server permita conexiones TCP/IP (SQL Server Configuration Manager).</li>
        <li>Confirma que el puerto 1433 esté abierto entre Apache y el servidor <code>sakorms</code>.</li>
        <li>Si usas Integrated Security, asegúrate que el proceso de Apache se ejecute con una cuenta que tenga acceso a la base de datos.</li>
        <li>Temporalmente prueba con autenticación SQL (usuario/clave) para descartar errores de Windows Auth.</li>
    </ul>

</body>
</html>