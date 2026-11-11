<?php
/**
 * Вымышленные таблицы для теста выдачи по акту без номера (tests/specs/eqhistory-act.spec.js): «акт» в ячейке номера
 * договора — договор «акт ДД.ММ.ГГГГ», свой на получателя и дату, как «б/н ДД.ММ.ГГГГ».
 *  - osv-01.xls: ведомость 1С по счету 01 — модель с пятью единицами;
 *  - census.xlsx: лист «концентр. кисл.».
 * Даты — от дня, зависящего от base: у прошлых прогонов на том же стенде договоры «акт» других дат.
 * Запуск внутри web-контейнера: php make-eqhistory-act.php <папка> <base 7 цифр> <метка>
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
$s->setTitle('ИВЛ');
$row = fn($c, $date, int $w, int $k, $ret = null) => [$c, $date, $who($w), 'г. Тестов', "ул. Тестовая, $w", null, 'Аппарат ИВЛ', null, $inv($k), $ret, 'до востр.', null];
fill($s, [
    ['№ договора', 'дата', 'ФИО', 'адрес', null, 'телефон', 'наименование', 'зав. №.', null, 'дата   возврата', 'срок пользования', null],
    [null, null, null, null, null, null, 'оборудования', null, 'инв. №', null, null, null],
    // выдача по акту, возвращена
    $row('акт', $d(0), 1, 1, $d(5)),
    // другой получатель той же даты — свой договор «(2)»; возврат раньше выдачи, в исправлениях — «возврат без даты»
    $row(' Акт ', $d(0), 2, 2, $d(-3)),
    // по акту и без номера у одного получателя в один день — разные договоры
    $row('АКТ', $d(10), 3, 3),
    $row('б/н', $d(10), 3, 4),
    // «акт» с номером — по-прежнему не распознан
    $row('акт 7', $d(20), 4, 5),
], [1, 9]);
$s->mergeCells('D1:E2');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/census.xlsx");
echo "ok\n";
