<?php
/**
 * Вымышленные таблицы для теста возврата, дата которого не восстанавливается (tests/specs/eqhistory-noret.spec.js):
 * дата возврата раньше выдачи, а по списку исправлений «… = возврат без даты» возврат проводится датой выдачи.
 *  - osv-01.xls: ведомость 1С по счету 01 — модель с пятью единицами;
 *  - census.xlsx: лист «концентр. кисл.».
 * Даты — от дня, зависящего от base: у прошлых прогонов на том же стенде договоры «б/н» других дат.
 * Запуск внутри web-контейнера: php make-eqhistory-noret.php <папка> <base 7 цифр> <метка>
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
    // возврат раньше выдачи; в исправлениях — «возврат без даты»
    $row($cn(1), $d(0), 1, 1, $d(-5)),
    // выдача сдвигается на возврат прошлой (d30), и ее возврат (d25) оказывается раньше; в исправлениях — по-украински
    $row($cn(2), $d(10), 2, 2, $d(30)),
    $row($cn(3), $d(20), 3, 2, $d(25)),
    // договор без номера, в исправлениях — номером «б/н ДД.ММ.ГГГГ», как в отчете
    $row('б/н', $d(40), 4, 3, $d(35)),
    // без строки исправлений — по-прежнему ошибка
    $row($cn(5), $d(50), 5, 4, $d(45)),
    // дата возврата верная, строка исправлений не нужна
    $row($cn(6), $d(60), 6, 5, $d(70)),
], [1, 9]);
$s->mergeCells('D1:E2');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/census.xlsx");
echo "ok\n";
