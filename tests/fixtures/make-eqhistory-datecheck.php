<?php
/**
 * Вымышленные таблицы для теста расходящихся дат одной выдачи (tests/specs/eqhistory-datecheck.spec.js): одна выдача
 * (единица и договор) записана в двух строках с разными датами, а первая к дате второй не закрыта: до 5 дней — одна выдача,
 * ровно через 1 или 3 месяца — продление, иначе — ошибка.
 *  - osv-01.xls: ведомость 1С по счету 01 — модель с шестью единицами;
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
         ['Основные средства', 'Дебет', 'Кредит'], ['01.1 (по счету: 01)', 6000]];
foreach (range(1, 6) as $k) {
    $rows[] = ["Аппарат ИВЛ тестовый {$tag}, инв.№: " . $inv($k), 1000];
}
$rows[] = ['Итого развернутое', 6000];
fill($book->getActiveSheet(), $rows);
(new \PhpOffice\PhpSpreadsheet\Writer\Xls($book))->save("$dir/osv-01.xls");

$book = new Spreadsheet();
$s = $book->getActiveSheet();
$s->setTitle('ИВЛ');
$row = fn(int $c, $date, int $w, int $k, $ret = null, $term = 'до востр.') => [$cn($c), $date, $who($w), 'г. Тестов', 'ул. Тестовая', null, 'Аппарат ИВЛ', null, $inv($k), $ret, $term, null];
fill($s, [
    ['№ договора', 'дата', 'ФИО', 'адрес', null, 'телефон', 'наименование', 'зав. №.', null, 'дата   возврата', 'срок пользования', null],
    [null, null, null, null, null, null, 'оборудования', null, 'инв. №', null, null, null],
    // та же выдача, что в таблице выдачи (там с возвратом)
    $row(1, $d('2025-02-11'), 1, 1),
    // та же выдача, что в таблице выдачи, дата другая (1 день) — одна выдача датой таблицы выдачи
    $row(4, $d('2025-06-10'), 2, 1),
    // выдача по тому же договору после возврата — две выдачи, не ошибка
    $row(6, $d('2025-04-01'), 4, 2, $d('2025-04-05')),
    $row(6, $d('2025-04-10'), 4, 2),
    // одна выдача дважды в переписи, 2 дня — одна выдача более ранней датой
    $row(7, $d('2025-05-01'), 5, 3),
    $row(7, $d('2025-05-03'), 5, 3),
    // через месяц тому же получателю — продление: срок по второй строке (в ней не указан — еще месяц)
    $row(8, $d('2025-07-01'), 6, 4, null, $d('2025-08-01')),
    $row(8, $d('2025-08-01'), 6, 4, null, null),
    // 19 дней — ошибка обеих строк
    $row(9, $d('2025-09-01'), 7, 5),
    $row(9, $d('2025-09-20'), 7, 5),
    // через 3 месяца и день — продление, срок по второй строке
    $row(10, $d('2025-10-01'), 8, 6, null, null),
    $row(10, $d('2026-01-02'), 8, 6, null, $d('2026-03-15')),
], [1, 9, 10]);
$s->mergeCells('D1:E2');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/census.xlsx");

$book = new Spreadsheet();
$s = $book->getActiveSheet();
$s->setTitle('обычные');
$jrow = fn(int $c, string $date, int $w, int $k, $ret = null) => [(string)($no + $c), $d($date), $who($w), 'ул. Тестовая', 'Врач тестовый', 'ИВЛ', $inv($k), $d($date), $ret, null];
fill($s, [
    ['№', 'дата регистрации договора', 'ФИО', 'Адрес проживания, телефон', 'Кем направлен', 'Наименование оборудования', 'Инв (заводской) №', 'Дата выдачи', 'дата возврата', 'примечание'],
    $jrow(1, '2025-02-11', 1, 1, $d('2025-06-05')),
    $jrow(4, '2025-06-11', 2, 1),
], [1, 7, 8]);
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/issues.xlsx");
echo "ok\n";
