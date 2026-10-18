<?php

namespace App\SQS;

/**
 * SquirrelStock: модули из папки app/sqs/modules/<имя>/ — доработки, которые живут отдельно от кода Zippy.
 *
 * module.php возвращает массив:
 *   'name'     => название (для людей),
 *   'menu'     => [[тип, meta_name, описание, группа меню], ...] — пункты меню (тип как в metadata: 1 документ, 2 отчет,
 *                 3 журнал, 4 справочник, 5 сервис), страница — класс App\Pages\<Doc|Report|Register|Reference|Service>\<meta_name>;
 *                 описание и группа — строка или ['uk' => ..., 'ru' => ...] (пишется на языке установки),
 *   'register' => function () { Hooks::on(...); Hooks::listen(...); } — обработчики событий (App\SQS\Hooks).
 * Файлы модуля (имена в нижнем регистре):
 *   pages/<тип>/<имя>.php — класс App\Pages\<Тип>\<Имя>;  <имя>.php — класс App\SQS\<Имя>;
 *   templates/pages/<тип>/<имя>.html — шаблон страницы;  templates/printforms/... — печатные формы (App\Report);
 *   schema.sql — таблицы модуля (CREATE TABLE IF NOT EXISTS ...), применяется при изменении файла;
 *   lang/<язык>.json — ключи перевода модуля;  cli/<скрипт>.php — консольные скрипты.
 * Правило: код Zippy не вызывает классы модуля напрямую — только через точки расширения (App\SQS\Hooks), иначе выключенный
 * модуль ронял бы программу (штатный автозагрузчик завершает работу, если файла класса нет).
 * Выключить модуль — удалить папку или переименовать с точкой в начале (.имя): его пункты меню выключаются (disabled = 1).
 * Загрузка — из init.php на каждый запрос (и в консольных скриптах): обход папок; база читается один раз за сессию и при
 * смене набора модулей (пункты меню, схемы); metadata и таблицы меняются только при смене. Так же применяются таблицы
 * платформы (app/sqs/schema.sql): копия старой версии после восстановления и обновление программы не требуют ручного шага;
 * смена версии программы (App\SQS\Release) отмечается здесь же.
 * Подробно — docs/MODULES.md.
 */
class Modules
{
    public const DIR = 'app/sqs/modules';
    public const PLATFORM_SCHEMA = 'app/sqs/schema.sql';   // таблицы платформы — досоздаются так же, как таблицы модулей
    public const SCHEMA_ERROR = 'sqs_schema_error';         // keyval: схемы, которые не применились, — [файл => ошибка базы]

    private static ?array $modules = null;   // имя => ['dir' => путь, 'def' => массив module.php]
    private static ?array $dirs = null;      // имя => путь: включенные модули, без чтения module.php

