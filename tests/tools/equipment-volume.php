<?php
/**
 * Вымышленные таблицы объема хосписа для замера отчетов оборудования (tools/equipment-volume.js): ~700 единиц, у каждой
 * от нуля до нескольких выдач с возвратами, часть на руках (со сроком — прошедшим, ближайшим, дальним — или «до востр.»).
 *  - osv-01.xls: ведомость 1С по счету 01 (основные средства) — единицы с инвентарными номерами;
 *  - census.xlsx: таблица переписи, по листу на вид.
 * Данные выдуманы, генератор детерминированный. Запуск в web-контейнере: php equipment-volume.php <папка> [единиц]
 */
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$dir = $argv[1] ?? '.';
$total = intval($argv[2] ?? 700);

$seed = 20261016;
$rnd = function (int $n) use (&$seed): int {
    $seed = ($seed * 1103515245 + 12345) % 2147483648;
    return intdiv($seed, 65536) % $n;
};
$xl = fn(int $ts) => \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTime(date('Y-m-d', $ts)));

// вид (лист переписи) => модели
$kinds = [
    'концентр. кисл.' => ['Концентратор кислородный тестовый', 6],
    'кровати'        => ['Кровать функциональная тестовая', 8],
    'коляски'        => ['Кресло-коляска тестовая', 8],
    'аспираторы'     => ['Аспиратор тестовый', 5],
    'прочее'         => ['Прибор тестовый', 10],
];
$units = [];   // [лист, модель, инв. №]
for ($k = 0; $k < $total; $k++) {
    $sheet = array_keys($kinds)[$rnd(count($kinds))];
    [$base, $models] = $kinds[$sheet];
    $units[] = [$sheet, $base . ' ' . sprintf('%02d', 1 + $rnd($models)), '0' . (3000001 + $k)];
}

$book = new Spreadsheet();
$rows = [['Учреждение (тест)'], ['Оборотно-сальдовая ведомость по счету 01.1 за 01.01.19'], ['Счет', 'Сальдо на начало периода'], ['Основные средства', 'Дебет', 'Кредит'],
         ['01.1 (по счету: 01)', 1000 * $total]];
foreach ($units as [$sheet, $model, $inv]) {
    $rows[] = ["{$model}, инв.№: {$inv}", 1000];
}
$rows[] = ['Итого развернутое', 1000 * $total];
$s = $book->getActiveSheet();
foreach ($rows as $r => $row) {
    foreach ($row as $c => $v) {
        $s->setCellValueExplicit([$c + 1, $r + 1], $v, is_string($v) ? 's' : 'n');
    }
}
(new \PhpOffice\PhpSpreadsheet\Writer\Xls($book))->save("$dir/osv-01.xls");

// выдачи: по единице цепочка «выдача — возврат» с 2019 года; последняя выдача — на руках или возвращена
$today = strtotime(date('Y-m-d'));
$start = strtotime('2019-01-10');
$sheets = [];
$contract = 0;
$issues = 0;
$onhand = 0;
foreach ($units as [$sheet, $model, $inv]) {
    $d = $start + $rnd(1500) * 86400;
    $n = $rnd(4);
    for ($i = 0; $i < $n && $d < $today - 86400; $i++) {
        $contract++;
        $who = 'Подопечный ' . sprintf('%04d', 1 + $rnd(420));
        $ret = $d + (20 + $rnd(600)) * 86400;
        $last = $i == $n - 1;
        $term = 'до востр.';
        if ($last && $rnd(10) < 6 || $ret >= $today) {
            $ret = null;   // на руках
            $onhand++;
            $t = $rnd(5);
            if ($t == 1) {
                $term = $xl($today - (1 + $rnd(60)) * 86400);   // срок прошел
            } elseif ($t == 2) {
                $term = $xl($today + $rnd(14) * 86400);         // истекает в ближайшие две недели
            } elseif ($t == 3) {
                $term = $xl($today + (30 + $rnd(300)) * 86400);
            }
        }
        $no = intval(substr($who, -4));
        $sheets[$sheet][] = [$contract . '/' . date('y', $d), $xl($d), $who, 'г. Тестов', 'ул. Тестовая, ' . $no, '+000 00 000-' . sprintf('%04d', $no), $model, null, $inv,
                             $ret ? $xl($ret) : null, $term, null];
        $issues++;
        if ($ret === null) {
            break;
        }
        $d = $ret + (1 + $rnd(90)) * 86400;
    }
}

$book = new Spreadsheet();
$book->removeSheetByIndex(0);
foreach ($sheets as $name => $data) {
    $s = $book->createSheet();
    $s->setTitle($name);
    $all = array_merge([['№ договора', 'дата', 'ФИО', 'адрес', null, 'телефон', 'наименование', 'зав. №.', null, 'дата   возврата', 'срок пользования', null],
                        [null, null, null, null, null, null, 'оборудования', null, 'инв. №', null, null, null]], $data);
    foreach ($all as $r => $row) {
        foreach ($row as $c => $v) {
            if ($v === null) {
                continue;
            }
            $s->setCellValueExplicit([$c + 1, $r + 1], $v, is_string($v) ? 's' : 'n');
            if (in_array($c, [1, 9, 10], true) && is_float($v)) {
                $s->getStyle([$c + 1, $r + 1])->getNumberFormat()->setFormatCode('dd.mm.yyyy');
            }
        }
    }
    $s->mergeCells('D1:E2');
}
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/census.xlsx");
echo "единиц: $total, выдач: $issues, на руках: $onhand\n";
