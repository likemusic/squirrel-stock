<?php

namespace App\SQS;

/**
 * SquirrelStock: временная папка программы и временные файлы в ней.
 * Папка (dir): системная временная (sys_get_temp_dir), если ее пропускает open_basedir и в нее можно писать; иначе
 * upload_tmp_dir из настроек PHP; иначе tmp/ внутри программы (закрыта от браузера: tmp/.htaccess и правило в .htaccess
 * корня). На хостинге ISPmanager open_basedir обычно без /tmp — tempnam(sys_get_temp_dir()) давал false, и копия падала
 * с «Path must not be empty».
 * Удаление (purge): файлы старше суток — копии таблиц импорта истории оборудования (sqs-eqh-*) от проверок без записи,
 * дампы резервной копии (sqs*), оставшиеся после оборванного запроса. В них персональные данные.
 * Удаляет веб-запрос (App\SQS\LoginGuard::purge — при каждой попытке входа), а не cron: у Apache с PrivateTmp (Debian)
 * своя временная папка, которую задания cron не видят.
 */
class TempFiles
{
    private const NAME_RE = '/^sqs(-eqh-)?[A-Za-z0-9]+(\.tmp|\.zip)?$/';

    private static ?string $dir = null;

    public static function dir(): string {
        if (self::$dir !== null) {
            return self::$dir;
        }
        foreach ([sys_get_temp_dir(), (string)ini_get('upload_tmp_dir')] as $d) {
            if (self::usable($d)) {
                return self::$dir = rtrim($d, '/\\');
            }
        }
        $own = _ROOT . 'tmp';
        if (self::usable($own) || (self::allowed($own) && @mkdir($own, 0700) && self::usable($own))) {
            if (!is_file($own . '/.htaccess')) {
                @file_put_contents($own . '/.htaccess', "Require all denied\n");
            }
            return self::$dir = $own;
        }
        throw new TempFilesException('sqs.tmp.no_dir', $own);
    }

    /**
     * Новый пустой временный файл с префиксом (sqs, sqs-eqh-); ошибка — TempFilesException, а не false (fopen(false) — «Path must not be empty»)
     */
    public static function file(string $prefix = 'sqs'): string {
        $f = @tempnam(self::dir(), $prefix);
        if ($f === false || realpath(dirname($f)) !== realpath(self::dir())) {
            // tempnam при ошибке берет системную папку сам — файл не там, где ищет purge
            $f !== false && @unlink($f);
            throw new TempFilesException('sqs.tmp.no_file', self::dir());
        }
        return $f;
    }

    public static function purge(int $age = 86400): int {
        try {
            $dir = self::dir();
        } catch (TempFilesException) {
            return 0;
        }
        $n = 0;
        foreach (glob($dir . DIRECTORY_SEPARATOR . 'sqs*') ?: [] as $f) {
            if (preg_match(self::NAME_RE, basename($f)) && is_file($f) && @filemtime($f) < time() - $age && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }

    // папка есть и пишется; вне open_basedir не проверяется вовсе (проверка дала бы предупреждение в журнал)
    private static function usable(string $d): bool {
        return $d !== '' && self::allowed($d) && @is_dir($d) && @is_writable($d);
    }

    private static function allowed(string $d): bool {
        $limits = (string)ini_get('open_basedir');
        if ($limits === '') {
            return true;
        }
        $norm = fn($p) => rtrim(str_replace('\\', '/', $p), '/') . '/';
        $path = $norm($d);
        foreach (explode(PATH_SEPARATOR, $limits) as $l) {
            if ($l === '') {
                continue;
            }
            if ($l === '.') {
                $l = getcwd() ?: '';
            }
            $real = realpath($l);
            foreach (array_unique([$l, $real ?: $l]) as $p) {
                if (str_starts_with($path, $norm($p))) {
                    return true;
                }
            }
        }
        return false;
    }
}
