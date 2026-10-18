<?php
/**
 * SquirrelStock: переименование ключей перевода по карте (ZIPPY_GAPS п. 24: черновые транслит-ключи → имена по смыслу).
 *
 * Карта — TSV «старый<TAB>новый», строки с # — комментарии. Заменяется:
 *   PHP      — строковый литерал, целиком равный старому ключу: __('k'), массивы ключей, __($a ? 'k1' : 'k2'); комментарии — нет;
 *   шаблоны  — ключ внутри {{#t}}…{{/t}} и {|#t|}…{|/t|};
 *   словари  — ключ в lang/uk.json, ru.json, quoted-keys.json и в словарях модулей, на том же месте (порядок не меняется);
 *   список разрешенных транслит-ключей (check-keys.php) — старые ключи убираются.
 * Два старых ключа в один новый — только при одинаковых текстах uk и ru.
 *
 * Ничего не записывается, если:
 *   - в карте дубль, ключ сам в себя или ключа нет в словарях;
 *   - новое имя занято, совпадает с началом другого ключа или другой ключ — с началом нового ('a.b' и 'a.b.c');
 *   - в коде ключ собирается из куска ('sqs.eqhist.kind_' . $x), с которого начинается старое или новое имя;
 *   - не сходится проверка после замены:
 *     каждый файл, «отрисованный» через uk.json и через ru.json (ключ → текст), побайтно тот же, что до замены;
 *     в словарях те же тексты, ключей меньше ровно на число склеенных, у uk и ru одинаковые ключи (если были);
 *     старых ключей в коде не осталось.
 *
 * php tools/i18n/rename.php --map=карта.tsv [--root=www] [--list=translit-keys.txt] [--dry-run] [пути относительно root ...]
 * Пути по умолчанию: app templates cli. Список по умолчанию — tools/i18n/translit-keys.txt, если root не задан.
 * Отчет — в stderr: замен по каждому ключу; ключи без единой замены в коде (собираются из кусков или только в тестах).
 */

require __DIR__ . '/lib.php';

const KEY_RE = '/^[a-z0-9_]+(\.[a-z0-9_]+)+$/';
const TPL_RE = '/(\{\{#t\}\}|\{\|#t\|\})(\s*)([^{}|]*?)(\s*)(\{\{\/t\}\}|\{\|\/t\|\})/';

$opts = ['map' => null, 'root' => null, 'list' => null, 'dry-run' => false];
$paths = [];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--dry-run') {
        $opts['dry-run'] = true;
    } elseif (preg_match('/^--(map|root|list)=(.+)$/', $a, $m)) {
        $opts[$m[1]] = $m[2];
    } elseif (str_starts_with($a, '--')) {
        fwrite(STDERR, "неизвестный параметр {$a}\n");
        exit(2);
    } else {
        $paths[] = $a;
    }
}
if ($opts['map'] === null) {
    fwrite(STDERR, "php tools/i18n/rename.php --map=карта.tsv [--root=www] [--list=файл] [--dry-run] [пути ...]\n");
    exit(2);
}
if ($opts['root'] === null) {
    $opts['root'] = dirname(__DIR__, 2) . '/www';
    $opts['list'] ??= __DIR__ . '/translit-keys.txt';
}
$root = realpath($opts['root']) or die("нет каталога {$opts['root']}\n");
$paths = $paths ?: ['app', 'templates', 'cli'];
$errors = [];

// ---------- карта ----------
$map = [];
foreach (file($opts['map'], FILE_IGNORE_NEW_LINES) as $n => $line) {
    $line = rtrim($line, "\r");
    if (trim($line) === '' || $line[0] === '#') {
        continue;
    }
    $f = explode("\t", $line);
    if (count($f) != 2 || !preg_match(KEY_RE, $f[0]) || !preg_match(KEY_RE, $f[1])) {
        $errors[] = 'карта, строка ' . ($n + 1) . ': нужно «старый<TAB>новый» из a-z 0-9 _ и точек';
    } elseif (isset($map[$f[0]])) {
        $errors[] = "в карте дважды: {$f[0]}";
    } elseif ($f[0] === $f[1]) {
        $errors[] = "ключ сам в себя: {$f[0]}";
    } else {
        $map[$f[0]] = $f[1];
    }
}

// ---------- словари ----------
$dicts = [];   // 'lang/uk.json' => [ключ => текст]
foreach (langDirs($root) as $dir) {
    foreach (['uk', 'ru', 'quoted-keys'] as $name) {
        if (is_file("{$root}/{$dir}/{$name}.json")) {
            $dicts["{$dir}/{$name}.json"] = readJson("{$root}/{$dir}/{$name}.json");
        }
    }
}
$isText = fn($file) => !str_ends_with($file, '/quoted-keys.json');
// словарь языка, как его видит переводчик: основной + модули, при совпадении — первый
$merged = function (array $dicts, string $lang) {
    $out = [];
    foreach ($dicts as $file => $d) {
        if (basename($file) === "{$lang}.json") {
            $out += $d;
        }
    }
    return $out;
};
$all = $merged($dicts, 'uk') + $merged($dicts, 'ru');

