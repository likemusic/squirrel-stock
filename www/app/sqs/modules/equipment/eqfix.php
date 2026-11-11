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
 *  - «12/25 Петров = возврат без даты» (или «повернення без дати») — дата возврата раньше выдачи и не восстанавливается
 *    (решение 11.11.2026): возврат проводится датой выдачи, в примечании возврата — «дата возврата не восстановлена»;
 *  - «№ 1234 = эл. отс. В-40+АКБ» — номер записан на нескольких листах переписи, верный — этот; строки других листов
 *    не переносятся.
 * В строках «договор ФИО = …» договор без номера пишется, как в отчете: «б/н 15.03.2022 Петров = …», по акту —
 * «акт 15.03.2022 Петров = …» (номер повтора
 * «(2)» можно не писать — строки отличаются по ФИО).
 * ФИО сравнивается без учета регистра, «ё» и пробелов (как написания в EqItemMap). Пустые строки и строки с «#» — пропуск.
 */
class EqFix
{
    public const KEY = 'sqs_eqfix';
    // «12/25», часть разбитого договора «12-1/25», год четырьмя цифрами «12/2025» (как в таблицах, EqHistory::contractNumber)
    private const CONTRACT = '\d+(?:[-–]\d+)?\/(?:\d{2}|(?:19|20)\d{2})(?:\/\d+)?';
    // договор без номера, как его заводит EqHistory: «б/н 15.03.2022», «б/н 15.03.2022 (2)», по акту — «акт 15.03.2022»
    private const NONUM = '(?:б\s*[\/\\\\]?\s*н|акт)\s+\d{1,2}\.\d{1,2}\.\d{4}(?:\s*\(\d+\))?';

    public static function text(): string {
        return (string)\App\Helper::getKeyVal(self::KEY);
    }

    public static function save(string $text): void {
        \App\Helper::setKeyVal(self::KEY, trim($text) === '' ? null : trim($text));
    }

    // номер — как его заводит EqHistory, иначе как записан
    private static function contract(string $s): string {
        if (preg_match('/^(б\s*[\/\\\\]?\s*н|акт)\s+(\d{1,2})\.(\d{1,2})\.(\d{4})/iu', $s, $m)) {
            // без номера повтора: до разбора у всех договоров без номера одной даты один номер (EqHistory::provisionalNumber)
            $prefix = mb_strtolower($m[1]) === EqHistory::ACT ? EqHistory::ACT : EqHistory::NO_NUMBER;
            return sprintf('%s %02d.%02d.%04d', $prefix, $m[2], $m[3], $m[4]);
        }
        return EqHistory::contractNumber($s) ?? $s;
    }

    private static function clean(string $s): string {
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * Разобрать текст. Возвращает ['fixes' => [['line' => номер строки, 'kind' => recipient|move|skip|noret|sheet, ...]], 'errors' => [номер строки => текст]]
     *  recipient: contract, name, holder; move: contract, who, to; skip, noret: contract, who; sheet: number, sheet
     */
    public static function parse(string $text): array {
        $res = ['fixes' => [], 'errors' => []];
        $c = self::CONTRACT;
        $cw = '(?:' . self::CONTRACT . '|' . self::NONUM . ')';   // договор слева от ФИО
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
                $res['fixes'][] = ['line' => $n, 'kind' => 'recipient', 'contract' => self::contract($left), 'name' => $right, 'holder' => $holder];
            } elseif (preg_match("/^({$cw})\s+(.+)$/iu", $left, $m)) {
                if ($right === '-') {
                    $res['fixes'][] = ['line' => $n, 'kind' => 'skip', 'contract' => self::contract($m[1]), 'who' => EqItemMap::key($m[2])];
                } elseif (preg_match('/^(?:возврат без даты|повернення без дати)$/iu', $right)) {
                    $res['fixes'][] = ['line' => $n, 'kind' => 'noret', 'contract' => self::contract($m[1]), 'who' => EqItemMap::key($m[2])];
                } elseif (preg_match("/^{$c}$/u", $right)) {
                    $res['fixes'][] = ['line' => $n, 'kind' => 'move', 'contract' => self::contract($m[1]), 'who' => EqItemMap::key($m[2]), 'to' => self::contract($right)];
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
