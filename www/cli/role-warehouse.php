<?php
/**
 * SquirrelStock: роль «Склад» для работника склада (TODO 2.17, решение владельца 23.10.2026) и, по желанию, пользователь с ней.
 * Роли разрешено все — документы (просмотр, правка, проведение, статусы, отмена, удаление), журналы, отчеты, справочники,
 * сервисные страницы, в том числе «Резервная копия» (создать копию и копию на флешку) — кроме импорта (DENY ниже) и
 * «Обновления программы». Раздел «Система» (Общие, Администратор, Настройка меню, Данные компании, Пользователи, Роли,
 * Кассы, Подписки, Планировщик, Системный журнал), восстановление из копии и настройки автокопии программа и так
 * открывает только роли admins. Права даются и на пункты, выключенные в меню: включенный потом пункт роль видит сразу.
 * То же можно сделать в интерфейсе: «Система → Роли» (docs/USER_GUIDE.ru.md). Повторный запуск обновляет права роли.
 *
 * php cli/role-warehouse.php                         роль «Склад» (создать или обновить права)
 * php cli/role-warehouse.php --user=sklad            и пользователь sklad с этой ролью, доступ ко всем складам;
 *                                                    пароль — из SQS_USER_PASS или случайный (печатается); у существующего
 *                                                    пользователя меняется только роль и склады, пароль — если задан SQS_USER_PASS
 * php cli/role-warehouse.php --name=Кладовщик        другое название роли
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// тип пункта метаданных (1 — документ, 2 — отчет, 3 — журнал, 4 — справочник, 5 — сервис) => без доступа
const DENY = [
    5 => ['Import', 'Import1C', 'ImportEquipmentHistory', 'AppUpdate'],
];

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require __DIR__ . '/../init.php';

$name = 'Склад';
$login = '';
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--name=(.+)$/u', $arg, $m)) {
        $name = trim($m[1]);
    } elseif (preg_match('/^--user=([A-Za-z0-9_.@-]+)$/', $arg, $m)) {
        $login = $m[1];
    } else {
        fwrite(STDERR, "Неизвестный параметр {$arg}; справка — в начале файла cli/role-warehouse.php\n");
        exit(2);
    }
}
if ($name === '' || $name === 'admins') {
    fwrite(STDERR, "Название роли не может быть пустым или admins\n");
    exit(2);
}

$conn = \ZDB\DB::getConnect();
$ids = [];
foreach ($conn->Execute('SELECT meta_id, meta_type, meta_name FROM metadata ORDER BY meta_id') as $r) {
    if (!in_array($r['meta_name'], DENY[(int)$r['meta_type']] ?? [], true)) {
        $ids[] = (int)$r['meta_id'];
    }
}
$all = implode(',', $ids);

$role = \App\Entity\UserRole::getFirst('rolename = ' . $conn->qstr($name)) ?? new \App\Entity\UserRole();
$new = $role->role_id == 0;
$role->rolename = $name;
$role->disabled = 0;
foreach (['aclview', 'acledit', 'aclexe', 'aclstate', 'aclcancel', 'acldelete'] as $k) {
    $role->{$k} = $all;
}
$role->custtype = 0;
$role->canevent = 1;
$role->dashboard = 0;
$role->noshowpartion = 0;
$role->modules = $role->modules ?? '';
$role->smartmenu = $role->smartmenu ?? '';
$role->save();
echo ($new ? 'Создана роль' : 'Обновлена роль') . " «{$name}» (id {$role->role_id}): пунктов с доступом " . count($ids) . "\n";

if ($login !== '') {
    $user = \App\Entity\User::getFirst('userlogin = ' . $conn->qstr($login)) ?? new \App\Entity\User();
    if ($user->user_id > 0 && $user->rolename == 'admins') {
        fwrite(STDERR, "Пользователь {$login} — администратор (роль admins), роль не меняется\n");
        exit(1);
    }
    $newUser = $user->user_id == 0;
    $pass = (string)getenv('SQS_USER_PASS');
    if ($newUser && $pass === '') {
        $pass = substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 14);
        $print = true;
    }
    $user->userlogin = $login;
    $user->role_id = $role->role_id;
    $user->disabled = 0;
    $user->onlymy = 0;
    $user->showotherstores = 1;
    // все склады и кассы — запросом: findArray в консоли отбирает по складам текущего пользователя, а его нет
    $stores = $conn->GetCol('SELECT store_id FROM stores ORDER BY store_id');
    $user->aclstore = implode(',', $stores);
    $user->aclmf = implode(',', $conn->GetCol('SELECT mf_id FROM mfund ORDER BY mf_id'));
    if ($newUser) {
        $user->email = '';
        $user->defstore = (int)($stores[0] ?? 0);
        $user->mainpage = '\App\Pages\Main';
    }
    if ($pass !== '') {
        $user->userpass = password_hash($pass, PASSWORD_DEFAULT);
    }
    $user->save();
    echo ($newUser ? 'Создан пользователь' : 'Обновлен пользователь') . " {$login} с ролью «{$name}»" . (!empty($print) ? ", пароль: {$pass}" : '') . "\n";
}
