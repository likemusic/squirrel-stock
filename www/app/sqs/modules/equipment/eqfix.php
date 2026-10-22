<?php

namespace App\SQS;

/**
 * SquirrelStock: исправления строк таблиц при импорте истории (App\SQS\EqHistory) — ответы хосписа на сверку таблиц
 * (вопросы 5.18, 5.22): исходные таблицы не правятся, исправления пишутся тут и применяются при каждой проверке.
 * Правится на странице импорта текстом, хранится в keyval `sqs_eqfix`. Строки:
 *  - «12/25 = Иванов Иван Иванович» — у всех строк договора получатель с этим ФИО (опечатки, нет имени и отчества);
 *  - «12/25 = Иванов Иван Иванович; договор с Петрова Анна Сергеевна» — то же, и договор заключен с другим лицом
 *    (представитель) в пользу получателя: поле договора «Договор заключен с»;
 *  - «12/25 Петров = 13/25» — строки договора 12/25 с этим ФИО относятся к договору 13/25 (опечатка в номере);
 *  - «12/25 Петров = -» — строки договора 12/25 с этим ФИО не переносить (запись задвоена);
 *  - «№ 1234 = эл. отс. В-40+АКБ» — номер записан на нескольких листах переписи, верный — этот; строки других листов
 *    не переносятся.
 * ФИО сравнивается без учета регистра, «ё» и пробелов (как написания в EqItemMap). Пустые строки и строки с «#» — пропуск.
 */
class EqFix
{
    public const KEY = 'sqs_eqfix';
    private const CONTRACT = '\d+\/\d{2}(?:\/\d+)?';

    public static function text(): string {
        return (string)\App\Helper::getKeyVal(self::KEY);
    }

    public static function save(string $text): void {
        \App\Helper::setKeyVal(self::KEY, trim($text) === '' ? null : trim($text));
    }

    private static function clean(string $s): string {
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * Разобрать текст. Возвращает ['fixes' => [['line' => номер строки, 'kind' => recipient|move|skip|sheet, ...]], 'errors' => [номер строки => текст]]
     *  recipient: contract, name, holder; move: contract, who, to; skip: contract, who; sheet: number, sheet
     */
    public static function parse(string $text): array {
        $res = ['fixes' => [], 'errors' => []];
        $c = self::CONTRACT;
        foreach (preg_split('/\r\n|\r|\n/', $text) as $i => $line) {
            $line = self::clean($line);
            if ($line === '' || $line[0] == '#') {
                continue;
            }
            $parts = explode('=', $line, 2);
            $left = self::clean($parts[0]);
            $right = self::clean($parts[1] ?? '');
            $n = $i + 1;
            if ($right === '') {
                $res['errors'][$n] = $line;
            } elseif (preg_match('/^№\s*(.+)$/u', $left, $m)) {
                $res['fixes'][] = ['line' => $n, 'kind' => 'sheet', 'number' => EqHistory::num($m[1]), 'sheet' => $right];
            } elseif (preg_match("/^({$c})$/u", $left)) {
                $holder = '';
                if (preg_match('/^(.+?)\s*;\s*(?:договор с|договір з)\s+(.+)$/iu', $right, $m)) {
                    [$right, $holder] = [self::clean($m[1]), self::clean($m[2])];
                }
                $res['fixes'][] = ['line' => $n, 'kind' => 'recipient', 'contract' => $left, 'name' => $right, 'holder' => $holder];
            } elseif (preg_match("/^({$c})\s+(.+)$/u", $left, $m)) {
                if ($right === '-') {
                    $res['fixes'][] = ['line' => $n, 'kind' => 'skip', 'contract' => $m[1], 'who' => EqItemMap::key($m[2])];
                } elseif (preg_match("/^{$c}$/u", $right)) {
                    $res['fixes'][] = ['line' => $n, 'kind' => 'move', 'contract' => $m[1], 'who' => EqItemMap::key($m[2]), 'to' => $right];
                } else {
                    $res['errors'][$n] = $line;
                }
            } else {
                $res['errors'][$n] = $line;
            }
        }
        return $res;
    }
}
