<?php
/**
 * scan_closure_captures.php  v2
 * ─────────────────────────────────────────────────────────────────────────────
 * Finds closures where an outer-scope variable is referenced as a PHP variable
 * ($var) inside the closure body but is NOT listed in its use() clause,
 * AND is NOT defined/assigned inside the closure body itself.
 *
 * v2 changes:
 *   - Matches $var (with dollar sign), not the bare word, to eliminate false
 *     positives from string literals ('user', 'fournisseur') and property
 *     accesses ($item->user).
 */

$dirs = [
    __DIR__ . '/app/Http/Controllers',
    __DIR__ . '/app/Services',
];

// PHP variables commonly defined in method scope that could be accidentally
// omitted from a closure's use() list.
$outerVars = ['$user', '$reservation', '$receiverType', '$fournisseur'];
$issues    = [];

function extractClosureBody(string $src, int $openBrace): string {
    $depth = 0;
    $body  = '';
    $len   = strlen($src);
    for ($i = $openBrace; $i < $len; $i++) {
        $ch = $src[$i];
        if ($ch === '{') $depth++;
        elseif ($ch === '}') { $depth--; if ($depth === 0) break; }
        $body .= $ch;
    }
    return $body;
}

foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') continue;
        $src = file_get_contents($file->getPathname());

        // Only scan files that contain closures with use()
        if (!preg_match_all('/function\s*\(\$\w+\)\s*use\s*\(([^)]*)\)/m', $src, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches[0] as $idx => $match) {
            $capturedStr = $matches[1][$idx][0];
            $offset      = $match[1];
            $openBrace   = strpos($src, '{', $offset + strlen($match[0]));
            if ($openBrace === false) continue;

            $body    = extractClosureBody($src, $openBrace);
            $lineNum = substr_count(substr($src, 0, $offset), "\n") + 1;

            global $outerVars;
            foreach ($outerVars as $var) {
                // Skip if already captured in use()
                if (strpos($capturedStr, $var) !== false) continue;

                // Match the PHP variable $var used standalone (not as a property name)
                // e.g.  $user->id  or  echo $user  or  $user === null
                // but NOT  $item->user  (that's a property, $item is the variable)
                $escapedVar = preg_quote($var, '/');
                // \$var followed by word boundary (not after ->  which would mean property)
                if (!preg_match('/(?<!->)' . $escapedVar . '\b/', $body)) continue;

                // Skip if $var is ASSIGNED inside the closure (local variable)
                if (preg_match('/' . $escapedVar . '\s*=(?!=)/', $body)) continue;

                $issues[] = sprintf(
                    "  %-80s  line %-5d  closure uses %s but does not capture it",
                    str_replace(__DIR__ . '/', '', $file->getPathname()),
                    $lineNum,
                    $var
                );
            }
        }
    }
}

if (empty($issues)) {
    echo "\n✅  No closure capture issues found across all controllers and services.\n\n";
} else {
    echo "\n⚠️  REAL CLOSURE CAPTURE ISSUES:\n\n";
    foreach ($issues as $i) echo $i . "\n";
    echo "\n";
    exit(1);
}
