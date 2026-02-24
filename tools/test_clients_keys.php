<?php
// tools/test_clients_keys.php
// Ejecutar en CLI: php tools/test_clients_keys.php

$base = 'http://localhost/Sakorms.org/Inventory-web1.5/api/admin/';

$cookieFile = __DIR__ . '/cookie.txt';

function call_api($url, $method='GET', $data=null, $cookieFile=null){
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
    ];
    $headers = ['Accept: application/json'];
    if ($method === 'POST'){
        $payload = json_encode($data ?: new stdClass());
        $opts[CURLOPT_POSTFIELDS] = $payload;
        $headers[] = 'Content-Type: application/json';
    }
    if ($cookieFile) {
        $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    if ($res === false){
        echo "CURL error: " . curl_error($ch) . PHP_EOL;
        curl_close($ch);
        return null;
    }
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode($res, true);
    return ['http'=>$http,'body'=>$decoded];
}

echo "== Iniciar sesión como admin (fallback) ==\n";
$login = call_api($base . 'login_admin.php', 'POST', ['email'=>'admin@local','password'=>'admin'], $cookieFile);
print_r($login);
if (!isset($login['body']['success']) || $login['body']['success'] !== true) {
    echo "Login falló. No autenticado. Abortar.\n";
    exit(1);
}

echo "== Crear cliente de prueba ==\n";
$clientName = 'CLI Test ' . time();
$resp = call_api($base . 'register_client.php', 'POST', ['name'=>$clientName, 'email'=>'test+' . time() . '@local', 'phone'=>'000'], $cookieFile);
print_r($resp);

$client_id = null;
if (isset($resp['body']['client_id'])) $client_id = $resp['body']['client_id'];
if (isset($resp['body']['client']['id'])) $client_id = $resp['body']['client']['id'];

if (!$client_id){
    echo "No se obtuvo client_id. Abortar.\n";
    exit(1);
}

echo "\n== Generar clave para client_id={$client_id} ==\n";
$g = call_api($base . 'generate_key.php', 'POST', ['client_id'=>$client_id], $cookieFile);
print_r($g);

echo "\n== Listar claves para client_id={$client_id} ==\n";
$l = call_api($base . 'list_keys.php?client_id=' . intval($client_id), 'GET', null, $cookieFile);
print_r($l);

echo "\nPruebas finalizadas.\n";
