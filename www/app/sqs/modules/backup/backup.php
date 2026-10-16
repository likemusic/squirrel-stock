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

    // таблицы, которые копия не переносит: журнал самих копий описывает эту установку, а не данные. В дампе — только
    // CREATE TABLE IF NOT EXISTS (чтобы дамп годился для новой базы), при восстановлении они не удаляются
    public const LOCAL_TABLES = ['sqs_backuplog'];

    private \mysqli $db;

    public function __construct() {
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
        $sql = tempnam(sys_get_temp_dir(), 'sqs');
        try {
            return $this->createZip($sql, $uploadDir, $password);
        } finally {
            @unlink($sql);   // дамп с персональными данными не остается во временной папке и при ошибке
        }
    }

    private function createZip(string $sql, string $uploadDir, string $password): string {
        $this->dump($sql);
        $base = tempnam(sys_get_temp_dir(), 'sqs');
        $zipFile = $base . '.zip';
        @unlink($base);
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
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zip->setEncryptionIndex($i, \ZipArchive::EM_AES_256, $password);
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
        fwrite($out, "-- SquirrelStock backup " . date('Y-m-d H:i:s') . "\nSET NAMES utf8;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        $tables = [];
        $views = [];
        foreach ($this->db->query('SHOW FULL TABLES')->fetch_all() as [$name, $type]) {
            if ($type == 'VIEW') {
                $views[] = $name;
            } else {
                $tables[] = $name;
            }
        }
        foreach ($tables as $t) {
            $create = $this->db->query("SHOW CREATE TABLE `{$t}`")->fetch_row()[1];
            if (in_array($t, self::LOCAL_TABLES, true)) {
                fwrite($out, preg_replace('/^CREATE TABLE/', 'CREATE TABLE IF NOT EXISTS', $create) . ";\n");
                continue;
            }
            fwrite($out, "DROP TABLE IF EXISTS `{$t}`;\n{$create};\n");
            $res = $this->db->query("SELECT * FROM `{$t}`", MYSQLI_USE_RESULT);
            $batch = [];
            while ($row = $res->fetch_row()) {
                $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : "'" . $this->db->real_escape_string($v) . "'", $row)) . ')';
                if (count($batch) == 200) {
                    fwrite($out, "INSERT INTO `{$t}` VALUES " . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            $res->free();
            if ($batch) {
                fwrite($out, "INSERT INTO `{$t}` VALUES " . implode(",\n", $batch) . ";\n");
            }
            fwrite($out, "\n");
        }
        // представления: сначала те, что не ссылаются на еще не созданные представления
        $defs = [];
        foreach ($views as $v) {
            $create = $this->db->query("SHOW CREATE VIEW `{$v}`")->fetch_row()[1];
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
                    fwrite($out, "DROP VIEW IF EXISTS `{$v}`;\n{$create};\n");
                    $done[$v] = true;
                    $progress = true;
                }
            }
            if (!$progress) {
                throw new \Exception('Циклическая зависимость представлений');
            }
        }
        $triggers = $this->db->query('SHOW TRIGGERS')->fetch_all(MYSQLI_ASSOC);
        if ($triggers) {
            fwrite($out, "\nDELIMITER ;;\n");
            foreach ($triggers as $tr) {
                $create = $this->db->query("SHOW CREATE TRIGGER `{$tr['Trigger']}`")->fetch_assoc()['SQL Original Statement'];
                $create = preg_replace('/^CREATE .*?TRIGGER/s', 'CREATE TRIGGER', $create);
                fwrite($out, "DROP TRIGGER IF EXISTS `{$tr['Trigger']}`;;\n{$create};;\n");
            }
            fwrite($out, "DELIMITER ;\n");
        }
        fwrite($out, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($out);
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
    public function restore(string $file, string $uploadDir, string $password = ''): array {
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
                $free = min(@disk_free_space(sys_get_temp_dir()) ?: PHP_INT_MAX, is_dir($uploadDir) ? (@disk_free_space($uploadDir) ?: PHP_INT_MAX) : PHP_INT_MAX);
                if ($zip->numFiles > self::RESTORE_MAX_FILES || $total > $free) {
                    throw new BackupException('service.backup.too_big', ['files' => $zip->numFiles, 'mb' => round($total / 1048576)]);
                }
                if ($password !== '') {
                    $zip->setPassword($password);
                }
                // дамп — потоком во временный файл: целиком в памяти большая база не помещается
                $tmp = tempnam(sys_get_temp_dir(), 'sqs');
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
            // текущие таблицы и представления удаляются: база становится такой, какой была в копии
            $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($this->db->query('SHOW FULL TABLES')->fetch_all() as [$name, $type]) {
                if (in_array($name, self::LOCAL_TABLES, true)) {
                    continue;
                }
                $this->db->query(($type == 'VIEW' ? 'DROP VIEW' : 'DROP TABLE') . " IF EXISTS `{$name}`");
            }
            $n = $this->execute($sqlFile);
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

    /**
     * Выполнить SQL-файл по операторам с учетом строк DELIMITER (как консольный mysql)
     */
    private function execute(string $file): int {
        $in = fopen($file, 'r');
        $delimiter = ';';
        $stmt = '';
        $n = 0;
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
            if (str_ends_with(rtrim($line), $delimiter)) {
                $sql = substr(rtrim($stmt), 0, -strlen($delimiter));
                if (!$this->db->query($sql)) {
                    throw new \Exception('Ошибка восстановления: ' . $this->db->error . ' — ' . mb_substr($sql, 0, 200));
                }
                $n++;
                $stmt = '';
            }
        }
        fclose($in);

        return $n;
    }
}
