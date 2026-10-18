<?php
/**
 * PDF большого отчета — без формы и без раздувания базы (tests/specs/report-export.spec.js): отчет-таблица вида «Состояние складов»
 * из вымышленных строк отдается в ShowReport::pdf, как при нажатии «PDF».
 * Запуск в web-контейнере от www-data, скрипт — на stdin: php -- <строк> <колонок> [split]
 * Вывод: split — JSON { tables, heads, rows } после разрезания; иначе — начало ответа (%PDF… или страница сообщения) и число страниц.
 */
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
require '/var/www/html/init.php';

[$_, $rows, $cols] = $argv;
$html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body><table class="ctable" border="0" cellpadding="2" cellspacing="0">'
    . '<tr><td colspan="' . $cols . '">Отчет (тест)</td></tr><tr>';
for ($c = 0; $c < $cols; $c++) {
    $html .= '<th style="border: solid black 1px">Колонка ' . $c . '</th>';
}
$html .= '</tr>';
for ($r = 0; $r < $rows; $r++) {
    $html .= '<tr><td>Позиция ' . $r . '</td>' . str_repeat('<td align="right">' . ($r % 7) . '</td>', $cols - 1) . '</tr>';
}
$html .= '</table></body></html>';

$call = fn(string $name, ...$args) => (new ReflectionMethod(\App\Pages\ShowReport::class, $name))->invoke(null, ...$args);
if (($argv[3] ?? '') == 'split') {
    $out = $call('splitTables', $html);
    echo json_encode(['tables' => substr_count($out, '<table'), 'heads' => substr_count($out, '<th style') / $cols, 'rows' => substr_count($out, '<tr><td>')]), "\n";
    exit;
}
$pdf = $call('pdf', $html, 'test');
echo substr($pdf, 0, 4), ' ', preg_match_all('~/Type\s*/Page[^s]~', $pdf), "\n";
