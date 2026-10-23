<?php

namespace App\SQS;

/**
 * SquirrelStock: поиск по словам в любом порядке (TODO 2.10). «Зонд 14» находит «Зонд гастростомический 14 Fr»: каждое
 * слово должно встретиться хотя бы в одном из полей (название, код, номер…), порядок и слова между ними не важны.
 * У Zippy искалась подстрока целиком (LIKE '%Зонд 14%'). Одно слово — то же условие, что раньше.
 */
class Search
{
    public const MAX_WORDS = 8;   // длинный текст — первые слова: условие не растет без меры

    /**
     * Условие SQL. $match получает слово в виде строки SQL для LIKE ('%слово%', спецсимволы LIKE экранированы) и
     * возвращает условие для одного слова, например fn($l) => "(itemname LIKE $l OR item_code LIKE $l)".
     * Пустой текст — '1=1'.
     */
    public static function words(string $text, callable $match): string {
        $words = self::split($text);
        if (!$words) {
            return '1=1';
        }
        $conn = \ZDB\DB::getConnect();
        $parts = [];
        foreach ($words as $w) {
            $parts[] = $match($conn->qstr('%' . addcslashes($w, '%_\\') . '%'));
        }
        return '(' . implode(' AND ', $parts) . ')';
    }

    /**
     * Условие «каждое слово — в одной из колонок»: words($text, columns(['i.itemname', 'i.item_code']))
     */
    public static function columns(array $columns): callable {
        return fn($like) => '(' . implode(' OR ', array_map(fn($c) => "{$c} LIKE {$like}", $columns)) . ')';
    }

    /**
     * То же в PHP (отчет собирается из нескольких запросов): все слова текста есть в строке, без учета регистра
     */
    public static function matches(string $haystack, string $text): bool {
        $haystack = mb_strtolower($haystack);
        foreach (self::split(mb_strtolower($text)) as $w) {
            if (!str_contains($haystack, $w)) {
                return false;
            }
        }
        return true;
    }

    public static function split(string $text): array {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_slice(array_values(array_unique($words)), 0, self::MAX_WORDS);
    }
}
