<?php

namespace App\Pages;

//страница  для  загрузки  файла отчета
class ShowReport extends \Zippy\Html\WebPage
{
    public function __construct($type, $filename="") {
        parent::__construct();
        $this->_tvars['t'] = new \App\Translator();   // перевод в шаблонах: {{#t}}ключ{{/t}}

        $common = \App\System::getOptions('common');
        $user = \App\System::getUser();
        $customer_id = intval(\App\System::getCustomer() );
        if ($user->user_id == 0 && $customer_id==0) {
            http_response_code(401);
            die;
        }

        
        if ($type == "print") {
            header("Content-Type: text/html;charset=UTF-8");
            
            $html = \App\Session::getSession()->printform;
            \App\Session::getSession()->printform ='';
            header('Content-Length: '.strlen($html));
            echo $html;
            flush()  ;
            die;            
        }        
        
        
        $filename = $filename . date('_Y_m_d');
        $html = \App\Session::getSession()->getExportReport();
        if (strlen($html) == 0) {
            http_response_code(404);
            die;
        }    
        
        
    
        if ($type == "preview") {
            header("Content-Type: text/html;charset=UTF-8");
        }
   
        if ($type == "doc") {
            header("Content-type: application/vnd.ms-word");
            header("Content-Disposition: attachment;Filename={$filename}.doc");
            header("Content-Transfer-Encoding: binary");
        }
        if ($type == "xls") {

        //    $file = tempnam(sys_get_temp_dir(), "".time());

        //    file_put_contents($file, $html);

            $reader =  new \PhpOffice\PhpSpreadsheet\Reader\Html()  ;
            $spreadsheet = $reader->loadFromString($html);


            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment;Filename={$filename}.xlsx");
            $writer->save('php://output');
            die;


        }
        if ($type == "html") {
            header("Content-type: text/plain");
            header("Content-Disposition: attachment;Filename={$filename}.html");
            header("Content-Transfer-Encoding: binary");
        }
        if ($type == "xml") {
            header("Content-type: text/xml");
            header("Content-Disposition: attachment;Filename={$filename}.xml");
            header("Content-Transfer-Encoding: binary");
            $html = \App\Session::getSession()->printxml;
            \App\Session::getSession()->printxml="";
        }
        if ($type == "pdf") {
            $html = self::pdf($html, $filename);   // SquirrelStock: заголовки — после генерации, большой отчет — сообщение вместо 500
            header("Content-type: application/pdf");
            header("Content-Disposition: attachment;Filename={$filename}.pdf");
            header("Content-Transfer-Encoding: binary");
        }
        header('Content-Length: '.strlen($html));
        echo $html;
        flush()  ;
        die;
    }

    // SquirrelStock: PDF большого отчета. dompdf верстает таблицу через все страницы целиком: память и время растут
    // квадратично (2000 строк — 3,3 ГБ и 2 минуты). Таблица режется на куски с повтором шапки — рост линейный
    // (2000 строк — около 0,5 ГБ и 25 с). Ширина колонок у всех кусков одна — по содержимому всей таблицы.
    // Что не помещается в предел — сообщение пользователю и запись в журнал.
    private const PDF_SPLIT = 500;        // таблица длиннее — режется (500 строк целиком — около 0,3 ГБ)
    private const PDF_ROWS = 50;          // строк в куске таблицы
    private const PDF_TAIL = 10;          // остаток короче — в последний кусок, а не отдельной таблицей с шапкой («Итого»)
    private const PDF_COL_CHARS = 40;     // текст длиннее при расчете ширины колонки не учитывается — переносится
    private const PDF_CELLS = 30000;      // больше ячеек — не пытаться: около 18 КБ памяти на ячейку
    private const PDF_MEMORY = '768M';
    private const PDF_SECONDS = 180;

    private static function pdf(string $html, string $filename): string {
        $cells = substr_count($html, '<td');
        if ($cells > self::PDF_CELLS) {
            self::pdfTooBig("{$filename}: {$cells} cells, limit " . self::PDF_CELLS);
        }
        if (self::bytes(ini_get('memory_limit')) < self::bytes(self::PDF_MEMORY)) {
            ini_set('memory_limit', self::PDF_MEMORY);
        }
        set_time_limit(self::PDF_SECONDS);
        // нехватка памяти или времени — фатальная ошибка, ее не поймать: сообщение — из завершения скрипта
        $reserve = str_repeat(' ', 1 << 20);
        register_shutdown_function(function () use (&$reserve, $filename, $cells) {
            $reserve = null;
            $e = error_get_last();
            if ($e !== null && $e['type'] == E_ERROR && !headers_sent()
                && (str_contains($e['message'], 'Allowed memory size') || str_contains($e['message'], 'Maximum execution time'))) {
                self::pdfTooBig("{$filename}: {$cells} cells, " . $e['message']);
            }
        });

        $dompdf = new \Dompdf\Dompdf(\App\SQS\Pdf::options(array('defaultFont' => 'DejaVu Sans')));   // SquirrelStock: временная папка программы (open_basedir хостинга)
        $dompdf->loadHtml(self::splitTables($html));
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $pdf = $dompdf->output();
        $reserve = null;

        return $pdf;
    }

