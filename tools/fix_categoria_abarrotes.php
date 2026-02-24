<?php
/**
 * tools/fix_categoria_abarrotes.php
 * Reasigna productos de categoria_id=6 a categoria_id=5, elimina la categoria 6
 * y aplica índice UNIQUE en categorias(nombre).
 * Uso: php tools/fix_categoria_abarrotes.php
 */

chdir(__DIR__ . '/..');
require_once __DIR__ . '/../src/config/db.php';

if (!isset($conn) || !$conn) {
    fwrite(STDERR, "Error: conexión a BD no disponible. Revisa src/config/db.php\n");
    exit(1);
}

$fromId = 6;
$toId = 5;

echo "[".date('Y-m-d H:i:s')."] Identificando productos con categoria_id={$fromId}\n";
$stmt = $conn->prepare('SELECT id, nombre FROM productos WHERE categoria_id = ?');
$stmt->execute([$fromId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "No se encontraron productos referenciando categoria_id={$fromId}.\n";
} else {
    echo "Productos referenciando categoria_id={$fromId}:\n";
    foreach ($rows as $r) echo " - id={$r['id']} nombre={$r['nombre']}\n";
}

// Confirmar existencias de las categorías involucradas
$c1 = $conn->prepare('SELECT id,nombre FROM categorias WHERE id IN (?,?)');
$c1->execute([$fromId, $toId]);
$cats = $c1->fetchAll(PDO::FETCH_ASSOC);
echo "\nCategorias involucradas:\n";
foreach ($cats as $c) echo " - id={$c['id']} nombre={$c['nombre']}\n";

// Ejecutar reasignación y eliminación en transacción
try {
    $conn->beginTransaction();
    echo "\nIniciando transacción: reasignar productos -> categoria_id={$toId}\n";
    $u = $conn->prepare('UPDATE productos SET categoria_id = ? WHERE categoria_id = ?');
    $u->execute([$toId, $fromId]);
    $cnt = $u->rowCount();
    echo "Productos actualizados: {$cnt}\n";

    echo "Intentando eliminar categoria id={$fromId}...\n";
    $d = $conn->prepare('DELETE FROM categorias WHERE id = ?');
    $d->execute([$fromId]);
    $delCnt = $d->rowCount();
    echo "Categorias eliminadas: {$delCnt}\n";

    $conn->commit();
    echo "Transacción completada exitosamente.\n";
} catch (PDOException $e) {
    $conn->rollBack();
    echo "Error durante transacción: {$e->getMessage()}\n";
    exit(1);
}

// Intentar agregar índice UNIQUE
try {
    echo "\nAplicando índice UNIQUE en categorias(nombre)...\n";
    $conn->exec("ALTER TABLE categorias ADD UNIQUE uk_categorias_nombre (nombre)");
    echo "Índice UNIQUE aplicado correctamente.\n";
} catch (PDOException $e) {
    echo "Fallo al aplicar índice UNIQUE: ({$e->getCode()}) {$e->getMessage()}\n";
    echo "Verifica duplicados residuales: ejecutar sql/find_duplicates.sql\n";
    exit(1);
}

echo "\nOperación completada. Revisa la BD y los backups si existen.\n";
