<?php
/**
 * test-connection.php
 * Prueba rápida de conexión a SQL Server 2025 y estado de la base de datos
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

// Incluir conexión
require __DIR__ . '/db.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Conexión - Sakorms Inventory</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .test-section {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eee;
        }
        .test-section:last-child {
            border-bottom: none;
        }
        .test-title {
            color: #333;
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .test-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
        }
        .status.info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .detail {
            color: #555;
            font-size: 14px;
            margin: 8px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f8f9fa;
        }
        code {
            background: #f1f3f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .emoji {
            font-size: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 12px;
        }
        ul {
            list-style-position: inside;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="emoji">🔍</span> Test de Conexión</h1>
        <p class="subtitle">Sakorms Inventory v1.5 - SQL Server 2025</p>

        <?php
        // Test 1: Verificar conexión
        echo '<div class="test-section">';
        echo '<div class="test-title"><span class="emoji">📡</span> Conexión a SQL Server</div>';
        echo '<div class="test-content">';

        if (!$conn) {
            echo '<div class="status error">❌ ERROR: Sin conexión</div>';
            if (isset($db_error)) {
                echo '<div class="detail">Error: ' . htmlspecialchars($db_error) . '</div>';
            }
        } else {
            echo '<div class="status success">✅ Conectado</div>';
            
            // Test 2: Información de la conexión
            echo '<div class="detail"><strong>Servidor:</strong> <code>sakorms</code></div>';
            echo '<div class="detail"><strong>Base de datos:</strong> <code>inventory</code></div>';
            echo '<div class="detail"><strong>Driver:</strong> <code>SQL Server 2025</code></div>';
        }
        echo '</div>';
        echo '</div>';

        if ($conn) {
            // Test 4: Listar tablas
            echo '<div class="test-section">';
            echo '<div class="test-title"><span class="emoji">📊</span> Tablas de Base de Datos</div>';
            echo '<div class="test-content">';

            try {
                $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' ORDER BY TABLE_NAME";
                $result = sqlsrv_query($conn, $query);
                
                if ($result === false) {
                    throw new Exception('Error al listar tablas: ' . json_encode(sqlsrv_errors()));
                }
                
                $tables = [];
                while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                    $tables[] = $row['TABLE_NAME'];
                }
                sqlsrv_free_stmt($result);

                $expected_tables = ['categorias', 'clientes', 'productos', 'proveedores', 'salidas', 'usuarios', 'ventas'];
                $all_present = !empty($tables) && empty(array_diff($expected_tables, $tables));

                if ($all_present) {
                    echo '<div class="status success">✅ Todas las tablas presentes (' . count($tables) . ')</div>';
                } elseif (!empty($tables)) {
                    echo '<div class="status warning">⚠️ ' . count($tables) . ' de 7 tablas encontradas</div>';
                } else {
                    echo '<div class="status error">❌ No se encontraron tablas</div>';
                }

                if (!empty($tables)) {
                    echo '<table>';
                    echo '<tr><th>#</th><th>Tabla</th><th>Registros</th><th>Estado</th></tr>';

                    foreach ($tables as $idx => $table) {
                        $count_query = "SELECT COUNT(*) as cnt FROM [dbo].[" . $table . "]";
                        $count_result = sqlsrv_query($conn, $count_query);
                        
                        if ($count_result === false) {
                            $count = 'Error';
                        } else {
                            $count_row = sqlsrv_fetch_array($count_result, SQLSRV_FETCH_ASSOC);
                            $count = $count_row['cnt'];
                            sqlsrv_free_stmt($count_result);
                        }
                        
                        $is_expected = in_array($table, $expected_tables);
                        $status = $is_expected ? '✅' : '⚠️';

                        echo '<tr>';
                        echo '<td>' . ($idx + 1) . '</td>';
                        echo '<td><code>' . htmlspecialchars($table) . '</code></td>';
                        echo '<td>' . $count . '</td>';
                        echo '<td>' . $status . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            } catch (Exception $e) {
                echo '<div class="status error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            
            echo '</div>';
            echo '</div>';

            // Test 5: Estructura de tabla (ejemplo: productos)
            echo '<div class="test-section">';
            echo '<div class="test-title"><span class="emoji">🔎</span> Estructura de Tabla: productos</div>';
            echo '<div class="test-content">';

            try {
                $desc_query = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'productos' ORDER BY ORDINAL_POSITION";
                $desc_result = sqlsrv_query($conn, $desc_query);
                
                if ($desc_result && sqlsrv_has_rows($desc_result)) {
                    echo '<table>';
                    echo '<tr><th>Campo</th><th>Tipo</th><th>Null</th></tr>';
                    while ($row = sqlsrv_fetch_array($desc_result, SQLSRV_FETCH_ASSOC)) {
                        echo '<tr>';
                        echo '<td><code>' . htmlspecialchars($row['COLUMN_NAME']) . '</code></td>';
                        echo '<td><code>' . htmlspecialchars($row['DATA_TYPE']) . '</code></td>';
                        echo '<td>' . ($row['IS_NULLABLE'] === 'YES' ? 'Sí' : 'No') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    sqlsrv_free_stmt($desc_result);
                } else {
                    echo '<div class="status warning">⚠️ Tabla productos no encontrada</div>';
                }
            } catch (Exception $e) {
                echo '<div class="status error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            
            echo '</div>';
            echo '</div>';

            // Test 6: Prueba de query
            echo '<div class="test-section">';
            echo '<div class="test-title"><span class="emoji">✔️</span> Prueba de Query</div>';
            echo '<div class="test-content">';

            try {
                $test_query = "SELECT COUNT(*) as total FROM productos";
                $test_result = sqlsrv_query($conn, $test_query);
                
                if ($test_result === false) {
                    throw new Exception('Error: ' . json_encode(sqlsrv_errors()));
                }
                
                $test_row = sqlsrv_fetch_array($test_result, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($test_result);
                
                echo '<div class="status success">✅ Query ejecutada exitosamente</div>';
                echo '<div class="detail">Total de productos en BD: <strong>' . $test_row['total'] . '</strong></div>';
            } catch (Exception $e) {
                echo '<div class="status error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            
            echo '</div>';
            echo '</div>';

            // Test final
            echo '<div class="test-section">';
            echo '<div class="test-title"><span class="emoji">✅</span> Estado General</div>';
            echo '<div class="test-content">';
            if (!empty($tables) && $all_present) {
                echo '<div class="status success">✅ Todos los tests pasaron correctamente</div>';
                echo '<div class="detail">La aplicación está lista para usar.</div>';
                echo '<div class="detail" style="margin-top: 15px;"><a href="index.html">👉 Ir a la aplicación</a></div>';
            } else {
                echo '<div class="status warning">⚠️ Revisar datos de conexión</div>';
                echo '<div class="detail">Asegúrate que SQL Server está corriendo y la base de datos "inventory" existe.</div>';
                echo '<div class="detail" style="margin-top: 15px;"><a href="setup-database.php">👉 Ejecutar Setup</a></div>';
            }
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="test-section">';
            echo '<div class="test-title"><span class="emoji">❌</span> Error de Conexión</div>';
            echo '<div class="test-content">';
            echo '<div class="status error">No se puede continuar sin conexión</div>';
            echo '<div class="detail">Verifica que:</div>';
            echo '<ul style="margin-left: 20px; margin-top: 10px;">';
            echo '<li>SQL Server está corriendo en "sakorms"</li>';
            echo '<li>La base de datos "inventory" existe</li>';
            echo '<li>El usuario tiene credenciales de Windows Auth correctas</li>';
            echo '<li>La extensión sqlsrv de PHP está instalada</li>';
            echo '</ul>';
            if (isset($db_error)) {
                echo '<div class="detail" style="margin-top: 10px; color: #721c24;"><strong>Error:</strong> ' . htmlspecialchars($db_error) . '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        ?>

        <div class="footer">
            <p><strong>Sakorms Inventory v1.5</strong> - Test de Conexión</p>
            <p style="margin-top: 5px;">SQL Server 2025 Edition</p>
            <p style="margin-top: 10px; opacity: 0.7;">Por seguridad, elimina este archivo (test-connection.php) después de confirmar que todo funciona.</p>
        </div>
    </div>
</body>
</html>
            line-height: 1.6;
        }
        .detail code {
            background: #eee;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }
        table th {
            background: #f0f0f0;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        table tr:hover {
            background: #fafafa;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #999;
            font-size: 12px;
            text-align: center;
        }
        .emoji { font-size: 18px; }
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="emoji">🔍</span> Test de Conexión</h1>
        <p class="subtitle">Sakorms Inventory v1.5 - MySQL</p>

        <?php
        // Test 1: Verificar conexión (MySQL via Database singleton / PDO)
        echo '<div class="test-section">';
        echo '<div class="test-title"><span class="emoji">📡</span> Conexión a MySQL</div>';
        echo '<div class="test-content">';

        require_once __DIR__ . '/src/config/Database.php';
        $pdo = null;
        $db_error = null;
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
        } catch (Exception $e) {
            $db_error = $e->getMessage();
        }

        if (!$pdo) {
            echo '<div class="status error">❌ ERROR: Sin conexión</div>';
            echo '<div class="detail">Error: ' . htmlspecialchars($db_error ?? 'Sin conexión') . '</div>';
        } else {
            echo '<div class="status success">✅ Conectado</div>';

            // Test 2: Información de la conexión (PDO)
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $serverVer = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?? '';
            $clientVer = $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION) ?? '';

            echo '<div class="detail"><strong>Driver:</strong> <code>' . htmlspecialchars($driver) . '</code></div>';
            echo '<div class="detail"><strong>Servidor:</strong> <code>' . htmlspecialchars($serverVer) . '</code></div>';
            echo '<div class="detail"><strong>Cliente:</strong> <code>' . htmlspecialchars($clientVer) . '</code></div>';

            // Test 3: Base de datos actual
            try {
                $row = $db->fetch('SELECT DATABASE() as db');
                if ($row) echo '<div class="detail"><strong>Base de datos:</strong> <code>' . htmlspecialchars($row['db']) . '</code></div>';
            } catch (Exception $e) {
                error_log('[test-connection.php] fetch DATABASE() failed: ' . $e->getMessage());
            }
        }
        echo '</div>';
        echo '</div>';

        if (isset($pdo) && $pdo) {
            // Test 4: Listar tablas
            echo '<div class="test-section">';
            echo '<div class="test-title"><span class="emoji">📊</span> Tablas de Base de Datos</div>';
            echo '<div class="test-content">';
            try {
                $rows = $db->fetchAll('SHOW TABLES');
                $tables = [];
                foreach ($rows as $r) {
                    $vals = array_values($r);
                    if (isset($vals[0])) $tables[] = $vals[0];
                }
            } catch (Exception $e) {
                error_log('[test-connection.php] SHOW TABLES failed: ' . $e->getMessage());
                $tables = [];
            }

            $expected_tables = ['categorias', 'clientes', 'productos', 'proveedores', 'salidas', 'usuarios', 'ventas'];
            $all_present = !empty($tables) && empty(array_diff($expected_tables, $tables));

            if ($all_present) {
                echo '<div class="status success">✅ Todas las tablas presentes (' . count($tables) . ')</div>';
            } else {
                echo '<div class="status warning">⚠️ ' . count($tables) . ' de 7 tablas encontradas</div>';
            }

            echo '<table>';
            echo '<tr><th>#</th><th>Tabla</th><th>Registros</th><th>Estado</th></tr>';

            foreach ($tables as $idx => $table) {
                try {
                    $r = $db->fetch('SELECT COUNT(*) as cnt FROM `' . str_replace('`','', $table) . '`');
                    $count = $r['cnt'] ?? 0;
                } catch (Exception $e) {
                    $count = 'Error';
                }
                $is_expected = in_array($table, $expected_tables);
                $status = $is_expected ? '✅' : '⚠️';

                echo '<tr>';
                echo '<td>' . ($idx + 1) . '</td>';
                echo '<td><code>' . htmlspecialchars($table) . '</code></td>';
                echo '<td>' . $count . '</td>';
                echo '<td>' . $status . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';
            echo '</div>';

            // Test 5: Estructura de tabla (ejemplo: productos)
            echo '<div class="test-section">';
            echo '<div class="test-title"><span class="emoji">🔎</span> Estructura de Tabla: productos</div>';
            echo '<div class="test-content">';

            try {
                $desc_rows = $db->fetchAll('DESCRIBE productos');
                if (!empty($desc_rows)) {
                    echo '<table>';
                    echo '<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>';
                    foreach ($desc_rows as $row) {
                        echo '<tr>';
                        echo '<td><code>' . htmlspecialchars($row['Field']) . '</code></td>';
                        echo '<td><code>' . htmlspecialchars($row['Type']) . '</code></td>';
                        echo '<td>' . ($row['Null'] === 'YES' ? 'Sí' : 'No') . '</td>';
                        echo '<td>' . ($row['Key'] ? '<strong>' . $row['Key'] . '</strong>' : '-') . '</td>';
                        echo '<td>' . ($row['Default'] ? '<code>' . htmlspecialchars($row['Default']) . '</code>' : '-') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            } catch (Exception $e) {
                error_log('[test-connection.php] DESCRIBE productos failed: ' . $e->getMessage());
            }
            echo '</div>';
            echo '</div>';

            // Test 6: Prueba de query
            echo '<div class="test-section">';
            echo '<div class="test-title"><span class="emoji">✔️</span> Prueba de Query</div>';
            echo '<div class="test-content">';

            try {
                $test_row = $db->fetch('SELECT COUNT(*) as total FROM productos');
                if ($test_row) {
                    echo '<div class="status success">✅ Query ejecutada exitosamente</div>';
                    echo '<div class="detail">Total de productos en BD: <strong>' . ($test_row['total'] ?? 0) . '</strong></div>';
                }
            } catch (Exception $e) {
                echo '<div class="status error">❌ Error en query: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            echo '</div>';
            echo '</div>';

            // Test 7: Información del servidor
            echo '<div class="test-section">';
            echo '<div class="test-title"><span class="emoji">ℹ️</span> Información del Servidor</div>';
            echo '<div class="test-content">';

            try {
                $variables = $db->fetchAll("SHOW VARIABLES LIKE 'version%'");
                echo '<table>';
                foreach ($variables as $var) {
                    echo '<tr>';
                    echo '<td><code>' . htmlspecialchars($var[array_keys($var)[0]]) . '</code></td>';
                    echo '<td>' . htmlspecialchars($var[array_keys($var)[1]]) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } catch (Exception $e) {
                error_log('[test-connection.php] SHOW VARIABLES failed: ' . $e->getMessage());
            }
            echo '</div>';
            echo '</div>';

            // Test final
            echo '<div class="test-section">';
            echo '<div class="test-title"><span class="emoji">✅</span> Estado General</div>';
            echo '<div class="test-content">';
            if ($all_present) {
                echo '<div class="status success">✅ Todos los tests pasaron correctamente</div>';
                echo '<div class="detail">La aplicación está lista para usar.</div>';
                echo '<div class="detail" style="margin-top: 15px;"><a href="http://localhost/Sakorms.org/Inventory-web1.5/">👉 Ir a la aplicación</a></div>';
            } else {
                echo '<div class="status warning">⚠️ Algunas tablas faltan</div>';
                echo '<div class="detail">Ejecuta el script de setup-database.php para completar la inicialización.</div>';
                echo '<div class="detail" style="margin-top: 15px;"><a href="setup-database.php">👉 Ejecutar Setup</a></div>';
            }
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="test-section">';
            echo '<div class="test-title"><span class="emoji">❌</span> Error de Conexión</div>';
            echo '<div class="test-content">';
            echo '<div class="status error">No se puede continuar sin conexión</div>';
            echo '<div class="detail">Verifica que:</div>';
            echo '<ul style="margin-left: 20px; margin-top: 10px;">';
            echo '<li>MySQL está corriendo en XAMPP</li>';
            echo '<li>Puerto 3306 es accesible</li>';
            echo '<li>Credenciales en db.php son correctas</li>';
            echo '</ul>';
            echo '</div>';
            echo '</div>';
        }
        ?>

        <div class="footer">
            <p><strong>Sakorms Inventory v1.5</strong> - Test de Conexión</p>
            <p style="margin-top: 5px;">Migración SQL Server → MySQL</p>
            <p style="margin-top: 10px; opacity: 0.7;">Por seguridad, elimina este archivo (test-connection.php) después de confirmar que todo funciona.</p>
        </div>
    </div>
</body>
</html>
