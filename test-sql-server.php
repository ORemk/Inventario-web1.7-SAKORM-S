<?php
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Test SQL Server</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        .success { color: #155724; background: #d4edda; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { color: #721c24; background: #f8d7da; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { color: #0c5460; background: #d1ecf1; padding: 15px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        .warning { color: #856404; background: #fff3cd; padding: 15px; margin: 10px 0; border-left: 4px solid #ffc107; }
        code { background: #f5f5f5; padding: 3px 8px; border-radius: 3px; font-family: monospace; }
        h2 { color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; }
    </style>
</head>
<body>";

echo "<h1>[DIAGNOSTICO SQL SERVER]</h1>";
echo "<hr>";

// 1. Verificar extensiones
echo "<h2>[1] Extensiones PHP</h2>";

$extensions = array(
    'sqlsrv' => 'SQL Server (sqlsrv)',
    'pdo_sqlsrv' => 'PDO SQL Server',
    'odbc' => 'ODBC',
    'pdo' => 'PDO'
);

foreach ($extensions as $ext => $name) {
    if (extension_loaded($ext)) {
        echo "<div class='success'>OK - $name esta instalada</div>";
    } else {
        echo "<div class='error'>NO - $name NO esta instalada</div>";
    }
}


if (extension_loaded('pdo_sqlsrv')) {
    echo "<div class='success'>OK - PDO SQL Server esta instalada</div>";
} else {
    echo "<div class='error'>NO - PDO SQL Server NO esta instalada</div>";
}

if (extension_loaded('odbc')) {
    echo "<div class='success'>OK - ODBC esta instalada</div>";
} else {
    echo "<div class='error'>NO - ODBC NO esta instalada</div>";
}

// 2. Intentar conexion
echo "<h2>[2] Prueba de Conexion</h2>";

if (extension_loaded('sqlsrv')) {
    echo "<div class='info'>Servidor: sakorms</div>";
    echo "<div class='info'>BD: inventory</div>";
    echo "<div class='info'>Auth: Windows</div>";
    
    $conn = sqlsrv_connect('sakorms', array('Database' => 'inventory', 'Connection Timeout' => 3));

    if ($conn === false) {
        echo "<div class='error'>ERROR - No se pudo conectar</div>";
        $errors = sqlsrv_errors();
        if (is_array($errors)) {
            foreach ($errors as $e) {
                echo "<div class='error'>Detalles: " . htmlspecialchars($e['message']) . "</div>";
            }
        }
    } else {
        echo "<div class='success'>EXITO - Conexion a SQL Server</div>";
        sqlsrv_close($conn);
    }
} else {
    echo "<div class='error'>ERROR - sqlsrv no disponible</div>";
}

// 3. Recomendaciones
echo "<h2>[3] Pasos Siguientes</h2>";
echo "<div class='info'>Si ves 'ERROR' arriba:</div>";
echo "<div class='warning'>1. Presiona Windows + R</div>";
echo "<div class='warning'>2. Escribe: services.msc</div>";
echo "<div class='warning'>3. Busca SQL Server o SQL Server (SQLEXPRESS)</div>";
echo "<div class='warning'>4. Si esta parado: clic derecho > Start</div>";
echo "<div class='warning'>5. Recarga esta pagina</div>";

echo "</body></html>";
?>

