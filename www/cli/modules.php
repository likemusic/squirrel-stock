<?php
/**
 * SquirrelStock: таблицы платформы (app/sqs/schema.sql) и модулей (app/sqs/modules/<имя>/schema.sql), пункты меню модулей —
 * без веб-запроса; применяется только изменившееся (App\SQS\Modules).
 * Для установщика и обновления: после импорта базы, до cli/charity-profile.php и cli/localize-db.php.
 *
 * php cli/modules.php      печатает включенные модули; код 1 — схема не применилась (текст ошибки — в stderr)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require __DIR__ . '/../init.php';   // загрузка модулей — в init.php (App\SQS\Modules::load)
echo 'Модули: ' . implode(', ', \App\SQS\Modules::names()) . "\n";
// схема не применилась (у пользователя базы нет права CREATE или ALTER и т. п.) — код 1: установщик и обновление это видят
if (($error = \App\SQS\Modules::schemaErrorText()) !== '') {
    fwrite(STDERR, $error . "\n");
    exit(1);
}
