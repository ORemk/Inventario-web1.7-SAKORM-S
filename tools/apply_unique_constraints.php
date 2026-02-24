<?php
/**
 * tools/apply_unique_constraints.php
 * Uso: php apply_unique_constraints.php [--apply]
 * - Sin --apply: inspecciona y muestra duplicados y el plan (dry-run).
 * - Con --apply: realiza copias de seguridad puntuales y ejecuta deduplicación mínimamente intrusiva, luego intenta agregar índices UNIQUE.
 *
 * IMPORTANTE: Haz un backup de la BD antes de ejecutar con --apply. Revisa primero la salida sin --apply.
 */

chdir(__DIR__ . '/..');
require_once __DIR__ . '/../db.php';

if (!isset($conn) || !$conn) {
    fwrite(STDERR, "Error: no se encontró conexión a la BD. Asegura que 'src/config/db.php' existe y define \$conn (PDO).\n");
    exit(1);
}

$apply = in_array('--apply', $argv);
echo "[", date('Y-m-d H:i:s'), "] Iniciando (apply=" . ($apply? 'yes':'no') . ")\n";

function fetchAllSafe($conn, $sql, $params = []){
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 1) Encontrar duplicados
echo "\n== Buscando duplicados: productos por codigo ==\n";
$dupCodigo = fetchAllSafe($conn, "SELECT codigo, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids FROM productos WHERE codigo IS NOT NULL AND TRIM(codigo) <> '' GROUP BY codigo HAVING cnt>1");
foreach ($dupCodigo as $r) {
    echo "codigo=[$r[codigo]] cnt={$r['cnt']} ids={$r['ids']}\n";
}

echo "\n== Buscando duplicados: productos por nombre ==\n";
$dupNombre = fetchAllSafe($conn, "SELECT LOWER(TRIM(nombre)) AS nombre_norm, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids FROM productos GROUP BY nombre_norm HAVING cnt>1");
foreach ($dupNombre as $r) {
    echo "nombre_norm=[$r[nombre_norm]] cnt={$r['cnt']} ids={$r['ids']}\n";
}

echo "\n== Buscando duplicados: categorias por nombre ==\n";
$dupCat = fetchAllSafe($conn, "SELECT LOWER(TRIM(nombre)) AS nombre_norm, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids FROM categorias GROUP BY nombre_norm HAVING cnt>1");
foreach ($dupCat as $r) {
    echo "categoria=[$r[nombre_norm]] cnt={$r['cnt']} ids={$r['ids']}\n";
}

echo "\n== Buscando duplicados: clientes por email ==\n";
$dupCliEmail = fetchAllSafe($conn, "SELECT LOWER(TRIM(email)) AS email_norm, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids FROM clientes WHERE email IS NOT NULL AND TRIM(email) <> '' GROUP BY email_norm HAVING cnt>1");
foreach ($dupCliEmail as $r) {
    echo "email=[$r[email_norm]] cnt={$r['cnt']} ids={$r['ids']}\n";
}

echo "\n== Buscando duplicados: clientes por nombre ==\n";
$dupCliNombre = fetchAllSafe($conn, "SELECT LOWER(TRIM(nombre)) AS nombre_norm, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids FROM clientes GROUP BY nombre_norm HAVING cnt>1");
foreach ($dupCliNombre as $r) {
    echo "nombre=[$r[nombre_norm]] cnt={$r['cnt']} ids={$r['ids']}\n";
}

if (!$apply) {
    echo "\nDry-run completo. Revisa los grupos arriba. Para aplicar deduplicación y agregar índices, ejecuta: php tools/apply_unique_constraints.php --apply\n";
    exit(0);
}

// 2) Aplicar deduplicación mínima y backup por grupo
echo "\n== MODO APPLY: creando backups y limpiando duplicados (manteniendo id menor) ==\n";
$timestamp = date('Ymd_His');
try {
    foreach ([$dupCodigo, $dupNombre, $dupCat, $dupCliEmail, $dupCliNombre] as $groupSet) {
        foreach ($groupSet as $g) {
            $ids = explode(',', $g['ids']);
            if (count($ids) <= 1) continue;
            $keep = (int) array_shift($ids);
            $toDelete = array_map('intval', $ids);
            $backupTable = '';
            if (isset($g['codigo']) || isset($g['nombre_norm']) && in_array($g['nombre_norm'], array_column($dupNombre,'nombre_norm'))) {
                // productos (best-effort detect)
                $backupTable = 'backup_duplicated_productos_' . $timestamp;
                $tbl = 'productos';
            } elseif (isset($g['nombre_norm']) && in_array($g['nombre_norm'], array_column($dupCat,'nombre_norm'))) {
                $backupTable = 'backup_duplicated_categorias_' . $timestamp;
                $tbl = 'categorias';
            } elseif (isset($g['email_norm'])) {
                $backupTable = 'backup_duplicated_clientes_' . $timestamp;
                $tbl = 'clientes';
            } else {
                // fallback: inspect ids to derive table by looking for id existence
                $tbl = null;
            }

            if ($tbl) {
                // create backup table once
                $createSql = "CREATE TABLE IF NOT EXISTS {$backupTable} AS SELECT * FROM {$tbl} WHERE id IN (" . implode(',', array_merge([$keep], $toDelete)) . ")";
                try { $conn->exec($createSql); echo "Backup creado: {$backupTable} (registros: " . (count($toDelete)+1) . ")\n"; } catch (PDOException $e) { echo "Aviso: no se pudo crear backup {$backupTable}: {$e->getMessage()}\n"; }

                // delete duplicates keeping the lowest id
                $delSql = "DELETE FROM {$tbl} WHERE id IN (" . implode(',', $toDelete) . ")";
                try {
                    $conn->beginTransaction();
                    $conn->exec($delSql);
                    $conn->commit();
                    echo "Eliminados duplicados en {$tbl}: ids=" . implode(',', $toDelete) . " (conservado id={$keep})\n";
                } catch (PDOException $e) {
                    $conn->rollBack();
                    echo "Error borrando duplicados en {$tbl}: {$e->getMessage()}\n";
                }
            } else {
                echo "No se pudo determinar tabla para grupo ids={$g['ids']}, omitiendo.\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Error durante deduplicación: " . $e->getMessage() . "\n";
}

// 3) Intentar agregar índices UNIQUE (puede fallar si aún hay duplicados)
echo "\n== Agregando índices UNIQUE (intentar) ==\n";
$uniqueQueries = [
    "ALTER TABLE productos ADD UNIQUE uk_productos_codigo (codigo)",
    "ALTER TABLE productos ADD UNIQUE uk_productos_nombre (nombre)",
    "ALTER TABLE categorias ADD UNIQUE uk_categorias_nombre (nombre)",
    "ALTER TABLE clientes ADD UNIQUE uk_clientes_email (email)"
];
foreach ($uniqueQueries as $q) {
    try {
        $conn->exec($q);
        echo "OK: {$q}\n";
    } catch (PDOException $e) {
        echo "Fallo ({$e->getCode()}): {$q} -> {$e->getMessage()}\n";
    }
}

echo "\nProceso terminado. Revise la base de datos y los backups creados (prefijo backup_duplicated_*).\n";
