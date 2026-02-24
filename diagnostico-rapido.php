<?php
/**
 * diagnostico-rapido.php
 * Diagnóstico rápido del sistema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico Rápido</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .check { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .pass { background: #d4edda; color: #155724; }
        .fail { background: #f8d7da; color: #721c24; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico Rápido</h1>
        
        <?php
        // Test 1: PHP Extensions
        echo '<h2>1. Extensiones PHP</h2>';
        $extensions = ['sqlsrv', 'json', 'odbc'];
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                echo '<div class="check pass">✅ ' . strtoupper($ext) . ' instalado</div>';
            } else {
                echo '<div class="check fail">❌ ' . strtoupper($ext) . ' NO ENCONTRADO</div>';
            }
        }
        
        // Test 2: PHP Version
        echo '<h2>2. Versión de PHP</h2>';
        echo '<div class="check pass">✅ PHP ' . phpversion() . '</div>';
        
        // Test 3: Conexión a SQL Server
        echo '<h2>3. Conexión a SQL Server</h2>';
        $serverName = 'sakorms';
        $connectionOptions = array(
            'Database' => 'inventory',
            'Uid' => '',
            'Pwd' => '',
            'Encrypt' => 'yes',
            'TrustServerCertificate' => 'yes',
            'Connection Timeout' => 5
        );
        
        if (extension_loaded('sqlsrv')) {
            $conn = sqlsrv_connect($serverName, $connectionOptions);
            if ($conn === false) {
                $errors = sqlsrv_errors();
                echo '<div class="check fail">❌ Conexión fallida</div>';
                echo '<pre>';
                print_r($errors);
                echo '</pre>';
            } else {
                echo '<div class="check pass">✅ Conectado a SQL Server</div>';
                
                // Test tablas
                $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' ORDER BY TABLE_NAME";
                $result = sqlsrv_query($conn, $query);
                
                if ($result === false) {
                    echo '<div class="check fail">❌ Error al listar tablas</div>';
                    echo '<pre>';
                    print_r(sqlsrv_errors());
                    echo '</pre>';
                } else {
                    echo '<h3>Tablas encontradas:</h3>';
                    $count = 0;
                    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                        echo '<div class="check pass">✅ ' . $row['TABLE_NAME'] . '</div>';
                        $count++;
                    }
                    echo '<p><strong>Total: ' . $count . ' tablas</strong></p>';
                    sqlsrv_free_stmt($result);
                }
                
                sqlsrv_close($conn);
            }
        } else {
            echo '<div class="check fail">❌ sqlsrv no disponible</div>';
        }
        
        // Test 4: Archivos PHP
        echo '<h2>4. Archivos PHP Principales</h2>';
        $files = [
            'db.php',
            'categorias.php',
            'productos.php',
            'clientes.php',
            'config/Database.php',
            'api/BaseAPI.php'
        ];
        
        foreach ($files as $file) {
            if (file_exists(__DIR__ . '/' . $file)) {
                echo '<div class="check pass">✅ ' . $file . ' existe</div>';
            } else {
                echo '<div class="check fail">❌ ' . $file . ' NO ENCONTRADO</div>';
            }
        }
        ?>
    </div>
</body>
</html>
