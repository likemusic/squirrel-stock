<?php
/**
 * SquirrelStock: профиль для благотворительной организации, которая бесплатно выдает расходники и оборудование
 * (первый пользователь — детский хоспис). Меню и настройки под эту работу:
 *  - в меню остаются пункты включенных модулей (app/sqs/modules) и приход, выдача, возврат, списание, перемещение, инвентаризация, складские отчеты и отчеты форка,
 *    журналы, справочники товаров, складов, контрагентов и договоров, импорт, экспорт, резервная копия;
 *    остальное (касса, продажи, производство, кафе, зарплата и т.д.) выключается — включить обратно можно
 *    в «Налаштування меню» или этим скриптом с --back;
 *  - настройки: учет по серийным номерам (оборудование по инвентарным номерам), без минусовых остатков,
 *    закупки без учета денег, цена выдачи по умолчанию 0, копейки;
 *  - флаг профиля (App\SQS\Profile): на главной нет денежных плиток и графиков, журнал выдач открывается со статусом «Все»,
 *    расходная накладная печатается формой «Выдал / Получатель»; журнал «Продажи» называется «Выдачи».
 * Повторный запуск безопасен. Настройки кэшируются в сессии: изменения видны после повторного входа.
 *
 * php cli/charity-profile.php          (в docker-стенде: docker exec squirrelstock-web-1 php cli/charity-profile.php)
 * php cli/charity-profile.php --back   включить в меню все выключенные профилем пункты, снять флаг профиля, вернуть название журнала
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// тип пункта метаданных (1 — документ, 2 — отчет, 3 — журнал, 4 — справочник, 5 — сервис) => оставить в меню
const KEEP = [
    1 => ['GoodsReceipt', 'IncomeItem', 'GoodsIssue', 'ReturnIssue', 'OutcomeItem', 'MoveItem', 'Inventory', 'RetCustIssue'],
    2 => ['StoreItems', 'ItemActivity', 'Income', 'NoLiq'],
    3 => ['DocList', 'GIList', 'GRList', 'ItemList'],
    4 => ['CategoryList', 'ItemList', 'StoreList', 'CustomerList', 'ContractList'],
    5 => ['Import', 'Export'],
];
const OPTIONS = ['usesnumber' => '3', 'allowminus' => 0, 'allowminusmf' => 1, 'defprice' => '0', 'amdigits' => '1', 'sqs_charity' => 1];
// название и группа журнала расходных накладных (metadata GIList): язык по умолчанию => профиль; меняется только непереименованное
const RENAME = ['Продажі' => 'Видачі', 'Продажи' => 'Выдачи'];

$_config = [];
require __DIR__ . '/../config/config.php';
$db = $_config['db'];
[$host, $port] = array_pad(explode(':', $db['host']), 2, 3306);
$conn = new mysqli($host, $db['user'], $db['pass'], $db['name'], (int)$port);
$conn->set_charset('utf8');

// пункты меню включенных модулей профиль не выключает: включенный модуль — нужный (docs/MODULES.md)
require __DIR__ . '/modules-menu.php';
$keepList = KEEP;
foreach (sqs_modules_menu() as [$type, $name]) {
    $keepList[intval($type)][] = (string)$name;
}

$keep = [];
foreach ($keepList as $type => $names) {
    foreach ($names as $name) {
        $keep[] = '(' . $type . ", '" . $conn->real_escape_string($name) . "')";
    }
}
$inKeep = '(meta_type, meta_name) IN (' . implode(', ', $keep) . ')';

if (in_array('--back', $argv, true)) {
    // включаются пункты, выключенные этим профилем (их список хранится в keyval)
    $saved = $conn->query("SELECT vald FROM keyval WHERE keyd = 'sqs_profile_disabled'")->fetch_row()[0] ?? '';
    $ids = array_filter(array_map('intval', explode(',', $saved)));
    if ($ids) {
        $conn->query('UPDATE metadata SET disabled = 0 WHERE meta_id IN (' . implode(',', $ids) . ')');
    }
    $conn->query("DELETE FROM keyval WHERE keyd = 'sqs_profile_disabled'");
    renameJournal($conn, array_flip(RENAME));
    setCommon($conn, fn(array $c) => array_diff_key($c, ['sqs_charity' => 1]));
    echo 'Включено пунктов меню: ' . count($ids) . "\n";
    exit;
}

$ids = array_map(fn($r) => (int)$r[0], $conn->query("SELECT meta_id FROM metadata WHERE disabled = 0 AND NOT {$inKeep}")->fetch_all());
if ($ids) {
    $conn->query('UPDATE metadata SET disabled = 1 WHERE meta_id IN (' . implode(',', $ids) . ')');
    $saved = $conn->query("SELECT vald FROM keyval WHERE keyd = 'sqs_profile_disabled'")->fetch_row()[0] ?? '';
    $all = implode(',', array_unique(array_merge(array_filter(explode(',', $saved)), $ids)));
    $stmt = $conn->prepare("REPLACE INTO keyval (keyd, vald) VALUES ('sqs_profile_disabled', ?)");
    $stmt->bind_param('s', $all);
    $stmt->execute();
}
$conn->query("UPDATE metadata SET disabled = 0 WHERE {$inKeep}");

renameJournal($conn, RENAME);
setCommon($conn, fn(array $c) => array_merge($c, OPTIONS));

echo 'Выключено пунктов меню: ' . count($ids) . ", настройки обновлены. Изменения видны после повторного входа в программу\n";

function renameJournal(mysqli $conn, array $pairs): void {
    $stmt = $conn->prepare("UPDATE metadata SET description = IF(description = ?, ?, description), menugroup = IF(menugroup = ?, ?, menugroup) WHERE meta_type = 3 AND meta_name = 'GIList'");
    foreach ($pairs as $from => $to) {
        $stmt->bind_param('ssss', $from, $to, $from, $to);
        $stmt->execute();
    }
}

function setCommon(mysqli $conn, callable $change): void {
    $raw = $conn->query("SELECT optvalue FROM options WHERE optname = 'common'")->fetch_row()[0] ?? '';
    $common = @unserialize(base64_decode($raw));
    $value = base64_encode(serialize($change(is_array($common) ? $common : [])));
    $stmt = $conn->prepare("UPDATE options SET optvalue = ? WHERE optname = 'common'");
    $stmt->bind_param('s', $value);
    $stmt->execute();
}
