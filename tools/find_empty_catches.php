<?php
// tools/find_empty_catches.php
$dir = getcwd();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$results = [];
foreach ($rii as $file) {
    if (!$file->isFile()) continue;
    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, ['php','js'])) continue;
    // skip vendor and node_modules
    $path = $file->getPathname();
    if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
    if (strpos($path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR) !== false) continue;

    $src = file_get_contents($path);
    if ($ext === 'php') {
        $tokens = token_get_all($src);
        $len = count($tokens);
        for ($i=0;$i<$len;$i++) {
            $t = $tokens[$i];
            if (is_array($t) && $t[0] === T_CATCH) {
                // find next '{'
                $j = $i+1; $foundBrace = false;
                for (; $j<$len; $j++){
                    $tk = $tokens[$j];
                    if (is_string($tk) && $tk === '{') { $foundBrace = true; break; }
                }
                if (!$foundBrace) continue;
                // find matching closing brace by tracking depth
                $depth = 1; $k = $j+1; $hasCode = false;
                for (; $k<$len; $k++){
                    $tk = $tokens[$k];
                    if (is_string($tk)) {
                        if ($tk === '{') $depth++;
                        if ($tk === '}') { $depth--; if ($depth===0) break; }
                    } else {
                        // if token is not whitespace/comment then consider code
                        if (!in_array($tk[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                            $hasCode = true;
                        }
                    }
                }
                if (!$hasCode) {
                    // determine line number
                    $line = is_array($t) ? $t[2] : '?' ;
                    $results[] = [ 'file' => $path, 'line' => $line ];
                }
            }
        }
    } else {
        // JS: simple regex for catch\s*\([^)]*\)\s*\{\s*\}
        if (preg_match_all('/catch\\s*\\([^)]*\\)\\s*\\{\\s*\\}/', $src, $m, PREG_OFFSET_CAPTURE)){
            foreach ($m[0] as $match) {
                $pos = $match[1];
                $before = substr($src, 0, $pos);
                $line = substr_count($before, "\n") + 1;
                $results[] = ['file'=>$path, 'line'=>$line];
            }
        }
    }
}
if (empty($results)) {
    echo "No empty catch blocks found.\n";
    exit(0);
}
foreach ($results as $r) echo "{$r['file']}:{$r['line']}\n";
exit(0);
