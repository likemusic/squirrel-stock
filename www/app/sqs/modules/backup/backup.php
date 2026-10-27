<?php

namespace App\SQS;

/**
 * SquirrelStock: резервная копия одной кнопкой — zip с дампом базы (database.sql) и вложениями (upload/).
 * Дамп пишется на PHP, без mysqldump (на Windows-установке путь к нему неизвестен): таблицы со структурой и данными,
 * затем представления в порядке зависимостей, затем триггеры в блоке DELIMITER — файл понимает и консольный mysql.
 */
class Backup
{
    public const SQL_NAME = 'database.sql';

    // таблицы, которые копия не переносит: журнал самих копий описывает эту установку, а не данные; неудачные попытки
    // входа (в логине бывает пароль, набранный не в то поле) и токены «Запомнить» в копии не нужны. В дампе — только
    // CREATE TABLE IF NOT EXISTS (чтобы дамп годился для новой базы), при восстановлении они не удаляются
    public const LOCAL_TABLES = ['sqs_backuplog', 'sqs_loginfail', 'sqs_remember'];

    private \mysqli $db;

    public function __construct() {
        $this->connect();
    }

    private function connect(): void {
        global $_config;
        $c = $_config['db'];
        [$host, $port] = array_pad(explode(':', $c['host']), 2, 3306);
        $this->db = new \mysqli($host, $c['user'], $c['pass'], $c['name'], (int)$port);
        $this->db->set_charset('utf8');
    }

    /**
     * Создать архив; возвращает путь к временному zip-файлу.
     * С паролем — каждый файл архива зашифрован AES-256 (открывается 7-Zip, WinRAR и этой же страницей восстановления).
     */
    public function create(string $uploadDir, string $password = ''): string {
        $sql = TempFiles::file();
        try {
            return $this->createZip($sql, $uploadDir, $password);
        } finally {
            @unlink($sql);   // дамп с персональными данными не остается во временной папке и при ошибке
        }
    }

    private function createZip(string $sql, string $uploadDir, string $password): string {
        $this->dump($sql);
        // без расширения .zip: в запасной папке tmp/ внутри сайта nginx хостинга отдал бы .zip сам, мимо .htaccess
        $zipFile = TempFiles::file();
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile($sql, self::SQL_NAME);
        if (is_dir($uploadDir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploadDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $zip->addFile($f->getPathname(), 'upload/' . str_replace('\\', '/', substr($f->getPathname(), strlen(rtrim($uploadDir, '/\\')) + 1)));
            }
        }
        if ($password !== '') {
            // нет шифрования в сборке zip — ошибка, а не незашифрованная копия в облако или сетевую папку
            $aes = defined('ZipArchive::EM_AES_256') && (!method_exists($zip, 'isEncryptionMethodSupported') || \ZipArchive::isEncryptionMethodSupported(\ZipArchive::EM_AES_256));
            for ($i = 0; $aes && $i < $zip->numFiles; $i++) {
                $aes = $zip->setEncryptionIndex($i, \ZipArchive::EM_AES_256, $password);
            }
            if (!$aes) {
                $zip->close();
                @unlink($zipFile);
                throw new BackupException('service.backup.no_encryption');
            }
        }
        if (!$zip->close()) {
            @unlink($zipFile);
            throw new \Exception('Не удалось записать архив ' . $zipFile);
        }

        return $zipFile;
    }

    public function dump(string $file): void {
        $out = fopen($file, 'w');
        if ($out === false) {
            throw new \Exception('Не удалось открыть для записи ' . $file);
        }
        try {
            $this->dumpTo($out);
        } finally {
            $closed = fclose($out);
        }
        if (!$closed) {
            throw new \Exception('Не удалось записать ' . $file . ': нет места на диске?');
        }
    }

    // кончилось место на диске — ошибка, а не обрезанный дамп без предупреждения (копия на флешку и в облако,
    // страховочная копия перед восстановлением)
    private function put($out, string $s): void {
        if (fwrite($out, $s) !== strlen($s)) {
            throw new \Exception('Не удалось записать дамп базы: нет места на диске?');
        }
    }

