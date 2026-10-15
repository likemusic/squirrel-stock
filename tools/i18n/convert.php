<?php
/**
 * SquirrelStock: конвертер «украинский текст в коде → английский ключ».
 *
 * Находит украинские фразы в PHP (через token_get_all) и в шаблонах (текст между тегами, атрибуты
 * placeholder/title/alt/aria-label, value у кнопок) и заменяет их:
 *   PHP:      'Зберегти'                 → __('common.zberehty')
 *             "Документ {$n} проведено"  → __('doc.dokument_provedeno', ['n' => $n])
 *   шаблоны:  Зберегти                   → {{#t}}common.zberehty{{/t}}
 * Тексты пишутся в lang/uk.json (ключ → фраза). Фраза, которая уже есть в uk.json, получает её ключ,
 * новая — черновой ключ из транслитерации (переименовать потом: tools/i18n/rename.php).
 * Опасные места не трогаются и попадают в отчёт: SQL, ключи массивов, значения по умолчанию
 * (константы, свойства, параметры), heredoc, JavaScript в шаблонах.
 *
 * php tools/i18n/convert.php [--dry-run] [--report=файл.json] [--root=www] [пути относительно root ...]
 * По умолчанию: app templates (кроме templates/site — сайт автора Zippy).
 */

require __DIR__ . '/lib.php';

$opts = ['dry-run' => false, 'report' => null, 'root' => dirname(__DIR__, 2) . '/www'];
$paths = [];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--dry-run') {
        $opts['dry-run'] = true;
    } elseif (str_starts_with($a, '--report=')) {
        $opts['report'] = substr($a, 9);
    } elseif (str_starts_with($a, '--root=')) {
        $opts['root'] = rtrim(substr($a, 7), '/');
    } else {
        $paths[] = $a;
    }
}
$root = realpath($opts['root']) or die("нет каталога {$opts['root']}\n");
$paths = $paths ?: ['app', 'templates'];
$exclude = ['templates/site/', 'app/translator.php', 'app/functions.php'];   // сайт автора Zippy; сам переводчик

$files = [];
foreach ($paths as $p) {
    $full = $root . '/' . $p;
    if (is_file($full)) {
        $files[] = $p;
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $rel = substr($f->getPathname(), strlen($root) + 1);
        if (!preg_match('/\.(php|html|tpl)$/', $rel)) {
            continue;
        }
        foreach ($exclude as $ex) {
            if (str_starts_with($rel, $ex)) {
                continue 2;
            }
        }
        $files[] = $rel;
    }
}
sort($files);

// ---------- проход 1: найти фразы ----------
$found = [];   // file => [ [start, len, kind, phrase, params(expr map), wrapSpaces] ]
$manual = [];  // [file, line, reason, text]
foreach ($files as $rel) {
    $src = file_get_contents($root . '/' . $rel);
    if (!preg_match(CYR, $src)) {
        continue;
    }
    $found[$rel] = str_ends_with($rel, '.php') ? scanPhp($src, $rel, $manual) : scanTemplate($src, $rel, $manual);
}

// ---------- ключи ----------
$ukFile = $root . '/lang/uk.json';
$uk = is_file($ukFile) ? json_decode(file_get_contents($ukFile), true) : [];
$byPhrase = [];
foreach ($uk as $k => $v) {
    $byPhrase[$v] ??= $k;
}
$areas = [];  // phrase => set of areas
foreach ($found as $rel => $items) {
    foreach ($items as $it) {
        $areas[$it['phrase']][area($rel)] = true;
    }
}
$newKeys = [];
foreach ($areas as $phrase => $set) {
    if (isset($byPhrase[$phrase])) {
        continue;
    }
    // фраза из трёх и более разделов — общая; иначе раздел основной программы важнее модуля
    $list = array_keys($set);
    usort($list, fn($x, $y) => [str_starts_with($x, 'mod.'), $x] <=> [str_starts_with($y, 'mod.'), $y]);
    $prefix = count($set) >= 3 ? 'common' : $list[0];
    $base = $prefix . '.' . slug($phrase);
    $key = $base;
    for ($i = 2; isset($uk[$key]); $i++) {
        $key = $base . '_' . $i;
    }
    $uk[$key] = $phrase;
    $byPhrase[$phrase] = $key;
    $newKeys[$key] = $phrase;
}

// ---------- проход 2: замены ----------
$changed = 0;
$count = 0;
foreach ($found as $rel => $items) {
    if (!$items) {
        continue;
    }
    $src = file_get_contents($root . '/' . $rel);
    usort($items, fn($a, $b) => $b['start'] <=> $a['start']);
    foreach ($items as $it) {
        $key = $byPhrase[$it['phrase']];
        if ($it['kind'] === 'php') {
            $code = "__('{$key}'";
            if ($it['params']) {
                $code .= ', [' . implode(', ', array_map(fn($n, $e) => "'{$n}' => {$e}", array_keys($it['params']), $it['params'])) . ']';
            }
            $code .= ')';
        } else {
            $code = $it['before'] . $key . $it['after'];
        }
        $src = substr_replace($src, $code, $it['start'], $it['len']);
        $count++;
    }
    $changed++;
    if (!$opts['dry-run']) {
        file_put_contents($root . '/' . $rel, $src);
    }
}
if (!$opts['dry-run']) {
    ksort($uk);
    file_put_contents($ukFile, json_encode($uk, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

$report = ['files' => $changed, 'replacements' => $count, 'new_keys' => $newKeys, 'manual' => $manual];
if ($opts['report']) {
    file_put_contents($opts['report'], json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}
$reasons = array_count_values(array_column($manual, 'reason'));
arsort($reasons);
fprintf(STDERR, "файлов изменено: %d, замен: %d, новых ключей: %d, оставлено для ручной правки: %d%s\n",
    $changed, $count, count($newKeys), count($manual), $opts['dry-run'] ? ' (dry-run, файлы не записаны)' : '');
foreach ($reasons as $r => $n) {
    fprintf(STDERR, "  %5d  %s\n", $n, $r);
}

