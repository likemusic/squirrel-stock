<?php

namespace App\SQS;

/**
 * SquirrelStock: соответствия «написание → позиция» для мелочи без номеров при импорте истории (App\SQS\EqHistory,
 * таблица sqs_eqitemmap). В таблицах хосписа один вид пишется по-разному («Ингалят. детский» и «Ингалятор
 * детский»); исходные таблицы не правятся, варианты сводятся к одной позиции здесь. Написание, которого нет
 * в соответствиях, не импортируется — в отчет, чтобы позиция не завелась дублем молча. Написание, совпадающее
 * с позицией из соответствий, известно и без своей строки.
 * Правится на странице импорта текстом: строка «написание = позиция». Позиция со «*» в конце — оборудование
 * с инвентарными номерами (кровати, концентраторы): строка такой позиции без номера не заводится мелочью количеством,
 * а идет в список «без инвентарного номера» для ручного ввода номера. Отметка хранится в позиции (в базе — «позиция *»).
 * Позиция «-» — расходник («Beurer Medical = -», ответ хосписа 5.17: пальчиковые пульсоксиметры): строки с таким
 * наименованием не переносятся, даже с номером в строке — список «расходники» в отчете.
 */
class EqItemMap
{
    // ключ написания: регистр, «ё», пробелы и пробелы вокруг «. , - /» не различаются
    public static function key(string $s): string {
        $s = mb_strtolower(str_replace(['ё', 'Ё'], 'е', $s));
        $s = preg_replace('/\s*([.,\/-])\s*/u', '$1', $s);
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    private static function clean(string $s): string {
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    // позиция и отметка «с инвентарными номерами» из текста «позиция *»
    private static function entry(string $w, string $item): array {
        if (self::clean($item) === '-') {
            return ['written' => $w, 'item' => '-', 'serial' => false, 'consumable' => true];
        }
        $serial = str_ends_with($item, '*');
        return ['written' => $w, 'item' => self::clean(rtrim($item, '* ')), 'serial' => $serial];
    }

    /**
     * Соответствия из базы: [ключ написания => ['written' => написание, 'item' => позиция, 'serial' => с инв. номерами]], в порядке ввода
     */
    public static function all(): array {
        $map = [];
        foreach (\ZDB\DB::getConnect()->Execute("SELECT written, itemname FROM sqs_eqitemmap ORDER BY id") as $r) {
            $map[self::key($r['written'])] = self::entry($r['written'], $r['itemname']);
        }
        return $map;
    }

    /**
     * Позиция для написания или null, если его нет в соответствиях
     */
    public static function resolve(array $map, string $written): ?string {
        $k = self::key($written);
        if ($k === '') {
            return null;
        }
        if (isset($map[$k])) {
            return empty($map[$k]['consumable']) ? $map[$k]['item'] : null;
        }
        foreach ($map as $m) {
            if (empty($m['consumable']) && self::key($m['item']) === $k) {
                return $m['item'];
            }
        }
        return null;
    }

    /**
     * Написание — расходник (позиция «-»): строка не переносится
     */
    public static function consumable(array $map, string $written): bool {
        return !empty($map[self::key($written)]['consumable']);
    }

    /**
     * Позиция ведется по инвентарным номерам: отмечена «*» хотя бы в одной строке
     */
    public static function serial(array $map, string $item): bool {
        foreach ($map as $m) {
            if (!empty($m['serial']) && self::key($m['item']) === self::key($item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Разобрать текст «написание = позиция» по строкам. Возвращает ['map' => [...как all()], 'errors' => [номер строки => текст]].
     * Пустые строки и строки с «#» в начале пропускаются; одно написание дважды с разными позициями — ошибка
     */
    public static function parse(string $text): array {
        $res = ['map' => [], 'errors' => []];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $i => $line) {
            $line = self::clean($line);
            if ($line === '' || $line[0] == '#') {
                continue;
            }
            $parts = explode('=', $line, 2);
            $w = self::clean($parts[0]);
            $e = self::entry($w, self::clean($parts[1] ?? ''));
            $item = $e['item'];
            if ($w === '' || $item === '') {
                $res['errors'][$i + 1] = $line;
                continue;
            }
            $k = self::key($w);
            if (isset($res['map'][$k]) && self::key($res['map'][$k]['item']) !== self::key($item)) {
                $res['errors'][$i + 1] = $line;
                continue;
            }
            $res['map'][$k] = $e;
        }
        return $res;
    }

    // текст для поля на странице импорта
    public static function toText(array $map): string {
        return implode("\n", array_map(fn($m) => $m['written'] . ' = ' . $m['item'] . (!empty($m['serial']) ? ' *' : ''), $map));
    }

    // заменить соответствия в базе
    public static function save(array $map): void {
        $conn = \ZDB\DB::getConnect();
        $conn->StartTrans();
        $conn->Execute("DELETE FROM sqs_eqitemmap");
        foreach ($map as $m) {
            $conn->Execute("INSERT INTO sqs_eqitemmap (written, itemname) VALUES (" . $conn->qstr($m['written']) . ", " . $conn->qstr($m['item'] . (!empty($m['serial']) ? ' *' : '')) . ")");
        }
        $conn->CompleteTrans();
    }
}
