<?php
/**
 * test-apache.php - Diagnóstico de Apache y rutas
 */

$baseURL = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
$baseDir = dirname(__FILE__);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico Apache</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .ok { border-left: 4px solid #4caf50; }
        .error { border-left: 4px solid #f44336; }
        h2 { color: #333; margin-top: 0; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
        .status { font-weight: bold; }
        .success { color: #4caf50; }
        .fail { color: #f44336; }
    </style>
</head>
<body>
    <h1>🔧 Diagnóstico de Apache y Rutas</h1>

    <div class="box ok">
        <h2>📋 Información del Servidor</h2>
        <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
        <p><strong>HTTP Host:</strong> <?php echo $_SERVER['HTTP_HOST']; ?></p>
        <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></p>
        <p><strong>Script Filename:</strong> <?php echo $_SERVER['SCRIPT_FILENAME']; ?></p>
        <p><strong>Request URI:</strong> <?php echo $_SERVER['REQUEST_URI']; ?></p>
        <p><strong>Base URL:</strong> <?php echo $baseURL; ?></p>
        <p><strong>Base Dir:</strong> <?php echo $baseDir; ?></p>
    </div>

    <div class="box ok">
        <h2>✅ Verificación de Archivos Críticos</h2>
        <?php
        $files = ['index.html', 'app.css', 'script.js', 'main.js', 'js/config.js', 'js/api.js'];
        foreach ($files as $file) {
            $path = $baseDir . '/' . $file;
            $exists = file_exists($path);
            $status = $exists ? '<span class="success">✅ OK</span>' : '<span class="fail">❌ FALTANTE</span>';
            echo "<p>$file: $status</p>";
        }
        ?>
    </div>

    <div class="box ok">
        <h2>🔗 URLs de Acceso</h2>
        <p><a href="<?php echo $baseURL; ?>/index.html" target="_blank">📱 Aplicación Principal</a></p>
        <p><a href="<?php echo $baseURL; ?>/verify-files.php" target="_blank">🎯 Centro de Resolución</a></p>
        <p><a href="<?php echo $baseURL; ?>/verify-files.php" target="_blank">📋 Verificador de Archivos</a></p>
    </div>

    <div class="box ok">
        <h2>✅ Módulos Apache Activos</h2>
        <?php
        $modules = ['mod_rewrite', 'mod_headers', 'mod_deflate', 'mod_expires', 'mod_mime'];
        $loaded = apache_get_modules();
        foreach ($modules as $module) {
            $active = in_array($module, $loaded) ? '<span class="success">✅ ACTIVO</span>' : '<span class="fail">❌ INACTIVO</span>';
            echo "<p>$module: $active</p>";
        }
        ?>
    </div>

    <div class="box ok">
        <h2>💬 Instrucciones de Resolución</h2>
        <ol>
            <li>Si ves todos los archivos con ✅, Apache está configurado correctamente</li>
            <li>Intenta acceder a: <a href="<?php echo $baseURL; ?>/index.html"><?php echo $baseURL; ?>/index.html</a></li>
            <li>Si aún ves 404, cierra navegador y limpia caché (Ctrl+Shift+Delete)</li>
            <li>Recarga con Ctrl+Shift+R</li>
        </ol>
    </div>

</body>
</html>