    /**
     * Папки включенных модулей — для переводчика и консольных скриптов, которым не нужен module.php
     */
    public static function dirs(): array {
        if (self::$dirs === null) {
            self::$dirs = [];
            foreach (glob(_ROOT . self::DIR . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $name = basename($dir);
                if ($name[0] != '.' && is_file($dir . '/module.php')) {
                    self::$dirs[$name] = $dir;
                }
            }
            ksort(self::$dirs);
        }
        return self::$dirs;
    }

    public static function load(): void {
        if (self::$modules !== null) {
            return;
        }
        self::$modules = [];
        foreach (self::dirs() as $name => $dir) {
            $def = require $dir . '/module.php';
            if (is_array($def)) {
                self::$modules[$name] = ['dir' => $dir, 'def' => $def];
            }
        }
        if (self::$modules) {
            // раньше штатного app_autoload: тот завершает работу, если файла класса нет в app/
            spl_autoload_register([self::class, 'autoload'], true, true);
        }
        self::sync();
        foreach (self::$modules as $m) {
            if (is_callable($m['def']['register'] ?? null)) {
                ($m['def']['register'])();
            }
        }
    }

    public static function names(): array {
        return array_keys(self::$modules ?? []);
    }

    public static function dir(string $name): ?string {
        return self::dirs()[$name] ?? null;
    }

    // App\Pages\Report\Hello → <модуль>/pages/report/hello.php; App\SQS\Units → <модуль>/units.php
    public static function autoload(string $class): void {
        if (strpos($class, 'App\\Pages\\') === 0) {
            $rel = 'pages/' . strtolower(str_replace('\\', '/', substr($class, strlen('App\\Pages\\')))) . '.php';
        } elseif (strpos($class, 'App\\SQS\\') === 0 && substr_count($class, '\\') == 2) {
            $rel = strtolower(substr($class, strlen('App\\SQS\\'))) . '.php';
        } else {
            return;
        }
        foreach (self::$modules ?? [] as $m) {
            if (is_file($m['dir'] . '/' . $rel)) {
                require_once $m['dir'] . '/' . $rel;
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
        return self::file('templates/' . strtolower(substr($class, 4)) . '.html');
    }

    /**
     * Печатная форма из модуля (полный путь) или null — для App\Report: 'report/issueact.tpl'
     */
    public static function printform(string $template): ?string {
        return self::file('templates/printforms/' . ltrim($template, '/'));
    }

    private static function file(string $rel): ?string {
        if (strpos($rel, '..') !== false) {
            return null;
        }
        foreach (self::$modules ?? [] as $m) {
            if (is_file($m['dir'] . '/' . $rel)) {
                return $m['dir'] . '/' . $rel;
            }
        }
        return null;
    }

    /**
     * Словарь модулей для языка: ключ => текст (App\Translator домешивает его к lang/<язык>.json)
     */
    public static function dictionary(string $lang): array {
        $dict = [];
        foreach (self::dirs() as $dir) {
            $f = $dir . '/lang/' . $lang . '.json';
            $data = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
            if (is_array($data)) {
                $dict += $data;
            }
        }
        return $dict;
    }

    /**
     * Таблицы платформы и модулей, пункты меню модулей. В этой сессии набор уже сверен с базой — обычный запрос
     * обходится без обращения к ней.
     */
    private static function sync(): void {
        $items = self::menuItems();
        $schemas = self::schemas();
        $fp = md5(implode(',', array_keys($items)) . '|' . json_encode($schemas) . '|' . Release::version());
        if (session_status() == PHP_SESSION_ACTIVE) {
            if (($_SESSION['sqs_modules_fp'] ?? null) === $fp) {
                return;
            }
            // меню кэшируется в сессии (App\Pages\Base): при смене пунктов модулей кэш этой сессии сбрасывается
            \App\Session::getSession()->menu = [];
            $_SESSION['sqs_modules_fp'] = $fp;
        }
        foreach ($schemas as $key => [$file, $hash]) {
            self::applySchema($key, $file, $hash);
        }
        self::syncMenu($items);
        Release::note();   // смена версии программы — в историю обновлений и журнал
    }

    /**
     * Схемы заново после замены базы (восстановление из копии): отметки о примененных схемах приходят из копии и могут
     * не соответствовать ее таблицам, поэтому сбрасываются, и все схемы выполняются сразу.
     */
    public static function reapply(): void {
        \ZDB\DB::getConnect()->Execute("DELETE FROM keyval WHERE keyd = 'sqs_schema' OR keyd LIKE 'sqs\\_schema\\_%'");
        unset($_SESSION['sqs_modules_fp']);
        if (self::$modules !== null) {
            self::sync();
        }
    }

    // [ключ в keyval => [файл, md5]]: сначала таблицы платформы (вход, журнал правок, названия из 1С), потом модулей
    private static function schemas(): array {
        $schemas = [];
        if (is_file(_ROOT . self::PLATFORM_SCHEMA)) {
            $schemas['sqs_schema'] = [_ROOT . self::PLATFORM_SCHEMA, md5_file(_ROOT . self::PLATFORM_SCHEMA)];
        }
        foreach (self::$modules as $name => $m) {
            if (is_file($m['dir'] . '/schema.sql')) {
                $schemas['sqs_schema_' . $name] = [$m['dir'] . '/schema.sql', md5_file($m['dir'] . '/schema.sql')];
            }
        }
        return $schemas;
    }

    // таблицы: schema.sql выполняется, когда файл изменился (CREATE TABLE IF NOT EXISTS — повторно безопасно)
    private static function applySchema(string $key, string $file, string $hash): void {
        if (\App\Helper::getKeyVal($key) === $hash) {
            return;
        }
        global $logger;
        $label = $key == 'sqs_schema' ? 'platform' : 'module ' . substr($key, strlen('sqs_schema_'));
        $conn = \ZDB\DB::getConnect();
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($file));
        try {
            foreach (preg_split('/;\s*(\n|$)/', $sql) as $stmt) {
                if (trim($stmt) !== '') {
                    $conn->Execute($stmt);
                }
            }
            \App\Helper::setKeyVal($key, $hash);
            self::noteSchema($file, null);
            // из консоли не пишется: скрипт от root создал бы журнал дня, в который веб-сервер потом не может писать
            if ($logger && PHP_SAPI !== 'cli') {
                $logger->info("SquirrelStock schema {$label} applied");
            }
        } catch (\Throwable $e) {
            // нет права CREATE у пользователя базы и т. п.: таблиц не будет — в журнал; попытка повторится в новой сессии
            if ($logger) {
                $logger->error("SquirrelStock schema {$label}: " . $e->getMessage());
            }
            // текст исключения — общий «ошибка базы», причина (нет права CREATE) — в сообщении драйвера
            self::noteSchema($file, (string)($conn->ErrorMsg() ?: $e->getMessage()));
        }
    }

    // ошибка схемы — для администратора (главная, «Обновление программы», cli/modules.php); null — схема применилась
    private static function noteSchema(string $file, ?string $error): void {
        try {
            $errors = self::schemaErrors();
            $rel = str_replace('\\', '/', substr($file, strlen(_ROOT)));
            if ($error === null && !isset($errors[$rel])) {
                return;
            }
            if ($error === null) {
                unset($errors[$rel]);
            } else {
                $errors[$rel] = mb_substr($error, 0, 300);
            }
            \App\Helper::setKeyVal(self::SCHEMA_ERROR, $errors ? json_encode($errors, JSON_UNESCAPED_UNICODE) : '');
        } catch (\Throwable $e) {
            // база не принимает и запись — ошибка схемы уже в журнале
        }
    }

    /**
     * Схемы, которые не применились: [файл схемы относительно корня => ошибка базы]. Чаще всего у пользователя базы нет
     * права CREATE или ALTER (хостинг) — тогда нет таблиц модуля и не расширены цены партии.
     */
    public static function schemaErrors(): array {
        $errors = json_decode((string)\App\Helper::getKeyVal(self::SCHEMA_ERROR), true);
        return is_array($errors) ? $errors : [];
    }

    // текст для администратора; '' — ошибок нет
    public static function schemaErrorText(): string {
        $errors = self::schemaErrors();
        if (!$errors) {
            return '';
        }
        return __('sqs.schema.error', ['files' => implode(', ', array_keys($errors)), 'error' => implode('; ', array_unique($errors))]);
    }

    // блок главной страницы (событие mainBlocks): администратору — схемы, которые не применились
    public static function mainBlock(): string {
        if (\App\System::getUser()->rolename != 'admins' || ($text = self::schemaErrorText()) === '') {
            return '';
        }
        return '<div class="alert alert-danger" id="sqs-schema-error">' . htmlspecialchars($text) . '</div>';
    }

    // пункты меню модулей: [ключ «тип|meta_name» => [тип, meta_name, описание, группа]] на языке установки
    private static function menuItems(): array {
        global $_config;
        $lang = $_config['common']['lang'] ?? 'uk';
        $pick = fn($v) => is_array($v) ? (string)($v[$lang] ?? $v['uk'] ?? reset($v)) : (string)$v;
        $items = [];
        foreach (self::$modules as $m) {
            foreach ($m['def']['menu'] ?? [] as $it) {
                [$type, $name, $desc, $group] = array_pad($it, 4, '');
                $items[intval($type) . '|' . $name] = [intval($type), (string)$name, $pick($desc), $pick($group)];
            }
        }
        ksort($items);
        return $items;
    }

    /**
     * Пункты меню модулей в metadata. Новый пункт добавляется; пункт исчезнувшего модуля выключается и запоминается —
     * при возвращении модуля включается снова только он. Пункт, который уже был в metadata (перенос в модуль существующей
     * страницы), не трогается: его могли выключить вручную или профилем.
     */
    private static function syncMenu(array $items): void {
        $prev = json_decode((string)\App\Helper::getKeyVal('sqs_modules_menu'), true);
        $prev = is_array($prev) ? $prev : [];
        if (array_keys($prev) === array_keys($items)) {
            return;
        }
        $off = json_decode((string)\App\Helper::getKeyVal('sqs_modules_off'), true);
        $off = is_array($off) ? $off : [];
        $conn = \ZDB\DB::getConnect();
        foreach (array_diff_key($items, $prev) as $key => [$type, $name, $desc, $group]) {
            $where = "meta_type = {$type} AND meta_name = " . $conn->qstr($name);
            if ($conn->GetOne("SELECT COUNT(*) FROM metadata WHERE {$where}") > 0) {
                if (isset($off[$key])) {
                    $conn->Execute("UPDATE metadata SET disabled = 0 WHERE {$where}");
                }
            } else {
                $conn->Execute("INSERT INTO metadata (meta_type, description, meta_name, menugroup, disabled) VALUES ({$type}, "
                    . $conn->qstr($desc) . ', ' . $conn->qstr($name) . ', ' . $conn->qstr($group) . ', 0)');
            }
            unset($off[$key]);
        }
        foreach (array_diff_key($prev, $items) as $key => [$type, $name]) {
            $where = "meta_type = " . intval($type) . " AND meta_name = " . $conn->qstr($name);
            if (intval($conn->GetOne("SELECT disabled FROM metadata WHERE {$where}")) == 0) {
                $conn->Execute("UPDATE metadata SET disabled = 1 WHERE {$where}");
                $off[$key] = 1;
            }
        }
        \App\Helper::setKeyVal('sqs_modules_menu', json_encode($items, JSON_UNESCAPED_UNICODE));
        \App\Helper::setKeyVal('sqs_modules_off', json_encode($off));
    }
}
