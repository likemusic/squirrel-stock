<?php

namespace App\SQS;

/**
 * SquirrelStock: настройки dompdf с временной папкой программы (App\SQS\TempFiles). По умолчанию dompdf берет
 * sys_get_temp_dir(): на хостинге с open_basedir без /tmp tempnam давал false, и PDF отчета или документа падал с
 * «Path must not be empty» (php-font-lib, подмножество шрифта).
 */
class Pdf
{
    public static function options(array $options = []): array {
        try {
            $options += ['tempDir' => TempFiles::dir()];
        } catch (TempFilesException) {
            // временной папки нет — как у dompdf по умолчанию; причина уже в журнале
        }
        return $options;
    }
}
