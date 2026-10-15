<?php

namespace App\SQS;

/**
 * SquirrelStock: модули из папки app/sqs/modules/<имя>/ — доработки, которые живут отдельно от кода Zippy.
 *
 * module.php возвращает массив:
 *   'name'     => название (для людей),
 *   'menu'     => [[тип, meta_name, описание, группа меню], ...] — пункты меню (тип как в metadata: 1 документ, 2 отчет,
 *                 3 журнал, 4 справочник, 5 сервис), страница — класс App\Pages\<Doc|Report|Register|Reference|Service>\<meta_name>,
 *   'register' => function () { Hooks::on(...); } — обработчики событий документа (App\SQS\Hooks).
 * Страницы модуля: <модуль>/pages/<тип>/<имя>.php, шаблоны: <модуль>/templates/pages/<тип>/<имя>.html (имена в нижнем регистре).
 * Выключить модуль — удалить папку или переименовать с точкой в начале (.имя): его пункты меню выключаются (disabled = 1).
 * Загрузка — из init.php на каждый запрос: обход папок; база читается один раз за сессию и при смене набора пунктов меню,
 * metadata меняется только при смене набора.
 * Подробно — docs/MODULES.md.
 */
class Modules
{
    public const DIR = 'app/sqs/modules';

    private static ?array $modules = null;   // имя => ['dir' => путь, 'def' => массив module.php]

    public static function load(): void {
        if (self::$modules !== null) {
            return;
        }
        self::$modules = [];
        foreach (glob(_ROOT . self::DIR . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if ($name[0] == '.' || !is_file($dir . '/module.php')) {
                continue;
            }
            $def = require $dir . '/module.php';
            if (is_array($def)) {
                self::$modules[$name] = ['dir' => $dir, 'def' => $def];
            }
        }
        if (self::$modules) {
            // раньше штатного app_autoload: тот завершает работу, если файла класса нет в app/
            spl_autoload_register([self::class, 'autoload'], true, true);
        }
        foreach (self::$modules as $m) {
            if (is_callable($m['def']['register'] ?? null)) {
                ($m['def']['register'])();
            }
        }
        self::syncMenu();
    }

    public static function names(): array {
        return array_keys(self::$modules ?? []);
    }

    // классы страниц модулей: App\Pages\Report\Hello → <модуль>/pages/report/hello.php
    public static function autoload(string $class): void {
        if (strpos($class, 'App\\Pages\\') !== 0) {
            return;
        }
        $rel = strtolower(str_replace('\\', '/', substr($class, strlen('App\\Pages\\')))) . '.php';
        foreach (self::$modules ?? [] as $m) {
            if (is_file($m['dir'] . '/pages/' . $rel)) {
                require_once $m['dir'] . '/pages/' . $rel;
                return;
            }
        }
    }

    /**
     * Шаблон страницы из модуля (полный путь) или null — для App\Application::getTemplate
     */
    public static function template(string $class): ?string {
        $class = ltrim(str_replace('\\', '/', $class), '/');
        if (strpos($class, 'App/') !== 0) {
            return null;
        }
        $rel = 'templates/' . strtolower(substr($class, 4)) . '.html';
        foreach (self::$modules ?? [] as $m) {
            if (is_file($m['dir'] . '/' . $rel)) {
                return $m['dir'] . '/' . $rel;
            }
        }
        return null;
    }

    /**
     * Пункты меню модулей в metadata. Новый пункт добавляется (или включается снова), пункт исчезнувшего модуля
     * выключается; пункты, которые остались, не трогаются — их можно выключить вручную в «Налаштування меню».
     */
    private static function syncMenu(): void {
        $items = [];
        foreach (self::$modules as $m) {
            foreach ($m['def']['menu'] ?? [] as $it) {
                [$type, $name, $desc, $group] = array_pad($it, 4, '');
                $items[intval($type) . '|' . $name] = [intval($type), (string)$name, (string)$desc, (string)$group];
            }
        }
        ksort($items);
        // меню кэшируется в сессии (App\Pages\Base): при смене пунктов модулей кэш этой сессии сбрасывается
        // в этой сессии набор уже сверен с базой — обычный запрос обходится без обращения к базе
        $fp = md5(implode(',', array_keys($items)));
        if (session_status() == PHP_SESSION_ACTIVE) {
            if (($_SESSION['sqs_modules_fp'] ?? null) === $fp) {
                return;
            }
            \App\Session::getSession()->menu = [];
            $_SESSION['sqs_modules_fp'] = $fp;
        }
        $prev = json_decode((string)\App\Helper::getKeyVal('sqs_modules_menu'), true);
        $prev = is_array($prev) ? $prev : [];
        if (array_keys($prev) === array_keys($items)) {
            return;
        }
        $conn = \ZDB\DB::getConnect();
        foreach (array_diff_key($items, $prev) as [$type, $name, $desc, $group]) {
            $where = "meta_type = {$type} AND meta_name = " . $conn->qstr($name);
            if ($conn->GetOne("SELECT COUNT(*) FROM metadata WHERE {$where}") > 0) {
                $conn->Execute("UPDATE metadata SET disabled = 0 WHERE {$where}");
            } else {
                $conn->Execute("INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled) VALUES ({$type}, "
                    . $conn->qstr($desc) . ', ' . $conn->qstr($name) . ', ' . $conn->qstr($group) . ', 0)');
            }
        }
        foreach (array_diff_key($prev, $items) as [$type, $name]) {
            $conn->Execute("UPDATE metadata SET disabled = 1 WHERE meta_type = " . intval($type) . " AND meta_name = " . $conn->qstr($name));
        }
        \App\Helper::setKeyVal('sqs_modules_menu', json_encode($items, JSON_UNESCAPED_UNICODE));
    }
}
