<?php
/**
 * Вымышленные ведомости 1С по счету 10 с одноименными позициями (разные цены) для tests/specs/import-1c-same-name.spec.js.
 * Названия — с меткой прогона, чтобы тест можно было повторять на той же базе.
 *  - v1: «Повязка» по 10 и по 50, «Бинт»;
 *  - v2: строки «Повязки» в обратном порядке, средние цены сдвинулись (12 и 48);
 *  - v3: одна «Повязка» по 48 — цена до копейки как у второй на прошлом импорте;
 *  - v4: одна «Повязка» по 30 — неоднозначно.
 * Запуск внутри web-контейнера (нужен PhpSpreadsheet): php make-1c-same.php <папка> <метка>
 */
require '/var/www/html/vendor/autoload.php';

[$_, $dir, $tag] = $argv;
$band = "Повязка тестовая {$tag}";
$bint = "Бинт тестовый {$tag}";

function osv(array $positions, string $file) {
    $rows = [['Учреждение (тест)'], ['Оборотно-сальдовая ведомость по счету 10.1 за Сентябрь 2026 г.'], ['Счет', 'Сальдо на начало периода'],
             ['Номенклатура', 'Дебет', 'Кредит'], ['Показатели'], ['10.1 (по счету: 10)', array_sum(array_column($positions, 1))], ['Кол-во', array_sum(array_column($positions, 2))]];
    foreach ($positions as [$name, $sum, $qty]) {
        $rows[] = [$name, $sum];
        $rows[] = ['Кол-во', $qty];
    }
    $rows[] = ['Итого развернутое', array_sum(array_column($positions, 1))];
    $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    foreach ($rows as $r => $row) {
        foreach ($row as $c => $v) {
            $book->getActiveSheet()->setCellValue([$c + 1, $r + 1], $v);
        }
    }
    (new \PhpOffice\PhpSpreadsheet\Writer\Xls($book))->save($file);
}

osv([[$band, 100, 10], [$band, 500, 10], [$bint, 30, 3]], "$dir/v1.xls");
osv([[$band, 480, 10], [$band, 120, 10]], "$dir/v2.xls");
osv([[$band, 480, 10]], "$dir/v3.xls");
osv([[$band, 300, 10]], "$dir/v4.xls");
echo "ok\n";
