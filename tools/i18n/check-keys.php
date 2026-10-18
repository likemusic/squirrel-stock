<?php
/**
 * SquirrelStock: новые ключи перевода — только осмысленные (ZIPPY_GAPS п. 24).
 *
 * Черновой ключ конвертера — транслит украинской фразы: 'app.base.drukuvaty'. Такие ключи из кода автора Zippy
 * разрешены списком tools/i18n/translit-keys.txt (снимок); любой другой транслит-ключ в lang/<язык>.json —
 * ошибка: дать ему имя по смыслу ('doc.list.btn_print'), для готового кода — tools/i18n/rename.php.
 *
 * php tools/i18n/check-keys.php [--root=www]             проверка: код возврата 1 и список новых транслит-ключей
 * php tools/i18n/check-keys.php [--root=www] --update    переписать список (после обновления от автора и convert.php;
 *                                                        английское слово, совпавшее с транслитом: model, period)
 */

require __DIR__ . '/lib.php';

$root = dirname(__DIR__, 2) . '/www';
$update = false;
$list = __DIR__ . '/translit-keys.txt';
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--update') {
        $update = true;
    } elseif (str_starts_with($a, '--root=')) {
        $root = rtrim(substr($a, 7), '/');
    } elseif (str_starts_with($a, '--list=')) {
        $list = substr($a, 7);
    } else {
        fwrite(STDERR, "неизвестный параметр {$a}\n");
        exit(2);
    }
}
$root = realpath($root) or die("нет каталога {$root}\n");

$found = [];   // ключ => [словарь, фраза]
foreach (langDirs($root) as $dir) {
    $uk = readJson("{$root}/{$dir}/uk.json");
    $ru = readJson("{$root}/{$dir}/ru.json");
    foreach ($uk + $ru as $key => $_) {
        $phrase = $uk[$key] ?? $ru[$key];
        if (isTranslitKey($key, $phrase)) {
            $found[$key] = [$dir, $phrase];
        }
    }
}
ksort($found, SORT_STRING);

if ($update) {
    file_put_contents($list, implode("\n", array_keys($found)) . "\n");
    fprintf(STDERR, "%s: %d ключей\n", basename($list), count($found));
    exit(0);
}

$allowed = array_flip(file($list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$new = array_diff_key($found, $allowed);
$gone = count(array_diff_key($allowed, $found));
foreach ($new as $key => [$dir, $phrase]) {
    printf("%s: %s = %s\n", $dir, $key, $phrase);
}
fprintf(STDERR, "транслит-ключей: %d, разрешенных: %d, новых: %d%s\n", count($found), count($allowed), count($new),
    $gone ? ", в списке без словаря: {$gone} (переименованы или удалены — убрать: --update)" : '');
if ($new) {
    fwrite(STDERR, "Новый ключ — по смыслу, а не транслит фразы: docs/DEVELOPMENT.md, «Interface languages»\n");
    exit(1);
}
