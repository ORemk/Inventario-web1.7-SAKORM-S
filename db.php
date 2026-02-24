<?php
// ARCHIVADO: db.php
// Esta copia fue movida a ARCHIVADOS/duplicados_20260204_085141/db.php
// Preferir `config/Database.php` o `src/config/db.php`.
// Mantener un stub para evitar errores por require accidental.
if (!function_exists('db_config_missing')) {
    function db_config_missing() {
        throw new Exception('Database configuration not found. See ARCHIVADOS/duplicados_20260204_085141/db.php for backup.');
    }
}

// EOF - db.php (placeholder)


$possiblePaths = [
    __DIR__ . '/src/config/db.php',
    __DIR__ . '/config/db.php',
    __DIR__ . '/src/db.php',
    __DIR__ . '/config.php',
];

$loaded = false;
foreach ($possiblePaths as $p) {
    if (file_exists($p)) {
        require_once $p;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    // Evitar fallo fatal en entornos donde el archivo aún no fue creado.
    error_log('db.php: No se encontró configuración de BD. Rutas comprobadas: ' . implode(', ', $possiblePaths));

    // Definir marcador para evitar referencias undefined y facilitar detección en tiempo de ejecución.
    $conn = null;

    /**
     * Lanza una excepción con mensaje claro si se intenta usar la DB sin configurar.
     * Uso sugerido: llamar db_config_missing() desde puntos críticos que requieran conexión.
     */
    function db_config_missing() {
        throw new Exception('Database configuration not found. Create "src/config/db.php" or set a valid DB config file.');
    }
}

