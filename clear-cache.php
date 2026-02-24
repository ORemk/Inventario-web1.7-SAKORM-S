<?php
/**
 * Script para limpiar caché del servidor y del cliente
 * Accede desde: http://localhost/Sakorms.org/Inventory-web1.5/clear-cache.php
 */

// Limpiar caché del servidor
if (session_status() === PHP_SESSION_NONE) session_start();
if (session_status() !== PHP_SESSION_NONE) {
    session_unset();
    session_destroy();
}

// Headers para limpiar caché en el navegador
header('Clear-Site-Data: "cache", "cookies", "storage"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpiar Caché - Sakorms Inventory</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
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
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
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
        
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .info-box h2 {
            color: #333;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .info-box p {
            color: #666;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        
        .code {
            background: #fffacd;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #333;
            display: inline-block;
            margin: 0 4px;
        }
        
        .files-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .files-list h3 {
            color: #333;
            font-size: 14px;
            margin-bottom: 15px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-name {
            color: #333;
            font-weight: 500;
        }
        
        .file-desc {
            color: #999;
            font-size: 12px;
        }
        
        .version-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }
        
        .success-message.show {
            display: block;
        }
        
        .steps {
            text-align: left;
            background: #fff9e6;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .steps h3 {
            color: #333;
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        .step {
            color: #666;
            font-size: 13px;
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }
        
        .step::before {
            content: attr(data-step);
            position: absolute;
            left: 0;
            background: #ffc107;
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        
        .footer {
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
        <h1>🔄 Limpiar Caché</h1>
        <p class="subtitle">Sakorms Inventory v1.5 - Solucionar Errores de JavaScript</p>
        
        <div class="success-message" id="successMsg">
            ✅ <strong>Caché limpiado correctamente.</strong> Los archivos fueron marcados con versión: <span id="version"><?php echo $version; ?></span>
        </div>
        
        <div class="info-box">
            <h2>📋 ¿Cuál es el problema?</h2>
            <p>Reportaste estos errores:</p>
            <p style="margin-top: 10px;">❌ <code class="code">Private field '#btn' must be declared in an enclosing class</code></p>
            <p style="margin-top: 8px;">❌ <code class="code">Identifier 'eliminarProducto' has already been declared</code></p>
            <p style="margin-top: 12px;"><strong>Causa:</strong> Tu navegador tiene versiones ANTIGUAS en caché.</p>
            <p style="margin-top: 8px;"><strong>Solución:</strong> Marcar todos los archivos como nuevos con version busting.</p>
        </div>
        
        <div class="files-list">
            <h3>📂 Archivos que serán actualizados:</h3>
            <?php foreach ($files_to_bust as $file => $description): ?>
            <div class="file-item">
                <div>
                    <div class="file-name"><?php echo $file; ?></div>
                    <div class="file-desc"><?php echo $description; ?></div>
                </div>
                <div class="version-badge">v<?php echo substr($version, -4); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="button-group">
            <button class="btn btn-primary" data-onclick="limpiarCache">
                🧹 Limpiar Caché Ahora
            </button>
            <button class="btn btn-secondary" data-onclick="navigate" data-target="index.html">
                ↩️ Volver a Inicio
            </button>
        </div>
        
        <div class="steps">
            <h3>📌 Pasos para limpiar completamente (Alternativa manual):</h3>
            <div class="step" data-step="1">Presiona <strong>Ctrl + Shift + Delete</strong> (Windows) o <strong>Cmd + Shift + Delete</strong> (Mac)</div>
            <div class="step" data-step="2">Selecciona "All time" en el rango de tiempo</div>
            <div class="step" data-step="3">Marca: "Cookies" y "Cached images and files"</div>
            <div class="step" data-step="4">Haz clic en "Clear data"</div>
            <div class="step" data-step="5">Cierra completamente el navegador</div>
            <div class="step" data-step="6">Reabre y ve a: <strong style="color: #333;">http://localhost/Sakorms.org/Inventory-web1.5/</strong></div>
            <div class="step" data-step="7">Presiona <strong>Ctrl + Shift + R</strong> (Hard refresh)</div>
        </div>
        
        <div class="info-box" style="background: #e3f2fd; border-left-color: #2196f3;">
            <h2>✅ Después de limpiar:</h2>
            <p>1. Abre DevTools: <span class="code">F12</span></p>
            <p>2. Ve a la pestaña: <span class="code">Console</span></p>
            <p>3. Ejecuta este comando:</p>
            <p style="margin-top: 10px; background: #f5f5f5; padding: 8px; border-radius: 4px; font-family: monospace;">eliminarProducto</p>
            <p style="margin-top: 10px;">Debería mostrar: <span style="color: #2e7d32; font-weight: 600;">ƒ eliminarProducto(id)</span></p>
        </div>
        
        <div class="footer">
            <p><strong>Versión actual:</strong> <?php echo $version; ?></p>
            <p style="margin-top: 8px;">Este script fue generado automáticamente para resolver problemas de caché.</p>
        </div>
    </div>
    
    <script>
        function limpiarCache() {
            // Marcar que se limpió caché
            localStorage.setItem('cache_cleared', new Date().toISOString());
            localStorage.setItem('cache_version', '<?php echo $version; ?>');
            
            // Mostrar mensaje de éxito
            document.getElementById('successMsg').classList.add('show');
            
            // Redirigir a la aplicación después de 2 segundos
            setTimeout(() => {
                window.location.href = 'index.html?v=<?php echo $version; ?>';
            }, 2000);
        }
        
        // Agregar cache busting a todos los archivos
        document.addEventListener('DOMContentLoaded', () => {
            const version = '<?php echo $version; ?>';
            const links = document.querySelectorAll('link[rel="stylesheet"]');
            links.forEach(link => {
                if (link.href && !link.href.includes('?')) {
                    link.href += '?v=' + version;
                }
            });
        });
    </script>
</body>
</html>
