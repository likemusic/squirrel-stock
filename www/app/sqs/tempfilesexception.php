<?php

namespace App\SQS;

/**
 * SquirrelStock: нет временной папки или файла (App\SQS\TempFiles). Текст — для пользователя, без путей сервера: путь
 * пишется в журнал программы.
 */
class TempFilesException extends \RuntimeException
{
    public function __construct(string $key, string $dir) {
        global $logger;
        $logger?->error('SquirrelStock temp: ' . $key . ' ' . $dir . ' (open_basedir: ' . ini_get('open_basedir') . ', upload_tmp_dir: ' . ini_get('upload_tmp_dir') . ', sys_get_temp_dir: ' . sys_get_temp_dir() . ')');
        parent::__construct(__($key));
    }
}
