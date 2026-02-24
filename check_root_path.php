<?php
$url = 'http://127.0.0.1:8000/Sakorms.org/Inventory-web1.5/';
$opts = ["http" => ["method" => "GET", "timeout" => 5]];
$ctx = stream_context_create($opts);
$content = file_get_contents($url, false, $ctx);
if ($content === false) {
    error_log('[check_root_path] failed to fetch ' . $url);
}
$status = 'no-response';
if (!empty($http_response_header) && preg_match('#HTTP/\d\.\d\s+(\d+)#', $http_response_header[0], $m)) {
    $status = $m[1];
}
echo "$url -> $status\n";
if ($content) {
    echo "--- body start ---\n";
    echo substr($content,0,800);
    echo "\n--- body end ---\n";
}