foreach ($map as $old => $new) {
    if (!isset($all[$old])) {
        $errors[] = "нет в словарях: {$old}";
    }
    if (isset($all[$new]) && !isset($map[$new])) {
        $errors[] = "имя занято: {$old} → {$new}";
    }
}
// склейка: у всех старых ключей одного нового — одинаковые тексты и кавычка
$groups = [];
foreach ($map as $old => $new) {
    $groups[$new][] = $old;
}
foreach ($groups as $new => $olds) {
    if (count($olds) < 2) {
        continue;
    }
    foreach ($dicts as $file => $d) {
        $values = array_unique(array_map(fn($o) => $d[$o] ?? null, $olds), SORT_REGULAR);
        if (count($values) > 1) {
            $errors[] = "склейка в {$new}: разные значения в {$file}: " . implode(', ', $olds);
        }
    }
}
// начало другого ключа: среди ключей после переименования
$final = array_diff_key($all, $map) + array_fill_keys($map, true);
foreach (array_unique($map) as $new) {
    $p = explode('.', $new);
    for ($i = 1; $i < count($p); $i++) {
        $head = implode('.', array_slice($p, 0, $i));
        if (isset($final[$head])) {
            $errors[] = "ключ {$head} — начало нового имени {$new}";
        }
    }
    foreach ($final as $k => $_) {
        if (str_starts_with($k, $new . '.')) {
            $errors[] = "новое имя {$new} — начало ключа {$k}";
            break;
        }
    }
}

// ---------- код ----------
$files = [];
foreach ($paths as $p) {
    $full = $root . '/' . $p;
    if (is_file($full)) {
        $files[] = $p;
        continue;
    }
    if (!is_dir($full)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $rel = substr($f->getPathname(), strlen($root) + 1);
        if (preg_match('/\.(php|html|tpl)$/', $rel)) {
            $files[] = $rel;
        }
    }
}
sort($files);

// замена ключей в коде: $fn(ключ) → строка-замена или null (не трогать); в PHP замена — уже готовый литерал
function transform(string $src, bool $php, callable $fn): string {
    if (!$php) {
        return preg_replace_callback(TPL_RE, fn($m) => ($r = $fn(trim($m[3]))) === null ? $m[0] : $m[1] . $m[2] . $r . $m[4] . $m[5], $src);
    }
    $out = '';
    foreach (token_get_all($src) as $t) {
        if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING && ($r = $fn(decodeConst($t[1]))) !== null) {
            $out .= $r;
        } else {
            $out .= is_array($t) ? $t[1] : $t;
        }
    }
    return $out;
}

