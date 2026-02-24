<?php
/**
 * admin/ai_review.php
 * Página de revisión básica para conversaciones y feedback (localhost)
 */

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1','::ffff:127.0.0.1'])) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Acceso restringido';
    exit;
}

$logDir = __DIR__ . '/../logs';
$convFile = $logDir . '/ai_conversations.log';
$fbFile = $logDir . '/ai_feedback.log';

function readLog($file) {
    $out = [];
    if (!file_exists($file)) return $out;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $l) {
        $decoded = json_decode($l, true);
        if ($decoded) $out[] = $decoded;
    }
    return $out;
}

$convs = readLog($convFile);
$fbs = readLog($fbFile);

?><!doctype html>
<html><head><meta charset="utf-8"><title>AI Review - Sakorms</title>
<style>body{font-family:Arial,Helvetica,sans-serif;padding:18px} table{border-collapse:collapse;width:100%} th,td{padding:8px;border:1px solid #ddd} pre{white-space:pre-wrap}</style>
</head><body>
<h1>Revisión AI - Conversaciones</h1>
<p>Conversaciones almacenadas (opt‑in): <?php echo count($convs); ?></p>
<table><tr><th>TS</th><th>Session</th><th>Prompt</th><th>Reply</th></tr>
<?php foreach (array_reverse($convs) as $c): ?>
<tr>
<td><?php echo htmlspecialchars($c['ts'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($c['session_id'] ?? ''); ?><br/><small>ID: <?php echo htmlspecialchars($c['conversation_id'] ?? ''); ?></small></td>
<td><pre><?php echo htmlspecialchars(substr($c['prompt'] ?? '',0,800)); ?></pre></td>
<td><pre><?php echo htmlspecialchars(substr($c['reply'] ?? '',0,1200)); ?></pre>
    <div style="margin-top:8px;display:flex;gap:8px;">
        <button class="btn" data-onclick="promotePrompt(<?php echo json_encode(addslashes(substr($c['prompt'] ?? '',0,600))); ?>, <?php echo json_encode(addslashes(substr($c['reply'] ?? '',0,1400))); ?>)">Promover a regla</button>
        <button class="btn" data-onclick="copyToClipboard(<?php echo json_encode(substr($c['conversation_id'] ?? '',0,40)); ?>)">Copiar ID</button>
    </div>
</td>
</tr>
<?php endforeach; ?></table>

<h2>Feedback (últimos)</h2>
<p>Total: <?php echo count($fbs); ?></p>
<table><tr><th>TS</th><th>Conv ID</th><th>Rating</th><th>Comment</th><th>IP</th></tr>
<?php foreach (array_reverse($fbs) as $f): ?>
<tr>
<td><?php echo htmlspecialchars($f['ts'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($f['conversation_id'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($f['rating'] ?? ''); ?></td>
<td><pre><?php echo htmlspecialchars(substr($f['comment'] ?? '',0,600)); ?></pre></td>
<td><?php echo htmlspecialchars($f['ip'] ?? ''); ?></td>
</tr>
<?php endforeach; ?></table>

<h2>Ejemplos Aprobados (auto-saved)</h2>
<?php
$examples = readLog($logDir . '/ai_examples.log');
?>
<p>Total: <?php echo count($examples); ?></p>
<table><tr><th>TS</th><th>Prompt</th><th>Reply</th><th>Acción</th></tr>
<?php foreach (array_reverse($examples) as $e): ?>
<tr>
<td><?php echo htmlspecialchars($e['ts'] ?? ''); ?></td>
<td><pre><?php echo htmlspecialchars(substr($e['prompt'] ?? '',0,600)); ?></pre></td>
<td><pre><?php echo htmlspecialchars(substr($e['reply'] ?? '',0,1200)); ?></pre></td>
<td><button class="btn" data-onclick="promotePrompt(<?php echo json_encode(addslashes(substr($e['prompt'] ?? '',0,600))); ?>, <?php echo json_encode(addslashes(substr($e['reply'] ?? '',0,1400))); ?>)">Promover a regla</button></td>
</tr>
<?php endforeach; ?></table>

<h2>Promociones automáticas</h2>
<?php $proms = readLog($logDir . '/ai_promotions.log'); ?>
<p>Total: <?php echo count($proms); ?></p>
<table><tr><th>TS</th><th>Conv ID</th><th>Rule ID</th><th>Pattern</th></tr>
<?php foreach (array_reverse($proms) as $p): ?>
<tr>
<td><?php echo htmlspecialchars($p['ts'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($p['conversation_id'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($p['rule_id'] ?? ''); ?></td>
<td><pre><?php echo htmlspecialchars(substr($p['pattern'] ?? '',0,300)); ?></pre></td>
</tr>
<?php endforeach; ?></table>

<p style="margin-top:20px;display:flex;gap:10px;align-items:center;">
    <a href="?export=1" class="btn">Exportar todo a JSONL</a>
    <button class="btn" data-onclick="importDocs()">Re‑indexar docs</button>
    <span style="color:#666;font-size:0.95rem;margin-left:6px;">(solo localhost)</span>
</p>
<?php if (isset($_GET['export'])) {
    header('Content-Type: application/json');
    $all = array_merge($convs, $fbs);
    echo json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<script>
async function importDocs(){
            try {
                const ok = (window.UI && window.UI.confirm) ? await window.UI.confirm('Reindexar docs/*.md ahora?') : confirm('Reindexar docs/*.md ahora?');
                if (!ok) return;
                const r = await fetch((window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('api/ai_import_docs.php') : (document.baseURI ? new URL('api/ai_import_docs.php', document.baseURI).href : '/Sakorms.org/Inventory-web1.5/api/ai_import_docs.php'),{method:'POST'});
                const j = await r.json();
                if (j && j.success) {
                    if (window.UI && window.UI.toast) window.UI.toast('Procesados: ' + j.processed, 'success'); else console.info('Procesados: '+j.processed);
                    location.reload();
                } else {
                    if (window.UI && window.UI.showDialog) window.UI.showDialog({ type:'error', title: 'Error', message: 'Error: ' + (j && j.error?j.error:'unknown') }); else console.error('Error:', j && j.error ? j.error : j);
                }
            } catch(e) {
                if (window.UI && window.UI.showDialog) window.UI.showDialog({ type:'error', title: 'Error', message: 'Error: ' + e }); else console.error(e);
            }
        }
</script>
<script>
async function promotePrompt(prompt, reply) {
    try {
        const pattern = prompt.slice(0,200).replace(/[^\w\s]/g,'').trim();
        const finalPattern = pattern.length ? pattern : prompt.slice(0,120);
        const ok = (window.UI && window.UI.confirm) ? await window.UI.confirm('Promover este ejemplo como regla? Pattern sugerido: "'+finalPattern+'"') : confirm('Promover este ejemplo como regla? Pattern sugerido: "'+finalPattern+'"');
        if (!ok) return;
        const r = await fetch((window.buildAppUrl && typeof window.buildAppUrl === 'function') ? window.buildAppUrl('api/ai_promote.php') : (document.baseURI ? new URL('api/ai_promote.php', document.baseURI).href : '/Sakorms.org/Inventory-web1.5/api/ai_promote.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ pattern: finalPattern, response: reply, created_by: 'admin' }) });
        const j = await r.json();
        if (j && j.success) {
            if (window.UI && window.UI.toast) window.UI.toast('Regla creada: ID ' + (j.id||'n/a'), 'success'); else console.info('Regla creada: ID ' + (j.id||'n/a'));
            location.reload();
        } else {
            if (window.UI && window.UI.showDialog) window.UI.showDialog({ type:'error', title:'Error', message: 'Error: ' + (j && j.error ? j.error : 'unknown') }); else console.error('Error:', j && j.error ? j.error : j);
        }
    } catch(e) {
        if (window.UI && window.UI.showDialog) window.UI.showDialog({ type:'error', title:'Error', message: 'Error: ' + e }); else console.error(e);
    }
}
function copyToClipboard(s) { try { navigator.clipboard.writeText(s).then(()=>{ if (window.UI && window.UI.toast) window.UI.toast('ID copiado','success'); else console.info('ID copiado'); }).catch(err=>{ if (window.UI && window.UI.showDialog) window.UI.showDialog({ type:'error', title:'Error', message: 'No se pudo copiar: ' + err }); else console.error('No se pudo copiar:', err); }); } catch(e){ if (window.UI && window.UI.showDialog) window.UI.showDialog({ type:'error', title:'Error', message: 'No se pudo copiar: ' + e }); else console.error(e); } }
</script>
<script src="../js/inline-handler-polyfill.js" defer></script>
</body></html>