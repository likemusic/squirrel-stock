<?php

namespace App\SQS;

/**
 * SquirrelStock: пределы времени и памяти PHP для долгих операций (TODO 2.25): проверка и запись импорта истории
 * оборудования, импорт 1С и Excel, создание копии и восстановление. На хостинге по умолчанию max_execution_time = 30 с
 * и memory_limit = 128 МБ, а на больших таблицах и базах этого мало. Операция поднимает пределы для своего запроса
 * (raise); если хостинг этого не дает (set_time_limit в disable_functions, значения закреплены php_admin_value) и
 * предел все же исчерпан, вместо «Fatal error: Maximum execution time …» — страница с сообщением, что попросить у
 * хостинга, и запись в журнал программы.
 */
class Limits
{
    public const SECONDS = 300;
    public const MEMORY = '512M';

    private static ?string $what = null;
    // тексты страницы — переведены заранее: после нехватки памяти переводчик догружал словарь и падал второй раз,
    // страница с сообщением не выводилась
    private static array $text = [];
    private static string $reserve = '';

    /**
     * $what — операция для журнала (null — оставить ту, что задана раньше в этом запросе); $hint — ключ перевода с тем,
     * что делать после обрыва (например, восстановить копию снова), $params — его параметры. Повторный вызов с $what
     * начинает отсчет времени заново: так у каждого этапа долгой операции (страховочная копия, восстановление) свой предел
     */
    public static function raise(?string $what, ?string $hint = null, array $params = []): void {
        if ($what === null && self::$what !== null) {
            return;
        }
        $limit = (int)ini_get('max_execution_time');
        if ($limit > 0 && function_exists('set_time_limit')) {
            @set_time_limit(max($limit, self::SECONDS));   // отсчет — заново, с этого места
        }
        if (self::bytes((string)ini_get('memory_limit')) < self::bytes(self::MEMORY)) {
            @ini_set('memory_limit', self::MEMORY);
        }
        if (self::$what === null) {
            register_shutdown_function([self::class, 'onShutdown']);
            self::$reserve = str_repeat(' ', 1 << 20);   // на сообщение после нехватки памяти
        }
        self::$what = $what ?? 'spreadsheet import';
        self::$text = [
            'exceeded' => __('sqs.limits.exceeded', ['seconds' => self::SECONDS, 'memory' => self::MEMORY]),
            'hint' => $hint ? __($hint, $params) : '',
            'back' => __('sqs.limits.back'),
        ];
    }

    // нехватка времени или памяти — фатальная ошибка, ее не поймать: сообщение — из завершения скрипта
    public static function onShutdown(): void {
        self::$reserve = '';
        $e = error_get_last();
        if ($e === null || $e['type'] != E_ERROR
            || !(str_contains($e['message'], 'Maximum execution time') || str_contains($e['message'], 'Allowed memory size'))) {
            return;
        }
        $hintText = self::$text['hint'];
        \App\Helper::logerror('SquirrelStock limits: ' . self::$what . ': ' . $e['message']
            . ' (max_execution_time ' . ini_get('max_execution_time') . ', memory_limit ' . ini_get('memory_limit') . ')'
            . ($hintText !== '' ? ' — ' . $hintText : ''));
        if (headers_sent() || PHP_SAPI == 'cli') {
            return;
        }
        self::page('sqs-limits', self::$text['exceeded'], $hintText, self::$text['back']);
    }

    /**
     * Страница с сообщением без шаблонов и без базы: после обрыва по пределу и когда база неполная (восстановление
     * копии, возврат которого не удался) страница программы не отрисуется. $back — текст ссылки назад
     */
    public static function page(string $id, string $text, string $hint, string $back): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);   // как у сообщения о слишком большом PDF: код ошибки хостинг может подменить своей страницей
        header('Content-Type: text/html;charset=UTF-8');
        $hint = $hint !== '' ? '<p>' . htmlspecialchars($hint) . '</p>' : '';
        // назад — на страницу, с которой отправлена форма (адрес обработчика Zippy повторил бы операцию); только путь своего сайта
        $ref = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''));
        $href = htmlspecialchars(($ref['path'] ?? '/') . (isset($ref['query']) ? '?' . $ref['query'] : ''));
        echo '<!doctype html><html><head><meta charset="UTF-8"><title>SquirrelStock</title></head><body style="font-family:sans-serif;margin:2em;max-width:50em">'
            . '<p id="' . $id . '">' . htmlspecialchars($text) . '</p>' . $hint . '<p><a href="' . $href . '">' . htmlspecialchars($back) . '</a></p></body></html>';
    }

    public static function bytes(string $v): int {
        $v = trim($v);
        if ($v === '-1' || $v === '') {
            return PHP_INT_MAX;
        }
        $n = intval($v);

        return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    }
}
