<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>SQL Diagnostico</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        .ok { color: #155724; background: #d4edda; padding: 10px; margin: 5px 0; }
        .no { color: #721c24; background: #f8d7da; padding: 10px; margin: 5px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; margin: 5px 0; }
    </style>
</head>
<body>
<h1>SQL SERVER TEST</h1>
<hr>

<?php
// Prueba 1: Extensiones
echo "<h2>EXTENSIONES PHP:</h2>";
if (extension_loaded('sqlsrv')) {
    echo "<div class='ok'>OK - sqlsrv disponible</div>";
} else {
    echo "<div class='no'>NO - sqlsrv NO disponible</div>";
}

// Prueba 2: Conexion
echo "<h2>CONEXION A SQL SERVER:</h2>";
    if (extension_loaded('sqlsrv')) {
    $conn = sqlsrv_connect('sakorms', array(
        'Database' => 'inventory',
        'Connection Timeout' => 3
    ));

    if ($conn === false) {
        echo "<div class='no'>ERROR - No conecta</div>";
        echo "<div class='info'>Servidor: sakorms</div>";
        echo "<div class='info'>BD: inventory</div>";
        $err = sqlsrv_errors();
        if ($err) {
            echo "<div class='no'>Razon: " . htmlspecialchars($err[0]['message']) . "</div>";
        }
    } else {
        echo "<div class='ok'>EXITO - Conectado a SQL Server</div>";
        sqlsrv_close($conn);
    }
} else {
    echo "<div class='no'>ERROR - sqlsrv no instalada</div>";
}
?>

<hr>
<p>Si ves ERROR arriba: abre Services (services.msc) y busca SQL Server. Debe estar corriendo (verde).</p>
</body>
</html>
