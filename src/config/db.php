<?php
// src/config/db.php - Configuración de conexión MariaDB para Sakorms Inventory v1.5

$host = '127.0.0.1';
$db   = 'inventory'; // Unified DB name for the project
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    $err = 'Error de conexión a la base de datos: ' . $e->getMessage();
    error_log($err);
    // Registrar en archivo de logs para facilitar debugging
    try {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                error_log('[db.php] failed to create log dir: ' . $logDir);
            }
        }
        $logFile = $logDir . '/db_connection.log';
        if (is_dir($logDir) && is_writable($logDir)) {
            $res = file_put_contents($logFile, date('Y-m-d H:i:s') . "\t" . $err . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($res === false) error_log('[db.php] failed to write db_connection log to ' . $logFile);
        } else {
            error_log('[db.php] Log dir not writable or missing: ' . $logDir);
        }
    } catch (Exception $ex) { error_log('[db.php] logging failed: ' . $ex->getMessage()); }

    // Evitar lanzar excepción no capturada aquí para dejar que los endpoints
    // manejen la ausencia de conexión y retornen JSON apropiado
    $conn = null;
    $dbConnectionError = $e->getMessage();
}
