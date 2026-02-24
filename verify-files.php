<?php
/**
 * verify-files.php - Verificación de archivos y estado del servidor
 * Fecha: 28 de Enero 2026
 */

// Obtener ruta base
$basePath = dirname(__FILE__);
$projectName = basename(dirname(__FILE__));
$baseURL = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Archivos - Sakorms</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 30px;
            max-width: 800px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            background: #667eea;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .file-item {
            display: flex;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 5px;
            background: #f5f5f5;
        }
        .file-item.found {
            background: #e8f5e9;
        }
        .file-item.missing {
            background: #ffebee;
        }
        .status-icon {
            margin-right: 10px;
            font-weight: bold;
            width: 20px;
        }
        .status-icon.ok { color: #4caf50; }
        .status-icon.error { color: #f44336; }
        .file-name {
            flex: 1;
            font-family: monospace;
            font-size: 13px;
        }
        .file-size {
            color: #999;
            font-size: 12px;
            margin-left: 10px;
        }
        .summary {
            background: #f9f9f9;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .summary-item:last-child {
            border-bottom: none;
        }
        .summary-value {
            font-weight: 600;
            color: #667eea;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn {
            flex: 1;
            min-width: 150px;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
        }
        .btn-secondary:hover {
            background: #efefef;
            transform: translateY(-2px);
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #1565c0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Verificación de Archivos</h1>
        <p class="subtitle">Sakorms Inventory System v1.5</p>

        <div class="info-box">
            <strong>ℹ️ Información:</strong> Esta página verifica que todos los archivos necesarios estén presentes y listos para usar.
        </div>

        <?php
        // Arrays de archivos a verificar
        $cssFiles = ['app.css', 'style.css', 'custom.css', 'ux-improvements.css', 'login.css'];
        $jsFiles = ['config.js', 'api.js', 'ui.js', 'calculator.js', 'auth.js', 'ai.js', 'sections.js', 'test.js', 'helpers.js', 'verification-cleanup.js'];
        $mainFiles = ['script.js', 'main.js', 'index.html'];
        $phpFiles = ['db.php', 'productos.php', 'categorias.php', 'clientes.php', 'proveedores.php', 'usuarios.php', 'ventas.php'];

        $totalFound = 0;
        $totalMissing = 0;

        // Función para mostrar archivo
        function showFile($path, $basePath, &$found, &$missing) {
            $fullPath = $basePath . '/' . $path;
            if (file_exists($fullPath)) {
                $size = filesize($fullPath);
                $sizeStr = $size < 1024 ? $size . 'B' : round($size / 1024, 1) . 'KB';
                echo '<div class="file-item found">';
                echo '<div class="status-icon ok">✅</div>';
                echo '<div class="file-name">' . htmlspecialchars($path) . '</div>';
                echo '<div class="file-size">(' . $sizeStr . ')</div>';
                echo '</div>';
                $found++;
            } else {
                echo '<div class="file-item missing">';
                echo '<div class="status-icon error">❌</div>';
                echo '<div class="file-name">' . htmlspecialchars($path) . '</div>';
                echo '</div>';
                $missing++;
            }
        }

        // CSS Files
        echo '<div class="section">';
        echo '<div class="section-title">📋 Archivos CSS (' . count($cssFiles) . ')</div>';
        $cssDone = 0;
        $cssMissing = 0;
        foreach ($cssFiles as $file) {
            showFile($file, $basePath, $cssDone, $cssMissing);
        }
        $totalFound += $cssDone;
        $totalMissing += $cssMissing;
        echo '</div>';

        // JS Files
        echo '<div class="section">';
        echo '<div class="section-title">📦 Módulos JavaScript (' . count($jsFiles) . ')</div>';
        $jsDone = 0;
        $jsMissing = 0;
        foreach ($jsFiles as $file) {
            showFile('js/' . $file, $basePath, $jsDone, $jsMissing);
        }
        $totalFound += $jsDone;
        $totalMissing += $jsMissing;
        echo '</div>';

        // Main Files
        echo '<div class="section">';
        echo '<div class="section-title">🔧 Archivos Principales (' . count($mainFiles) . ')</div>';
        $mainDone = 0;
        $mainMissing = 0;
        foreach ($mainFiles as $file) {
            showFile($file, $basePath, $mainDone, $mainMissing);
        }
        $totalFound += $mainDone;
        $totalMissing += $mainMissing;
        echo '</div>';

        // PHP Files
        echo '<div class="section">';
        echo '<div class="section-title">⚙️ Archivos PHP (' . count($phpFiles) . ')</div>';
        $phpDone = 0;
        $phpMissing = 0;
        foreach ($phpFiles as $file) {
            showFile($file, $basePath, $phpDone, $phpMissing);
        }
        $totalFound += $phpDone;
        $totalMissing += $phpMissing;
        echo '</div>';

        // Database Files
        echo '<div class="section">';
        echo '<div class="section-title">🗄️ SQL</div>';
        $sqlDone = 0;
        $sqlMissing = 0;
        showFile('sql/crear_tablas_inventory.sql', $basePath, $sqlDone, $sqlMissing);
        $totalFound += $sqlDone;
        $totalMissing += $sqlMissing;
        echo '</div>';

        // Summary
        $percentage = $totalFound > 0 ? round(($totalFound / ($totalFound + $totalMissing)) * 100, 1) : 0;
        $statusColor = $totalMissing === 0 ? '#4caf50' : '#ff9800';
        $statusText = $totalMissing === 0 ? 'CORRECTO ✅' : 'FALTAN ARCHIVOS ⚠️';

        echo '<div class="summary">';
        echo '<div class="summary-item">';
        echo '<span>Archivos Encontrados:</span>';
        echo '<span class="summary-value" style="color: #4caf50;">' . $totalFound . '</span>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<span>Archivos Faltantes:</span>';
        echo '<span class="summary-value" style="color: ' . ($totalMissing > 0 ? '#f44336' : '#4caf50') . ';">' . $totalMissing . '</span>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<span>Integridad del Proyecto:</span>';
        echo '<span class="summary-value" style="color: ' . $statusColor . ';">' . $percentage . '%</span>';
        echo '</div>';
        echo '<div class="summary-item" style="margin-top: 10px; padding-top: 10px; border-top: 2px solid #e0e0e0;">';
        echo '<strong>Estado General:</strong>';
        echo '<strong class="summary-value" style="color: ' . $statusColor . '; font-size: 16px;">' . $statusText . '</strong>';
        echo '</div>';
        echo '</div>';

        // Información del servidor
        echo '<div class="section">';
        echo '<div class="section-title">🖥️ Información del Servidor</div>';
        echo '<div class="file-item">';
        echo '<div class="file-name"><strong>PHP Version:</strong> ' . phpversion() . '</div>';
        echo '</div>';
        echo '<div class="file-item">';
        echo '<div class="file-name"><strong>Servidor Web:</strong> ' . (isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Desconocido') . '</div>';
        echo '</div>';
        echo '<div class="file-item">';
        echo '<div class="file-name"><strong>URL Base:</strong> ' . htmlspecialchars($baseURL) . '/</div>';
        echo '</div>';
        echo '<div class="file-item">';
        echo '<div class="file-name"><strong>Directorio Base:</strong> ' . htmlspecialchars($basePath) . '</div>';
        echo '</div>';
        echo '</div>';
        ?>

        <div class="action-buttons">
            <a class="btn btn-primary" href="<?php echo htmlspecialchars($baseURL); ?>/index.html">
                🚀 Abrir Aplicación
            </a>
            <a class="btn btn-secondary" href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                🔄 Actualizar Verificación
            </a>
        </div>

        <div class="footer">
            <p>Verificación de Arquivos - Sakorms Inventory System v1.5</p>
            <p>Última actualización: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>
    <script src="js/inline-handler-polyfill.js" defer></script>
</body>
</html>
