<?php
/**
 * SquirrelStock: загрузить модули (app/sqs/modules) — создать их таблицы (schema.sql) и пункты меню без веб-запроса.
 * Для установщика и обновления: после импорта базы, до cli/charity-profile.php и cli/localize-db.php.
 *
 * php cli/modules.php      печатает включенные модули
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require __DIR__ . '/../init.php';   // загрузка модулей — в init.php (App\SQS\Modules::load)
echo 'Модули: ' . implode(', ', \App\SQS\Modules::names()) . "\n";
