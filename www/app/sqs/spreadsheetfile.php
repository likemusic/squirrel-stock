<?php

namespace App\SQS;

/**
 * SquirrelStock: чтение загруженной таблицы для импорта — только Excel (xlsx, xls), OpenDocument (ods) и CSV.
 * IOFactory::load() по временному файлу без расширения перебирает все читатели PhpSpreadsheet, в том числе HTML и XML
 * (SpreadsheetML, Gnumeric, SYLK); импорту они не нужны. Текст, похожий на HTML или XML, как CSV тоже не читается:
 * иначе строки разметки стали бы позициями.
 */
class SpreadsheetFile
{
    public const READERS = ['Xlsx', 'Xls', 'Ods', 'Csv'];

    public static function load(string $path): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path, self::READERS);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            throw new \Exception(__('sqs.import.bad_format'));
        }
        if ($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Csv) {
            $head = (string)@file_get_contents($path, false, null, 0, 4096);
            if (preg_match('/^(\xEF\xBB\xBF)?\s*</', $head) || str_contains($head, "\0")) {
                throw new \Exception(__('sqs.import.bad_format'));
            }
        }
        return $reader->load($path);
    }
}
