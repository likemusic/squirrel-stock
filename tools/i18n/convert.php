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

const CYR = '/[А-Яа-яІіЇїЄєҐґЁё]/u';

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
$exclude = ['templates/site/'];

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
            $code = $it['before'] . '{{#t}}' . $key . '{{/t}}' . $it['after'];
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

// =====================================================================

function area(string $rel): string {
    $rel = preg_replace('/\.(php|html|tpl)$/', '', $rel);
    $parts = explode('/', $rel);
    $name = strtolower(array_pop($parts));
    $parts = array_values(array_diff($parts, ['app', 'templates', 'pages', 'entity']));
    if ($parts && $parts[0] === 'printforms') {
        return 'print.' . $name;
    }
    if ($parts && $parts[0] === 'modules') {
        return 'mod.' . ($parts[1] ?? 'x') . '.' . $name;
    }
    $group = $parts ? end($parts) : 'app';
    return $group . '.' . $name;
}

function slug(string $phrase): string {
    static $tr = ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ie', 'ж' => 'zh', 'з' => 'z',
        'и' => 'y', 'і' => 'i', 'ї' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '',
        'ю' => 'iu', 'я' => 'ia', 'ы' => 'y', 'э' => 'e', 'ё' => 'io', 'ъ' => '', '\'' => '', '’' => '', '`' => ''];
    $s = mb_strtolower(strip_tags(preg_replace('/\{\{\{?[^}]*\}?\}\}|\{[a-z_0-9]+\}/i', ' ', $phrase)));
    $s = strtr($s, $tr);
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    $words = array_slice(array_filter(explode('_', $s), 'strlen'), 0, 5);
    $s = substr(implode('_', $words), 0, 40);
    return $s !== '' ? rtrim($s, '_') : 'x';
}

function hasCyr(string $s): bool {
    return (bool)preg_match(CYR, $s);
}

function looksLikeSql(string $s): bool {
    return (bool)preg_match('/\b(select|insert\s+into|update\s+\w+\s+set|delete\s+from|where|order\s+by|group\s+by)\b/i', $s);
}

// ---------- PHP ----------

