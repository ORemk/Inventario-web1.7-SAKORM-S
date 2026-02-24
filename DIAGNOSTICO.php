<?php
/**
 * Herramienta de Diagnóstico y Limpieza de Caché
 * Diagnostica problemas de JavaScript y proporciona soluciones
 */

header('Content-Type: text/html; charset=utf-8');

// Función para limpiar caché de navegador (simulado)
$cache_cleared = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear') {
    // Intentar establecer headers para limpiar caché
    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    $cache_cleared = true;
}

// Verificar integridad de archivos
$file_checks = [
    'script.js' => [
        'path' => __DIR__ . '/script.js',
        'checks' => [
            'Sin #btn' => !file_contains_string(__DIR__ . '/script.js', '#btn'),
            'toggleCalculator en línea 13' => file_contains_at_line(__DIR__ . '/script.js', 'window.toggleCalculator', 13),
            'toggleAIChat en línea 48' => file_contains_at_line(__DIR__ . '/script.js', 'window.toggleAIChat', 48),
        ]
    ],
    'main.js' => [
        'path' => __DIR__ . '/main.js',
        'checks' => [
            'Sin duplicados de eliminarProducto' => count_occurrences(__DIR__ . '/main.js', 'window.eliminarProducto = async function eliminarProducto') === 1,
            'Con protección if typeof' => file_contains_string(__DIR__ . '/main.js', 'if (typeof window.eliminarProducto === \'undefined\')'),
            'Definida solo una vez' => count_occurrences(__DIR__ . '/main.js', 'window.eliminarProducto = async function eliminarProducto') === 1,
        ]
    ]
];

function file_contains_string($filepath, $search) {
    if (!file_exists($filepath)) return false;
    $content = file_get_contents($filepath);
    return strpos($content, $search) !== false;
}

function file_contains_at_line($filepath, $search, $target_line) {
    if (!file_exists($filepath)) return false;
    $lines = file($filepath);
    if (!isset($lines[$target_line - 1])) return false;
    return strpos($lines[$target_line - 1], $search) !== false;
}

function count_occurrences($filepath, $search) {
    if (!file_exists($filepath)) return 0;
    $content = file_get_contents($filepath);
    return substr_count($content, $search);
}

