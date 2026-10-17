<?php

namespace App\SQS;

/**
 * SquirrelStock: временные файлы программы старше суток — копии таблиц импорта истории оборудования (sqs-eqh-*) от проверок
 * без записи, дампы резервной копии (sqs*), оставшиеся после оборванного запроса. В них персональные данные.
 * Удаляет веб-запрос (App\SQS\LoginGuard::purge — при каждой попытке входа), а не cron: у Apache с PrivateTmp (Debian)
 * своя временная папка, которую задания cron не видят.
 */
class TempFiles
{
    private const NAME_RE = '/^sqs(-eqh-)?[A-Za-z0-9]+(\.tmp|\.zip)?$/';

    public static function purge(int $age = 86400): int {
        $n = 0;
        foreach (glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sqs*') ?: [] as $f) {
            if (preg_match(self::NAME_RE, basename($f)) && is_file($f) && @filemtime($f) < time() - $age && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }
}
