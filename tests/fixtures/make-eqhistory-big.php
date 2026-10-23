<?php
/**
 * Большие вымышленные таблицы учета оборудования (раскладка как в make-eqhistory.php) — для проверки времени разбора
 * (tests/specs/timelimit.spec.js): перепись — $n строк на каждом из трех листов, журнал — $n строк. Номера единиц
 * в базе не ищутся: разбор дает ошибки «нет в базе», время уходит на чтение файлов и разбор строк, как у реальных таблиц.
 * Запуск внутри web-контейнера: php make-eqhistory-big.php <папка> <строк на лист> <метка>
 */
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

[$_, $dir, $n, $tag] = $argv;
$n = intval($n);
$d = fn(int $k) => \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTime('2020-01-01 +' . ($k % 2000) . ' days'));
$who = fn(int $k) => "Подопечный {$tag}-" . sprintf('%05d', $k);

function fill($sheet, array $rows, array $dates) {
    foreach ($rows as $r => $row) {
        foreach ($row as $c => $v) {
            if ($v !== null) {
                $sheet->setCellValueExplicit([$c + 1, $r + 1], $v, is_string($v) ? 's' : 'n');
            }
        }
    }
    foreach ($dates as $c) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1);
        $sheet->getStyle("{$col}3:{$col}" . (count($rows) + 1))->getNumberFormat()->setFormatCode('dd.mm.yyyy');
    }
}

$book = new Spreadsheet();
foreach (['концентр. кисл.', 'кровати', 'коляски'] as $i => $title) {
    $s = $i ? $book->createSheet() : $book->getActiveSheet();
    $s->setTitle($title);
    $rows = [['№ договора', 'дата', 'ФИО', 'адрес', null, 'телефон', 'наименование', 'зав. №.', null, 'дата   возврата', 'срок пользования', null],
             [null, null, null, null, null, null, 'оборудования', null, 'инв. №', null, null, null]];
    for ($k = 1; $k <= $n; $k++) {
        $j = $i * $n + $k;
        $rows[] = [($j % 900 + 1) . '/' . (20 + $j % 6), $d($j), $who($j), 'г. Тестов', "ул. Тестовая, {$j}", '37529' . sprintf('%07d', $j), $title . ' тестовый',
                   "ZV-{$tag}-{$j}", sprintf('09%06d', $j), $k % 3 ? $d($j + 60) : null, 'до востр.', $k % 7 ? null : 'примечание'];
    }
    fill($s, $rows, [1, 9]);
    $s->mergeCells('D1:E2');
}
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/census-big.xlsx");

$book = new Spreadsheet();
$s = $book->getActiveSheet();
$s->setTitle('обычные');
$rows = [['№', 'дата регистрации договора', 'ФИО', 'Адрес проживания, телефон', 'Кем направлен', 'Наименование оборудования', 'Инв (заводской) №', 'Дата выдачи', 'дата возврата', 'примечание']];
for ($k = 1; $k <= $n; $k++) {
    $rows[] = [(float)$k, $d($k), $who($k), "ул. Тестовая, {$k} +375 29 " . sprintf('%07d', $k), 'Врач тестовый', 'концентратор', sprintf('09%06d', $k), $d($k), $k % 3 ? $d($k + 60) : null, 'до востребования'];
}
fill($s, $rows, [1, 7, 8]);
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/issues-big.xlsx");
echo "ok\n";
