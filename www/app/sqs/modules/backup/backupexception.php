<?php

namespace App\SQS;

/**
 * SquirrelStock: ошибка резервной копии с ключом перевода. Текст — на языке того, кто запустил копию (CLI, страница),
 * а в журнал sqs_backuplog пишется ключ с параметрами: страница показывает его на языке того, кто смотрит.
 */
class BackupException extends \Exception
{
    public function __construct(public string $key, public array $params = []) {
        parent::__construct(__($key, $params));
    }

    // запись для журнала
    public function stored(): string {
        return self::encode($this->key, $this->params);
    }

    public static function encode(string $key, array $params = []): string {
        return json_encode(['key' => $key, 'params' => $params], JSON_UNESCAPED_UNICODE);
    }

    // текст записи журнала на языке интерфейса; запись без ключа (например, текст rclone) — как есть
    public static function text(string $stored): string {
        $j = json_decode($stored, true);
        return is_array($j) && isset($j['key']) ? __($j['key'], is_array($j['params'] ?? null) ? $j['params'] : []) : $stored;
    }
}
