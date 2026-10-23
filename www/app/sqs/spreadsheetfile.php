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
        return self::reader($path)->load($path);
    }

    /**
     * Листы таблицы по одному: [название => лист]. Каждый лист читается отдельно и освобождается, когда взят следующий, —
     * в памяти один лист, а не вся книга (таблица переписи на сотни килобайт целиком не помещалась в 128 МБ, TODO 2.25).
     * Пустые ячейки (бывают только с оформлением) не читаются.
     */
    public static function sheetNames(string $path): array {
        return self::reader($path)->listWorksheetNames($path);
    }

    public static function sheets(string $path): \Generator {
        $reader = self::reader($path);
        $reader->setReadEmptyCells(false);
        foreach ($reader->listWorksheetNames($path) as $name) {
            $book = $reader->setLoadSheetsOnly([$name])->load($path);
            yield $name => $book->getSheet(0);
            $book->disconnectWorksheets();
            unset($book);
        }
    }

    private static function reader(string $path): \PhpOffice\PhpSpreadsheet\Reader\IReader {
        Limits::raise(null);   // таблица на тысячи строк читается дольше 30 с и больше 128 МБ хостинга по умолчанию (импорт 1С, Excel, истории)
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
        return $reader;
    }
}
