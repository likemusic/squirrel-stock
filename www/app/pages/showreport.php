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
    // (2000 строк — около 0,5 ГБ и 25 с), но ширина колонок у каждого куска своя — режется только длинная таблица.
    // Что не помещается в предел — сообщение пользователю и запись в журнал.
    private const PDF_SPLIT = 500;        // таблица длиннее — режется (500 строк целиком — около 0,3 ГБ)
    private const PDF_ROWS = 50;          // строк в куске таблицы
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

        $dompdf = new \Dompdf\Dompdf(array('defaultFont' => 'DejaVu Sans'));
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
            if (!preg_match('~^(<table\b[^>]*>)(.*)</table>$~is', $m[0], $t)
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
            $out = $t[1] . implode('', array_slice($rows, 0, $head));
            foreach (array_slice($rows, $head) as $i => $r) {
                if ($i > 0 && $i % self::PDF_ROWS == 0) {
                    $out .= '</table>' . $t[1] . $repeat;
                }
                $out .= $r;
            }

            return $out . '</table>';
        }, $html) ?? $html;   // ошибка PCRE (предел возвратов) — как есть
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
