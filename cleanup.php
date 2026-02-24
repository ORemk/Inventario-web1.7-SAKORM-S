<?php
/**
 * cleanup.php - Limpieza completa de caché y sesiones
 * Acceso: http://localhost/Sakorms.org/Inventory-web1.5/cleanup.php
 * 
 * ✅ Limpia todo el caché del navegador y servidor
 * ✅ Soluciona errores de caché viejo como:
 *    - Private field '#btn' must be declared
 *    - Identifier 'eliminarProducto' already declared
 */

// SIN DEPENDENCIA DE BASE DE DATOS - Limpieza pura

// Limpiar sesión
if (session_status() === PHP_SESSION_NONE) session_start();
if (session_status() !== PHP_SESSION_NONE) {
    session_unset();
    session_destroy();
}

// Headers para limpiar caché COMPLETAMENTE
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
    <title>✅ Limpieza de Caché Completada</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            color: #28a745;
            margin-bottom: 20px;
            font-size: 2.5em;
        }
        
        .success-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        .message {
            color: #333;
            font-size: 1.1em;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        
        .steps {
            text-align: left;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .step {
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
        }
        
        .step:last-child {
            border-bottom: none;
        }
        
        .step-icon {
            font-size: 1.5em;
            margin-right: 15px;
            width: 30px;
            text-align: center;
        }
        
        .step-text {
            color: #666;
        }
        
        .buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        button {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
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
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .info-box {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: left;
        }
        
        .info-box strong {
            color: #0c5460;
        }
        
        .info-box p {
            color: #0c5460;
            margin: 8px 0 0 0;
            font-size: 0.95em;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 25px;
            }
            
            h1 {
                font-size: 2em;
            }
            
            .buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1>¡Limpieza Completada!</h1>
        
        <p class="message">
            El caché de tu navegador ha sido <strong>completamente limpiado</strong>. 
            Ahora cargarás versiones frescas de todos los archivos.
        </p>
        
        <div class="info-box">
            <strong>ℹ️ Qué se limpió:</strong>
            <p>• Caché del navegador</p>
            <p>• Cookies</p>
            <p>• LocalStorage</p>
            <p>• Sesiones</p>
        </div>
        
        <div class="steps">
            <div class="step">
                <div class="step-icon">✅</div>
                <div class="step-text"><strong>Sesiones eliminadas</strong></div>
            </div>
            <div class="step">
                <div class="step-icon">✅</div>
                <div class="step-text"><strong>Cookies borradas</strong></div>
            </div>
            <div class="step">
                <div class="step-icon">✅</div>
                <div class="step-text"><strong>Storage limpiado</strong></div>
            </div>
            <div class="step">
                <div class="step-icon">✅</div>
                <div class="step-text"><strong>Caché del navegador vaciado</strong></div>
            </div>
        </div>
        
        <p style="color: #999; margin-bottom: 30px; font-size: 0.95em;">
            Cualquier error de caché viejo (como "eliminarProducto already declared" o "#btn private field") 
            ha sido eliminado del navegador.
        </p>
        
        <div class="buttons">
            <button class="btn-primary" data-onclick="abrirApp">
                📦 Abrir Aplicación
            </button>
            <button class="btn-secondary" data-onclick="volverAtrás">
                ⬅️ Volver Atrás
            </button>
        </div>
    </div>

    <script>
        // Log en consola
        console.log('%c✅ LIMPIEZA COMPLETADA', 'font-size: 16px; font-weight: bold; color: #28a745;');
        console.log('Sesiones eliminadas');
        console.log('Cookies borradas');
        console.log('LocalStorage limpiado');
        console.log('Caché del navegador vaciado');
        console.log('');
        console.log('%c📦 Abriendo aplicación...', 'font-size: 14px; color: #667eea;');
        
        // Auto-redirigir a la app después de 3 segundos
        setTimeout(() => {
            window.location.href = 'index.html';
        }, 3000);
        
        function abrirApp() {
            window.location.href = 'index.html';
        }
        
        function volverAtrás() {
            window.history.back();
        }
    </script>
</body>
</html>