// кусок ключа перед конкатенацией: 'sqs.eqhist.kind_' . $x, "sqs.doclog.{$f}"
function keyPrefixes(string $src): array {
    $tokens = array_values(array_filter(token_get_all($src), fn($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    $out = [];
    foreach ($tokens as $i => $t) {
        $v = null;
        if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING && ($tokens[$i + 1] ?? null) === '.') {
            $v = decodeConst($t[1]);
        } elseif ($t === '"' && is_array($tokens[$i + 1] ?? null) && $tokens[$i + 1][0] === T_ENCAPSED_AND_WHITESPACE && ($tokens[$i + 2] ?? '"') !== '"') {
            $v = $tokens[$i + 1][1];
        }
        if ($v !== null && preg_match('/^[a-z0-9_]+\.[a-z0-9_.]*$/', $v)) {
            $out[] = [$v, is_array($t) ? $t[2] : $tokens[$i + 1][2]];
        }
    }
    return $out;
}

$render = function (string $src, bool $php, array $dict) {
    return transform($src, $php, fn($k) => isset($dict[$k]) ? ($php ? var_export($dict[$k], true) : $dict[$k]) : null);
};
$count = array_fill_keys(array_keys($map), 0);
$before = [];
$after = [];
foreach ($files as $rel) {
    $src = file_get_contents($root . '/' . $rel);
    $php = str_ends_with($rel, '.php');
    if ($php) {
        foreach (keyPrefixes($src) as [$prefix, $line]) {
            foreach ($map as $old => $new) {
                foreach ([$old, $new] as $k) {
                    if (str_starts_with($k, $prefix)) {
                        $errors[] = "{$rel}:{$line}: ключ собирается из '{$prefix}' — под него попадает {$k}";
                    }
                }
            }
        }
    }
    $new = transform($src, $php, function ($k) use ($map, &$count, $php) {
        if (!isset($map[$k])) {
            return null;
        }
        $count[$k]++;
        return $php ? "'{$map[$k]}'" : $map[$k];
    });
    if ($new !== $src) {
        $before[$rel] = $src;
        $after[$rel] = $new;
    }
}

// ---------- словари после ----------
$newDicts = [];
$mergedIn = [];   // файл => сколько ключей склеено
foreach ($dicts as $file => $d) {
    $out = [];
    foreach ($d as $k => $v) {
        $nk = $map[$k] ?? $k;
        if (array_key_exists($nk, $out)) {
            $mergedIn[$file] = ($mergedIn[$file] ?? 0) + 1;
            continue;
        }
        $out[$nk] = $v;
    }
    $newDicts[$file] = $out;
}

// ---------- проверка ----------
if (!$errors) {
    foreach (['uk', 'ru'] as $lang) {
        $old = $merged($dicts, $lang);
        $cur = $merged($newDicts, $lang);
        foreach ($before as $rel => $src) {
            $php = str_ends_with($rel, '.php');
            if ($render($src, $php, $old) !== $render($after[$rel], $php, $cur)) {
                $errors[] = "{$rel}: текст через {$lang}.json после замены другой";
            }
        }
    }
    foreach ($dicts as $file => $d) {
        $nd = $newDicts[$file];
        if (count($nd) != count($d) - ($mergedIn[$file] ?? 0)) {
            $errors[] = "{$file}: ключей " . count($nd) . ' вместо ' . (count($d) - ($mergedIn[$file] ?? 0));
        }
        $vals = fn($x) => array_values(array_unique(array_map('strval', $x)));
        if ($isText($file) && array_diff($vals($d), $vals($nd)) + array_diff($vals($nd), $vals($d))) {
            $errors[] = "{$file}: изменился набор текстов";
        }
    }
    foreach (langDirs($root) as $dir) {
        $k = fn($dd, $l) => array_keys($dd["{$dir}/{$l}.json"] ?? []);
        $same = fn($dd) => !array_diff($k($dd, 'uk'), $k($dd, 'ru')) && !array_diff($k($dd, 'ru'), $k($dd, 'uk'));
        if ($same($dicts) && !$same($newDicts)) {
            $errors[] = "{$dir}: у uk.json и ru.json разные ключи";
        }
    }
    // старый ключ где-то кроме литерала и {{#t}} — в строке с подстановкой, в атрибуте, в JS
    $olds = array_diff_key($map, array_flip($map));   // ключ, который сам стал новым именем другого (обмен), не ищется
    if ($olds) {
        $re = '/(?<![a-z0-9_.])(' . implode('|', array_map(fn($k) => preg_quote($k, '/'), array_keys($olds))) . ')(?![a-z0-9_]|\.[a-z0-9_])/';
        foreach ($files as $rel) {
            $src = $after[$rel] ?? file_get_contents($root . '/' . $rel);
            if (str_ends_with($rel, '.php')) {   // комментарии не в счет
                $src = implode('', array_map(fn($t) => is_array($t) ? (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : $t[1]) : $t, token_get_all($src)));
            }
            if (preg_match_all($re, $src, $m)) {
                $errors[] = "{$rel}: старый ключ остался: " . implode(', ', array_unique($m[1]));
            }
        }
    }
}

if ($errors) {
    fwrite(STDERR, implode("\n", array_unique($errors)) . "\nничего не записано\n");
    exit(1);
}

// ---------- запись ----------
if (!$opts['dry-run']) {
    foreach ($after as $rel => $src) {
        file_put_contents($root . '/' . $rel, $src);
    }
    foreach ($newDicts as $file => $d) {
        if ($d !== $dicts[$file]) {
            writeJson($root . '/' . $file, $d);
        }
    }
    if ($opts['list'] !== null && is_file($opts['list'])) {
        $list = file($opts['list'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $keep = array_values(array_filter($list, fn($k) => !isset($map[$k])));
        if ($keep !== $list) {
            file_put_contents($opts['list'], $keep ? implode("\n", $keep) . "\n" : '');
        }
    }
}
$unused = array_keys(array_filter($count, fn($n) => $n == 0));
fprintf(STDERR, "ключей: %d, файлов кода: %d, замен: %d, склеено: %d%s\n", count($map), count($after), array_sum($count),
    array_sum($mergedIn), $opts['dry-run'] ? ' (dry-run, файлы не записаны)' : '');
foreach ($count as $k => $n) {
    fprintf(STDERR, "  %4d  %s → %s\n", $n, $k, $map[$k]);
}
if ($unused) {
    fprintf(STDERR, "без замен в коде (собирается из кусков, только в тестах или не используется): %s\n", implode(', ', $unused));
}