function scanPhp(string $src, string $rel, array &$manual): array {
    $tokens = token_get_all($src);
    $out = [];
    $pos = 0;
    $offsets = [];
    foreach ($tokens as $i => $t) {
        $offsets[$i] = $pos;
        $pos += strlen(is_array($t) ? $t[1] : $t);
    }
    $sig = [];  // индексы значимых токенов
    foreach ($tokens as $i => $t) {
        if (!is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $sig[] = $i;
        }
    }
    $sigPos = array_flip($sig);
    $txt = fn($i) => is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
    $id = fn($i) => is_array($tokens[$i]) ? $tokens[$i][0] : $tokens[$i];
    $prev = function ($i, $n = 1) use ($sig, $sigPos) {
        $p = $sigPos[$i] - $n;
        return $p >= 0 ? $sig[$p] : null;
    };
    $next = function ($i, $n = 1) use ($sig, $sigPos) {
        $p = $sigPos[$i] + $n;
        return $p < count($sig) ? $sig[$p] : null;
    };

    $stack = [];          // 'class' | 'function' | 'block'
    $pending = null;      // 'class' | 'function' — ждём '{'
    $parenDepth = 0;      // глубина скобок в заголовке функции
    $inFuncHeader = false;
    $n = count($tokens);
    $lineOf = function ($i) use ($tokens) {
        for ($j = $i; $j >= 0; $j--) {
            if (is_array($tokens[$j])) {
                return $tokens[$j][2];
            }
        }
        return 0;
    };

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        $tid = $id($i);
        if (in_array($tid, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        if (in_array($tid, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            $p = $prev($i);
            if ($p === null || $id($p) !== T_DOUBLE_COLON) {
                $pending = 'class';
            }
            continue;
        }
        if ($tid === T_FUNCTION) {
            $pending = 'function';
            $inFuncHeader = true;
            $parenDepth = 0;
            continue;
        }
        if ($inFuncHeader) {
            if ($t === '(') {
                $parenDepth++;
            } elseif ($t === ')') {
                $parenDepth--;
            } elseif ($t === ';' && $parenDepth === 0) {   // абстрактный метод
                $inFuncHeader = false;
                $pending = null;
            }
        }
        if ($t === '{' || $tid === T_CURLY_OPEN || $tid === T_DOLLAR_OPEN_CURLY_BRACES) {
            if ($t === '{' && $pending !== null && !($inFuncHeader && $parenDepth > 0)) {
                $stack[] = $pending;
                $pending = null;
                $inFuncHeader = false;
            } else {
                $stack[] = 'block';
            }
            continue;
        }
        if ($t === '}') {
            array_pop($stack);
            continue;
        }

        $isConst = $tid === T_CONSTANT_ENCAPSED_STRING;
        $isEncaps = $t === '"';
        $isHeredoc = $tid === T_START_HEREDOC;
        if (!$isConst && !$isEncaps && !$isHeredoc) {
            continue;
        }

        // конец строкового выражения
        $end = $i;
        if ($isEncaps || $isHeredoc) {
            for ($end = $i + 1; $end < $n && ($isEncaps ? $tokens[$end] !== '"' : $id($end) !== T_END_HEREDOC); $end++);
        }
        $raw = '';
        for ($j = $i; $j <= $end; $j++) {
            $raw .= $txt($j);
        }
        $literalText = '';
        for ($j = $i; $j <= $end; $j++) {
            if ($isConst || $id($j) === T_ENCAPSED_AND_WHITESPACE) {
                $literalText .= $txt($j);
            }
        }
        if (!hasCyr($literalText)) {
            $i = $end;
            continue;
        }
        $ln = $lineOf($i);
        $skip = function ($reason) use (&$manual, $rel, $ln, $raw) {
            $manual[] = ['file' => $rel, 'line' => $ln, 'reason' => $reason, 'text' => mb_substr($raw, 0, 200)];
        };

        $inFunction = in_array('function', $stack, true);
        $inClass = in_array('class', $stack, true);
        $p1 = $prev($i);
        $n1 = $next($end);
        if ($isHeredoc) {
            $skip('heredoc');
        } elseif ($inFuncHeader && $parenDepth > 0) {
            $skip('значение параметра по умолчанию');
        } elseif ($inClass && !$inFunction) {
            $skip('константа или свойство класса');
        } elseif (looksLikeSql($literalText)) {
            $skip('SQL');
        } elseif ($n1 !== null && $id($n1) === T_DOUBLE_ARROW) {
            $skip('ключ массива');
        } elseif ($p1 !== null && $tokens[$p1] === '[' && $n1 !== null && $tokens[$n1] === ']' && ($pp = $prev($p1)) !== null && in_array($id($pp), [T_VARIABLE, ']', ')', T_STRING], true)) {
            $skip('индекс массива');
        } elseif ($p1 !== null && $id($p1) === T_CASE) {
            $skip('case');
        } elseif (staticOrConstStatement($i, $tokens, $prev, $id)) {
            $skip('static/const');
        } elseif ($isConst && preg_match('#^[\'"][/~\#].*[/~\#][imsxuU]*[\'"]$#s', $raw)) {
            $skip('регулярное выражение');
        } elseif ($isConst) {
            $out[] = ['start' => $offsets[$i], 'len' => strlen($raw), 'kind' => 'php', 'phrase' => decodeConst($raw), 'params' => []];
        } else {
            $r = encapsToPhrase($tokens, $i, $end, $txt, $id);
            if ($r === null) {
                $skip('сложная подстановка в строке');
            } else {
                $out[] = ['start' => $offsets[$i], 'len' => strlen($raw), 'kind' => 'php', 'phrase' => $r[0], 'params' => $r[1]];
            }
        }
        $i = $end;
    }
    return $out;
}

// оператор начинается с static $x = или const X =
function staticOrConstStatement(int $i, array $tokens, $prev, $id): bool {
    for ($j = $prev($i); $j !== null; $j = $prev($j)) {
        $t = $tokens[$j];
        if ($t === ';' || $t === '{' || $t === '}') {
            break;
        }
        if (in_array($id($j), [T_CONST], true)) {
            return true;
        }
        if ($id($j) === T_STATIC) {
            $n = $j + 1;
            while (is_array($tokens[$n]) && $tokens[$n][0] === T_WHITESPACE) {
                $n++;
            }
            if (is_array($tokens[$n]) && $tokens[$n][0] === T_VARIABLE) {
                $p = $prev($j);
                if ($p === null || in_array($tokens[$p], [';', '{', '}'], true)) {
                    return true;
                }
            }
        }
    }
    return false;
}

function decodeConst(string $raw): string {
    $q = $raw[0];
    $body = substr($raw, 1, -1);
    if ($q === "'") {
        return str_replace(["\\\\", "\\'"], ["\\", "'"], $body);
    }
    return stripcslashes($body);
}

// "Текст {$a->b} ще $c" → ['Текст {b} ще {c}', ['b' => '$a->b', 'c' => '$c']]
function encapsToPhrase(array $tokens, int $i, int $end, $txt, $id): ?array {
    $phrase = '';
    $params = [];
    for ($j = $i + 1; $j < $end; $j++) {
        $tid = $id($j);
        if ($tid === T_ENCAPSED_AND_WHITESPACE) {
            $phrase .= stripcslashes($txt($j));
            continue;
        }
        if ($tid === T_CURLY_OPEN) {
            $depth = 1;
            $expr = '';
            for ($j++; $j < $end; $j++) {
                $t = $tokens[$j];
                if ($t === '{' || $id($j) === T_CURLY_OPEN) {
                    $depth++;
                } elseif ($t === '}') {
                    if (--$depth === 0) {
                        break;
                    }
                }
                $expr .= $txt($j);
            }
        } elseif ($tid === T_VARIABLE) {
            $expr = $txt($j);
            if ($id($j + 1) === T_OBJECT_OPERATOR && $id($j + 2) === T_STRING) {
                $expr .= '->' . $txt($j + 2);
                $j += 2;
            } elseif ($tokens[$j + 1] === '[') {
                return null;   // "$a[key]" — простой синтаксис индекса, руками
            }
        } else {
            return null;
        }
        $name = paramName($expr, $params);
        $params[$name] = $expr;
        $phrase .= '{' . $name . '}';
    }
    return [$phrase, $params];
}

function paramName(string $expr, array $params): string {
    preg_match_all('/[A-Za-z_][A-Za-z_0-9]*/', preg_replace('/\$this->/', '', $expr), $m);
    $words = array_values(array_filter($m[0], fn($w) => !in_array(strtolower($w), ['this', 'self', 'h', 'helper'], true)));
    $name = strtolower($words ? end($words) : 'v');
    $base = $name;
    for ($k = 2; isset($params[$name]); $k++) {
        $name = $base . $k;
    }
    return $name;
}

// ---------- шаблоны ----------

function scanTemplate(string $src, string $rel, array &$manual): array {
    $out = [];
    $re = '#(<!--.*?-->)|(<script\b.*?</script\s*>)|(<style\b.*?</style\s*>)|(<[^>]+>)#si';
    preg_match_all($re, $src, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    $pos = 0;
    $chunks = [];
    foreach ($m as $mm) {
        [$text, $off] = $mm[0];
        if ($off > $pos) {
            $chunks[] = ['text', substr($src, $pos, $off - $pos), $pos];
        }
        $kind = $mm[1][1] >= 0 && $mm[1][0] !== '' ? 'comment' : ((isset($mm[2]) && $mm[2][0] !== '') ? 'script' : ((isset($mm[3]) && $mm[3][0] !== '') ? 'style' : 'tag'));
        $chunks[] = [$kind, $text, $off];
        $pos = $off + strlen($text);
    }
    if ($pos < strlen($src)) {
        $chunks[] = ['text', substr($src, $pos), $pos];
    }
    $lineAt = fn($off) => substr_count($src, "\n", 0, $off) + 1;

    foreach ($chunks as [$kind, $text, $off]) {
        if (!hasCyr($text)) {
            continue;
        }
        if ($kind === 'comment' || $kind === 'style') {
            continue;
        }
        if ($kind === 'script') {
            $manual[] = ['file' => $rel, 'line' => $lineAt($off), 'reason' => 'JavaScript в шаблоне', 'text' => mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 200)];
            continue;
        }
        if ($kind === 'tag') {
            $isButton = preg_match('#^<\s*(button|input[^>]*type\s*=\s*["\']?(submit|button|reset))#i', $text);
            preg_match_all('#\s(placeholder|title|alt|aria-label|data-bs-original-title|data-original-title|value)\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $text, $am, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
            foreach ($am as $a) {
                $attr = strtolower($a[1][0]);
                $val = $a[3][1] >= 0 ? $a[3][0] : $a[4][0];
                $voff = $a[3][1] >= 0 ? $a[3][1] : $a[4][1];
                if (!hasCyr($val)) {
                    continue;
                }
                if ($attr === 'value' && !$isButton) {
                    $manual[] = ['file' => $rel, 'line' => $lineAt($off), 'reason' => 'атрибут value (данные)', 'text' => mb_substr($text, 0, 200)];
                    continue;
                }
                if (preg_match('/\{\{[#^\/]/', $val)) {
                    $manual[] = ['file' => $rel, 'line' => $lineAt($off), 'reason' => 'условие Mustache в атрибуте', 'text' => mb_substr($text, 0, 200)];
                    continue;
                }
                $core = trim($val);
                $lead = substr($val, 0, strlen($val) - strlen(ltrim($val)));
                $out[] = ['start' => $off + $voff + strlen($lead), 'len' => strlen($core), 'kind' => 'tpl', 'phrase' => preg_replace('/\s+/u', ' ', $core), 'before' => '', 'after' => ''];
            }
            // остальной текст тега с кириллицей (например, в onclick) — руками
            $rest = preg_replace('#\s(placeholder|title|alt|aria-label|data-bs-original-title|data-original-title|value)\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $text);
            if (hasCyr($rest)) {
                $manual[] = ['file' => $rel, 'line' => $lineAt($off), 'reason' => 'кириллица в другом атрибуте тега', 'text' => mb_substr($text, 0, 200)];
            }
            continue;
        }
        // текст: делим по управляющим тегам Mustache ({{#x}}, {{^x}}, {{/x}}, {{!x}}, {{>x}})
        $parts = preg_split('/(\{\{[#^\/!>=][^}]*\}\})/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE);
        foreach ($parts as [$seg, $soff]) {
            if (!hasCyr($seg) || preg_match('/^\{\{[#^\/!>=]/', $seg)) {
                continue;
            }
            $core = trim($seg);
            $lead = strlen($seg) - strlen(ltrim($seg));
            $out[] = ['start' => $off + $soff + $lead, 'len' => strlen($core), 'kind' => 'tpl', 'phrase' => preg_replace('/\s+/u', ' ', $core), 'before' => '', 'after' => ''];
        }
    }
    return $out;
}
