<?php
// tools/run_php_lint.php
// Recorre recursivamente el repositorio y ejecuta `php -l` en cada archivo .php
$dir = getcwd();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$errors = [];
foreach ($rii as $file) {
    if ($file->isFile() && strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)) === 'php') {
        $path = $file->getPathname();
        echo "---- $path\n";
        $output = null;
        $ret = null;
        passthru('php -l "' . $path . '"', $ret);
        if ($ret !== 0) {
            $errors[] = $path;
        }
    }
}
if (count($errors) === 0) {
    echo "\nDone: no se detectaron errores de sintaxis en los archivos PHP analizados.\n";
    exit(0);
} else {
    echo "\nFinished: se detectaron errores de sintaxis en " . count($errors) . " archivo(s).\n";
    foreach ($errors as $e) echo " - $e\n";
    exit(2);
}
