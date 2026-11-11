<?php
/**
 * Вымышленные таблицы для теста расходящихся дат одной выдачи (tests/specs/eqhistory-datecheck.spec.js): одна выдача
 * (единица и договор) записана в двух строках с разными датами, а первая к дате второй не закрыта.
 *  - osv-01.xls: ведомость 1С по счету 01 — модель с четырьмя единицами;
 *  - census.xlsx: лист «ИВЛ» переписи;
 *  - issues.xlsx: лист «обычные» таблицы выдачи.
 * Запуск внутри web-контейнера: php make-eqhistory-datecheck.php <папка> <base 7 цифр> <метка>
 */
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

[$_, $dir, $base, $tag] = $argv;
$base = intval($base);
$inv = fn(int $k) => '0' . ($base + $k);
$no = intval(substr((string)$base, -5));
$cn = fn(int $k) => ($no + $k) . '/25';
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
         ['Основные средства', 'Дебет', 'Кредит'], ['01.1 (по счету: 01)', 4000]];
foreach (range(1, 4) as $k) {
    $rows[] = ["Аппарат ИВЛ тестовый {$tag}, инв.№: " . $inv($k), 1000];
}
$rows[] = ['Итого развернутое', 4000];
fill($book->getActiveSheet(), $rows);
(new \PhpOffice\PhpSpreadsheet\Writer\Xls($book))->save("$dir/osv-01.xls");

$book = new Spreadsheet();
$s = $book->getActiveSheet();
$s->setTitle('ИВЛ');
$row = fn(int $c, $date, int $w, int $k, $ret = null) => [$cn($c), $date, $who($w), 'г. Тестов', 'ул. Тестовая', null, 'Аппарат ИВЛ', null, $inv($k), $ret, 'до востр.', null];
fill($s, [
    ['№ договора', 'дата', 'ФИО', 'адрес', null, 'телефон', 'наименование', 'зав. №.', null, 'дата   возврата', 'срок пользования', null],
    [null, null, null, null, null, null, 'оборудования', null, 'инв. №', null, null, null],
    // та же выдача, что в таблице выдачи (там с возвратом)
    $row(1, $d('2025-02-11'), 1, 1),
    // та же выдача, что в таблице выдачи, а дата другая — ошибка обеих строк
    $row(4, $d('2025-06-10'), 2, 1),
    // выдача по тому же договору после возврата — две выдачи, не ошибка
    $row(6, $d('2025-04-01'), 4, 2, $d('2025-04-05')),
    $row(6, $d('2025-04-10'), 4, 2),
    // одна выдача дважды в переписи с разными датами, первая не закрыта — ошибка
    $row(7, $d('2025-05-01'), 5, 3),
    $row(7, $d('2025-05-03'), 5, 3),
], [1, 9]);
$s->mergeCells('D1:E2');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/census.xlsx");

$book = new Spreadsheet();
$s = $book->getActiveSheet();
$s->setTitle('обычные');
$jrow = fn(int $c, string $date, int $w, int $k, $ret = null) => [(string)($no + $c), $d($date), $who($w), 'ул. Тестовая', 'Врач тестовый', 'ИВЛ', $inv($k), $d($date), $ret, null];
fill($s, [
    ['№', 'дата регистрации договора', 'ФИО', 'Адрес проживания, телефон', 'Кем направлен', 'Наименование оборудования', 'Инв (заводской) №', 'Дата выдачи', 'дата возврата', 'примечание'],
    $jrow(1, '2025-02-11', 1, 1, $d('2025-06-26')),
    $jrow(4, '2025-06-11', 2, 1),
], [1, 7, 8]);
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/issues.xlsx");
echo "ok\n";
