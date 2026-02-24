<?php
// tools/test_verify_master.php - prueba simple para verify_master.php
error_reporting(E_ALL);
ini_set('display_errors',1);

function http_get($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $resp, $err];
}

function http_post_json($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $resp, $err];
}

echo "GET:\n";
list($s, $r, $e) = http_get('http://localhost/Sakorms.org/Inventory-web1.5/api/admin/verify_master.php');
echo "HTTP status: $s\n";
if ($e) echo "curl error: $e\n";
echo "body:\n".($r?:'(empty)') ."\n\n";

echo "POST:\n";
list($s2, $r2, $e2) = http_post_json('http://localhost/Sakorms.org/Inventory-web1.5/api/admin/verify_master.php', ['email'=>'admin@local','password'=>'admin']);
echo "HTTP status: $s2\n";
if ($e2) echo "curl error: $e2\n";
echo "body:\n".($r2?:'(empty)') ."\n\n";

echo "Done\n";
