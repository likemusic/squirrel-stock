<?php

namespace App\SQS;

/**
 * SquirrelStock: автоматическая резервная копия в несколько мест — по расписанию (cli/backup.php из планировщика ОС).
 *  - local   — папка на этом компьютере (всегда);
 *  - network — папка на другом компьютере или сетевом хранилище (\\server\share, смонтированная папка);
 *  - cloud   — облака через rclone (remote настраивает установщик: Яндекс Диск, Google Drive, S3...), по одному в строке настройки;
 *              только зашифрованный архив (один на все облака), у каждого облака своя попытка, запись журнала и ротация.
 * В журнале облако — place = 'cloud', file = «remote/имя файла» (по нему статус каждого облака).
 * В каждом месте хранятся последние N копий, старые удаляются (только файлы с именем squirrelstock-ГГГГ-ММ-ДД-ЧЧММСС.zip).
 * Ошибка в одном месте не мешает остальным. Попытки — в таблице sqs_backuplog, настройки — keyval 'sqs_backup'.
 * На флешку — вручную, кнопкой на странице «Резервная копия» (copyTo).
 */
class BackupJob
{
    public const PLACES = ['local', 'network', 'cloud'];   // виды мест (облаков может быть несколько)
    public const STALE_HOURS = 48;   // нет успешной копии дольше — предупреждение
    private const NAME_RE = '/^squirrelstock-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/';

    public static function defaults(): array {
        return [
            'local'        => dirname(rtrim(_ROOT, '/\\')) . DIRECTORY_SEPARATOR . 'squirrelstock-backup',
            'network'      => '',
            'cloud'        => '',        // rclone remote:путь по одному в строке, например yandex:squirrelstock
            'rclone'       => 'rclone',  // путь к rclone (на Windows — C:\rclone\rclone.exe)
            'rclone_conf'  => '',        // файл настроек rclone, если не стандартный для пользователя планировщика
            'password'     => '',        // пароль шифрования копии в облаке
            'keep'         => 30,
        ];
    }

