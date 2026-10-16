<?php

namespace App\SQS;

/**
 * SquirrelStock: признак получателя в профиле благотворительной организации — один из трех (ответ хосписа 2.1):
 * подопечный, по запросу, снят с опеки. Хранится тегом контрагента (App\Entity\Tag), поэтому работает отбор по тегу
 * в справочнике и в отчетах; в форме контрагента выбирается списком, остальные теги — как раньше.
 */
class RecipientMark
{
    private const KEYS = ['sqs.mark.ward', 'sqs.mark.on_request', 'sqs.mark.off_care'];

    // названия признаков на языке интерфейса — они же имена тегов
    public static function names(): array {
        return array_map(fn($k) => __($k), self::KEYS);
    }

    // для выпадающего списка: '' — не указан
    public static function options(): array {
        $names = self::names();
        return ['' => __('common.ne_vkazano')] + array_combine($names, $names);
    }

    // [признак ('' — нет), остальные теги]; признак узнается без учета регистра. Если меток-признаков несколько
    // (до списка их вписывали тегами: «подопечный» и «снят с опеки»), берется последняя по KEYS — «снят с опеки» важнее «подопечный»
    public static function split(array $tags): array {
        $names = self::names();
        $lower = array_map('mb_strtolower', $names);
        $found = -1;
        $rest = [];
        foreach ($tags as $tag) {
            if (trim((string)$tag) === '') {
                continue;   // пустое поле тегов приходит как ['']
            }
            $i = array_search(mb_strtolower(trim($tag)), $lower, true);
            if ($i === false) {
                $rest[] = $tag;
            } else {
                $found = max($found, $i);
            }
        }
        return [$found >= 0 ? $names[$found] : '', $rest];
    }

    // теги для сохранения: остальные теги без признаков (вписанных вручную тоже) плюс выбранный признак
    public static function merge(string $mark, array $tags): array {
        [, $rest] = self::split($tags);
        if (in_array($mark, self::names(), true)) {
            $rest[] = $mark;
        }
        return $rest;
    }
}
