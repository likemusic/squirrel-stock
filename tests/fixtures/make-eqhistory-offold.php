<?php
/**
 * Вымышленные таблицы для теста единиц, списанных до ведомости 1С (tests/specs/eqhistory-offold.spec.js): инвентарного
 * номера нет ни в базе, ни в пределах номеров ведомости.
 *  - osv-01.xls: ведомость 1С по счету 01 — единицы 1 и 3 (номер 2 — в пределах ведомости, но в ней нет);
 *  - census.xlsx: лист «концентр. кисл.» переписи.
 * Запуск внутри web-контейнера: php make-eqhistory-offold.php <папка> <base 7 цифр> <метка>
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
         ['Основные средства', 'Дебет', 'Кредит'], ['01.1 (по счету: 01)', 2000]];
foreach ([1, 3] as $k) {
    $rows[] = ["Концентратор ПВ тестовый {$tag}, инв.№: " . $inv($k), 1000];
}
$rows[] = ['Итого развернутое', 2000];
fill($book->getActiveSheet(), $rows);
(new \PhpOffice\PhpSpreadsheet\Writer\Xls($book))->save("$dir/osv-01.xls");

$book = new Spreadsheet();
$s = $book->getActiveSheet();
$s->setTitle('концентр. кисл.');
$row = fn(int $c, $date, int $w, string $n, $ret = null) => ["{$c}/25", $date, $who($w), 'г. Тестов', 'ул. Тестовая', null, 'Концентратор ПВ', null, $n, $ret, 'до востр.', null];
fill($s, [
    ['№ договора', 'дата', 'ФИО', 'адрес', null, 'телефон', 'наименование', 'зав. №.', null, 'дата   возврата', 'срок пользования', null],
    [null, null, null, null, null, null, 'оборудования', null, 'инв. №', null, null, null],
    // номер того же формата, что в ведомости, но больше наибольшего; возвращена — переносится и списывается
    $row($no, $d('2025-02-01'), 1, $inv(50), $d('2025-02-20')),
    // номер не в формате ведомости; первая выдача без возврата закрыта следующей, последняя возвращена
    $row($no + 1, $d('2025-01-10'), 2, "7{$no}"),
    $row($no + 2, $d('2025-03-01'), 3, "7{$no}", $d('2025-04-01')),
    // на руках, но «списан» — переносится, списание датой импорта
    $row($no + 3, $d('2025-02-03'), 4, "8{$no}", 'списан'),
    // на руках без «списан» — ошибка
    $row($no + 4, $d('2025-02-04'), 5, "6{$no}"),
    // дата в колонке инвентарного номера — по-прежнему ошибка «нет в базе»
    $row($no + 5, $d('2025-02-05'), 6, '12.03.2024'),
    // в пределах номеров ведомости, на руках — как раньше (5.21): возврат и списание датой импорта
    $row($no + 6, $d('2025-02-06'), 7, $inv(2)),
], [1, 9]);
$s->mergeCells('D1:E2');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/census.xlsx");
echo "ok\n";
