<?php
/**
 * SquirrelStock: профиль для благотворительной организации, которая бесплатно выдает расходники и оборудование
 * (первый пользователь — детский хоспис). Меню и настройки под эту работу:
 *  - в меню остаются пункты включенных модулей (app/sqs/modules) и приход, выдача, возврат, списание, перемещение, инвентаризация, складские отчеты и отчеты форка,
 *    журналы, справочники товаров, складов, контрагентов и договоров, импорт, экспорт, резервная копия;
 *    остальное (касса, продажи, производство, кафе, зарплата и т.д.) выключается — включить обратно можно
 *    в «Система → Настройки → Настройка меню» или этим скриптом с --back;
 *  - настройки: учет по серийным номерам (оборудование по инвентарным номерам), без минусовых остатков,
 *    закупки без учета денег, цена выдачи по умолчанию 0, копейки, точная цена партии (сумма остатков совпадает с приходом и ведомостью 1С);
 *  - флаг профиля (App\SQS\Profile): на главной нет денежных плиток и графиков, журнал выдач открывается со статусом «Все»,
 *    расходная накладная и возврат печатаются формами «Выдал / Получатель» и «Вернул / Принял», в журнале выдач нет кнопок доставки,
 *    ТТН и гарантийного талона, в формах выдачи и возврата — «Получатель», без кассы, цен и оплаты (цена выдачи 0);
 *  - названия (RENAME): журнал «Продажи» — «Выдачи», группы документов «Продажи» — «Выдача», «Закупки» — «Поступление»,
 *    «Расходная накладная» — «Выдача (накладная)», «Возврат от покупателя» — «Возврат от получателя», группа отчетов и отчет «Закупки» — «Поступление»;
 *  - оперативное меню (кнопки вверху страниц, настройка роли): у ролей со стандартным «Номенклатура / Общий журнал» —
 *    «Выдачи», «Выдача расходников», «Выдача оборудования» (SMART); настроенное вручную остается; --back — обратно;
 *  - служебный контрагент Zippy «Фiз. особа» / «Физ. лицо» (розничный покупатель из db.sql) выключается, если по нему нет документов:
 *    в справочнике и подсказках его нет; --back — включается.
 * Повторный запуск безопасен. Настройки кэшируются в сессии: изменения видны после повторного входа.
 *
 * php cli/charity-profile.php          (в docker-стенде: docker exec -u www-data squirrelstock-web-1 php cli/charity-profile.php)
 * php cli/charity-profile.php --back   включить в меню все выключенные профилем пункты, снять флаг профиля, вернуть названия
 *                                      и цену партии до копейки (уже записанные точные цены остаются)
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
const OPTIONS = ['usesnumber' => '3', 'allowminus' => 0, 'allowminusmf' => 1, 'defprice' => '0', 'amdigits' => '1', 'sqs_partion_exact' => 1, 'sqs_charity' => 1];
// названия в metadata: [тип, meta_name ('' — все пункты типа), поле, [язык по умолчанию => профиль]] для uk и ru (cli/localize-db.php
// переводит и профильные названия). Меняется только название по умолчанию: переименованное вручную остается; --back — обратно
const RENAME = [
    [3, 'GIList', 'description', ['Продажі' => 'Видачі', 'Продажи' => 'Выдачи']],
    [3, 'GIList', 'menugroup', ['Продажі' => 'Видачі', 'Продажи' => 'Выдачи']],
    [1, '', 'menugroup', ['Продажі' => 'Видача', 'Продажи' => 'Выдача', 'Закупівлі' => 'Надходження', 'Закупки' => 'Поступление']],
    [1, 'GoodsIssue', 'description', ['Видаткова накладна' => 'Видача (накладна)', 'Расходная накладная' => 'Выдача (накладная)']],
    [1, 'ReturnIssue', 'description', ['Повернення від покупця' => 'Повернення від отримувача', 'Возврат от покупателя' => 'Возврат от получателя']],
    [2, '', 'menugroup', ['Закупівлі' => 'Надходження', 'Закупки' => 'Поступление']],
    [2, 'Income', 'description', ['Закупівлі' => 'Надходження', 'Закупки' => 'Поступление']],
];
// оперативное меню: [тип, meta_name] стандартное (у роли admins в db.sql) => в профиле (пункты, которых нет в меню, пропускаются)
const SMART_DEFAULT = [[4, 'ItemList'], [3, 'DocList']];
const SMART = [[3, 'GIList'], [5, 'ConsumablesIssue'], [5, 'EquipmentIssue']];
// служебный контрагент из db.sql (имя на украинском и после cli/localize-db.php ru)
const RETAIL_CUSTOMER = ['Фiз. особа', 'Физ. лицо'];

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
    rename_items($conn, true);
    smartmenu($conn, true);
    retailCustomer($conn, true);
    setCommon($conn, fn(array $c) => array_diff_key($c, ['sqs_charity' => 1, 'sqs_partion_exact' => 1]));   // цена партии — снова до копейки
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

rename_items($conn, false);
smartmenu($conn, false);
retailCustomer($conn, false);
setCommon($conn, fn(array $c) => array_merge($c, OPTIONS));

echo 'Выключено пунктов меню: ' . count($ids) . ", настройки обновлены. Изменения видны после повторного входа в программу\n";

function rename_items(mysqli $conn, bool $back): void {
    foreach (RENAME as [$type, $name, $field, $pairs]) {
        $stmt = $conn->prepare("UPDATE metadata SET {$field} = ? WHERE meta_type = {$type} AND {$field} = ?" . ($name === '' ? '' : ' AND meta_name = ?'));
        foreach ($back ? array_flip($pairs) : $pairs as $from => $to) {
            $name === '' ? $stmt->bind_param('ss', $to, $from) : $stmt->bind_param('sss', $to, $from, $name);
            $stmt->execute();
        }
    }
}

// оперативное меню ролей: стандартное => профиля (--back — обратно). Меняется только роль, у которой меню ровно стандартное (или ровно профиля)
function smartmenu(mysqli $conn, bool $back): void {
    $ids = function (array $items) use ($conn): array {
        $ids = [];
        foreach ($items as [$type, $name]) {
            $id = $conn->query("SELECT meta_id FROM metadata WHERE disabled = 0 AND meta_type = {$type} AND meta_name = '" . $conn->real_escape_string($name) . "'")->fetch_row()[0] ?? null;
            if ($id !== null) {
                $ids[] = (int)$id;
            }
        }
        return $ids;
    };
    [$from, $to] = $back ? [$ids(SMART), $ids(SMART_DEFAULT)] : [$ids(SMART_DEFAULT), $ids(SMART)];
    if (!$from || !$to) {
        return;
    }
    $key = fn(array $a) => implode(',', array_unique(array_map('intval', array_filter($a, 'strlen'))));
    sort($from);
    $stmt = $conn->prepare('UPDATE roles SET acl = ? WHERE role_id = ?');
    foreach ($conn->query('SELECT role_id, acl FROM roles')->fetch_all() as [$id, $raw]) {
        $acl = @unserialize((string)$raw, ['allowed_classes' => false]);
        if (!is_array($acl)) {
            continue;
        }
        $cur = explode(',', (string)($acl['smartmenu'] ?? ''));
        sort($cur);
        if ($key($cur) !== $key($from)) {
            continue;
        }
        $acl['smartmenu'] = implode(',', $to);
        $value = serialize($acl);
        $stmt->bind_param('si', $value, $id);
        $stmt->execute();
    }
}

// служебный контрагент: выключить (если по нему нет документов) и запомнить в keyval; --back — включить запомненных
function retailCustomer(mysqli $conn, bool $back): void {
    $saved = array_filter(array_map('intval', explode(',', $conn->query("SELECT vald FROM keyval WHERE keyd = 'sqs_profile_customers_off'")->fetch_row()[0] ?? '')));
    if ($back) {
        if ($saved) {
            $conn->query('UPDATE customers SET status = 0 WHERE status = 1 AND customer_id IN (' . implode(',', $saved) . ')');
        }
        $conn->query("DELETE FROM keyval WHERE keyd = 'sqs_profile_customers_off'");
        return;
    }
    $names = implode(', ', array_map(fn($n) => "'" . $conn->real_escape_string($n) . "'", RETAIL_CUSTOMER));
    $ids = array_map(fn($r) => (int)$r[0], $conn->query("SELECT customer_id FROM customers c WHERE status = 0 AND customer_name IN ({$names})
        AND NOT EXISTS (SELECT 1 FROM documents d WHERE d.customer_id = c.customer_id)")->fetch_all());
    if (!$ids) {
        return;
    }
    $conn->query('UPDATE customers SET status = 1 WHERE customer_id IN (' . implode(',', $ids) . ')');
    $all = implode(',', array_unique(array_merge($saved, $ids)));
    $stmt = $conn->prepare("REPLACE INTO keyval (keyd, vald) VALUES ('sqs_profile_customers_off', ?)");
    $stmt->bind_param('s', $all);
    $stmt->execute();
}

function setCommon(mysqli $conn, callable $change): void {
    $raw = $conn->query("SELECT optvalue FROM options WHERE optname = 'common'")->fetch_row()[0] ?? '';
    $common = @unserialize(base64_decode($raw), ['allowed_classes' => false]);   // объекты (App\DataItem) сохраняются как есть
    $value = base64_encode(serialize($change(is_array($common) ? $common : [])));
    $stmt = $conn->prepare("UPDATE options SET optvalue = ? WHERE optname = 'common'");
    $stmt->bind_param('s', $value);
    $stmt->execute();
}
