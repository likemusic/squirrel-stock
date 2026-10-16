<?php
/**
 * SquirrelStock: перевести значения по умолчанию в базе (названия документов и пунктов меню, основной склад,
 * касса, виды начислений, типы цен) на язык интерфейса. Тексты интерфейса переводятся словарями lang/*.json,
 * а эти значения лежат в базе (их записывает db/db.sql) и словарям не видны.
 *
 * Меняется только значение, совпадающее с украинским по умолчанию: то, что переименовал пользователь, не трогается.
 * Повторный запуск безопасен.
 *
 * php cli/localize-db.php ru          (в docker-стенде: docker exec -u www-data squirrelstock-web-1 php cli/localize-db.php ru)
 * php cli/localize-db.php ru --back   вернуть украинские значения
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$lang = $argv[1] ?? '';
$dictFile = __DIR__ . "/i18n/{$lang}.json";
if (!preg_match('/^[a-z]{2,3}$/', $lang) || !is_file($dictFile)) {
    fwrite(STDERR, "php cli/localize-db.php <язык>; словари: " . implode(', ', array_map(fn($f) => basename($f, '.json'), glob(__DIR__ . '/i18n/*.json'))) . "\n");
    exit(1);
}
$dict = json_decode(file_get_contents($dictFile), true);
// названия и группы пунктов меню модулей — из их module.php ('desc' => ['uk' => ..., 'ru' => ...])
require __DIR__ . '/modules-menu.php';
foreach (sqs_modules_menu() as [$type, $name, $desc, $group]) {
    foreach (['metadata.description' => $desc, 'metadata.menugroup' => $group] as $target => $v) {
        if (is_array($v) && isset($v['uk'], $v[$lang]) && $v['uk'] !== $v[$lang]) {
            $dict[$target][$v['uk']] = $v[$lang];
        }
    }
}
if (in_array('--back', $argv, true)) {
    foreach ($dict as $target => $pairs) {
        $dict[$target] = str_starts_with($target, 'options.') ? array_map('array_flip', $pairs) : array_flip($pairs);
    }
}

$_config = [];
require __DIR__ . '/../config/config.php';
$db = $_config['db'];
[$host, $port] = array_pad(explode(':', $db['host']), 2, 3306);
$conn = new mysqli($host, $db['user'], $db['pass'], $db['name'], (int)$port);
$conn->set_charset('utf8');

$total = 0;
foreach ($dict as $target => $pairs) {
    [$table, $column] = explode('.', $target, 2);
    if ($table === 'options') {
        $total += localizeOption($conn, $column, $pairs);
        continue;
    }
    $stmt = $conn->prepare("UPDATE `{$table}` SET `{$column}` = ? WHERE `{$column}` = ?");
    foreach ($pairs as $from => $to) {
        $stmt->bind_param('ss', $to, $from);
        $stmt->execute();
        $total += $stmt->affected_rows;
    }
}
echo "Изменено значений: {$total}\n";
if ($total > 0) {
    echo "Настройки кэшируются в сессии: новые названия видны после повторного входа в программу\n";
}

// options: значение — base64 от serialize(); меняются отдельные поля
function localizeOption(mysqli $conn, string $optname, array $fields): int {
    $stmt = $conn->prepare('SELECT optvalue FROM `options` WHERE optname = ?');
    $stmt->bind_param('s', $optname);
    $stmt->execute();
    $raw = $stmt->get_result()->fetch_row()[0] ?? null;
    $data = $raw === null ? false : @unserialize(base64_decode($raw), ['allowed_classes' => false]);   // объекты (App\DataItem) сохраняются как есть
    if (!is_array($data)) {
        return 0;
    }
    $n = 0;
    foreach ($fields as $field => $pairs) {
        if (isset($data[$field], $pairs[$data[$field]])) {
            $data[$field] = $pairs[$data[$field]];
            $n++;
        }
    }
    if ($n > 0) {
        $value = base64_encode(serialize($data));
        $upd = $conn->prepare('UPDATE `options` SET optvalue = ? WHERE optname = ?');
        $upd->bind_param('ss', $value, $optname);
        $upd->execute();
    }
    return $n;
}
