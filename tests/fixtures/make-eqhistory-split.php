<?php
/**
 * Вымышленные таблицы для теста номеров договоров с частью через дефис и с годом четырьмя цифрами
 * (tests/specs/eqhistory-split.spec.js): задублированный договор хоспис разбивает на «12-1/25» и «12-2/25».
 *  - osv-01.xls: ведомость 1С по счету 01 — модель с восемью единицами;
 *  - census.xlsx: лист «концентр. кисл.» переписи;
 *  - issues.xlsx: лист «обычные» таблицы выдачи.
 * Запуск внутри web-контейнера: php make-eqhistory-split.php <папка> <base 7 цифр> <метка>
 */
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

[$_, $dir, $base, $tag] = $argv;
$base = intval($base);
$inv = fn(int $k) => '0' . ($base + $k);
$no = intval(substr((string)$base, -5));
$who = fn(int $k) => "Подопечный {$tag}-" . sprintf('%03d', $k);
$d = fn(string $s) => \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTime($s));

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
$rows = [['Учреждение (тест)'], ['Оборотно-сальдовая ведомость по счету 01.1 за 01.01.24'], ['Счет', 'Сальдо на начало периода'],
         ['Основные средства', 'Дебет', 'Кредит'], ['01.1 (по счету: 01)', 8000]];
foreach (range(1, 8) as $k) {
    $rows[] = ["Концентратор ПВ тестовый {$tag}, инв.№: " . $inv($k), 1000];
}
$rows[] = ['Итого развернутое', 8000];
fill($book->getActiveSheet(), $rows);
(new \PhpOffice\PhpSpreadsheet\Writer\Xls($book))->save("$dir/osv-01.xls");

$book = new Spreadsheet();
$s = $book->getActiveSheet();
$s->setTitle('концентр. кисл.');
$row = fn($c, $date, $w, int $k, $ret = null) => [$c, $date, is_int($w) ? $who($w) : $w, 'г. Тестов', 'ул. Тестовая', null, 'Концентратор ПВ', null, $inv($k), $ret, 'до востр.', null];
fill($s, [
    ['№ договора', 'дата', 'ФИО', 'адрес', null, 'телефон', 'наименование', 'зав. №.', null, 'дата   возврата', 'срок пользования', null],
    [null, null, null, null, null, null, 'оборудования', null, 'инв. №', null, null, null],
    // задублированный договор разбит на части: два договора с разными получателями
    $row("{$no}-1/25", $d('2025-02-01'), 1, 1, $d('2025-02-20')),
    $row("{$no}-2/25", $d('2025-02-02'), 2, 2),
    // пробелы вокруг дефиса и косой черты
    $row(' ' . ($no + 1) . ' - 1 / 25 ', $d('2025-02-03'), 3, 3),
    // год четырьмя цифрами, с ведущим нулем у номера
    $row(($no + 2) . '/2025', $d('2025-02-04'), 4, 4),
    $row('0' . ($no + 3) . '/2025', $d('2025-02-05'), 5, 5),
    // часть без года в переписи и не номер вовсе — по-прежнему ошибки
    $row(($no + 4) . '-1', $d('2025-02-06'), 6, 6),
    $row('акт', $d('2025-02-07'), 6, 6),
    // получатель с опечаткой, исправляется строкой «N-K/ГГ = ФИО»
    $row(($no + 5) . '-1/25', $d('2025-02-08'), "Опечатка {$tag}", 7),
], [1, 9]);
$s->mergeCells('D1:E2');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/census.xlsx");

$book = new Spreadsheet();
$s = $book->getActiveSheet();
$s->setTitle('обычные');
fill($s, [
    ['№', 'дата регистрации договора', 'ФИО', 'Адрес проживания, телефон', 'Кем направлен', 'Наименование оборудования', 'Инв (заводской) №', 'Дата выдачи', 'дата возврата', 'примечание'],
    // та же выдача, что первая строка переписи: часть без года — год из даты регистрации
    ["{$no}-1", $d('2025-02-01'), $who(1), 'ул. Тестовая', 'Врач тестовый', 'концентратор', $inv(1), $d('2025-02-01'), $d('2025-02-20'), null],
    // только в журнале
    [($no + 6) . '-2', $d('2025-03-01'), $who(8), 'ул. Тестовая', 'Врач тестовый', 'концентратор', $inv(8), $d('2025-03-01'), null, null],
], [1, 7, 8]);
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/issues.xlsx");
echo "ok\n";