    private static function pdfTooBig(string $why) {
        \App\Helper::logerror('PDF report is too big: ' . $why);
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        header("Content-Type: text/html;charset=UTF-8");
        echo '<!doctype html><html><head><meta charset="UTF-8"><title>PDF</title></head><body style="font-family:sans-serif;margin:2em">'
            . '<p>' . htmlspecialchars(__('sqs.report.pdf_too_big')) . '</p></body></html>';
        die;
    }

    // таблица длиннее PDF_SPLIT строк без вложенных таблиц и rowspan — куски по PDF_ROWS строк, строки шапки (с <th>) повторяются в каждом
    private static function splitTables(string $html): string {
        return preg_replace_callback('~<table\b[^>]*>.*?</table>~is', function ($m) {
            // <thead>/<tbody> (шапка отчета повторяется на листах печати) не мешают: куски собираются из строк
            if (!preg_match('~^(<table\b[^>]*>)(.*)</table>$~is', $m[0], $t)
                || ($t[2] = preg_replace('~</?t(?:head|body|foot)\b[^>]*>~i', '', $t[2])) === null
                || stripos($t[2], '<table') !== false || stripos($t[2], 'rowspan') !== false
                || trim(preg_replace('~<tr\b.*?</tr>~is', '', $t[2])) !== '') {
                return $m[0];
            }
            preg_match_all('~<tr\b.*?</tr>~is', $t[2], $rows);
            $rows = $rows[0];
            $head = 0;   // шапка — до последней строки с <th> среди первых пяти
            foreach (array_slice($rows, 0, 5) as $i => $r) {
                if (stripos($r, '<th') !== false) {
                    $head = $i + 1;
                }
            }
            if (count($rows) - $head <= self::PDF_SPLIT) {
                return $m[0];
            }
            $repeat = implode('', array_filter(array_slice($rows, 0, $head), fn($r) => stripos($r, '<th') !== false));
            $open = self::fixedTable($t[1], $rows);
            $out = $open . implode('', array_slice($rows, 0, $head));
            $body = count($rows) - $head;
            foreach (array_slice($rows, $head) as $i => $r) {
                if ($i > 0 && $i % self::PDF_ROWS == 0 && $body - $i >= self::PDF_TAIL) {
                    $out .= '</table>' . $open . $repeat;
                }
                $out .= $r;
            }

            return $out . '</table>';
        }, $html) ?? $html;   // ошибка PCRE (предел возвратов) — как есть
    }

    // начало куска: table-layout: fixed и невидимая первая строка с шириной колонок — dompdf при fixed берет ширину
    // только из ячеек первой строки (<col> не поддерживает). Ширина — доля самого длинного текста колонки
    // (не длиннее PDF_COL_CHARS, у заголовка — самое длинное слово, он переносится).
    private static function fixedTable(string $open, array $rows): string {
        $len = [];
        foreach ($rows as $r) {
            preg_match_all('~<t([dh])\b([^>]*)>(.*?)</t[dh]>~is', $r, $cells, PREG_SET_ORDER);
            $col = 0;
            foreach ($cells as [, $tag, $attr, $text]) {
                $span = preg_match('~colspan\s*=\s*["\']?(\d+)~i', $attr, $s) ? max((int)$s[1], 1) : 1;
                if ($span == 1) {
                    $text = trim(html_entity_decode(strip_tags(preg_replace('~<br\s*/?>~i', ' ', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $n = strtolower($tag) == 'h' ? max(array_map('mb_strlen', preg_split('~\s+~u', $text))) : mb_strlen($text);
                    $len[$col] = max($len[$col] ?? 2, min($n, self::PDF_COL_CHARS));
                }
                $col += $span;
            }
            for ($c = 0; $c < $col; $c++) {
                $len[$c] ??= 2;   // колонка только под colspan
            }
        }
        ksort($len);
        $total = array_sum($len);
        $widths = '';
        foreach ($len as $n) {
            $widths .= '<td style="width:' . round($n * 100 / $total, 2) . '%;padding:0;border:0;height:0"></td>';
        }
        $fixed = 'table-layout:fixed;width:100%;';
        $open = preg_match('~\bstyle\s*=\s*["\']~i', $open)
            ? preg_replace('~\bstyle\s*=\s*(["\'])~i', 'style=$1' . $fixed, $open, 1)
            : preg_replace('~^<table\b~i', '<table style="' . $fixed . '"', $open);

        return $open . '<tr>' . $widths . '</tr>';
    }

    private static function bytes(string $v): int {
        $v = trim($v);
        if ($v === '-1') {
            return PHP_INT_MAX;
        }
        $n = intval($v);

        return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    }

}
