<?php

namespace App\SQS;

use App\Entity\Item;

/**
 * SquirrelStock: остаток единицы с серийным (инвентарным) номером на дату документа. Ядро при запрете минуса сверяет
 * только общий остаток позиции на сегодня, поэтому у модели с несколькими единицами выдавался уже выданный номер, а
 * документ задним числом уводил номер в минус на прошлые даты. Вызывается из проведения расходной накладной и списания.
 */
class SerialStock
{
    /**
     * Сколько единиц номера $item->snumber не хватает, чтобы списать $qty со склада $store_id датой $date: остаток на
     * конец каждого дня с $date по последний документ (без самого документа $document_id) не должен уйти в минус.
     * 0 — хватает (или номер не задан, позиция без серийного учета, минус разрешен в настройках).
     */
    public static function shortage(Item $item, float $qty, int $store_id, int $date, int $document_id = 0): float {
        if ($item->useserial != 1 || strlen((string)$item->snumber) == 0 || \App\System::getOption('common', 'allowminus') == 1) {
            return 0;
        }
        $conn = \ZDB\DB::getConnect();
        $rows = $conn->GetAll("SELECT DATE(d.document_date) AS dd, SUM(e.quantity) AS q FROM entrylist e
            JOIN documents d ON d.document_id = e.document_id JOIN store_stock s ON s.stock_id = e.stock_id
            WHERE s.item_id = " . intval($item->item_id) . " AND s.snumber = " . $conn->qstr($item->snumber) . " AND s.store_id = " . intval($store_id) . "
              AND e.document_id <> " . intval($document_id) . " GROUP BY DATE(d.document_date) ORDER BY dd");
        $day = date('Y-m-d', $date);
        $run = 0.0;
        $min = null;
        foreach ($rows as $r) {
            if ($r['dd'] >= $day && $min === null && $r['dd'] > $day) {
                $min = $run;   // в день документа движений не было: остаток в этот день — на конец предыдущего
            }
            $run += doubleval($r['q']);
            if ($r['dd'] >= $day) {
                $min = $min === null ? $run : min($min, $run);
            }
        }
        $min = $min ?? $run;
        return max(0.0, round($qty - $min, 4));
    }
}
