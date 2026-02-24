<?php
$urls = ['http://localhost:8000/sqltest.php','http://localhost:8000/test_bd.php','http://localhost:8000/diagnostico-rapido.php'];
foreach ($urls as $u) {
    echo "---- $u\n";
    $opts = ['http' => ['timeout' => 5]];
    $ctx = stream_context_create($opts);
    $content = @file_get_contents($u, false, $ctx);
    if ($content === false) {
        echo "ERROR: no response or timeout\n";
        $err = error_get_last();
        if ($err && isset($err['message'])) echo "ERR: " . $err['message'] . "\n";
    } else {
        echo "Len: " . strlen($content) . " bytes\n";
        $preview = substr($content, 0, 1000);
        echo $preview . (strlen($content) > 1000 ? "...\n" : "\n");
    }
}
