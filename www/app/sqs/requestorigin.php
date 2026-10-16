<?php

namespace App\SQS;

/**
 * SquirrelStock: действие выполняется, только если запрос пришел со страниц самой программы.
 * В Zippy нет токена формы, а любое действие — «событие» по постоянному адресу index.php?q=<страница>::<компонент>:
 * ссылки (ClickLink) переходят на него методом GET, формы читают поля из $_REQUEST, то есть принимаются и в адресе.
 * Cookie сессии с SameSite=Lax браузер отправляет при переходе по ссылке с чужого сайта, поэтому одной ссылкой
 * на чужой странице можно было выполнить действие от имени вошедшего сотрудника.
 * Проверка — до создания страницы (index.php, перед Application::Run), для события и для любого POST (кроме /api/:
 * у API свой вход по токену, а вызов с сайта — POST, с которым Lax cookie не отправляет):
 *  - Sec-Fetch-Site (браузер шлет по HTTPS и на localhost): same-origin и none (адрес набран, закладка) — да, иначе нет;
 *  - без него (обычный HTTP в локальной сети) — Origin, а если его нет — Referer: host:port как в HTTP_HOST.
 *    Свои страницы шлют Referer себе (Referrer-Policy same-origin в .htaccess); X-Forwarded-Host не учитывается;
 *  - ни одного из заголовков — нет: чужая страница может не отправлять Referer.
 * Адрес события без компонента (?q=p:<страница>) — показ страницы (перенаправления Zippy), его проверка не касается.
 * Отказ: событие не выполняется, в журнал — адрес и заголовки источника (без cookie), пользователю — общая фраза,
 * на AJAX и запрос файла — ответ 403.
 */
class RequestOrigin
{
    public static function check(\Zippy\WebApplication $app): void {
        $req = $app->getRequest();
        $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'POST';
        if ($req->querytype != \Zippy\HttpRequest::QUERY_EVENT && !($post && strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '/api/') !== 0)) {
            return;
        }
        // ?q=p:<страница> без компонента — показ страницы, а не действие: так выглядят перенаправления Zippy
        // (Application::Redirect), в том числе на страницу входа при первом открытии сайта
        if (!$post && $req->querytype == \Zippy\HttpRequest::QUERY_EVENT && count($req->request_c) <= 1) {
            return;
        }
        if (self::allowed($_SERVER)) {
            return;
        }
        global $logger;
        $logger->warning('Request from another site rejected: ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '')
            . ' Host: ' . ($_SERVER['HTTP_HOST'] ?? '') . ' Sec-Fetch-Site: ' . ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '-')
            . ' Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '-') . ' Referer: ' . ($_SERVER['HTTP_REFERER'] ?? '-'));
        if ($req->isAjaxRequest() || $req->isBinaryRequest()) {
            http_response_code(403);
            echo __('sqs.request_rejected');
            die;
        }
        \App\System::setErrorMsg(__('sqs.request_rejected'));
        $req->querytype = \Zippy\HttpRequest::QUERY_PAGE;
        $req->request_page = '\\App\\Pages\\Error';
        $req->request_page_arg = [];
    }

    /**
     * Пришел ли запрос со страниц этой программы (по заголовкам запроса из $server)
     */
    public static function allowed(array $server): bool {
        $site = strtolower(trim((string)($server['HTTP_SEC_FETCH_SITE'] ?? '')));
        if ($site !== '') {
            return $site == 'same-origin' || $site == 'none';
        }
        $host = strtolower((string)($server['HTTP_HOST'] ?? ''));
        $source = (string)($server['HTTP_ORIGIN'] ?? '');
        if ($source === '') {
            $source = (string)($server['HTTP_REFERER'] ?? '');
        }
        if ($source === '' || $host === '') {
            return false;
        }
        $u = parse_url($source);
        if (!is_array($u) || !isset($u['host']) || !in_array(strtolower($u['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }
        $from = strtolower($u['host']) . (isset($u['port']) ? ':' . $u['port'] : '');
        return $from === $host;
    }
}
