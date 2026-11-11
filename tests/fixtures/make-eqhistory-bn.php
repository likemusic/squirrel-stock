<?php
/**
 * Вымышленная таблица выдачи для теста инвентарного номера «б/н» (tests/specs/eqhistory-bn.spec.js): номера нет,
 * строка идет путем строки без номера, а не ищется единица с номером «б/н».
 *  - issues.xlsx: лист «обычные» таблицы выдачи.
 * Запуск внутри web-контейнера: php make-eqhistory-bn.php <папка> <base 7 цифр> <метка>
 */
require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

[$_, $dir, $base, $tag] = $argv;
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
$s = $book->getActiveSheet();
$s->setTitle('обычные');
$row = fn(int $k, string $item, string $n, $ret = null) => [(string)($no + $k), $d('2025-10-0' . $k), $who($k), 'ул. Тестовая', 'Врач тестовый', $item, $n, null, $ret, null];
fill($s, [
    ['№', 'дата регистрации договора', 'ФИО', 'Адрес проживания, телефон', 'Кем направлен', 'Наименование оборудования', 'Инв (заводской) №', 'Дата выдачи', 'дата возврата', 'примечание'],
    // на руках, позиции нет в соответствиях
    $row(1, "рециркулятор тест {$tag}", 'б/н'),
    // на руках, позиция с учетом по номерам («*» в соответствиях) — в список без инвентарного номера
    $row(2, "стул тест {$tag}", ' Б\\Н '),
    // возвращено — как строка без номера, пропуск
    $row(3, "рециркулятор тест {$tag}", 'бн', $d('2025-10-20')),
], [1, 7, 8]);
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save("$dir/issues.xlsx");
echo "ok\n";