    public static function settings(): array {
        $saved = json_decode((string)\App\Helper::getKeyVal('sqs_backup'), true);
        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    public static function saveSettings(array $s): void {
        $s = array_intersect_key(array_merge(self::settings(), $s), self::defaults());
        $s['keep'] = max(1, intval($s['keep']));
        foreach (['local', 'network', 'rclone', 'rclone_conf'] as $k) {
            $s[$k] = trim((string)$s[$k]);
        }
        $s['cloud'] = implode("\n", self::cloudRemotes($s));
        \App\Helper::setKeyVal('sqs_backup', json_encode($s, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Облака из настройки: по одному remote в строке (старая настройка — одна строка — тоже список)
     */
    public static function cloudRemotes(array $s): array {
        return array_values(array_unique(array_filter(array_map(fn($r) => rtrim(trim($r), '/'), preg_split('/\R/', (string)($s['cloud'] ?? ''))), 'strlen')));
    }

    /**
     * Места копии: [ключ => ['place' => local|network|cloud, 'target' => папка или remote]]; ключ облака — «cloud:remote»
     */
    public static function places(array $s): array {
        $ret = ['local' => ['place' => 'local', 'target' => $s['local']]];
        if ($s['network'] !== '') {
            $ret['network'] = ['place' => 'network', 'target' => $s['network']];
        }
        foreach (self::cloudRemotes($s) as $r) {
            $ret['cloud:' . $r] = ['place' => 'cloud', 'target' => $r];
        }
        return $ret;
    }

    // подпись места для людей: «Папка на этом компьютере», «Облако (rclone) yandex:squirrelstock»
    public static function label(string $key): string {
        [$place, $remote] = array_pad(explode(':', $key, 2), 2, '');
        return __('service.backup.place_' . $place) . ($remote !== '' ? ' ' . $remote : '');
    }

    /**
     * Папка внутри корня сайта открыта из браузера: копии с персональными данными туда класть нельзя
     */
    public static function insideWebRoot(string $dir): bool {
        $root = realpath(_ROOT);
        $real = realpath($dir);
        $norm = fn($p) => rtrim(str_replace('\\', '/', $p), '/') . '/';
        return $root !== false && $real !== false && str_starts_with($norm($real), $norm($root));
    }

    /**
     * Сделать копию во все настроенные места. $only — только эти места. Возвращает [место => ['ok' => bool, 'message' => ...]]
     */
    public static function run(array $overrides = [], ?array $only = null): array {
        $s = array_merge(self::settings(), $overrides);
        $keep = max(1, intval($s['keep']));
        $name = 'squirrelstock-' . date('Y-m-d-His') . '.zip';
        $result = [];
        $plain = null;
        $enc = null;   // зашифрованный архив — один на все облака
        $places = array_filter(self::places($s), fn($p) => $only === null || in_array($p['place'], $only, true));

        foreach ($places as $key => ['place' => $place, 'target' => $target]) {
            $started = microtime(true);
            $size = 0;
            $file = $place == 'cloud' ? $target . '/' . $name : $name;
            try {
                if ($place == 'cloud') {
                    if ($s['password'] === '') {
                        throw new BackupException('service.backup.cloud_no_password');
                    }
                    $enc ??= (new Backup())->create(_ROOT . 'upload', $s['password']);
                    $size = filesize($enc);
                    self::cloudUpload($s, $target, $enc, $name);
                    $removed = self::cloudRotate($s, $target, $keep);
                } else {
                    $plain ??= (new Backup())->create(_ROOT . 'upload');
                    $size = filesize($plain);
                    $removed = self::folderPut($target, $plain, $name, $keep);
                }
                $result[$key] = ['ok' => true, 'message' => $removed ? __('service.backup.removed_old', ['n' => $removed]) : '',
                                 'stored' => $removed ? BackupException::encode('service.backup.removed_old', ['n' => $removed]) : ''];
            } catch (\Throwable $e) {
                $result[$key] = ['ok' => false, 'message' => $e->getMessage(),
                                 'stored' => $e instanceof BackupException ? $e->stored() : $e->getMessage()];
            }
            // в журнал — ключ перевода (BackupException), страница покажет его на языке того, кто смотрит
            self::log($place, $result[$key]['ok'], $file, $size, $result[$key]['stored'], microtime(true) - $started);
        }
        foreach ([$plain, $enc] as $tmp) {
            if ($tmp) {
                @unlink($tmp);
            }
        }

        $failed = array_filter($result, fn($r) => !$r['ok']);
        if ($failed) {
            $lines = [];
            foreach ($failed as $key => $r) {
                $lines[] = self::label($key) . ': ' . $r['message'];
            }
            self::notifyAdmins(__('service.backup.failed_notify') . "\n" . implode("\n", $lines));
        }

        return $result;
    }

    /**
     * Копия на флешку или в любую папку вручную, без удаления старых
     */
    public static function copyTo(string $dir): string {
        $name = 'squirrelstock-' . date('Y-m-d-His') . '.zip';
        $zip = (new Backup())->create(_ROOT . 'upload');
        try {
            self::folderPut($dir, $zip, $name, 0);
        } finally {
            @unlink($zip);
        }
        return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * Положить файл в папку и оставить последние $keep копий (0 — не удалять). Возвращает число удаленных.
     */
    private static function folderPut(string $dir, string $file, string $name, int $keep): int {
        $dir = rtrim($dir, '/\\');
        if ($dir === '') {
            throw new BackupException('service.backup.no_folder');
        }
        if (!is_dir($dir) && !@mkdir($dir, 0770, true)) {
            throw new BackupException('service.backup.folder_unavailable', ['dir' => $dir]);
        }
        if (self::insideWebRoot($dir)) {
            throw new BackupException('service.backup.folder_in_webroot', ['dir' => $dir]);
        }
        $target = $dir . DIRECTORY_SEPARATOR . $name;
        // сначала во временный файл: оборванная копия не выглядит как готовая
        if (!@copy($file, $target . '.part') || filesize($target . '.part') != filesize($file) || !@rename($target . '.part', $target)) {
            @unlink($target . '.part');
            throw new BackupException('service.backup.write_failed', ['dir' => $dir]);
        }
        if ($keep <= 0) {
            return 0;
        }
        $old = array_values(array_filter(scandir($dir) ?: [], fn($f) => preg_match(self::NAME_RE, $f)));
        sort($old);
        $removed = 0;
        foreach (array_slice($old, 0, max(0, count($old) - $keep)) as $f) {
            if (@unlink($dir . DIRECTORY_SEPARATOR . $f)) {
                $removed++;
            }
        }
        return $removed;
    }

    private static function cloudUpload(array $s, string $remote, string $file, string $name): void {
        self::rclone($s, ['copyto', $file, $remote . '/' . $name]);
    }

    private static function cloudRotate(array $s, string $remote, int $keep): int {
        $list = self::rclone($s, ['lsf', '--files-only', $remote . '/']);
        $old = array_values(array_filter(array_map('trim', explode("\n", $list)), fn($f) => preg_match(self::NAME_RE, $f)));
        sort($old);
        $removed = 0;
        foreach (array_slice($old, 0, max(0, count($old) - $keep)) as $f) {
            self::rclone($s, ['deletefile', $remote . '/' . $f]);
            $removed++;
        }
        return $removed;
    }

    /**
     * Запуск rclone без оболочки; ненулевой код возврата — исключение с текстом ошибки rclone
     */
    private static function rclone(array $s, array $args): string {
        $cmd = array_merge([$s['rclone'] !== '' ? $s['rclone'] : 'rclone'], $s['rclone_conf'] !== '' ? ['--config', $s['rclone_conf']] : [],
                           ['--retries', '2', '--contimeout', '30s', '--timeout', '5m'], $args);
        $p = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($p)) {
            throw new BackupException('service.backup.rclone_missing', ['path' => $cmd[0]]);
        }
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($p);
        if ($code !== 0) {
            $err = trim(preg_replace('/\s+/', ' ', $err));
            if ($code == 127) {
                throw new BackupException('service.backup.rclone_missing', ['path' => $cmd[0]]);
            }
            throw new \Exception('rclone (' . $code . '): ' . mb_substr($err, -400));
        }
        return $out;
    }

    private static function log(string $place, bool $ok, string $file, int $size, string $message, float $seconds): void {
        $conn = \ZDB\DB::getConnect();
        // время PHP, а не NOW() базы: часовой пояс сервера базы может отличаться (в Docker — UTC), а сравнивается с time()
        $conn->Execute('INSERT INTO sqs_backuplog (place, createdon, ok, file, size, seconds, message) VALUES ('
            . $conn->qstr($place) . ', ' . $conn->qstr(date('Y-m-d H:i:s')) . ', ' . ($ok ? 1 : 0) . ', ' . $conn->qstr($file) . ', ' . intval($size) . ', '
            . round($seconds) . ', ' . $conn->qstr(mb_substr($message, 0, 2000)) . ')');
    }

    private static function notifyAdmins(string $message): void {
        foreach (\App\Entity\User::find("rolename = 'admins'") as $u) {
            $n = new \App\Entity\Notify();
            $n->user_id = $u->user_id;
            $n->message = $message;
            $n->sender_id = \App\Entity\Notify::SYSTEM;
            $n->save();
        }
    }

    /**
     * Состояние по местам: [ключ места => ['place', 'target', 'enabled', 'last_ok' (метка времени или 0),
     * 'last' (последняя попытка), 'stale']]. Не настроенные сеть и облако — строкой «не настроено».
     */
    public static function status(): array {
        $s = self::settings();
        $conn = \ZDB\DB::getConnect();
        $places = self::places($s);
        $places += ['network' => ['place' => 'network', 'target' => ''], 'cloud' => ['place' => 'cloud', 'target' => '']];
        $remotes = self::cloudRemotes($s);
        $ret = [];
        foreach (['local', 'network'] as $k) {
            $ret[$k] = $places[$k];
        }
        foreach ($places as $k => $p) {
            if ($p['place'] == 'cloud' && ($k != 'cloud' || !$remotes)) {
                $ret[$k] = $p;
            }
        }
        foreach ($ret as $k => $p) {
            $where = 'place = ' . $conn->qstr($p['place']);
            if ($p['place'] == 'cloud' && $p['target'] !== '') {
                // запись облака — file «remote/имя»; записи одного облака до списка (file — только имя) — первому облаку
                $like = $conn->qstr(addcslashes($p['target'], '%_\\') . '/%');
                $where .= " AND (file LIKE {$like}" . ($p['target'] === ($remotes[0] ?? '') ? " OR file NOT LIKE '%/%'" : '') . ')';
            }
            $lastOk = $conn->GetOne("SELECT MAX(createdon) FROM sqs_backuplog WHERE {$where} AND ok = 1");
            $last = $conn->GetRow("SELECT createdon, ok, message FROM sqs_backuplog WHERE {$where} ORDER BY id DESC LIMIT 1");
            $enabled = $p['place'] == 'local' || $p['target'] !== '';
            $lastOk = $lastOk ? strtotime($lastOk) : 0;
            $ret[$k] += [
                'enabled' => $enabled,
                'last_ok' => $lastOk,
                'last'    => $last ?: null,
                'stale'   => $enabled && time() - $lastOk > self::STALE_HOURS * 3600,
            ];
        }
        return $ret;
    }

    /**
     * Последняя успешная копия в папку этого компьютера моложе $hours часов — для запуска планировщиком каждый час
     * («--if-older=20»): компьютер могли выключить в назначенное время, копия сделается при следующем запуске.
     */
    public static function freshLocal(float $hours): bool {
        $conn = \ZDB\DB::getConnect();
        $last = $conn->GetOne("SELECT MAX(createdon) FROM sqs_backuplog WHERE place = 'local' AND ok = 1");
        return $last && time() - strtotime($last) < $hours * 3600;
    }

    // блок главной страницы (событие mainBlocks): администратору — предупреждение, если в каком-то месте нет удачной копии
    // дольше STALE_HOURS (если планировщик не запущен вовсе, уведомление о сбое не придет — проверяет веб-сторона)
    public static function mainBlock(): string {
        if (\App\System::getUser()->rolename != 'admins') {
            return '';
        }
        $stale = [];
        foreach (self::status() as $key => $st) {
            if ($st['stale']) {
                $stale[] = self::label($key);
            }
        }
        if (!$stale) {
            return '';
        }
        return (new \App\Report('sqs/backupstale.tpl'))->generate(['sqs_backup_stale' => __('service.backup.stale', ['places' => implode(', ', $stale), 'hours' => self::STALE_HOURS])], false);
    }
}