    private function dumpTo($out): void {
        // версия программы — в первой строке: восстановление копии новой версии на старой предупреждает (newerThan)
        $this->put($out, "-- SquirrelStock backup " . date('Y-m-d H:i:s') . ' version ' . Release::version() . "\nSET NAMES utf8;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        $tables = [];
        $views = [];
        foreach ($this->query('SHOW FULL TABLES')->fetch_all() as [$name, $type]) {
            if ($type == 'VIEW') {
                $views[] = $name;
            } else {
                $tables[] = $name;
            }
        }
        foreach ($tables as $t) {
            $create = $this->query("SHOW CREATE TABLE `{$t}`")->fetch_row()[1];
            if (in_array($t, self::LOCAL_TABLES, true)) {
                $this->put($out, preg_replace('/^CREATE TABLE/', 'CREATE TABLE IF NOT EXISTS', $create) . ";\n");
                continue;
            }
            $this->put($out, "DROP TABLE IF EXISTS `{$t}`;\n{$create};\n");
            $res = $this->query("SELECT * FROM `{$t}`", MYSQLI_USE_RESULT);
            $batch = [];
            while ($row = $res->fetch_row()) {
                $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : "'" . $this->db->real_escape_string($v) . "'", $row)) . ')';
                if (count($batch) == 200) {
                    $this->put($out, "INSERT INTO `{$t}` VALUES " . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            $res->free();
            if ($batch) {
                $this->put($out, "INSERT INTO `{$t}` VALUES " . implode(",\n", $batch) . ";\n");
            }
            $this->put($out, "\n");
        }
        // представления: сначала те, что не ссылаются на еще не созданные представления
        $defs = [];
        foreach ($views as $v) {
            $create = $this->query("SHOW CREATE VIEW `{$v}`")->fetch_row()[1];
            $defs[$v] = preg_replace('/^CREATE .*? VIEW/s', 'CREATE VIEW', $create);   // без DEFINER: восстановит любой пользователь
        }
        $done = [];
        while (count($done) < count($defs)) {
            $progress = false;
            foreach ($defs as $v => $create) {
                if (isset($done[$v])) {
                    continue;
                }
                $deps = array_filter(array_keys($defs), fn($o) => $o != $v && !isset($done[$o]) && str_contains($create, "`{$o}`"));
                if (!$deps) {
                    $this->put($out, "DROP VIEW IF EXISTS `{$v}`;\n{$create};\n");
                    $done[$v] = true;
                    $progress = true;
                }
            }
            if (!$progress) {
                throw new \Exception('Циклическая зависимость представлений');
            }
        }
        $triggers = $this->query('SHOW TRIGGERS')->fetch_all(MYSQLI_ASSOC);
        if ($triggers) {
            $this->put($out, "\nDELIMITER ;;\n");
            foreach ($triggers as $tr) {
                $create = $this->query("SHOW CREATE TRIGGER `{$tr['Trigger']}`")->fetch_assoc()['SQL Original Statement'];
                $create = preg_replace('/^CREATE .*?TRIGGER/s', 'CREATE TRIGGER', $create);
                $this->put($out, "DROP TRIGGER IF EXISTS `{$tr['Trigger']}`;;\n{$create};;\n");
            }
            $this->put($out, "DELIMITER ;\n");
        }
        $this->put($out, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
    }

    // запрос дампа: отказ в правах (у пользователя базы нет SHOW VIEW и т. п.) — понятная ошибка вместо падения на false
    private function query(string $sql, int $mode = MYSQLI_STORE_RESULT): \mysqli_result {
        try {
            $res = $this->db->query($sql, $mode);
        } catch (\mysqli_sql_exception $e) {
            $res = false;
        }
        if (!$res instanceof \mysqli_result) {
            throw new BackupException('service.backup.no_rights_dump', ['error' => $this->db->error]);
        }
        return $res;
    }

    /**
     * Права на структуру базы — до удаления текущих таблиц: без CREATE (CREATE VIEW, TRIGGER) восстановление с правом DROP
     * удалило бы все таблицы и не создало их заново. Проверка — пробной таблицей, представлением и триггером: список прав
     * пользователя (SHOW GRANTS) на хостинге бывает выдан через роль или шаблон имени базы и надежно не разбирается.
     */
    private function checkRights(): void {
        $probe = 'sqs_restore_probe';
        $steps = [
            "DROP TABLE IF EXISTS `{$probe}`",
            "CREATE TABLE `{$probe}` (id int)",
            "ALTER TABLE `{$probe}` ADD COLUMN x int",
            "CREATE VIEW `{$probe}_v` AS SELECT id FROM `{$probe}`",
            "CREATE TRIGGER `{$probe}_tr` BEFORE INSERT ON `{$probe}` FOR EACH ROW SET NEW.x = 0",
            "DROP TRIGGER `{$probe}_tr`",
            "DROP VIEW `{$probe}_v`",
            "DROP TABLE `{$probe}`",
        ];
        foreach ($steps as $sql) {
            try {
                $ok = $this->db->query($sql);
            } catch (\mysqli_sql_exception $e) {
                $ok = false;
            }
            if (!$ok) {
                $error = $this->db->error;
                foreach (["DROP TRIGGER IF EXISTS `{$probe}_tr`", "DROP VIEW IF EXISTS `{$probe}_v`", "DROP TABLE IF EXISTS `{$probe}`"] as $undo) {
                    try {
                        $this->db->query($undo);
                    } catch (\mysqli_sql_exception $e) {
                    }
                }
                throw new BackupException('service.backup.no_rights_restore', ['error' => $error]);
            }
        }
    }

    // файлы, которые восстановление записывает в upload/: картинки и документы. Скрипты, .htaccess и прочие файлы
    // настройки сервера из копии не пишутся — подмененная копия не должна выполнить код на сервере
    public const RESTORE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'rtf', 'zip'];
    public const RESTORE_MAX_FILES = 50000;

    /** Восстановить ли файл копии с этим именем (upload/…) */
    public static function restorable(string $name): bool {
        if (!str_starts_with($name, 'upload/') || str_ends_with($name, '/') || str_contains($name, '..') || str_contains($name, '\\')) {
            return false;
        }
        foreach (explode('/', substr($name, 7)) as $part) {
            // пустая часть, скрытый файл или папка (.htaccess), «x.php.png» — нет
            if ($part === '' || $part[0] === '.' || preg_match('/\.(php\d*|pht|phtml|phar|cgi|pl|py|sh|shtml)(\.|$)/i', $part)) {
                return false;
            }
        }
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::RESTORE_EXT, true);
    }

    /**
     * Восстановить из zip (database.sql + upload/) или из .sql; возвращает [число выполненных операторов, число пропущенных файлов].
     * $password — для зашифрованного архива (копия в облаке): тогда все файлы архива должны быть зашифрованы, иначе это
     * не та копия (ZipArchive читает незашифрованные файлы и без пароля). Проверки архива — до удаления текущих таблиц.
     */
    public function restore(string $file, string $uploadDir, string $password = '', bool $newerOk = false): array {
        $sqlFile = $file;
        $tmp = null;
        $zip = new \ZipArchive();
        $isZip = $zip->open($file) === true;
        try {
            if ($isZip) {
                if ($zip->locateName(self::SQL_NAME) === false) {
                    throw new \Exception('В архиве нет ' . self::SQL_NAME);
                }
                $total = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $st = $zip->statIndex($i);
                    if ($password !== '' && ($st['encryption_method'] ?? 0) == \ZipArchive::EM_NONE && !str_ends_with($st['name'], '/')) {
                        throw new BackupException('service.backup.not_encrypted');
                    }
                    $total += $st['size'];
                }
                // распакованный размер — по заголовкам архива: не больше свободного места (дамп во временную папку, файлы в upload/)
                $free = min(@disk_free_space(TempFiles::dir()) ?: PHP_INT_MAX, is_dir($uploadDir) ? (@disk_free_space($uploadDir) ?: PHP_INT_MAX) : PHP_INT_MAX);
                if ($zip->numFiles > self::RESTORE_MAX_FILES || $total > $free) {
                    throw new BackupException('service.backup.too_big', ['files' => $zip->numFiles, 'mb' => round($total / 1048576)]);
                }
                if ($password !== '') {
                    $zip->setPassword($password);
                }
                // дамп — потоком во временный файл: целиком в памяти большая база не помещается
                $tmp = TempFiles::file();
                $in = $zip->getStream(self::SQL_NAME);
                $out = fopen($tmp, 'w');
                $copied = $in ? stream_copy_to_stream($in, $out) : false;
                fclose($out);
                $in && fclose($in);
                if ($copied === false || $copied != $zip->statName(self::SQL_NAME)['size']) {
                    throw new \Exception(__('service.backup.bad_password'));
                }
                $sqlFile = $tmp;
            }
            // не копия (другой файл, пустой или нечитаемый) — до удаления текущих таблиц: иначе база осталась бы пустой
            $head = @file_get_contents($sqlFile, false, null, 0, 65536);
            if (!is_string($head) || !preg_match('/^\s*(CREATE TABLE|INSERT INTO|DROP TABLE)\b/mi', $head)) {
                throw new BackupException('service.backup.not_backup');
            }
            // копия более новой версии программы: старый код может не знать ее таблиц и столбцов — только после
            // второго подтверждения ($newerOk), без запрета (откат на старую версию бывает нужен)
            $copyVersion = self::copyVersion($head);
            if (!$newerOk && $copyVersion !== null && self::newerThan($copyVersion, Release::version())) {
                throw new BackupException('service.backup.newer', ['copy' => $copyVersion, 'installed' => Release::version()]);
            }
            // обрезанный или недокачанный дамп (кончается посреди оператора) и дамп без таблиц — до удаления текущих таблиц
            self::check($sqlFile);
            $this->checkRights();
            $n = $this->replaceDb($sqlFile);
            $skipped = 0;
            if ($isZip) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (!str_starts_with($name, 'upload/') || str_ends_with($name, '/')) {
                        continue;
                    }
                    if (!self::restorable($name)) {
                        $skipped++;
                        continue;
                    }
                    $target = rtrim($uploadDir, '/\\') . '/' . substr($name, 7);
                    @mkdir(dirname($target), 0777, true);
                    $in = $zip->getStream($name);
                    $out = fopen($target, 'w');
                    stream_copy_to_stream($in, $out);
                    fclose($out);
                    fclose($in);
                }
            }
        } finally {
            if ($tmp !== null) {
                @unlink($tmp);
            }
            if ($isZip) {
                $zip->close();
            }
        }
        \App\SQS\Modules::reapply();   // копия могла быть сделана до появления таблиц платформы или модулей — досоздать

        return [$n, $skipped];
    }

    // версия программы из первой строки дампа; null — копия до 18.10.2026 или дамп не этой программы
    public static function copyVersion(string $head): ?string {
        return preg_match('/^-- SquirrelStock backup \S+ \S+ version ([\w.+-]{1,64})\s/', $head, $m) ? $m[1] : null;
    }

    // версия пакета «2026.10.18-<коммит>»: новее — по дате; dev (стенд, git-копия без версии) и одна дата не сравниваются
    public static function newerThan(string $copy, string $installed): bool {
        $date = fn(string $v) => preg_match('/^(\d{4})\.(\d{2})\.(\d{2})(?!\d)/', $v, $m) ? $m[1] . $m[2] . $m[3] : null;
        return $date($copy) !== null && $date($installed) !== null && $date($copy) > $date($installed);
    }

    /**
     * Заменить базу дампом. До удаления текущих таблиц — страховочный дамп текущей базы: ошибка посреди дампа копии
     * (ошибка SQL, оборванное соединение) иначе оставляла базу без части таблиц, и сайт показывал «Ошибка базы данных».
     * После ошибки база возвращается из страховочного дампа. Транзакция не помогает (CREATE и DROP в MySQL ее
     * завершают), временная база — нет права CREATE DATABASE на хостинге.
     * Обрыв по пределу времени или памяти PHP (fatal, finally не выполняется) — сообщение App\SQS\Limits, страховочный
     * дамп остается во временной папке и удаляется через сутки (TempFiles::purge).
     */
    private function replaceDb(string $sqlFile): int {
        $this->checkSpace();
        \App\SQS\Limits::raise('backup restore: safety dump', 'sqs.limits.restore_unchanged');
        $safety = TempFiles::file();
        try {
            $this->dump($safety);
        } catch (\Throwable $e) {
            @unlink($safety);
            throw new BackupException('service.backup.safety_failed', ['error' => $e->getMessage()]);
        }
        // свой отсчет времени у восстановления: страховочный дамп его не съедает
        \App\SQS\Limits::raise('backup restore', 'sqs.limits.restore_kept', ['file' => $safety]);
        try {
            $this->dropAll();
            $n = $this->execute($sqlFile);
        } catch (\Throwable $e) {
            $this->rollback($safety, $e);
        }
        @unlink($safety);   // в нем персональные данные

        return $n;
    }

    // вернуть базу из страховочного дампа; исключение — всегда: с исходной ошибкой и тем, удался ли возврат
    private function rollback(string $safety, \Throwable $error): never {
        global $logger;
        \App\SQS\Limits::raise('backup restore: rollback', 'sqs.limits.restore_kept', ['file' => $safety]);
        try {
            try {
                $alive = $this->db->query('SELECT 1') !== false;
            } catch (\mysqli_sql_exception) {
                $alive = false;
            }
            if (!$alive) {
                $this->connect();   // соединение оборвалось посреди дампа (max_allowed_packet, перезапуск сервера)
            }
            $this->dropAll();
            $this->execute($safety);
        } catch (\Throwable $e) {
            // страховочный дамп не удаляется: это единственная копия базы до восстановления (удалится через сутки)
            $logger?->error('SquirrelStock restore: rollback failed: ' . $e->getMessage() . '; safety dump kept: ' . $safety);
            throw new BackupException('service.backup.rollback_failed', ['error' => $error->getMessage(), 'rollback' => $e->getMessage(), 'file' => $safety]);
        }
        @unlink($safety);
        throw new BackupException('service.backup.rolled_back', ['error' => $error->getMessage()]);
    }

    // текущие таблицы и представления удаляются (кроме LOCAL_TABLES): база становится такой, какой была в дампе
    private function dropAll(): void {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->db->query('SHOW FULL TABLES')->fetch_all() as [$name, $type]) {
            if (in_array($name, self::LOCAL_TABLES, true)) {
                continue;
            }
            $this->db->query(($type == 'VIEW' ? 'DROP VIEW' : 'DROP TABLE') . " IF EXISTS `{$name}`");
        }
    }

    // место под страховочный дамп — вдвое больше данных таблиц (data_length): дамп бывает и меньше, и больше их
    // (на стенде 12,7 МБ при 10,5 МБ), а data_length — статистика InnoDB, она отстает от данных
    private function checkSpace(): void {
        $need = 2 * (int)$this->db->query('SELECT COALESCE(SUM(data_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetch_row()[0];
        $free = @disk_free_space(TempFiles::dir());
        if ($free !== false && $need > $free) {
            throw new BackupException('service.backup.no_space_safety', ['mb' => round($need / 1048576), 'free' => round($free / 1048576)]);
        }
    }

    /**
     * Дамп целиком «вхолостую», тем же разбором, что и выполнение, без запросов к базе: файл обрывается посреди
     * оператора (недокачан, обрезан) — ошибка; ни одного CREATE TABLE — не копия
     */
    public static function check(string $file): void {
        $tables = 0;
        $last = '';
        foreach (self::statements($file) as $sql) {
            if (preg_match('/^\s*CREATE\s+TABLE\b/i', $sql)) {
                $tables++;
            }
            $last = $sql;
        }
        if ($tables == 0) {
            throw new BackupException('service.backup.not_backup');
        }
        // копия этой программы всегда кончается этим оператором: обрыв сразу после «;» внутри строки данных разбор по
        // строкам принимает за конец оператора
        $head = (string)@file_get_contents($file, false, null, 0, 64);
        if (str_starts_with($head, '-- SquirrelStock backup ') && !preg_match('/^\s*SET FOREIGN_KEY_CHECKS\s*=\s*1\s*$/i', $last)) {
            throw new BackupException('service.backup.truncated');
        }
    }

    /**
     * Выполнить SQL-файл по операторам
     */
    private function execute(string $file): int {
        $n = 0;
        foreach (self::statements($file) as $sql) {
            if (!$this->db->query($sql)) {
                throw new \Exception('Ошибка восстановления: ' . $this->db->error . ' — ' . mb_substr($sql, 0, 200));
            }
            $n++;
        }

        return $n;
    }

    /**
     * Операторы SQL-файла по одному, с учетом строк DELIMITER (как консольный mysql). Файл кончается недописанным
     * оператором — BackupException truncated
     */
    private static function statements(string $file): \Generator {
        $in = fopen($file, 'r');
        if ($in === false) {
            throw new BackupException('service.backup.not_backup');
        }
        $delimiter = ';';
        $stmt = '';
        while (($line = fgets($in)) !== false) {
            $trim = trim($line);
            if ($stmt === '' && ($trim === '' || str_starts_with($trim, '-- '))) {
                continue;
            }
            if ($stmt === '' && preg_match('/^DELIMITER\s+(\S+)$/i', $trim, $m)) {
                $delimiter = $m[1];
                continue;
            }
            $stmt .= $line;
            $end = rtrim($line);
            // хвостовой комментарий после разделителя («…;   -- текст», «…; # текст»; так писала сборка пакета, так пишут и
            // вручную): оператор закончен. Только без кавычек в комментарии — «--» внутри строки данных не комментарий
            if (!str_ends_with($end, $delimiter) && preg_match('/^(.*' . preg_quote($delimiter, '/') . ')\s+(?:--\s|#)[^\'"`]*$/s', $end, $m)) {
                $stmt = substr($stmt, 0, -strlen($line)) . $m[1];
                $end = $m[1];
            }
            if (str_ends_with($end, $delimiter)) {
                yield substr(rtrim($stmt), 0, -strlen($delimiter));
                $stmt = '';
            }
        }
        fclose($in);
        if (trim($stmt) !== '') {
            throw new BackupException('service.backup.truncated');
        }
    }
}
