<?php
/**
 * SquirrelStock: пары «украинская фраза → русская» из украинской и русской версий Zippy одного времени.
 *
 * Для каждого файла, который есть в обеих версиях, фразы находятся тем же разбором, что в convert.php,
 * и сопоставляются: при равном числе фраз — по порядку, иначе — по окружающему коду (без кириллицы),
 * через наибольшую общую подпоследовательность. Для фразы берётся самый частый русский вариант.
 * Пропускаются пары, где русский текст не переведён (совпадает с украинским или содержит і, ї, є, ґ).
 *
 * php tools/i18n/pairs.php --ua=<корень www украинской версии> --ru=<корень www русской версии> > pairs.json
 */

require __DIR__ . '/lib.php';

$opt = getopt('', ['ua:', 'ru:']);
$ua = rtrim($opt['ua'] ?? '', '/');
$ru = rtrim($opt['ru'] ?? '', '/');
if (!is_dir($ua) || !is_dir($ru)) {
    fwrite(STDERR, "php pairs.php --ua=DIR --ru=DIR\n");
    exit(1);
}

$counts = [];   // ua => [ru => n]
$stat = ['files' => 0, 'positional' => 0, 'aligned' => 0, 'pairs' => 0];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ua, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $rel = substr($f->getPathname(), strlen($ua) + 1);
    if (!preg_match('/\.(php|html|tpl)$/', $rel) || str_starts_with($rel, 'templates/site/') || str_starts_with($rel, 'vendor/')) {
        continue;
    }
    if (!is_file($ru . '/' . $rel)) {
        continue;
    }
    $a = items($ua . '/' . $rel, $rel);
    $b = items($ru . '/' . $rel, $rel);
    if (!$a || !$b) {
        continue;
    }
    $stat['files']++;
    if (count($a) === count($b)) {
        $pairs = array_map(null, $a, $b);
        $stat['positional']++;
    } else {
        $pairs = align($a, $b);
        $stat['aligned']++;
    }
    foreach ($pairs as [$x, $y]) {
        $u = $x['phrase'];
        $r = $y['phrase'];
        if ($u === $r || preg_match('/[іїєґІЇЄҐ]/u', $r) || !hasCyr($u)) {
            continue;
        }
        // плейсхолдеры {name} должны совпасть, иначе пара сомнительная
        preg_match_all('/\{[a-z_0-9]+\}/', $u, $pu);
        preg_match_all('/\{[a-z_0-9]+\}/', $r, $pr);
        sort($pu[0]);
        sort($pr[0]);
        if ($pu[0] !== $pr[0]) {
            continue;
        }
        $counts[$u][$r] = ($counts[$u][$r] ?? 0) + 1;
        $stat['pairs']++;
    }
}

$out = [];
foreach ($counts as $u => $variants) {
    arsort($variants);
    $out[$u] = array_key_first($variants);
}
ksort($out);
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
fprintf(STDERR, "файлов: %d (по порядку %d, выравниванием %d), пар-вхождений: %d, уникальных фраз: %d\n",
    $stat['files'], $stat['positional'], $stat['aligned'], $stat['pairs'], count($out));

function items(string $path, string $rel): array {
    $src = file_get_contents($path);
    if (!hasCyr($src)) {
        return [];
    }
    $manual = [];
    $list = str_ends_with($rel, '.php') ? scanPhp($src, $rel, $manual) : scanTemplate($src, $rel, $manual);
    usort($list, fn($x, $y) => $x['start'] <=> $y['start']);
    foreach ($list as &$x) {
        // подпись — код перед фразой без кириллицы (одинаков в обеих версиях, если код не менялся)
        $ctx = substr($src, max(0, $x['start'] - 80), min(80, $x['start']));
        $x['sig'] = preg_replace('/\s+/', ' ', preg_replace('/[^\x00-\x7F]/', '', $ctx));
    }
    return $list;
}

// наибольшая общая подпоследовательность по подписям
function align(array $a, array $b): array {
    $n = count($a);
    $m = count($b);
    $L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
    for ($i = $n - 1; $i >= 0; $i--) {
        for ($j = $m - 1; $j >= 0; $j--) {
            $L[$i][$j] = $a[$i]['sig'] === $b[$j]['sig'] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
        }
    }
    $pairs = [];
    for ($i = 0, $j = 0; $i < $n && $j < $m;) {
        if ($a[$i]['sig'] === $b[$j]['sig']) {
            $pairs[] = [$a[$i], $b[$j]];
            $i++;
            $j++;
        } elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) {
            $i++;
        } else {
            $j++;
        }
    }
    return $pairs;
}
