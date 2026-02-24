<?php
$urls = [
    'http://127.0.0.1:8000/index.php',
    'http://127.0.0.1:8000/login.html',
    'http://127.0.0.1:8000/admin/ai_review.php',
    'http://127.0.0.1:8000/api/login.php',
    'http://127.0.0.1:8000/productos.php',
    'http://127.0.0.1:8000/ventas.php',
    'http://127.0.0.1:8000/VERIFICAR_SETUP.php'
];
foreach ($urls as $u) {
    $opts = ["http" => ["method" => "GET", "timeout" => 5]];
    $ctx = stream_context_create($opts);
    $content = file_get_contents($u, false, $ctx);
    if ($content === false) {
        error_log('[check_pages] failed to fetch ' . $u);
    }
    $status = 'no-response';
    if (!empty($http_response_header) && preg_match('#HTTP/\d\.\d\s+(\d+)#', $http_response_header[0], $m)) {
        $status = $m[1];
    }
    echo "$u -> $status\n";
}
