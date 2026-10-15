<?php
/**
 * SquirrelStock: резервная копия по расписанию во все места из настроек страницы «Сервис → Резервная копия»
 * (папка на этом компьютере, сетевая папка, облако через rclone). Для планировщика ОС:
 *
 * php cli/backup.php                   копия во все настроенные места
 * php cli/backup.php D:\Backup         папка на этом компьютере — эта, а не из настроек
 * php cli/backup.php --keep=N          хранить последние N копий в каждом месте (по умолчанию из настроек, 30)
 * php cli/backup.php --only=local,cloud  только эти места
 * php cli/backup.php --if-older=20     ничего не делать, если успешная копия в папку этого компьютера моложе 20 часов:
 *                                      так планировщик запускает скрипт каждый час, а копия делается раз в день,
 *                                      даже если в назначенное время компьютер был выключен
 *
 * Код возврата: 0 — все места успешно, 1 — хотя бы одно место с ошибкой (подробности — на странице и в журнале sqs_backuplog).
 * Примеры расписания — docs/BACKUP.ru.md.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require __DIR__ . '/../init.php';

$overrides = [];
$only = null;
$ifOlder = 0;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--keep=(\d+)$/', $arg, $m)) {
        $overrides['keep'] = (int)$m[1];
    } elseif (preg_match('/^--only=([a-z,]+)$/', $arg, $m)) {
        $only = array_values(array_intersect(explode(',', $m[1]), \App\SQS\BackupJob::PLACES));
    } elseif (preg_match('/^--if-older=([\d.]+)$/', $arg, $m)) {
        $ifOlder = (float)$m[1];
    } elseif (!str_starts_with($arg, '--')) {
        $overrides['local'] = $arg;
    } else {
        fwrite(STDERR, "Неизвестный параметр {$arg}; справка — в начале файла cli/backup.php\n");
        exit(2);
    }
}

if ($ifOlder > 0 && \App\SQS\BackupJob::freshLocal($ifOlder)) {
    echo "Копия моложе {$ifOlder} ч уже есть, пропуск\n";
    exit(0);
}

$result = \App\SQS\BackupJob::run($overrides, $only);
$failed = 0;
foreach ($result as $place => $r) {
    echo str_pad($place, 8) . ($r['ok'] ? 'OK' : 'ОШИБКА') . ($r['message'] !== '' ? ': ' . $r['message'] : '') . "\n";
    $failed += $r['ok'] ? 0 : 1;
}
exit($failed ? 1 : 0);
