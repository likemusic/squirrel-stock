<?php
/**
 * Вымышленные таблицы для теста ответов хосписа на сверку таблиц (tests/specs/eqhistory-fixes.spec.js): список исправлений
 * (ФИО, номер договора, задвоенная строка, представитель, номер на двух листах), выдача раньше возврата, номера нет в 1С,
 * пальчиковые пульсоксиметры — расходник. Раскладка — как у таблицы переписи.
 *  - osv-01.xls: ведомость 1С по счету 01 — модель с единицами 1, 2, 3 и 9 (номеров 6 и 7 в ней нет — списаны в 1С);
 *  - census.xlsx: листы «концентр. кисл.», «пульсоксиметр», «эл.отс. В-40», «эл. отс. В-40+АКБ».
 * Запуск внутри web-контейнера: php make-eqhistory-fixes.php <папка> <base 7 цифр> <метка>
 */
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

[$_, $dir, $base, $tag] = $argv;
$base = intval($base);
$inv = fn(int $k) => '0' . ($base + $k);
$fac = fn(string $p, int $k) => $p . $base . $k;
$cn = fn(int $k) => (intval(substr((string)$base, -5)) + $k) . '/25';
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
foreach ([1, 2, 3, 9] as $k) {
    $rows[] = ["Концентратор ИС тестовый {$tag}, инв.№: " . $inv($k), 1000];
}
$rows[] = ['Итого развернутое', 4000];
fill($book->getActiveSheet(), $rows);
(new \PhpOffice\PhpSpreadsheet\Writer\Xls($book))->save("$dir/osv-01.xls");

$head = [['№ договора', 'дата', 'ФИО', 'адрес', null, 'телефон', 'наименование', 'зав. №.', null, 'дата   возврата', 'срок пользования', null],
         [null, null, null, null, null, null, 'оборудования', null, 'инв. №', null, null, null]];
$row = fn(string $c, string $date, string $name, int $k, string $item, ?string $factory, ?string $inv, ?string $ret = null) =>
    [$c, $d($date), $name, 'г. Тестов', "ул. Тестовая, {$k}", null, $item, $factory, $inv, $ret ? $d($ret) : null, 'до востр.', null];

$book = new Spreadsheet();
$s = $book->getActiveSheet();
$s->setTitle('концентр. кисл.');
fill($s, array_merge($head, [
    // 5.19: единица 1 возвращена 01.03, по строке 4 выдана снова 20.02 — верен возврат: выдача сдвигается на 01.03
    $row($cn(1), '2025-02-01', $who(1), 1, 'Концентратор ИС', null, $inv(1), '2025-03-01'),
    $row($cn(2), '2025-02-20', $who(2), 2, 'Концентратор ИС', null, $inv(1), '2025-04-01'),
    // 5.18: опечатка в ФИО — «12/25 = ФИО»
    $row($cn(3), '2025-02-05', $who(3), 3, 'Концентратор ИС', null, $inv(2)),
    $row($cn(3), '2025-02-05', "Подопечная {$tag}-003", 4, 'Концентратор ИС', null, $inv(3)),
    // опечатка в номере договора: вторая строка — договор 5 («договор ФИО = договор»)
    $row($cn(4), '2025-03-10', $who(4), 5, 'Концентратор ИС', $fac('F', 1), null),
    $row($cn(4), '2025-03-11', $who(9), 6, 'Концентратор ИС', $fac('F', 2), null),
    // задвоенная запись: не переносить («договор ФИО = -»)
    $row($cn(6), '2025-03-12', $who(10), 7, 'Концентратор ИС', $fac('F', 3), null),
    // 5.21: номера нет в 1С (в пределах номеров ведомости) — списан там: возвращен — списание датой возврата, на руках —
    // возврат и списание датой импорта; номер не в формате инвентарных номеров базы — по-прежнему ошибка
    $row($cn(7), '2025-03-01', $who(7), 8, 'Концентратор ИС', null, $inv(6), '2025-03-15'),
    $row($cn(8), '2025-04-01', $who(8), 9, 'Концентратор ИС', null, $inv(7)),
    $row($cn(14), '2025-04-02', $who(16), 15, 'Концентратор ИС', null, "ИС-{$base}"),
    // договор с представителем в пользу подопечного: в строке ФИО представителя
    $row($cn(9), '2025-04-05', "Представитель {$tag}-011", 10, 'Концентратор ИС', $fac('F', 4), null),
]), [1, 9]);
$s->mergeCells('D1:E2');

// 5.17: «Пульсар» — единица по номеру; пальчиковые — расходник, с номером и без
$s = $book->createSheet();
$s->setTitle('пульсоксиметр');
fill($s, array_merge($head, [
    $row($cn(10), '2025-05-01', $who(12), 11, 'Пульсар М1', $fac('P', 1), null),
    $row($cn(11), '2025-05-02', $who(13), 12, 'Beurer Medical', $fac('P', 2), null),
    $row($cn(12), '2025-05-03', $who(14), 13, 'пульсоксиметр пальчиковый', null, null),
]), [1, 9]);

// 5.22: заводской номер на двух листах — верен «эл. отс. В-40+АКБ»
foreach (['эл.отс. В-40', 'эл. отс. В-40+АКБ'] as $title) {
    $s = $book->createSheet();
    $s->setTitle($title);
    fill($s, array_merge($head, [$row($cn(13), '2025-06-01', $who(15), 14, 'Э/отсос', $fac('S', 1), null)]), [1, 9]);
}
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/census.xlsx");
echo "ok\n";
