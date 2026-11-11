<?php
/**
 * Вымышленные таблицы для теста переноса в договор без номера (tests/specs/eqhistory-move-bn.spec.js): номер договора
 * в строке ошибочный (у другого получателя), верный не восстановить — «договор ФИО = б/н» в списке исправлений.
 *  - osv-01.xls: ведомость 1С по счету 01 — модель с пятью единицами;
 *  - census.xlsx: лист «концентр. кисл.».
 * Даты — от дня, зависящего от base: у прошлых прогонов на том же стенде договоры «б/н» других дат.
 * Запуск внутри web-контейнера: php make-eqhistory-move-bn.php <папка> <base 7 цифр> <метка>
 */
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

[$_, $dir, $base, $tag] = $argv;
$base = intval($base);
$inv = fn(int $k) => '0' . ($base + $k);
$cn = fn(int $k) => (intval(substr((string)$base, -5)) + $k) . '/25';
$who = fn(int $k) => "Подопечный {$tag}-" . sprintf('%03d', $k);
$day0 = (new DateTime('2015-01-01'))->modify('+' . ($base % 2500) . ' days');
$d = fn(int $n) => \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel((clone $day0)->modify("+$n days"));

function fill($sheet, array $rows, array $dates = []) {
    foreach ($rows as $r => $row) {
        foreach ($row as $c => $v) {
            if ($v === null) {
                continue;
            }
            $sheet->setCellValueExplicit([$c + 1, $r + 1], $v, is_string($v) ? 's' : 'n');
            if (in_array($c, $dates, true) && is_float($v)) {
                $sheet->getStyle([$c + 1, $r + 1])->getNumberFormat()->setFormatCode('dd.mm.yyyy');
            }
        }
    }
}

$book = new Spreadsheet();
$rows = [['Учреждение (тест)'], ['Оборотно-сальдовая ведомость по счету 01.1 за 01.01.14'], ['Счет', 'Сальдо на начало периода'],
         ['Основные средства', 'Дебет', 'Кредит'], ['01.1 (по счету: 01)', 5000]];
foreach (range(1, 5) as $k) {
    $rows[] = ["Концентратор ПВ тестовый {$tag}, инв.№: " . $inv($k), 1000];
}
$rows[] = ['Итого развернутое', 5000];
fill($book->getActiveSheet(), $rows);
(new \PhpOffice\PhpSpreadsheet\Writer\Xls($book))->save("$dir/osv-01.xls");

$book = new Spreadsheet();
$s = $book->getActiveSheet();
$s->setTitle('концентр. кисл.');
$row = fn($c, $date, int $w, int $k, $ret = null) => [$c, $date, $who($w), 'г. Тестов', "ул. Тестовая, $w", null, 'Концентратор ПВ', null, $inv($k), $ret, 'до востр.', null];
fill($s, [
    ['№ договора', 'дата', 'ФИО', 'адрес', null, 'телефон', 'наименование', 'зав. №.', null, 'дата   возврата', 'срок пользования', null],
    [null, null, null, null, null, null, 'оборудования', null, 'инв. №', null, null, null],
    // настоящий договор
    $row($cn(1), $d(0), 1, 1),
    // тот же номер у другого получателя — ошибочный; в исправлениях «= б/н»
    $row($cn(1), $d(5), 2, 2),
    // другой получатель той же даты, в исправлениях «= бн» — свой договор «(2)»
    $row($cn(3), $d(5), 3, 3),
], [1, 9]);
$s->mergeCells('D1:E2');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/census.xlsx");
echo "ok\n";
