<?php
$url = 'http://localhost/Sakorms.org/Inventory-web1.5/';
$opts = ["http" => ["method" => "GET", "timeout" => 10, "ignore_errors" => true]];
$ctx = stream_context_create($opts);
$content = file_get_contents($url, false, $ctx);
if ($content === false) {
    error_log('[check_apache_root] failed to fetch ' . $url);
}
$status = 'no-response';
if (!empty($http_response_header) && preg_match('#HTTP/\d\.\d\s+(\d+)#', $http_response_header[0], $m)) {
    $status = $m[1];
}
echo "$url -> $status\n";
if ($content !== false) {
    echo "--- body (first 800 chars) ---\n";
    echo substr($content,0,800) . "\n";
}
