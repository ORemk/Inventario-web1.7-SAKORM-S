<?php
require_once __DIR__ . '/_api_bootstrap.php';
/**
 * api/ai_assistant.php
 * Proxy PoC para LLM hospedado (OpenAI)
 * - POST { prompt: string, session_id?: string, consent?: true|false }
 * - Requires: OPENAI_API_KEY in environment or src/config/env.php
 */

header('Content-Type: application/json; charset=utf-8');
// CORS default for development (adjust in production)
header('Access-Control-Allow-Origin: *');

// Use centralized PDO Database
@include_once __DIR__ . '/../src/config/Database.php';
// Allow local requests from the same origin (app served from same host)
// Basic CORS for dev (adjust for production)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

try {

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        error_log('[AI_ASSISTANT] JSON inválido recibido: ' . substr($raw,0,500));
        echo json_encode(['success' => false, 'error' => 'El formato de la petición no es válido (JSON inválido). Por favor, actualiza la página o contacta soporte.']);
        exit;
    }

    $prompt = isset($data['prompt']) ? trim($data['prompt']) : '';
    $session_id = $data['session_id'] ?? uniqid('', true);
    $consent = (isset($data['consent']) && $data['consent']) ? true : false;

    // Basic validation and limits
    if ($prompt === '' || mb_strlen($prompt) < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Debes escribir una pregunta o mensaje para el asistente.']);
        exit;
    }

    // Limit prompt length to reasonable size to avoid abuse
    $prompt = mb_substr($prompt, 0, 3000);

    // Simple per-session rate limiting: max 10 requests per 60s, 60 per hour and minimum gap 700ms
    $rateDir = __DIR__ . '/../logs/ai_rate';
    if (!is_dir($rateDir)) {
        if (!mkdir($rateDir, 0755, true)) {
            error_log('[AI_ASSISTANT] failed to create rate dir: ' . $rateDir);
        }
    }
    $rateFile = $rateDir . '/r_' . preg_replace('/[^a-z0-9_\-]/i', '_', $session_id) . '.log';
    $now = microtime(true);
    $entries = [];
    if (file_exists($rateFile)) {
        $rawLines = file($rateFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($rawLines === false) $rawLines = [];
        foreach ($rawLines as $ln) { $t = floatval(trim($ln)); if ($t > 0) $entries[] = $t; }
    }
    // prune entries older than 3600s
    $entries = array_filter($entries, function($t) use ($now){ return ($now - $t) <= 3600; });
    // check min gap
    if (!empty($entries)) {
        $last = end($entries);
        if (($now - $last) < 0.7) {
            http_response_code(429);
            echo json_encode(['success'=>false,'error'=>'Estás enviando solicitudes muy rápido. Espera un momento y vuelve a intentarlo.']);
            exit;
        }
    }
    // count in last 60s and last hour
    $count60 = 0; $count3600 = 0;
    foreach ($entries as $t) { if (($now - $t) <= 60) $count60++; if (($now - $t) <= 3600) $count3600++; }
    if ($count60 >= 10 || $count3600 >= 600) {
        http_response_code(429);
        echo json_encode(['success'=>false,'error'=>'Límite excedido. Intenta de nuevo más tarde.']);
        exit;
    }
    // append current
    $entries[] = $now;
    if (is_dir($rateDir) && is_writable($rateDir)) {
        $res_rate = file_put_contents($rateFile, implode(PHP_EOL, $entries) . PHP_EOL, LOCK_EX);
        if ($res_rate === false) error_log('[AI_ASSISTANT] failed to write rate file: ' . $rateFile);
    } else {
        error_log('[AI_ASSISTANT] rate dir not writable or missing: ' . $rateDir);
    }

    // Obtener API key (prefer env var)

    $apiKey = getenv('OPENAI_API_KEY') ?: null;
    // Fallback: src/config/env.php may define OPENAI_API_KEY constant or array
    if (!$apiKey && file_exists(__DIR__ . '/../src/config/env.php')) {
        try {
            include_once __DIR__ . '/../src/config/env.php';
        } catch (Exception $e) {
            error_log('[AI_ASSISTANT] include env.php failed: ' . $e->getMessage());
        }
    }

    // Si no hay API key y no es modo local, error explícito
    $mode = $data['mode'] ?? null;
    if (!$apiKey && (!$mode || $mode !== 'local')) {
        http_response_code(503);
        error_log('[AI_ASSISTANT] Falta OPENAI_API_KEY para modo remoto.');
        echo json_encode(['success' => false, 'error' => 'El asistente remoto no está disponible (API key no configurada). Usa el modo local o contacta al administrador.']);
        exit;
    }

    // If OPENAI_API_KEY missing or caller requested local mode, use local RAG/rules engine
    if (!$apiKey || ($mode && $mode === 'local')) {

        // Local search: check ai_rules first, then ai_docs
        $reply = '';
        $found = false;
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            // quick intent handlers for common flows (register product, sale, inventory)
            $lowPrompt = mb_strtolower($prompt, 'UTF-8');
            $actions = [];
            if (preg_match('/\b(registrar|agregar).*(producto|productos)\b/i', $prompt) || preg_match('/\b(producto).*(registrar|agregar)\b/i', $prompt)) {
                $reply = "Registrar un producto - pasos:\n1) Ve a Registrar Producto. 2) Completa Nombre, Categoría, Cantidad, Precio y Costo. 3) Agrega imagen opcional. 4) Presiona 'Agregar Producto'.";
                $actions[] = ['label' => 'Abrir formulario', 'navigate' => 'registro-productos'];
                $actions[] = ['label' => 'Ver guía', 'url' => '/Sakorms.org/Inventory-web1.5/guia-paso-a-paso.html'];
                $found = true;
                // optionally persist rule when user consented
                    if ($consent) {
                        try {
                            $tbl = $db->fetchAll("SHOW TABLES LIKE 'ai_rules'");
                            if (!empty($tbl)) {
                                $pattern = 'registrar.*producto';
                                $response = $reply;
                                $db->execute("INSERT INTO ai_rules (pattern, response) VALUES (?, ?)", [$pattern, $response]);
                            }
                        } catch (Exception $e) {
                            error_log('[AI_ASSISTANT] ai_rules insert failed: ' . $e->getMessage());
                        }
                    }
            }
            if (!$found && preg_match('/\b(venta|vender|ventas|registrar venta)\b/i', $prompt)) {
                $reply = "Registrar venta - pasos:\n1) Abre Ventas. 2) Selecciona cliente. 3) Agrega productos y cantidades. 4) Verifica precios y método de pago. 5) Finaliza y genera recibo.";
                $actions[] = ['label' => 'Abrir Ventas', 'navigate' => 'ventas'];
                $actions[] = ['label' => 'Mostrar guía', 'url' => '/Sakorms.org/Inventory-web1.5/guia-paso-a-paso.html'];
                $found = true;
            }
            if (!$found && preg_match('/\b(inventario|stock|existencias|productos)\b/i', $prompt)) {
                $reply = "Ver inventario - consejos:\nVe a Productos/Inventario, usa filtros por nombre o categoría, y revisa columna de stock. Para bajas, genera reporte de inventario.";
                $actions[] = ['label' => 'Abrir Productos', 'navigate' => 'productos'];
                $found = true;
            }

            // Check rules (only if table exists)
            $tblRules = $db->fetchAll("SHOW TABLES LIKE 'ai_rules'");
            if (!empty($tblRules)) {
                $res = $db->fetchAll("SELECT pattern, response FROM ai_rules ORDER BY id DESC");
                if (!empty($res)) {
                    foreach ($res as $row) {
                        $pattern = $row['pattern'];
                        $flags = 'i';
                        $regex = '/' . str_replace('/', '\/', $pattern) . '/' . $flags;
                        try {
                            $matchResult = preg_match($regex, $prompt);
                            $pregErr = preg_last_error();
                            if ($pregErr !== PREG_NO_ERROR) {
                                error_log('[AI_ASSISTANT] invalid ai_rules pattern (preg_last_error=' . $pregErr . '): ' . $pattern);
                            } else {
                                if ($matchResult) {
                                    $reply = $row['response'];
                                    $found = true;
                                    break;
                                }
                            }
                        } catch (Throwable $e) {
                            error_log('[AI_ASSISTANT] invalid ai_rules pattern exception: ' . $e->getMessage());
                        }
                    }
                }
            }

            if (!$found) {
                // Support explicit product searches: if prompt asks to "buscar" o "encontrar" producto, run safe query
                if (preg_match('/\b(buscar|buscar\s+producto|buscar\s+productos|encontrar|buscar\s+por)\b/i', $prompt)) {
                    $clean = trim(preg_replace('/[^\p{L}\p{N}\s\-\.]/u',' ', $prompt));
                    $q = '%' . $clean . '%';
                    $products = $db->fetchAll("SELECT id, codigo, nombre, categoria_id, cantidad, costo, precio, fecha_caducidad, imagen FROM productos WHERE nombre LIKE ? OR codigo LIKE ? LIMIT 20", [$q, $q]);
                    if (!empty($products)) {
                        $found = true;
                        $reply = "He encontrado " . count($products) . " producto(s) que coinciden:\n";
                        foreach (array_slice($products,0,8) as $p) {
                            $reply .= "- [" . ($p['codigo'] ?: 'N/A') . "] " . $p['nombre'] . " — Cantidad: " . ($p['cantidad'] ?? 0) . ", Precio: " . (isset($p['precio']) ? '$'.number_format((float)$p['precio'],2) : 'N/A') . "\n";
                        }
                        // Attach structured products array to output (frontend can use this)
                        $outProducts = $products;
                    }
                } else {
                    // Simple relevance search using LIKE on title and content (only if ai_docs table exists)
                    $tblDocs = $db->fetchAll("SHOW TABLES LIKE 'ai_docs'");
                    if (!empty($tblDocs)) {
                        $like = '%' . $prompt . '%';
                        $items = $db->fetchAll("SELECT title, path, excerpt, SUBSTRING(content,1,800) as snippet FROM ai_docs WHERE title LIKE ? OR content LIKE ? LIMIT 5", [$like, $like]);
                        if (!empty($items)) {
                            $found = true;
                            $reply = "He encontrado información en la documentación relevante:\n";
                            foreach ($items as $it) {
                                $reply .= "\n- " . $it['title'] . " (" . $it['path'] . ")\n  " . trim(preg_replace('/\s+/',' ', strip_tags($it['snippet']))) . "...\n";
                            }
                            $reply .= "\n¿Quieres que abra la sección correspondiente o que copie el fragmento?";
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('[AI_ASSISTANT] Error de acceso a la DB (local-mode): ' . $e->getMessage());
            $reply = 'No se pudo acceder a la base de datos local para buscar información. Intenta más tarde o contacta soporte.';
        }

        if (!$found && $reply === '') {
            $reply = "Lo siento, no encontré información precisa en las reglas ni en la documentación. Puedo guardar este ejemplo para revisión si lo deseas.";
        }

        $conversation_id = substr(md5($session_id . microtime(true)), 0, 20);
        // Log if consent
        if ($consent) {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                if (!mkdir($logDir, 0755, true)) error_log('[AI_ASSISTANT] failed to create log dir: ' . $logDir);
            }
            $entry = [
                'ts' => date('c'),
                'session_id' => $session_id,
                'conversation_id' => $conversation_id,
                'prompt' => $prompt,
                'reply' => $reply,
                'mode' => 'local'
            ];
            $convFile = $logDir . '/ai_conversations.log';
            if (is_dir($logDir) && is_writable($logDir)) {
                $res_c = file_put_contents($convFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
                if ($res_c === false) error_log('[AI_ASSISTANT] failed to write ai_conversations.log');
            } else {
                error_log('[AI_ASSISTANT] Log dir not writable or missing: ' . $logDir);
            }
        }

        $out = ['reply' => $reply, 'model' => 'local'];
        if (!empty($actions)) $out['actions'] = $actions;
        if (!empty($outProducts)) $out['products'] = $outProducts;
        // suggest_save used by frontend to show 'Guardar como regla'
        if ($found && $consent) $out['suggest_save'] = true;

        echo json_encode(['success' => true, 'data' => $out, 'conversation_id' => $conversation_id]);
        exit;
    }

    // Optionally enrich the LLM prompt with live DB results for product searches
    $liveProductsForPrompt = null;
                try {
                    $db = Database::getInstance();
                    if (preg_match('/\b(buscar|buscar\s+producto|buscar\s+productos|encontrar|buscar\s+por)\b/i', $prompt)) {
                        $clean = trim(preg_replace('/[^\p{L}\p{N}\s\-\.]/u',' ', $prompt));
                        $q = '%' . $clean . '%';
                        $lp = $db->fetchAll("SELECT id, codigo, nombre, categoria_id, cantidad, precio FROM productos WHERE nombre LIKE ? OR codigo LIKE ? LIMIT 12", [$q, $q]);
                        if (!empty($lp)) $liveProductsForPrompt = $lp;
                    }
                } catch (Exception $e) {
                    error_log('[AI_ASSISTANT] db enrichment failed: ' . $e->getMessage());
                }

    // Optionally perform a lightweight web search (DuckDuckGo Instant Answer) when prompt asks for online info
    $webSummary = null;
    try {
        if (preg_match('/\b(internet|web|online|google|buscar\s+en\s+internet|fuente)\b/i', $prompt)) {
            $qs = urlencode(mb_substr($prompt,0,250));
            $ddgUrl = 'https://api.duckduckgo.com/?q=' . $qs . '&format=json&no_html=1&skip_disambig=1';
            $ctx = stream_context_create(['http'=>['timeout'=>6,'header'=>'User-Agent: SakormsAI/1.0']]);
            $body = file_get_contents($ddgUrl, false, $ctx);
            if ($body === false) {
                error_log('[AI_ASSISTANT] failed web fetch: ' . $ddgUrl);
            }
            if ($body) {
                $j = json_decode($body, true);
                if (is_array($j)) {
                    $parts = [];
                    if (!empty($j['Abstract'])) $parts[] = trim($j['Abstract']);
                    if (!empty($j['AbstractURL'])) $parts[] = 'Fuente: ' . $j['AbstractURL'];
                    if (empty($parts) && !empty($j['RelatedTopics']) && is_array($j['RelatedTopics'])) {
                        // take first few related topics text
                        $cnt = 0;
                        foreach ($j['RelatedTopics'] as $rt) {
                            if (isset($rt['Text'])) { $parts[] = $rt['Text']; $cnt++; }
                            if ($cnt >= 3) break;
                        }
                    }
                    if (!empty($parts)) $webSummary = implode("\n", array_slice($parts,0,6));
                    }
                }
            }
        } catch (Exception $e) {
            error_log('[AI_ASSISTANT] web search failed: ' . $e->getMessage());
        }

    // Build request to OpenAI Chat Completions (GPT-3.5 / GPT-4 family - PoC uses gpt-3.5-turbo by default)
    $model = 'gpt-3.5-turbo';
    $messages = [
        ['role' => 'system', 'content' => "Eres un asistente útil para la aplicación Sakorms Inventory. Responde con instrucciones claras y acciones que el usuario pueda realizar en la UI. No divulgues datos sensibles."],
    ];
    if ($liveProductsForPrompt) {
        $messages[] = ['role' => 'system', 'content' => "Datos en tiempo real: se han encontrado los siguientes productos en la base de datos. Úsalos para responder exactamente sobre stock, precios y disponibilidad. Responde primero con un breve resumen y luego con detalles. JSON: " . json_encode($liveProductsForPrompt)];
    }
    if ($webSummary) {
        $messages[] = ['role' => 'system', 'content' => "Resumen web (búsqueda rápida): " . $webSummary . "\n\nIncluye esta información si es relevante y cita la(s) fuente(s) si están disponibles."];
    }
    $messages[] = ['role' => 'user', 'content' => $prompt];

    $payload = ['model' => $model, 'messages' => $messages, 'max_tokens' => 800, 'temperature' => 0.2];

    // cURL
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $res = curl_exec($ch);

    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        http_response_code(502);
        error_log('[AI_ASSISTANT] Error cURL: ' . $err);
        echo json_encode(['success' => false, 'error' => 'No se pudo contactar al proveedor de IA. Intenta de nuevo más tarde.']);
        exit;
    }

    $json = json_decode($res, true);
    if (!is_array($json) || !isset($json['choices'][0]['message']['content'])) {
        http_response_code(502);
        error_log('[AI_ASSISTANT] Respuesta inesperada del proveedor: ' . substr($res,0,500));
        echo json_encode(['success' => false, 'error' => 'El asistente no pudo procesar la respuesta del proveedor. Intenta de nuevo más tarde.', 'raw' => $res]);
        exit;
    }

    $reply = $json['choices'][0]['message']['content'];
    $conversation_id = substr(md5($session_id . microtime(true)), 0, 20);

    // Log conversation if user consented (opt-in)
    if ($consent) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) error_log('[AI_ASSISTANT] failed to create log dir: ' . $logDir);
        }
        $entry = [
            'ts' => date('c'),
            'session_id' => $session_id,
            'conversation_id' => $conversation_id,
            'prompt' => $prompt,
            'reply' => $reply,
            'model' => $model
        ];
        $convFile = $logDir . '/ai_conversations.log';
        if (is_dir($logDir) && is_writable($logDir)) {
            $w = file_put_contents($convFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($w === false) error_log('[AI_ASSISTANT] failed to write ai_conversations.log');
        } else {
            error_log('[AI_ASSISTANT] Log dir not writable or missing: ' . $logDir);
        }
    }

    echo json_encode(['success' => true, 'data' => ['reply' => $reply, 'model' => $model], 'conversation_id' => $conversation_id]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    error_log('[AI_ASSISTANT] Excepción: ' . $e->getMessage());
    $errOut = ['success' => false, 'error' => 'Ocurrió un error interno en el asistente. Intenta de nuevo más tarde.'];
    // Si se solicita debug via ?debug=1, incluir mensaje de excepción (solo para desarrollo)
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        $errOut['exception'] = $e->getMessage();
        $errOut['trace'] = $e->getTraceAsString();
    }
    echo json_encode($errOut);
    exit;
}