$all_good = true;
foreach ($file_checks as $file) {
    foreach ($file['checks'] as $check => $result) {
        if (!$result) $all_good = false;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Diagnóstico y Limpieza - Sakorms Inventory</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .status-badge.good {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .section {
            margin-bottom: 40px;
            border-bottom: 1px solid #eee;
            padding-bottom: 30px;
        }
        
        .section:last-child {
            border-bottom: none;
        }
        
        .section h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .check-item {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 12px;
            background: #f9f9f9;
            border-left: 4px solid #ddd;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .check-item.pass {
            background: #d4edda;
            border-left-color: #28a745;
        }
        
        .check-item.fail {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .check-icon {
            font-size: 24px;
            min-width: 30px;
        }
        
        .check-content {
            flex: 1;
        }
        
        .check-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }
        
        .check-detail {
            font-size: 13px;
            color: #666;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        button {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            border: 2px solid #ddd;
        }
        
        .btn-secondary:hover {
            background: #eee;
        }
        
        .instructions {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            color: #0c5aa0;
            line-height: 1.8;
        }
        
        .instructions h3 {
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .instructions ol {
            margin-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 10px;
        }
        
        .code-block {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            overflow-x: auto;
            margin: 15px 0;
            font-size: 13px;
        }
        
        .kbd {
            background: #333;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-weight: bold;
            display: inline-block;
            margin: 0 4px;
        }
        
        .file-list {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        
        .file-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
        
        .file-list li:last-child {
            border-bottom: none;
        }
        
        .file-path {
            font-family: monospace;
            color: #333;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Diagnóstico del Sistema</h1>
            <p>Verifica y soluciona errores de JavaScript</p>
        </div>
        
        <div class="content">
            <?php if ($all_good): ?>
                <div class="status-badge good">✅ Sistema OK - Archivos correctos</div>
            <?php else: ?>
                <div class="status-badge warning">⚠️ Caché activo - Necesita limpiar</div>
            <?php endif; ?>
            
            <!-- Sección: Estado de Archivos -->
            <div class="section">
                <h2>📂 Estado de Archivos</h2>
                
                <?php foreach ($file_checks as $filename => $data): ?>
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: #667eea; font-size: 16px; margin-bottom: 15px;">📄 <?php echo $filename; ?></h3>
                        
                        <?php foreach ($data['checks'] as $check_name => $check_result): ?>
                            <div class="check-item <?php echo $check_result ? 'pass' : 'fail'; ?>">
                                <div class="check-icon">
                                    <?php echo $check_result ? '✅' : '❌'; ?>
                                </div>
                                <div class="check-content">
                                    <div class="check-name"><?php echo $check_name; ?></div>
                                    <div class="check-detail">
                                        <?php echo $check_result ? 'Verificación exitosa' : 'Probablemente en caché del navegador'; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Sección: Solución -->
            <div class="section">
                <h2>🧹 Cómo Limpiar Caché</h2>
                
                <div class="instructions">
                    <h3>Opción 1: Atajo Rápido (Recomendado)</h3>
                    <p>Presiona estas teclas en tu navegador:</p>
                    <div class="code-block">
                        <span class="kbd">Ctrl</span> + <span class="kbd">Shift</span> + <span class="kbd">Delete</span>
                    </div>
                    <p>Luego selecciona:</p>
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li>✓ Cookies y datos de sitios</li>
                        <li>✓ Imágenes y caché</li>
                    </ul>
                    <p style="margin-top: 15px;">Haz clic en "Borrar datos" y luego recarga con <span class="kbd">Ctrl</span> + <span class="kbd">Shift</span> + <span class="kbd">R</span></p>
                </div>
                
                <div class="instructions" style="margin-top: 20px;">
                    <h3>Opción 2: Hard Refresh</h3>
                    <p>Simplemente presiona:</p>
                    <div class="code-block">
                        <span class="kbd">Ctrl</span> + <span class="kbd">Shift</span> + <span class="kbd">R</span>
                    </div>
                    <p style="margin-top: 10px;">(O <span class="kbd">Ctrl</span> + <span class="kbd">F5</span> en Windows)</p>
                </div>
                
                <div class="instructions" style="margin-top: 20px;">
                    <h3>Opción 3: DevTools (F12)</h3>
                    <ol>
                        <li>Abre DevTools con <span class="kbd">F12</span></li>
                        <li>Pestaña <strong>Application</strong> o <strong>Storage</strong></li>
                        <li>En la izquierda, busca <strong>localhost</strong></li>
                        <li>Clic derecho → <strong>Clear site data</strong></li>
                        <li>Recarga con <span class="kbd">F5</span></li>
                    </ol>
                </div>
            </div>
            
            <!-- Sección: Verificación -->
            <div class="section">
                <h2>✨ Verificar Después de Limpiar</h2>
                
                <div class="instructions">
                    <h3>Paso 1: Abre la Consola (F12)</h3>
                    <p>Deberías ver:</p>
                    <div class="code-block">
🚀 ====== DOMContentLoaded Event ======
🔍 Verificando funciones globales:
  - window.toggleCalculator: ✅ Definida
  - window.toggleAIChat: ✅ Definida
✅ Script.js inicialización completada
                    </div>
                </div>
                
                <div class="instructions" style="margin-top: 20px;">
                    <h3>Paso 2: Ejecuta en la Consola</h3>
                    <div class="code-block">
window.toggleCalculator
// Debería mostrar: ƒ toggleCalculator()

window.toggleAIChat
// Debería mostrar: ƒ toggleAIChat()

eliminarProducto
// Debería mostrar: ƒ eliminarProducto(id)
                    </div>
                </div>
                
                <div class="instructions" style="margin-top: 20px;">
                    <h3>Paso 3: Verifica la Pestaña Network</h3>
                    <p>Recarga la página (F5) y en DevTools → Network:</p>
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li>✅ <strong>script.js</strong> - Status 200 (no 304)</li>
                        <li>✅ <strong>main.js</strong> - Status 200 (no 304)</li>
                        <li>✅ <strong>index.html</strong> - Status 200</li>
                    </ul>
                </div>
            </div>
            
            <!-- Sección: URLs Correctas -->
            <div class="section">
                <h2>🌐 URLs Correctas para Acceder</h2>
                
                <div class="file-list">
                    <ul style="list-style: none;">
                        <li>✅ <span class="file-path">http://localhost/Sakorms.org/Inventory-web1.5/</span></li>
                        <li>✅ <span class="file-path">http://127.0.0.1/Sakorms.org/Inventory-web1.5/</span></li>
                        <li>❌ <span class="file-path">file:///D:/XAMP/htdocs/Sakorms.org/Inventory-web1.5/</span> (NO usar)</li>
                        <li>❌ Abrir index.html directamente (NO hacer)</li>
                    </ul>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="button-group">
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn-primary">🧹 Intentar Limpiar Caché</button>
                </form>
                <button class="btn-secondary" data-onclick="navigateToUrl('http://localhost/Sakorms.org/Inventory-web1.5/')">
                    ↩️ Volver al Sistema
                </button>
            </div>
            
            <?php if ($cache_cleared): ?>
                <div class="instructions" style="margin-top: 30px;">
                    <h3>✅ Comando Ejecutado</h3>
                    <p>Se han enviado headers para limpiar caché. Ahora:</p>
                    <ol>
                        <li>Presiona <span class="kbd">Ctrl</span> + <span class="kbd">Shift</span> + <span class="kbd">R</span> para hard refresh</li>
                        <li>Cierra y reabre el navegador</li>
                        <li>Ve a <span class="file-path">http://localhost/Sakorms.org/Inventory-web1.5/</span></li>
                    </ol>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
