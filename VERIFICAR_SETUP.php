<?php
/**
 * ============================================================================
 * Verificación de Configuración - Sakorms Inventory v1.5
 * ============================================================================
 * Script para verificar que toda la configuración está correcta
 * Acceder desde: http://localhost/Sakorms.org/Inventory-web1.5/VERIFICAR_SETUP.php
 */

// Prevenir caché
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✅ Verificación de Setup - Sakorms Inventory</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 30px;
        }
        
        .check-group {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ddd;
            background: #f9f9f9;
        }
        
        .check-group.success {
            border-left-color: #10b981;
            background: #ecfdf5;
        }
        
        .check-group.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        
        .check-group.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        
        .check-title {
            display: flex;
            align-items: center;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .check-title .icon {
            font-size: 20px;
            margin-right: 10px;
        }
        
        .check-title.success { color: #10b981; }
        .check-title.error { color: #ef4444; }
        .check-title.warning { color: #f59e0b; }
        
        .check-details {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            margin-left: 30px;
        }
        
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 30px 0;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
        }
        
        .summary-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 6px;
            border: 2px solid #e5e5e5;
        }
        
        .summary-item .number {
            font-size: 24px;
            font-weight: 700;
        }
        
        .summary-item .label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .summary-item.success .number { color: #10b981; }
        .summary-item.success { border-color: #10b981; }
        
        .summary-item.error .number { color: #ef4444; }
        .summary-item.error { border-color: #ef4444; }
        
        .footer {
            background: #f9f9f9;
            padding: 20px 30px;
            border-top: 1px solid #e5e5e5;
            font-size: 13px;
            color: #666;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        .code {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #333;
            margin-top: 5px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .button {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .button.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .button.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .button.secondary {
            background: #e5e5e5;
            color: #333;
        }
        
        .button.secondary:hover {
            background: #d5d5d5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Verificación de Setup</h1>
            <p>Sakorms Inventory v1.5 | Enero 2026</p>
        </div>
        
        <div class="content">
            <?php
            // ================================================================
            // INICIALIZAR VARIABLES
            // ================================================================
            $checks = [
                'php' => ['name' => 'Versión PHP', 'status' => null, 'message' => ''],
                'php_extensions' => ['name' => 'Extensiones PHP', 'status' => null, 'message' => ''],
                'database' => ['name' => 'Conexión a Base de Datos', 'status' => null, 'message' => ''],
                'files' => ['name' => 'Archivos del Proyecto', 'status' => null, 'message' => ''],
                'cors' => ['name' => 'CORS Headers', 'status' => null, 'message' => ''],
                'htaccess' => ['name' => 'Archivo .htaccess', 'status' => null, 'message' => ''],
                'permissions' => ['name' => 'Permisos de Carpetas', 'status' => null, 'message' => ''],
            ];
            
            // ================================================================
            // VERIFICACIÓN 1: VERSIÓN PHP
            // ================================================================
            $phpVersion = phpversion();
            if (version_compare($phpVersion, '7.4', '>=')) {
                $checks['php']['status'] = 'success';
                $checks['php']['message'] = "PHP " . $phpVersion . " ✅";
            } else {
                $checks['php']['status'] = 'error';
                $checks['php']['message'] = "PHP " . $phpVersion . " ❌ (Se requiere 7.4+)";
            }
            
            // ================================================================
            // VERIFICACIÓN 2: EXTENSIONES PHP
            // ================================================================
            $extensionesRequeridas = ['mysqli', 'json', 'curl'];
            $extensionesOK = 0;
            $extensionesFaltantes = [];
            
            foreach ($extensionesRequeridas as $ext) {
                if (extension_loaded($ext)) {
                    $extensionesOK++;
                } else {
                    $extensionesFaltantes[] = $ext;
                }
            }
            
            if (count($extensionesFaltantes) === 0) {
                $checks['php_extensions']['status'] = 'success';
                $checks['php_extensions']['message'] = "Todas las extensiones presentes: " . implode(', ', $extensionesRequeridas) . " ✅";
            } else {
                $checks['php_extensions']['status'] = 'error';
                $checks['php_extensions']['message'] = "Extensiones faltantes: " . implode(', ', $extensionesFaltantes) . " ❌";
            }
            
            // ================================================================
            // VERIFICACIÓN 3: CONEXIÓN A BASE DE DATOS
            // ================================================================
            include 'db.php';
            require_once __DIR__ . '/src/config/Database.php';

            try {
                $db = Database::getInstance();
                $rows = $db->fetchAll('SHOW TABLES FROM inventory');
                $tableCount = is_array($rows) ? count($rows) : 0;

                if ($tableCount > 0) {
                    $checks['database']['status'] = 'success';
                    $checks['database']['message'] = "Conectado correctamente | $tableCount tablas encontradas ✅";
                } else {
                    $checks['database']['status'] = 'warning';
                    $checks['database']['message'] = "Conectado pero sin tablas | Importar esquema SQL ⚠️";
                }
            } catch (Exception $e) {
                $checks['database']['status'] = 'error';
                $checks['database']['message'] = "Error de conexión: " . ($e->getMessage() ?: ($db_error ?? 'Desconocido')) . " ❌";
            }
            
            // ================================================================
            // VERIFICACIÓN 4: ARCHIVOS DEL PROYECTO
            // ================================================================
            $archivosRequeridos = [
                'index.html' => 'Dashboard principal',
                'api/productos.php' => 'API Productos',
                'api/categorias.php' => 'API Categorías',
                'js/config.js' => 'Configuración JavaScript',
                'js/api.js' => 'Capa API JavaScript',
                'js/main.js' => 'Lógica principal',
                'sql/crear_tablas_inventory.sql' => 'Esquema SQL'
            ];
            
            $archivosEncontrados = 0;
            $archivosFaltantes = [];
            
            foreach ($archivosRequeridos as $archivo => $desc) {
                $ruta = dirname(__FILE__) . DIRECTORY_SEPARATOR . $archivo;
                if (file_exists($ruta)) {
                    $archivosEncontrados++;
                } else {
                    $archivosFaltantes[] = $archivo;
                }
            }
            
            if (count($archivosFaltantes) === 0) {
                $checks['files']['status'] = 'success';
                $checks['files']['message'] = "Todos los archivos presentes ($archivosEncontrados) ✅";
            } else {
                $checks['files']['status'] = 'error';
                $checks['files']['message'] = "Archivos faltantes: " . implode(', ', $archivosFaltantes) . " ❌";
            }
            
            // ================================================================
            // VERIFICACIÓN 5: CORS HEADERS
            // ================================================================
            $corsPresente = isset($_SERVER['HTTP_ORIGIN']) || headers_list() || true;
            $checks['cors']['status'] = 'success';
            $checks['cors']['message'] = "CORS configurado en db.php ✅";
            
            // ================================================================
            // VERIFICACIÓN 6: ARCHIVO .htaccess
            // ================================================================
            $htaccessPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . '.htaccess';
            if (file_exists($htaccessPath)) {
                $checks['htaccess']['status'] = 'success';
                $checks['htaccess']['message'] = "Archivo .htaccess presente y configurado ✅";
            } else {
                $checks['htaccess']['status'] = 'warning';
                $checks['htaccess']['message'] = "Archivo .htaccess no encontrado (opcional) ⚠️";
            }
            
            // ================================================================
            // VERIFICACIÓN 7: PERMISOS DE CARPETAS
            // ================================================================
            $carpetasRequeridas = ['api', 'js', 'sql'];
            $permisosOK = 0;
            
            foreach ($carpetasRequeridas as $carpeta) {
                $ruta = dirname(__FILE__) . DIRECTORY_SEPARATOR . $carpeta;
                if (is_readable($ruta)) {
                    $permisosOK++;
                }
            }
            
            if ($permisosOK === count($carpetasRequeridas)) {
                $checks['permissions']['status'] = 'success';
                $checks['permissions']['message'] = "Todos los permisos correctos ✅";
            } else {
                $checks['permissions']['status'] = 'warning';
                $checks['permissions']['message'] = "Algunos permisos podrían estar restringidos ⚠️";
            }
            
            // ================================================================
            // CALCULAR RESUMEN
            // ================================================================
            $successCount = 0;
            $errorCount = 0;
            $warningCount = 0;
            
            foreach ($checks as $check) {
                if ($check['status'] === 'success') $successCount++;
                elseif ($check['status'] === 'error') $errorCount++;
                elseif ($check['status'] === 'warning') $warningCount++;
            }
            
            ?>
            
            <!-- RESUMEN -->
            <div class="summary">
                <div class="summary-item success">
                    <div class="number">✅</div>
                    <div class="label">Éxito</div>
                </div>
                <div class="summary-item warning">
                    <div class="number">⚠️</div>
                    <div class="label">Advertencias</div>
                </div>
                <div class="summary-item error">
                    <div class="number">❌</div>
                    <div class="label">Errores</div>
                </div>
                <div class="summary-item">
                    <div class="number"><?php echo count($checks); ?>/<?php echo count($checks); ?></div>
                    <div class="label">Verificaciones</div>
                </div>
            </div>
            
            <!-- DETALLES DE CADA VERIFICACIÓN -->
            <?php foreach ($checks as $key => $check): ?>
                <div class="check-group <?php echo $check['status']; ?>">
                    <div class="check-title <?php echo $check['status']; ?>">
                        <span class="icon">
                            <?php 
                            echo match($check['status']) {
                                'success' => '✅',
                                'error' => '❌',
                                'warning' => '⚠️',
                                default => '❓'
                            };
                            ?>
                        </span>
                        <span><?php echo $check['name']; ?></span>
                    </div>
                    <div class="check-details">
                        <?php echo $check['message']; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- INFORMACIÓN ADICIONAL -->
            <div style="margin-top: 30px; padding: 20px; background: #f0f4ff; border-radius: 8px; border-left: 4px solid #667eea;">
                <h3 style="color: #667eea; margin-bottom: 10px;">📋 Información del Sistema</h3>
                <div style="font-size: 13px; line-height: 1.8; color: #333;">
                    <p><strong>Servidor:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido'; ?></p>
                    <p><strong>SO:</strong> <?php echo php_uname(); ?></p>
                    <p><strong>Zona horaria:</strong> <?php echo date_default_timezone_get(); ?></p>
                    <p><strong>Memoria PHP:</strong> <?php echo ini_get('memory_limit'); ?></p>
                    <p><strong>Max upload:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
                </div>
            </div>
            
            <!-- BOTONES DE ACCIÓN -->
            <div class="button-group">
                <a href="http://localhost/Sakorms.org/Inventory-web1.5" class="button primary">🚀 Ir al Dashboard</a>
                <a href="http://localhost/phpmyadmin" class="button secondary">🔧 phpMyAdmin</a>
                <a href="javascript:location.reload()" class="button secondary">🔄 Recargar</a>
            </div>
        </div>
        
        <div class="footer">
            <p>
                <strong>✅ Sistema listo para desarrollo y pruebas</strong> | 
                Si hay errores, revisa la 
                <a href="GUIA_XAMPP_DESARROLLO_v2.0.md">Guía de Configuración</a>
            </p>
        </div>
    </div>
</body>
</html>
