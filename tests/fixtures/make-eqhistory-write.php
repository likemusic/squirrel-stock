<?php
/**
 * Вымышленные таблицы для теста записи истории оборудования (tests/specs/eqhistory-write.spec.js): пересекающиеся выдачи,
 * договор с двумя ФИО, возврат будущим числом, событие, которое не запишется. Раскладка — как у переписи хосписа.
 *  - osv-01.xls: ведомость 1С по счету 01 — модель с четырьмя единицами (1, 2, 3, 5) и модель с одной единицей (4);
 *  - perepis.xlsx: лист «концентр. кисл.».
 * Запуск внутри web-контейнера: php make-eqhistory-write.php <папка> <base 7 цифр> <метка> <дата возврата в будущем Y-m-d>
 */
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

[$_, $dir, $base, $tag, $future] = $argv;
$base = intval($base);
$inv = fn(int $k) => '0' . ($base + $k);
$cn = fn(int $k, string $yy) => (intval(substr((string)$base, -5)) + $k) . '/' . $yy;
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
         ['Основные средства', 'Дебет', 'Кредит'], ['01.1 (по счету: 01)', 5000]];
foreach ([1, 2, 3, 5] as $k) {
    $rows[] = ["Концентратор ПВ тестовый {$tag}, инв.№: " . $inv($k), 1000];
}
$rows[] = ["Аппарат ПВ одиночный {$tag}, инв.№: " . $inv(4), 1000];
$rows[] = ['Итого развернутое', 5000];
fill($book->getActiveSheet(), $rows);
(new \PhpOffice\PhpSpreadsheet\Writer\Xls($book))->save("$dir/osv-01.xls");

$book = new Spreadsheet();
$s = $book->getActiveSheet();
$s->setTitle('концентр. кисл.');
fill($s, [
    ['№ договора', 'дата', 'ФИО', 'адрес', null, 'телефон', 'наименование', 'зав. №.', null, 'дата   возврата', 'срок пользования', null],
    [null, null, null, null, null, null, 'оборудования', null, 'инв. №', null, null, null],
    // единица 1: выдана и возвращена 01.05; по строке 4 выдана снова 01.03 — раньше возврата (ошибка); 01.06 — снова, верно
    [$cn(1, '25'), $d('2025-02-01'), $who(1), 'г. Тестов', 'ул. Тестовая, 1', null, 'Концентратор ПВ', null, $inv(1), $d('2025-05-01'), 'до востр.', null],
    [$cn(2, '25'), $d('2025-03-01'), $who(2), 'г. Тестов', 'ул. Тестовая, 2', null, 'Концентратор ПВ', null, $inv(1), null, 'до востр.', null],
    [$cn(3, '25'), $d('2025-06-01'), $who(3), 'г. Тестов', 'ул. Тестовая, 3', null, 'Концентратор ПВ', null, $inv(1), null, 'до востр.', null],
    // один договор — два разных ФИО: вторая строка — ошибка при проверке, а не сбой при записи
    [$cn(4, '25'), $d('2025-02-10'), $who(4), 'г. Тестов', 'ул. Тестовая, 4', null, 'Концентратор ПВ', null, $inv(2), null, 'до востр.', null],
    [$cn(4, '25'), $d('2025-02-11'), $who(5), 'г. Тестов', 'ул. Тестовая, 5', null, 'Концентратор ПВ', null, $inv(3), null, 'до востр.', null],
    // возврат будущим числом, срок пуст — плановый срок: единица на руках, дата — срок договора
    [$cn(6, '25'), $d('2025-03-05'), $who(6), 'г. Тестов', 'ул. Тестовая, 6', null, 'Концентратор ПВ', null, $inv(5), $d($future), null, null],
    // единица одиночной модели к записи уже не на складе: выдача не запишется, ее возврат — пропущен с объяснением
    [$cn(7, '25'), $d('2025-04-01'), $who(7), 'г. Тестов', 'ул. Тестовая, 7', null, 'Аппарат ПВ', null, $inv(4), $d('2025-05-01'), 'до востр.', null],
], [1, 9]);
$s->mergeCells('D1:E2');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/perepis.xlsx");
echo "ok\n";
