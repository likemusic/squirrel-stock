<?php

namespace App\SQS;

/**
 * SquirrelStock: страница выключенного пункта меню (metadata.disabled = 1) не открывается и по прямому адресу.
 * Профиль (cli/charity-profile.php) и выключенный модуль убирают пункт из меню, а права ACL на disabled не смотрят:
 * без этой проверки страница открывалась по ссылке, в том числе у администратора; страница выключенного модуля
 * завершала работу сообщением автозагрузчика «Невірний клас».
 * Проверка — до создания страницы (index.php, перед Application::Run): класса выключенного модуля может не быть вовсе.
 * Страница — класс App\Pages\<Doc|Report|Register|Reference|Service>\<meta_name> (тип metadata 1–5).
 */
class PageGuard
{
    private const TYPES = ['doc' => 1, 'report' => 2, 'register' => 3, 'reference' => 4, 'service' => 5];

    /**
     * Если запрошена страница выключенного пункта — вместо нее страница ошибки с сообщением «нет права доступа»
     */
    public static function check(\Zippy\WebApplication $app): void {
        $req = $app->getRequest();
        if (!in_array($req->querytype, [\Zippy\HttpRequest::QUERY_PAGE, \Zippy\HttpRequest::QUERY_EVENT], true)) {
            return;
        }
        $desc = self::disabled((string)$req->request_page);
        if ($desc === null) {
            return;
        }
        \App\System::setErrorMsg(__('app.acl.nemaie_prava_dostupu_do_storinky') . $desc);
        $req->querytype = \Zippy\HttpRequest::QUERY_PAGE;
        $req->request_page = '\\App\\Pages\\Error';
        $req->request_page_arg = [];
    }

    /**
     * Описание выключенного пункта меню для класса страницы или null, если пункт не выключен (или это не пункт меню)
     */
    public static function disabled(string $class): ?string {
        if (!preg_match('/^\\\\?App\\\\Pages\\\\([A-Za-z]+)\\\\([A-Za-z0-9_]+)$/', $class, $m)) {
            return null;
        }
        $type = self::TYPES[strtolower($m[1])] ?? 0;
        if ($type == 0) {
            return null;
        }
        $conn = \ZDB\DB::getConnect();
        $row = $conn->GetRow("SELECT description FROM metadata WHERE disabled = 1 AND meta_type = {$type} AND meta_name = " . $conn->qstr($m[2]));
        return $row ? (string)$row['description'] : null;
    }
}
