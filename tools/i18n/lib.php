<?php
/**
 * Разбор украинских (и любых кириллических) фраз в PHP и шаблонах: общая часть convert.php и pairs.php.
 */

const CYR = '/[А-Яа-яІіЇїЄєҐґЁё]/u';


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
    // смена разделителей Mustache ({{={| |}=}} — чтобы {{ }} достались Vue): метки ставятся текущими разделителями
    preg_match_all('/(?:\{\{|\{\|)=(\S+)\s+(\S+)=(?:\}\}|\|\})/', $src, $dm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    $delimAt = function ($off) use ($dm) {
        $d = ['{{', '}}'];
        foreach ($dm as $x) {
            if ($x[0][1] < $off) {
                $d = [$x[1][0], $x[2][0]];
            }
        }
        return $d;
    };
    $mark = function ($off) use ($delimAt) {
        [$o, $c] = $delimAt($off);
        return [$o . '#t' . $c, $o . '/t' . $c];
    };

    foreach ($chunks as [$kind, $text, $off]) {
        if (!hasCyr($text)) {
            continue;
        }
        if ($kind === 'comment' || $kind === 'style') {
            continue;
        }
        if ($kind === 'script') {
            // строковые литералы JS: шаблон Mustache обрабатывает и <script>, метка внутри кавычек даёт перевод.
            // Перевод не должен содержать кавычку литерала — проверяет tools/i18n/merge_tsv.py по lang/quoted-keys.json
            preg_match_all('/\'(?:[^\'\\\\\n]|\\\\.)*\'|"(?:[^"\\\\\n]|\\\\.)*"|`(?:[^`\\\\]|\\\\.)*`/', $text, $lm, PREG_OFFSET_CAPTURE);
            foreach ($lm[0] as [$lit, $loff]) {
                $inner = substr($lit, 1, -1);
                if (!hasCyr($inner)) {
                    continue;
                }
                if ($lit[0] === '`' && str_contains($inner, '${')) {
                    $manual[] = ['file' => $rel, 'line' => $lineAt($off + $loff), 'reason' => 'шаблонная строка JS', 'text' => mb_substr($lit, 0, 200)];
                    continue;
                }
                $core = trim($inner);
                $lead = strlen($inner) - strlen(ltrim($inner));
                [$b, $e] = $mark($off + $loff);
                $out[] = ['start' => $off + $loff + 1 + $lead, 'len' => strlen($core), 'kind' => 'tpl', 'phrase' => stripcslashes($core), 'before' => $b, 'after' => $e, 'quote' => $lit[0]];
            }
            continue;
        }
        if ($kind === 'tag') {
            $isButton = preg_match('#^<\s*(button|input[^>]*type\s*=\s*["\']?(submit|button|reset))#i', $text);
            preg_match_all('#\s(placeholder|title|alt|aria-label|data-bs-original-title|data-original-title|data-bs-content|data-content|value)\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $text, $am, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
            foreach ($am as $a) {
                $attr = strtolower($a[1][0]);
                $val = $a[3][1] >= 0 ? $a[3][0] : $a[4][0];
                $quote = $a[3][1] >= 0 ? '"' : "'";
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
                [$b, $e] = $mark($off);
                $out[] = ['start' => $off + $voff + strlen($lead), 'len' => strlen($core), 'kind' => 'tpl', 'phrase' => preg_replace('/\s+/u', ' ', $core), 'before' => $b, 'after' => $e, 'quote' => $quote];
            }
            // остальной текст тега с кириллицей (например, в onclick) — руками
            $rest = preg_replace('#\s(placeholder|title|alt|aria-label|data-bs-original-title|data-original-title|data-bs-content|data-content|value)\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $text);
            if (hasCyr($rest)) {
                $manual[] = ['file' => $rel, 'line' => $lineAt($off), 'reason' => 'кириллица в другом атрибуте тега', 'text' => mb_substr($text, 0, 200)];
            }
            continue;
        }
        // текст: делим по управляющим тегам Mustache ({{#x}}, {{^x}}, {{/x}}, {{!x}}, {{>x}})
        $parts = preg_split('/(\{\{[#^\/!>=][^}]*\}\}|\{\|[#^\/!>=][^|]*\|\})/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE);
        foreach ($parts as [$seg, $soff]) {
            if (!hasCyr($seg) || preg_match('/^(\{\{|\{\|)[#^\/!>=]/', $seg)) {
                continue;
            }
            $core = trim($seg);
            $lead = strlen($seg) - strlen(ltrim($seg));
            [$b, $e] = $mark($off + $soff);
            $out[] = ['start' => $off + $soff + $lead, 'len' => strlen($core), 'kind' => 'tpl', 'phrase' => preg_replace('/\s+/u', ' ', $core), 'before' => $b, 'after' => $e];
        }
    }
    return $out;
}

// ---------- словари ----------

// каталоги словарей относительно root: основной и модулей SquirrelStock (ключи одного пространства — переводчик их сливает)
function langDirs(string $root): array {
    $dirs = ['lang'];
    foreach (glob($root . '/app/sqs/modules/*/lang', GLOB_ONLYDIR) as $d) {
        $dirs[] = substr($d, strlen($root) + 1);
    }
    return $dirs;
}

function readJson(string $file): array {
    return is_file($file) ? json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : [];
}

function writeJson(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

// черновой ключ конвертера: последняя часть — транслит фразы (slug), может быть с _2, _3 при совпадении
function isTranslitKey(string $key, string $phrase): bool {
    if (!hasCyr($phrase)) {
        return false;
    }
    $tail = substr($key, strrpos($key, '.') === false ? 0 : strrpos($key, '.') + 1);
    $s = slug($phrase);
    return $tail === $s || preg_match('/^' . preg_quote($s, '/') . '_\d+$/', $tail);
}
