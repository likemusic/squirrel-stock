<?php
/**
 * SquirrelStock: пункты меню включенных модулей (app/sqs/modules/<имя>/module.php, ключ 'menu') — для консольных скриптов,
 * которые работают без init.php (localize-db.php, charity-profile.php). Модули — App\SQS\Modules, docs/MODULES.md.
 * Возвращает [[тип, meta_name, описание, группа], ...]; описание и группа — строка или ['uk' => ..., 'ru' => ...].
 */
function sqs_modules_menu(): array {
    $items = [];
    foreach (glob(__DIR__ . '/../app/sqs/modules/*/module.php') ?: [] as $file) {
        if (basename(dirname($file))[0] == '.') {
            continue;
        }
        $def = (static fn() => require $file)();
        foreach (is_array($def) ? ($def['menu'] ?? []) : [] as $it) {
            $items[] = array_pad($it, 4, '');
        }
    }
    return $items;
}
