<?php
/**
 * SquirrelStock: резервная копия по расписанию — скрипт модуля backup (app/sqs/modules/backup/cli/backup.php).
 * Путь cli/backup.php для планировщика (Windows, cron, systemd) и документации не меняется. Параметры — там же.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$script = __DIR__ . '/../app/sqs/modules/backup/cli/backup.php';
if (!is_file($script)) {
    fwrite(STDERR, "Модуль резервной копии выключен: нет папки app/sqs/modules/backup\n");
    exit(2);
}
require $script;
