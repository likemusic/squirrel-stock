<?php

namespace App;

/**
 * SquirrelStock: перевод интерфейса по английским ключам.
 *
 * Словари — lang/<язык>.json, плоский объект «ключ → текст». Исходный язык — uk (тексты автора Zippy),
 * если ключа нет в словаре выбранного языка, берётся uk, затем сам ключ; пропуск пишется в лог.
 * Язык: настройка пользователя (профиль), иначе $_config['common']['lang'], иначе uk.
 *
 * В PHP: __('doc.goods_issue.err.no_store'), с подстановкой: __('msg.stock.below_min', ['name' => $n]) — {name} в тексте.
 * В шаблонах Mustache: {{#t}}doc.goods_issue.label.store{{/t}}. Объект-переводчик кладётся в _tvars['t'];
 * это класс с __invoke, а не замыкание: страницы Zippy сериализуются в сессию, замыкания — нет.
 */
class Translator
{
    public const SOURCE = 'uk';

    private static $dicts = [];
    private static $lang = null;
    private static $missing = [];

    /**
     * Лямбда Mustache: {{#t}}ключ{{/t}}
     */
    public function __invoke($text) {
        return self::get(trim((string)$text));
    }

    public static function get(string $key, array $params = []): string {
        $lang = self::lang();
        $text = self::dict($lang)[$key] ?? null;
        if ($text === null && $lang != self::SOURCE) {
            $text = self::dict(self::SOURCE)[$key] ?? null;
        }
        if ($text === null) {
            self::logMissing($key, $lang);
            $text = $key;
        }
        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string)$value, $text);
        }

        return $text;
    }

    /**
     * Текущий язык интерфейса
     */
    public static function lang(): string {
        if (self::$lang === null) {
            global $_config;
            $lang = $_config['common']['lang'] ?? self::SOURCE;
            // пока читается пользователь, язык — из конфига: код загрузки пользователя сам вызывает __()
            self::$lang = self::isAvailable($lang) ? $lang : self::SOURCE;
            $userlang = session_status() == PHP_SESSION_ACTIVE ? (System::getUser()->lang ?? '') : '';
            if (strlen($userlang) > 0) {
                $lang = $userlang;
            }
            self::$lang = self::isAvailable($lang) ? $lang : self::SOURCE;
        }

        return self::$lang;
    }

    /**
     * Сбросить выбранный язык (после смены в профиле)
     */
    public static function reset(): void {
        self::$lang = null;
    }

    /**
     * Доступные языки: код → название на самом языке
     */
    public static function getLanguages(): array {
        $list = [];
        foreach (glob(self::dir() . '*.json') as $file) {
            $code = basename($file, '.json');
            if (!self::isAvailable($code)) {   // служебные файлы, например quoted-keys.json
                continue;
            }
            $list[$code] = self::dict($code)['lang.name'] ?? $code;
        }
        ksort($list);

        return $list;
    }

    public static function isAvailable(string $lang): bool {
        return preg_match('/^[a-z]{2,3}$/', $lang) && is_file(self::dir() . $lang . '.json');
    }

    private static function dict(string $lang): array {
        if (!isset(self::$dicts[$lang])) {
            $data = self::isAvailable($lang) ? json_decode((string)file_get_contents(self::dir() . $lang . '.json'), true) : null;
            self::$dicts[$lang] = is_array($data) ? $data : [];
        }

        return self::$dicts[$lang];
    }

    private static function dir(): string {
        return _ROOT . 'lang/';
    }

    private static function logMissing(string $key, string $lang): void {
        if (isset(self::$missing[$lang . ':' . $key])) {
            return;
        }
        self::$missing[$lang . ':' . $key] = true;
        global $logger;
        if ($logger) {
            $logger->warning("Немає перекладу '{$key}' ({$lang})");
        }
    }
}
