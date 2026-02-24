<?php
/**
 * SQL_SERVER_VERIFICATION.php
 * Verifica que el sistema está correctamente configurado para SQL Server 2025
 */

error_reporting(E_ALL);
// Mostrar errores en pantalla solo en entornos de desarrollo o si se solicita con ?debug=1
$showErrors = (getenv('APP_ENV') !== 'production') || (isset($_GET['debug']) && $_GET['debug'] === '1');
ini_set('display_errors', $showErrors ? 1 : 0);
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/db.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación SQL Server - Sakorms Inventory</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
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
            margin-bottom: 5px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .check {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid #ccc;
        }
        .check.pass {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .check.fail {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .check.info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
        }
        .check-icon {
            font-size: 24px;
            margin-right: 15px;
            min-width: 30px;
        }
        .check-text {
            flex: 1;
        }
        .check-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .check-detail {
            font-size: 13px;
            opacity: 0.8;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            color: #333;
        }
        .summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }
        .summary-stat {
            display: inline-block;
            margin-right: 30px;
            margin-bottom: 10px;
        }
        .summary-number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }
        .summary-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Verificación de Configuración SQL Server</h1>
            <p>Sakorms Inventory v1.5 - SQL Server 2025 Edition</p>
        </div>

        <div class="content">
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:18px;flex-wrap:wrap;">
                <button id="btn-refresh" style="padding:10px 14px;border-radius:6px;background:#667eea;color:#fff;border:0;cursor:pointer;">🔄 Refrescar ahora</button>
                <button id="btn-toggle-auto" style="padding:10px 14px;border-radius:6px;background:#17a2b8;color:#fff;border:0;cursor:pointer;">⏱️ Auto-Refresh: OFF</button>
                <button id="btn-open-diagn" style="padding:10px 14px;border-radius:6px;border:1px solid #667eea;background:#fff;color:#667eea;cursor:pointer;">🔧 Abrir Diagnóstico</button>
                <button id="btn-copy-conn" style="padding:10px 14px;border-radius:6px;border:1px dashed #667eea;background:#fff;color:#667eea;cursor:pointer;">📋 Copiar Cadena</button>
                <button id="btn-fix-issues" style="padding:10px 14px;border-radius:6px;background:#ffb74d;color:#fff;border:0;cursor:pointer;">🛠️ Solucionar Problemas</button>
                <button id="btn-download-json" style="padding:10px 14px;border-radius:6px;background:#43cea2;color:#fff;border:0;cursor:pointer;">📥 Descargar Informe</button>
                <div id="last-updated" style="margin-left:auto;color:#666;font-size:13px"></div>
            </div>

            <?php
            $checks_passed = 0;
            $checks_total = 0;
            $tables_found = 0;

            // ==== SECCIÓN 1: PHP EXTENSIONS ====
            echo '<div class="section">';
            echo '<div class="section-title">📦 Extensiones de PHP Requeridas</div>';

            $required_extensions = ['sqlsrv', 'json'];
            foreach ($required_extensions as $ext) {
                $checks_total++;
                if (extension_loaded($ext)) {
                    $checks_passed++;
                    $version = phpversion($ext) ?: '(sin versión)';
                    echo '<div class="check pass">';
                    echo '<div class="check-icon">✅</div>';
                    echo '<div class="check-text">';
                    echo '<div class="check-title">' . strtoupper($ext) . ' instalado</div>';
                    echo '<div class="check-detail">Versión: ' . htmlspecialchars($version) . '</div>';
                    echo '</div></div>';
                } else {
                    echo '<div class="check fail">';
                    echo '<div class="check-icon">❌</div>';
                    echo '<div class="check-text">';
                    echo '<div class="check-title">' . strtoupper($ext) . ' NO ENCONTRADO</div>';
                    echo '<div class="check-detail">Este driver es requerido. Instala con: pecl install ' . $ext . '</div>';
                    echo '</div></div>';
                }
            }

            // Extensión ODBC como fallback
            $checks_total++;
            if (extension_loaded('odbc')) {
                $checks_passed++;
                echo '<div class="check pass">';
                echo '<div class="check-icon">✅</div>';
                echo '<div class="check-text">';
                echo '<div class="check-title">ODBC (Fallback) instalado</div>';
                echo '<div class="check-detail">Se usará si sqlsrv no está disponible</div>';
                echo '</div></div>';
            } else {
                echo '<div class="check info">';
                echo '<div class="check-icon">ℹ️</div>';
                echo '<div class="check-text">';
                echo '<div class="check-title">ODBC (Fallback) no encontrado</div>';
                echo '<div class="check-detail">Útil como respaldo si sqlsrv falla</div>';
                echo '</div></div>';
            }

            echo '</div>';

            // ==== SECCIÓN 2: DATABASE CONNECTION ====
            echo '<div class="section">';
            echo '<div class="section-title">🗄️ Conexión a Base de Datos</div>';

            $checks_total++;
            if ($conn) {
                $checks_passed++;
                echo '<div class="check pass">';
                echo '<div class="check-icon">✅</div>';
                echo '<div class="check-text">';
                echo '<div class="check-title">Conexión a SQL Server Exitosa</div>';
                echo '<div class="check-detail">Servidor: <code>sakorms</code> | Base de datos: <code>inventory</code></div>';
                echo '</div></div>';

                // ==== SECCIÓN 3: TABLES ====
                echo '</div><div class="section">';
                echo '<div class="section-title">📊 Tablas de Base de Datos</div>';

                try {
                    $query = "SHOW TABLES";
                    $stmt = $conn->query($query);
                    $tables = [];
                    $expected_tables = ['categorias', 'clientes', 'productos', 'proveedores', 'salidas', 'usuarios', 'ventas'];
                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                        $tables[] = $row[0];
                    }
                    $tables_found = count($tables);
                    // Verificar tablas esperadas
                    $checks_total++;
                    if (count($tables) === 7 && empty(array_diff($expected_tables, $tables))) {
                        $checks_passed++;
                        echo '<div class="check pass">';
                        echo '<div class="check-icon">✅</div>';
                        echo '<div class="check-text">';
                        echo '<div class="check-title">Todas las 7 tablas encontradas</div>';
                        echo '<div class="check-detail">' . implode(', ', $tables) . '</div>';
                        echo '</div></div>';
                    } else {
                        echo '<div class="check fail">';
                        echo '<div class="check-icon">❌</div>';
                        echo '<div class="check-text">';
                        echo '<div class="check-title">Solo ' . count($tables) . ' de 7 tablas encontradas</div>';
                        echo '<div class="check-detail">Encontradas: ' . (count($tables) > 0 ? implode(', ', $tables) : 'Ninguna') . '</div>';
                        echo '</div></div>';
                    }
                    // Mostrar tabla de contenidos
                    if (!empty($tables)) {
                        echo '<table>';
                        echo '<tr><th>#</th><th>Tabla</th><th>Registros</th><th>Estado</th></tr>';
                        foreach ($tables as $idx => $table) {
                            $count_query = "SELECT COUNT(*) as cnt FROM `" . $table . "`";
                            $count = 'Error';
                            try {
                                $count_stmt = $conn->query($count_query);
                                $count_row = $count_stmt->fetch(PDO::FETCH_ASSOC);
                                $count = $count_row ? $count_row['cnt'] : '0';
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
                    }
                    
                } catch (Exception $e) {
                    echo '<div class="check fail">';
                    echo '<div class="check-icon">❌</div>';
                    echo '<div class="check-text">';
                    echo '<div class="check-title">Error al listar tablas</div>';
                    $errMsg = $showErrors ? htmlspecialchars($e->getMessage()) : 'Hubo un error al listar las tablas. Revisa los logs del servidor.';
                    echo '<div class="check-detail">' . $errMsg . '</div>';
                    echo '</div></div>';
                }

                // ==== SECCIÓN 4: FUNCIONES AUXILIARES ====
                echo '</div><div class="section">';
                echo '<div class="section-title">⚙️ Funciones de Base de Datos</div>';

                $functions = ['executeQuery', 'fetchAll', 'fetchOne'];
                foreach ($functions as $func) {
                    $checks_total++;
                    if (function_exists($func)) {
                        $checks_passed++;
                        echo '<div class="check pass">';
                        echo '<div class="check-icon">✅</div>';
                        echo '<div class="check-text">';
                        echo '<div class="check-title"><code>' . $func . '()</code> disponible</div>';
                        echo '</div></div>';
                    } else {
                        echo '<div class="check fail">';
                        echo '<div class="check-icon">❌</div>';
                        echo '<div class="check-text">';
                        echo '<div class="check-title"><code>' . $func . '()</code> NO ENCONTRADA</div>';
                        echo '<div class="check-detail">Asegúrate de incluir db.php</div>';
                        echo '</div></div>';
                    }
                }

            } else {
                // No hay conexión
                echo '<div class="check fail">';
                echo '<div class="check-icon">❌</div>';
                echo '<div class="check-text">';
                echo '<div class="check-title">Error de Conexión a SQL Server</div>';
                if (isset($db_error)) {
                    echo '<div class="check-detail">' . htmlspecialchars($db_error) . '</div>';
                }
                echo '</div></div>';
            }

            echo '</div>';
            // Contenedor para actualización dinámica
            echo '<div id="live-updates"></div>';
            // ==== RESUMEN FINAL ====
            echo '<div class="summary">';
            echo '<div style="margin-bottom: 20px;">';
            echo '<div class="section-title">📋 Resumen de Verificación</div>';
            echo '</div>';
            
            $percentage = round(($checks_passed / $checks_total) * 100);
            
            echo '<div class="summary-stat">';
            echo '<div class="summary-number">' . $checks_passed . '/' . $checks_total . '</div>';
            echo '<div class="summary-label">Verificaciones Pasadas</div>';
            echo '</div>';
            
            echo '<div class="summary-stat">';
            echo '<div class="summary-number">' . $percentage . '%</div>';
            echo '<div class="summary-label">Completitud</div>';
            echo '</div>';
            
            echo '<div class="summary-stat">';
            echo '<div class="summary-number">' . $tables_found . '/7</div>';
            echo '<div class="summary-label">Tablas Encontradas</div>';
            echo '</div>';
            
            echo '<div style="clear: both; margin-top: 15px; font-size: 14px;">';
            if ($percentage === 100 && $tables_found === 7) {
                echo '<strong style="color: #28a745;">✅ El sistema está correctamente configurado</strong><br>';
                echo '<a href="index.html" style="margin-top: 10px; display: inline-block;">👉 Ir a la Aplicación</a>';
            } elseif ($percentage >= 80) {
                echo '<strong style="color: #ffc107;">⚠️ El sistema está parcialmente configurado</strong><br>';
                echo 'Revisa los errores arriba antes de usar la aplicación.';
            } else {
                echo '<strong style="color: #dc3545;">❌ El sistema no está configurado correctamente</strong><br>';
                echo 'Soluciona los errores antes de continuar.';
            }
            echo '</div>';
            
            echo '</div>';
            ?>
        </div>
    </div>

    <script>
    (function(){
        const endpoint = 'test_bd.php?json=1';
        const sseEndpoint = 'sse_bd.php';
        const btnRefresh = document.getElementById('btn-refresh');
        const btnToggle = document.getElementById('btn-toggle-auto');
        const btnOpen = document.getElementById('btn-open-diagn');
        const btnCopy = document.getElementById('btn-copy-conn');
        const btnFix = document.getElementById('btn-fix-issues');
        const btnDownload = document.getElementById('btn-download-json');
        const lastUpdated = document.getElementById('last-updated');
        const liveContainer = document.getElementById('live-updates');

        let auto = false;
        let intervalId = null;
        let eventSource = null;
        let lastData = null;

        function render(data) {
            lastData = data;
            lastUpdated.textContent = 'Última actualización: ' + (new Date()).toLocaleString();

            // Extensiones
            let html = '';
            html += '<div class="section"><div class="section-title">📦 Extensiones de PHP (en vivo)</div>';
            const required = data.required_extensions || {};
            for (const key in required) {
                const info = required[key];
                html += '<div class="check ' + (info.loaded ? 'pass' : 'fail') + '">';
                html += '<div class="check-icon">' + (info.loaded ? '✅' : '❌') + '</div>';
                html += '<div class="check-text"><div class="check-title">' + key.toUpperCase() + (info.version ? ' - ' + info.version : '') + '</div></div></div>';
            }
            html += '</div>';

            // Conexión
            html += '<div class="section"><div class="section-title">🗄️ Conexión a Base de Datos (en vivo)</div>';
            if (data.connected) {
                html += '<div class="check pass"><div class="check-icon">✅</div><div class="check-text"><div class="check-title">Conexión establecida</div><div class="check-detail">Método: ' + (data.connection_method || '(desconocido)') + '</div></div></div>';
            } else {
                html += '<div class="check fail"><div class="check-icon">❌</div><div class="check-text"><div class="check-title">Sin conexión</div><div class="check-detail">' + (data.db_error || 'No se pudo conectar') + '</div></div></div>';
            }
            html += '</div>';

            // Tablas
            html += '<div class="section"><div class="section-title">📊 Tablas de Base de Datos (en vivo)</div>';
            if (Array.isArray(data.table_details) && data.table_details.length) {
                html += '<table><tr><th>#</th><th>Tabla</th><th>Registros</th><th>Estado</th></tr>';
                data.table_details.forEach((t,i)=>{
                    html += '<tr><td>'+(i+1)+'</td><td><code>'+t.name+'</code></td><td>'+ (t.count===null? 'Error' : t.count) +'</td><td>' + (t.expected ? '✅' : '⚠️') + '</td></tr>';
                });
                html += '</table>';
            } else {
                html += '<div class="check info"><div class="check-icon">ℹ️</div><div class="check-text"><div class="check-title">No hay tablas disponibles</div></div></div>';
            }
            html += '</div>';

            // Funciones
            html += '<div class="section"><div class="section-title">⚙️ Funciones y Helpers</div>';
            const helpers = data.helpers || {};
            for (const f in helpers) {
                const ok = helpers[f];
                html += '<div class="check '+(ok? 'pass' : 'fail')+'"><div class="check-icon">'+(ok? '✅':'❌')+'</div><div class="check-text"><div class="check-title"><code>'+f+'()</code> '+(ok? 'disponible':'NO ENCONTRADA')+'</div></div></div>';
            }
            html += '</div>';

            // Resumen
            html += '<div class="section"><div class="section-title">📋 Resumen</div>';
            if (data.summary) {
                html += '<div class="summary" style="margin-top:10px;padding:12px;background:#f4f4f4;border-radius:6px">';
                html += '<div class="summary-stat"><div class="summary-number">'+(data.summary.passed||0)+'/'+(data.summary.total||0)+'</div><div class="summary-label">Verificaciones Pasadas</div></div>';
                html += '<div class="summary-stat"><div class="summary-number">'+(data.summary.percent||0)+'%</div><div class="summary-label">Completitud</div></div>';
                html += '</div>';
            }
            html += '</div>';

            liveContainer.innerHTML = html;
        }

        async function fetchStatus() {
            try {
                const r = await fetch(endpoint, {cache: 'no-store'});
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const j = await r.json();
                render(j);
                return j;
            } catch (err) {
                liveContainer.innerHTML = '<div class="check fail"><div class="check-icon">❌</div><div class="check-text"><div class="check-title">Error al obtener estado</div><div class="check-detail">'+err.message+'</div></div></div>';
                console.error('Fetch error:', err);
                return null;
            }
        }

        function startPoll() {
            if (intervalId) return;
            intervalId = setInterval(fetchStatus, 10000);
            fetchStatus();
            btnToggle.textContent = '⏱️ Auto-Refresh: ON (poll)';
        }

        function stopPoll() {
            if (intervalId) { clearInterval(intervalId); intervalId = null; }
            btnToggle.textContent = '⏱️ Auto-Refresh: OFF';
        }

        function startSSE() {
            if (!('EventSource' in window)) { startPoll(); return; }
            if (eventSource) return;
            eventSource = new EventSource(sseEndpoint);
            eventSource.addEventListener('diag', (e) => {
                try { const d = JSON.parse(e.data); render(d); } catch(ex) { console.error('SSE parse error', ex); }
            });
            eventSource.onerror = (err) => {
                console.error('SSE error', err);
                // Si falla, hacer fallback a polling
                stopSSE(); startPoll();
            };
            btnToggle.textContent = '⏱️ Auto-Refresh: ON (SSE)';
        }

        function stopSSE() {
            if (!eventSource) return;
            eventSource.close();
            eventSource = null;
            btnToggle.textContent = '⏱️ Auto-Refresh: OFF';
        }

        btnRefresh.addEventListener('click', ()=>{ fetchStatus(); });
        btnOpen.addEventListener('click', ()=>{ window.open('test_bd.php','_blank'); });
        btnDownload.addEventListener('click', ()=>{
            if (!lastData) { if (typeof UI !== 'undefined' && UI.toast) UI.toast('No hay datos para descargar. Haz refresh primero.', 'warn'); else console.warn('No hay datos para descargar. Haz refresh primero.'); return; }
            const blob = new Blob([JSON.stringify(lastData, null, 2)], {type:'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = 'diagnostico_bd_'+(new Date()).toISOString().slice(0,19).replace(/[:T]/g,'-')+'.json';
            a.click(); setTimeout(()=>URL.revokeObjectURL(url),5000);
        });

        // Copiar cadena de conexión enmascarada
        if (btnCopy) {
            btnCopy.addEventListener('click', ()=>{
                const txt = window.__SAKORMS_CONN_MASKED ? typeof window.__SAKORMS_CONN_MASKED === 'string' ? window.__SAKORMS_CONN_MASKED : JSON.stringify(window.__SAKORMS_CONN_MASKED) : 'No disponible';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(txt).then(()=>{
                        if (typeof UI !== 'undefined' && UI.toast) UI.toast('Cadena de conexión copiada al portapapeles', 'success');
                        else console.info('Cadena copiada: ' + txt);
                    }).catch(err=>{ if (typeof UI !== 'undefined' && UI.showDialog) UI.showDialog({ type:'error', title: 'Error', message: 'No se pudo copiar: ' + err }); else console.error('No se pudo copiar: ', err); });
                } else {
                    prompt('Cadena de conexión (copiar manual):', txt);
                }
            });
        }

        if (btnFix) {
            btnFix.addEventListener('click', ()=>{
                const message = '<ul style="text-align:left">\n<li>Verifica que SQL Server tenga TCP/IP habilitado (SQL Server Configuration Manager).</li>\n<li>Revisa que el puerto 1433 esté accesible y no bloqueado por firewall.</li>\n<li>Si usas Integrated Security, comprueba la cuenta de servicio de Apache.</li>\n<li>Prueba con credenciales SQL (DB_UID/DB_PWD) temporalmente para diagnosticar autenticación.</li>\n<li>Revisa el logfile de Apache (error.log) para ver errores 500 en endpoints.</li>\n</ul>';
                if (typeof UI !== 'undefined' && UI.showDialog) {
                    UI.showDialog({ title: '🔧 Solucionar Problemas', icon: '🛠️', message: message, buttons: [{ text: 'Cerrar', action: 'close' }] });
                } else {
                    console.warn('Solucionar problemas: - Verificar TCP/IP y firewall - Revisar permisos de cuenta de Apache - Probar autenticación SQL con credenciales');
                }
            });
        }

        btnToggle.addEventListener('click', ()=>{
            auto = !auto;
            if (auto) {
                // preferir SSE
                startSSE();
            } else {
                stopSSE(); stopPoll();
            }
        });

        // Auto-start one initial fetch
        fetchStatus();
    })();
    </script>

    <?php
    // Exponer una cadena de conexión enmascarada al cliente para COPY (sin credenciales)
    $conn_masked = null;
    if (isset($connectionOptions) && is_array($connectionOptions)) {
        $m = $connectionOptions;
        if (isset($m['PWD'])) $m['PWD'] = '***';
        if (isset($m['Pwd'])) $m['Pwd'] = '***';
        if (isset($m['Uid'])) $m['Uid'] = ($m['Uid'] ? '***' : '(empty)');
        $conn_masked = htmlspecialchars(json_encode($m, JSON_UNESCAPED_UNICODE));
    } else {
        $conn_masked = json_encode(['Server' => ($serverName ?? null), 'Database' => ($database ?? null)]);
    }
    echo "<script>window.__SAKORMS_CONN_MASKED = $conn_masked; </script>";
    ?>

</body>
</html>
