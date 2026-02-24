<?php
require_once __DIR__ . '/_api_bootstrap.php';
/**
 * api/ai_assistant_stream.php
 * Streaming proxy to OpenAI Chat Completions (stream=true)
 * Accepts POST { prompt, session_id?, consent?: bool, mode?: 'local'|'remote' }
 * Returns chunks as text/event-stream (SSE-like "data: ...\n\n" frames)
 */

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Allow local dev
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

ignore_user_abort(true);
set_time_limit(0);
if (function_exists('ob_end_flush') && ob_get_level() > 0) { ob_end_flush(); }
if (function_exists('ob_flush') && ob_get_level() > 0) { ob_flush(); }
if (function_exists('flush')) { flush(); }

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo "data: {\"error\":\"Invalid JSON\"}\n\n"; flush(); exit;
    }
    $prompt = isset($data['prompt']) ? trim($data['prompt']) : '';
    $session_id = $data['session_id'] ?? uniqid('', true);
    $consent = (isset($data['consent']) && $data['consent']) ? true : false;
    $mode = $data['mode'] ?? null;

    if ($prompt === '' || mb_strlen($prompt) < 2) {
        echo "data: {\"error\":\"Prompt too short\"}\n\n"; flush(); exit;
    }

    // Limit prompt length
    $prompt = mb_substr($prompt, 0, 3000);

    $apiKey = getenv('OPENAI_API_KEY') ?: null;
    if (!$apiKey && file_exists(__DIR__ . '/../src/config/env.php')) {
        try {
            include_once __DIR__ . '/../src/config/env.php';
        } catch (Exception $e) {
            error_log('[AI_ASSISTANT_STREAM] include env.php failed: ' . $e->getMessage());
        }
    }

    // If no API key OR mode local requested => fallback to local assistant (non-streaming)
    if (!$apiKey || ($mode && $mode === 'local')) {
        // reuse ai_assistant local logic minimally: simple rules search (non-stream)
        // For streaming endpoint, just send a single data chunk with result
        // (This keeps client logic simple: it receives a single data event then stream ends)
        $reply = "Lo siento, el asistente remoto no está disponible. Usa el modo local o contacta al administrador.";

        // quick local checks - simplified
        if (preg_match('/\b(registrar|agregar).*(producto|productos)\b/i', $prompt)) {
            $reply = "Registrar producto: ve a Registrar Producto, completa los campos y presiona Agregar Producto.";
        } elseif (preg_match('/\b(venta|ventas|registrar venta)\b/i', $prompt)) {
            $reply = "Registrar venta: abre Ventas, selecciona cliente, agrega productos y finaliza la venta.";
        } elseif (preg_match('/\b(inventario|stock|productos)\b/i', $prompt)) {
            $reply = "Ver inventario: ve a Productos/Inventario, filtra por categoría o nombre y revisa stock.";
        }

        echo "data: " . json_encode(['text'=> $reply, 'model'=>'local']) . "\n\n";
        echo "data: [DONE]\n\n";
        if (function_exists('ob_flush') && ob_get_level() > 0) { ob_flush(); }
        if (function_exists('flush')) { flush(); }
        
        exit;
    }

    // Build messages for OpenAI
    $messages = [
        ['role' => 'system', 'content' => "Eres un asistente útil para la aplicación Sakorms Inventory. Responde con instrucciones y acciones que el usuario pueda realizar en la UI. No divulgues datos sensibles."],
        ['role' => 'user', 'content' => $prompt]
    ];

    $payload = json_encode(['model' => 'gpt-3.5-turbo', 'messages' => $messages, 'temperature' => 0.2, 'max_tokens' => 1000, 'stream' => true]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);

    // Disable buffering
    if (function_exists('ob_flush') && ob_get_level() > 0) { ob_flush(); }
    if (function_exists('flush')) { flush(); }

    // Buffer to assemble partial lines
    $buffer = '';

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$buffer) {
        // OpenAI streaming sends lines like: "data: {...}\n\n" and a final "data: [DONE]\n\n"
        // We'll forward data as received to the client (SSE).
        echo $data;
        if (function_exists('ob_flush') && ob_get_level() > 0) { ob_flush(); }
        if (function_exists('flush')) { flush(); }
        return strlen($data);
    });

    $res = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        echo "data: {\"error\":\"cURL error: $err\"}\n\n";
        if (function_exists('ob_flush') && ob_get_level() > 0) { ob_flush(); }
        if (function_exists('flush')) { flush(); }
        exit;
    }

    // After stream ends, send DONE marker to ensure client completes
    echo "data: [DONE]\n\n";
    if (function_exists('ob_flush') && ob_get_level() > 0) { ob_flush(); }
    if (function_exists('flush')) { flush(); }
    exit;

} catch (Exception $e) {
    echo "data: {\"error\":\"Server exception: " . addslashes($e->getMessage()) . "\"}\n\n";
    if (function_exists('ob_flush') && ob_get_level() > 0) { ob_flush(); }
    if (function_exists('flush')) { flush(); }
    exit;
}
