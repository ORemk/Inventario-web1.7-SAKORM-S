<?php
require_once __DIR__ . '/_api_bootstrap.php';
/**
 * api/ai_feedback.php
 * - POST: { conversation_id, rating: 1|0, comment?: string, session_id?: string }
 * - GET: returns recent feedback entries (localhost only)
 */

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0755, true)) {
        error_log('[ai_feedback] failed to create log dir: ' . $logDir);
    }
}
$feedbackFile = $logDir . '/ai_feedback.log';

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'JSON inválido']);
        exit;
    }

    $entry = [
        'ts' => date('c'),
        'conversation_id' => $data['conversation_id'] ?? null,
        'session_id' => $data['session_id'] ?? null,
        'rating' => isset($data['rating']) ? (int)$data['rating'] : null,
        'comment' => isset($data['comment']) ? substr($data['comment'],0,2000) : null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ];

    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            error_log('[ai_feedback] failed to create log dir: ' . $logDir);
        }
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        $res_fb = file_put_contents($feedbackFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($res_fb === false) error_log('[ai_feedback] failed to write feedback to ' . $feedbackFile);
    } else {
        error_log('[ai_feedback] Log dir not writable or missing: ' . $logDir);
    }

    // If positive rating, try to save example pair for admin review
    $promoted = false; $promoted_rule_id = null;
    if ($entry['rating'] === 1 && $entry['conversation_id']) {
        $convFile = $logDir . '/ai_conversations.log';
        if (file_exists($convFile)) {
            $lines = file($convFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $l) {
                $c = json_decode($l, true);
                if ($c && ($c['conversation_id'] ?? '') === $entry['conversation_id']) {
                    $example = [ 'ts' => date('c'), 'conversation_id' => $entry['conversation_id'], 'prompt' => $c['prompt'] ?? '', 'reply' => $c['reply'] ?? '' ];
                    $examplesFile = $logDir . '/ai_examples.log';
                    if (is_dir($logDir) && is_writable($logDir)) {
                        $res_ex = file_put_contents($examplesFile, json_encode($example, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
                        if ($res_ex === false) error_log('[ai_feedback] failed to write ai_examples.log');
                    } else {
                        error_log('[ai_feedback] Log dir not writable or missing: ' . $logDir);
                    }
                    break;
                }
            }
        }

        // AUTO-PROMOTION: promover automáticamente si se alcanzan suficientes votos positivos
        $AUTO_PROMOTE_THRESHOLD = getenv('AI_AUTO_PROMOTE_THRESHOLD') ? (int)getenv('AI_AUTO_PROMOTE_THRESHOLD') : 3;
        $convId = $entry['conversation_id'];
        $count = 0;
        $fbLines = file($feedbackFile);
        foreach ($fbLines as $fl) {
            $f = json_decode($fl, true);
            if ($f && ($f['conversation_id'] ?? '') === $convId && isset($f['rating']) && (int)$f['rating'] === 1) $count++;
        }

        if ($count >= $AUTO_PROMOTE_THRESHOLD) {
            $promFile = $logDir . '/ai_promotions.log';
            $already = false;
            if (file_exists($promFile)) {
                $plines = file($promFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($plines as $pl) {
                    $p = json_decode($pl, true);
                    if ($p && ($p['conversation_id'] ?? '') === $convId) { $already = true; break; }
                }
            }

            if (!$already) {
                // Buscar prompt+reply en conversaciones o ejemplos
                $promptText = null; $replyText = null;
                if (file_exists($convFile)) {
                    foreach (array_reverse(file($convFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) as $l) {
                        $c = json_decode($l, true);
                        if ($c && ($c['conversation_id'] ?? '') === $convId) { $promptText = $c['prompt'] ?? ''; $replyText = $c['reply'] ?? ''; break; }
                    }
                }
                if ((!$promptText || !$replyText) && file_exists($logDir . '/ai_examples.log')) {
                    foreach (array_reverse(file($logDir . '/ai_examples.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) as $l) {
                        $e = json_decode($l, true);
                        if ($e && ($e['conversation_id'] ?? '') === $convId) { $promptText = $e['prompt'] ?? ''; $replyText = $e['reply'] ?? ''; break; }
                    }
                }

                if ($promptText && $replyText) {
                    // Generar pattern seguro
                    $pattern = mb_substr(preg_replace('/[^\p{L}\p{N}\s]/u','',trim($promptText)), 0, 250);
                    if ($pattern === '') $pattern = mb_substr(trim($promptText), 0, 120);

                    // Insertar regla en la base de datos usando Database::getInstance()
                    try {
                        require_once __DIR__ . '/../src/config/Database.php';
                        $db = Database::getInstance();
                        $created_by = 'auto';
                        $ok = $db->execute("INSERT INTO ai_rules (pattern, response, created_by) VALUES (?,?,?)", [$pattern, $replyText, $created_by]);
                        if ($ok) {
                            $ruleId = $db->getConnection()->lastInsertId();
                            $promEntry = ['ts'=>date('c'),'conversation_id'=>$convId,'rule_id'=>$ruleId,'pattern'=>$pattern];
                            if (is_dir($logDir) && is_writable($logDir)) {
                                $r_prom = file_put_contents($promFile, json_encode($promEntry, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND | LOCK_EX);
                                if ($r_prom === false) error_log('[ai_feedback] failed to write prom entry to ' . $promFile);
                            } else {
                                error_log('[ai_feedback] Log dir not writable or missing: ' . $logDir);
                            }
                            $promoted = true; $promoted_rule_id = $ruleId;
                        }
                    } catch (Exception $e) {
                        error_log('[ai_feedback] DB insert/promote error: ' . $e->getMessage());
                    }
                }
            }
        }

    }

    echo json_encode(['success' => true, 'promoted' => $promoted, 'rule_id' => $promoted_rule_id]);
    exit;
}

// GET - return last 200 entries but restrict to localhost
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1','::1','::ffff:127.0.0.1'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso restringido a localhost']);
    exit;
}

$lines = [];
if (file_exists($feedbackFile)) {
    $all = file($feedbackFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $count = count($all);
    $start = max(0, $count - 200);
    for ($i = $start; $i < $count; $i++) {
        $lines[] = json_decode($all[$i], true);
    }
}

echo json_encode(['success' => true, 'data' => $lines]);
exit;
?>