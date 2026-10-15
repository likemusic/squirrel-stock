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
        $this->dump($sql);
        $zipFile = tempnam(sys_get_temp_dir(), 'sqs') . '.zip';
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
            throw new \Exception('Не удалось записать архив ' . $zipFile);
        }
        unlink($sql);

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

    /**
     * Восстановить из zip (database.sql + upload/) или из .sql; возвращает число выполненных операторов.
     * $password — для зашифрованного архива (копия в облаке).
     */
    public function restore(string $file, string $uploadDir, string $password = ''): int {
        $sqlFile = $file;
        $zip = new \ZipArchive();
        $isZip = $zip->open($file) === true;
        if ($isZip) {
            if ($password !== '') {
                $zip->setPassword($password);
            }
            if ($zip->locateName(self::SQL_NAME) === false) {
                throw new \Exception('В архиве нет ' . self::SQL_NAME);
            }
            $sql = $zip->getFromName(self::SQL_NAME);
            if ($sql === false) {
                throw new \Exception(__('service.backup.bad_password'));
            }
            $sqlFile = tempnam(sys_get_temp_dir(), 'sqs');
            file_put_contents($sqlFile, $sql);
        }
        // текущие таблицы и представления удаляются: база становится такой, какой была в копии
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->db->query('SHOW FULL TABLES')->fetch_all() as [$name, $type]) {
            $this->db->query(($type == 'VIEW' ? 'DROP VIEW' : 'DROP TABLE') . " IF EXISTS `{$name}`");
        }
        $n = $this->execute($sqlFile);
        if ($isZip) {
            unlink($sqlFile);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_starts_with($name, 'upload/') && !str_ends_with($name, '/') && !str_contains($name, '..')) {
                    $target = rtrim($uploadDir, '/\\') . '/' . substr($name, 7);
                    @mkdir(dirname($target), 0777, true);
                    file_put_contents($target, $zip->getFromIndex($i));
                }
            }
            $zip->close();
        }

        return $n;
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
