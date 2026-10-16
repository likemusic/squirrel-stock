<?php

namespace App\SQS;

/**
 * SquirrelStock: версия установленной программы и состав пакета.
 *
 * Пакет tools/release/build.sh кладет в config/:
 *   version.txt  — версия (дата коммита и его номер, например 2026.10.16-d2489fb22);
 *   manifest.txt — список файлов пакета с md5 (формат md5sum: «md5  путь»).
 * Установка tools/install/linux.sh из git-копии пишет только version.txt. Без файла (стенд разработки) версия — «dev».
 *
 * Смена версии замечается при сверке модулей (App\SQS\Modules) и записывается в keyval 'sqs_versions' (история
 * обновлений: с какой, на какую, когда) и в журнал программы. Страница «Сервис → Обновление программы» (модуль update)
 * показывает версию, историю и файлы, которых нет в пакете: распаковка нового пакета поверх старого (хостинг) оставляет
 * удаленные в новой версии файлы на месте.
 */
class Release
{
    public const VERSION_FILE = 'config/version.txt';
    public const MANIFEST_FILE = 'config/manifest.txt';
    // папки пакета, в которых лишние файлы — остатки старых версий и удаляются кнопкой; в остальных местах (корень сайта,
    // config/, upload/, logs/) лишнее показывается, но не трогается: это настройки, файлы пользователей, файлы хостинга
    public const CODE_DIRS = ['app', 'assets', 'cli', 'lang', 'templates', 'vendor'];
    private const KEEP = ['config/config.php'];
    private const KEEP_DIRS = ['upload', 'logs'];

    private static ?string $version = null;

    public static function version(): string {
        if (self::$version === null) {
            $v = is_file(_ROOT . self::VERSION_FILE) ? trim((string)file_get_contents(_ROOT . self::VERSION_FILE)) : '';
            self::$version = preg_match('/^[\w.+-]{1,64}$/', $v) ? $v : 'dev';
        }
        return self::$version;
    }

    /**
     * Смена версии с прошлой сверки: запись в историю и журнал. Вызывается из App\SQS\Modules::sync (раз за сессию и
     * при смене набора файлов) и из cli/modules.php при обновлении через установщик.
     */
    public static function note(): void {
        $cur = self::version();
        $prev = \App\Helper::getKeyVal('sqs_version');
        if ($prev === $cur) {
            return;
        }
        $hist = self::history();
        $hist[] = ['from' => $prev === null || $prev === '' ? '' : (string)$prev, 'to' => $cur, 'at' => date('Y-m-d H:i:s')];
        \App\Helper::setKeyVal('sqs_versions', json_encode(array_slice($hist, -50), JSON_UNESCAPED_UNICODE));
        \App\Helper::setKeyVal('sqs_version', $cur);
        global $logger;
        // из консоли не пишется: скрипт от root создал бы журнал дня, в который веб-сервер потом не может писать
        if ($logger && PHP_SAPI !== 'cli') {
            $logger->warning('SquirrelStock version ' . ($prev ?: '(unknown)') . ' -> ' . $cur);
        }
    }

    /** История обновлений: [['from' => ..., 'to' => ..., 'at' => 'Y-m-d H:i:s'], ...], старые первыми */
    public static function history(): array {
        $h = json_decode((string)\App\Helper::getKeyVal('sqs_versions'), true);
        return is_array($h) ? $h : [];
    }

    /** [путь => md5] из manifest.txt или null, если установка не из пакета */
    public static function manifest(): ?array {
        $f = _ROOT . self::MANIFEST_FILE;
        if (!is_file($f)) {
            return null;
        }
        $ret = [];
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^([0-9a-f]{32})  (.+)$/', $line, $m) && strpos($m[2], '..') === false) {
                $ret[$m[2]] = $m[1];
            }
        }
        return $ret;
    }

    /**
     * Сверка файлов сайта с пакетом: ['missing' => [...], 'changed' => [...], 'stale' => [...], 'foreign' => [...]] или null.
     * missing и changed — пакет распакован не полностью (обрыв загрузки), stale — остатки старых версий в папках пакета
     * (удаляются кнопкой), foreign — прочие файлы не из пакета (не трогаются).
     */
    public static function check(): ?array {
        $man = self::manifest();
        if ($man === null) {
            return null;
        }
        $ret = ['missing' => [], 'changed' => [], 'stale' => [], 'foreign' => []];
        foreach ($man as $path => $md5) {
            $f = _ROOT . $path;
            if (!is_file($f)) {
                $ret['missing'][] = $path;
            } elseif ($path != self::MANIFEST_FILE && md5_file($f) !== $md5) {
                $ret['changed'][] = $path;
            }
        }
        foreach (self::files() as $path) {
            if (isset($man[$path]) || in_array($path, self::KEEP, true)) {
                continue;
            }
            $top = explode('/', $path)[0];
            if (in_array($top, self::KEEP_DIRS, true)) {
                continue;
            }
            $ret[in_array($top, self::CODE_DIRS, true) && strpos($path, '/') !== false ? 'stale' : 'foreign'][] = $path;
        }
        return $ret;
    }

    /**
     * Удалить остатки старых версий (check()['stale']) и опустевшие после этого папки. Возвращает [удалено, не удалось].
     */
    public static function removeStale(): array {
        $chk = self::check();
        if ($chk === null) {
            return [0, []];
        }
        $done = 0;
        $failed = [];
        $dirs = [];
        foreach ($chk['stale'] as $path) {
            if (@unlink(_ROOT . $path)) {
                $done++;
                $dirs[dirname($path)] = true;
            } else {
                $failed[] = $path;
            }
        }
        // опустевшие папки снизу вверх (верхние папки пакета — app/, vendor/... — остаются); rmdir удаляет только пустую
        $all = [];
        foreach (array_keys($dirs) as $d) {
            for ($p = $d; strpos($p, '/') !== false; $p = dirname($p)) {
                $all[$p] = true;
            }
        }
        $dirs = array_keys($all);
        usort($dirs, fn($a, $b) => substr_count($b, '/') <=> substr_count($a, '/'));
        foreach ($dirs as $d) {
            if (is_dir(_ROOT . $d) && count(scandir(_ROOT . $d)) == 2) {
                @rmdir(_ROOT . $d);
            }
        }
        global $logger;
        if ($logger && PHP_SAPI !== 'cli') {
            $logger->warning("SquirrelStock: {$done} stale files removed" . ($failed ? ', failed: ' . count($failed) : ''));
        }
        return [$done, $failed];
    }

    // все файлы сайта (пути от корня, через /), без upload/ и logs/ — там файлы пользователей и журналы
    private static function files(): array {
        $root = rtrim(_ROOT, '/\\');
        $ret = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            function ($f) use ($root) {
                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
                return !in_array($rel, self::KEEP_DIRS, true) && !$f->isLink();
            }
        ));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $ret[] = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            }
        }
        sort($ret);
        return $ret;
    }
}
