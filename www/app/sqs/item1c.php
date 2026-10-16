<?php

namespace App\SQS;

/**
 * SquirrelStock: позиции из 1С (таблица sqs_item1c, app/sqs/schema.sql). Импорт ведомости отмечает позицию названием,
 * как в 1С, и ценой за единицу на последнем импорте. Название из 1С печатается в акте списания (бухгалтерия сопоставляет
 * акт по названию), поэтому переименование позиции в программе акт не ломает. ZIPPY_GAPS п. 29–30.
 */
class Item1C
{
    /**
     * Название из 1С, как его сохранит программа: Item::beforeSave меняет кавычки ' и " на `. Сравнивать название позиции
     * с названием из 1С только так, иначе позиция с кавычками в названии считается переименованной и не находится по названию.
     */
    public static function itemName(string $name1c): string {
        return str_replace(["'", '"'], '`', $name1c);
    }

    public static function get(int $item_id): ?array {
        $r = \ZDB\DB::getConnect()->GetRow("SELECT name1c, price1c FROM sqs_item1c WHERE item_id = " . intval($item_id));
        return $r ? ['name1c' => $r['name1c'], 'price1c' => (float)$r['price1c']] : null;
    }

    public static function set(int $item_id, string $name1c, ?float $price1c = null): void {
        $conn = \ZDB\DB::getConnect();
        $price = $price1c === null ? 'NULL' : $conn->qstr(round($price1c, 2));
        $conn->Execute("INSERT INTO sqs_item1c (item_id, name1c, price1c) VALUES (" . intval($item_id) . ", " . $conn->qstr($name1c) . ", {$price})
                        ON DUPLICATE KEY UPDATE name1c = VALUES(name1c), price1c = COALESCE(VALUES(price1c), price1c)");
    }

    /**
     * Позиции с этим названием из 1С: [['item_id', 'itemname', 'price1c'], ...]
     */
    public static function candidates(string $name1c): array {
        $conn = \ZDB\DB::getConnect();
        $list = [];
        foreach ($conn->Execute("SELECT c.item_id, i.itemname, c.price1c FROM sqs_item1c c JOIN items i ON i.item_id = c.item_id
                                 WHERE c.name1c = " . $conn->qstr($name1c) . " AND i.disabled <> 1 ORDER BY c.price1c, c.item_id") as $r) {
            $list[] = ['item_id' => (int)$r['item_id'], 'itemname' => $r['itemname'], 'price1c' => $r['price1c'] === null ? null : (float)$r['price1c']];
        }
        return $list;
    }

    /**
     * Названия из 1С для позиций: [item_id => name1c]
     */
    public static function names(array $item_ids): array {
        $ids = array_filter(array_map('intval', $item_ids));
        if (count($ids) == 0) {
            return [];
        }
        $map = [];
        foreach (\ZDB\DB::getConnect()->Execute("SELECT item_id, name1c FROM sqs_item1c WHERE item_id IN (" . implode(',', $ids) . ")") as $r) {
            $map[(int)$r['item_id']] = $r['name1c'];
        }
        return $map;
    }

    /**
     * Сопоставить строки ведомости с одинаковым названием позициям. $rows — [['price' => цена за единицу|null, ...], ...].
     * Возвращает ['match' => [индекс строки => item_id|0 (создать)], 'ambiguous' => bool, 'drift' => [индексы строк с ценой,
     * ушедшей больше чем на 50%]]. Правила: строк столько же, сколько позиций — пары по возрастанию цены; иначе строка
     * сопоставляется, только если ее цена до копейки равна прошлой цене ровно одной позиции; нет позиций — создать.
     */
    public static function match(array $rows, array $cands): array {
        $res = ['match' => [], 'ambiguous' => false, 'drift' => []];
        if (count($cands) == 0) {
            foreach (array_keys($rows) as $i) {
                $res['match'][$i] = 0;
            }
            return $res;
        }
        $order = array_keys($rows);
        usort($order, fn($a, $b) => [$rows[$a]['price'] ?? 0, $a] <=> [$rows[$b]['price'] ?? 0, $b]);
        if (count($rows) == count($cands)) {
            foreach ($order as $k => $i) {
                $res['match'][$i] = $cands[$k]['item_id'];
                $old = $cands[$k]['price1c'];
                $new = $rows[$i]['price'];
                if ($old > 0 && $new !== null && abs($new - $old) / $old > 0.5) {
                    $res['drift'][] = $i;
                }
            }
            return $res;
        }
        $used = [];
        foreach ($order as $i) {
            $p = $rows[$i]['price'];
            $same = array_values(array_filter($cands, fn($c) => $p !== null && $c['price1c'] !== null && abs($c['price1c'] - $p) < 0.005 && !isset($used[$c['item_id']])));
            if (count($same) != 1) {
                $res['ambiguous'] = true;
                $res['match'] = [];
                return $res;
            }
            $used[$same[0]['item_id']] = 1;
            $res['match'][$i] = $same[0]['item_id'];
        }
        return $res;
    }

    // событие itemSaved (справочник номенклатуры): позиция из 1С переименована — акт списания печатает название из 1С (ZIPPY_GAPS п. 30)
    public static function itemSaved($item, $page, string $oldname): void {
        $i1c = self::get((int)$item->item_id);
        if ($i1c && $oldname !== $item->itemname && self::itemName($i1c['name1c']) !== $item->itemname) {
            $page->setWarn(__('sqs.item1c.renamed', ['name1c' => $i1c['name1c']]));
        }
    }
}
