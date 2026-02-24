<?php
/**
 * sse_bd.php
 * Endpoint Server-Sent Events para emitir diagnóstico en tiempo real
 * Uso: EventSource('sse_bd.php') o ?once=1 para un único evento
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/db.php';

ignore_user_abort(true);
set_time_limit(0);

$interval = isset($_GET['interval']) ? max(1, intval($_GET['interval'])) : 6; // segundos
$maxLoops = isset($_GET['loops']) ? intval($_GET['loops']) : 0; // 0 = infinito

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

function get_status_sse() {
    global $serverName, $connectionOptions, $conn, $db_error, $connection_method;

    $response = [];
    $response['php_version'] = PHP_VERSION;
    $response['extensions'] = get_loaded_extensions();
    $response['server'] = $serverName ?? null;

    $response['connection_options_masked'] = null;
    if (isset($connectionOptions) && is_array($connectionOptions)) {
        $masked = $connectionOptions;
        if (isset($masked['Pwd'])) $masked['Pwd'] = '***';
        if (isset($masked['PWD'])) $masked['PWD'] = '***';
        if (isset($masked['Uid'])) $masked['Uid'] = ($masked['Uid'] ? '***' : '(empty)');
        $response['connection_options_masked'] = $masked;
    }

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
            error_log('[sse_bd] failed to list tables: ' . $e->getMessage());
        }
        $response['tables_found'] = $tables;
        $tableDetails = [];
        foreach ($tables as $t) {
            try {
                $countQ = db_query("SELECT COUNT(*) as cnt FROM [dbo].[" . $t . "]");
                $crow = db_fetch_array($countQ);
                $cnt = $crow['cnt'];
                db_free_stmt($countQ);
            } catch (Exception $e) {
                $cnt = null;
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

    $functions = ['executeQuery', 'fetchAll', 'fetchOne'];
    $funcStatus = [];
    foreach ($functions as $f) {
        $funcStatus[$f] = function_exists($f);
    }
    $response['helpers'] = $funcStatus;

    // Resumen
    $checks_total = 0; $checks_passed = 0;
    foreach ($reqStatus as $ext => $info) { $checks_total++; if ($info['loaded']) $checks_passed++; }
    $checks_total++; if (extension_loaded('odbc')) $checks_passed++;
    $checks_total++; if ($response['connected']) $checks_passed++;
    foreach ($funcStatus as $ok) { $checks_total++; if ($ok) $checks_passed++; }
    $response['summary'] = ['passed' => $checks_passed, 'total' => $checks_total, 'percent' => ($checks_total ? round(($checks_passed / $checks_total) * 100) : 0)];

    return $response;
}

// Si se solicita un único envío, enviar y salir
if (isset($_GET['once']) && $_GET['once'] === '1') {
    $payload = get_status_sse();
    echo "event: diag\n";
    echo 'data: ' . json_encode($payload) . "\n\n";
    if (function_exists('ob_flush') && ob_get_level() > 0) { ob_flush(); }
    if (function_exists('flush')) { flush(); }
    exit;
}

$loop = 0;
while (connection_status() === CONNECTION_NORMAL) {
    $payload = get_status_sse();
    echo "event: diag\n";
    echo 'data: ' . json_encode($payload) . "\n\n";
    if (function_exists('ob_flush') && ob_get_level() > 0) { ob_flush(); }
    if (function_exists('flush')) { flush(); }
    $loop++;
    if ($maxLoops > 0 && $loop >= $maxLoops) break;
    sleep($interval);
}

exit;