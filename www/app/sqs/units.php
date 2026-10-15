<?php

namespace App\SQS;

/**
 * SquirrelStock: единицы оборудования. Единица — товар с серийным номером Zippy (store_stock.snumber), номер — инвентарный.
 * Заводской номер хранится отдельно (таблица sqs_unit, db/squirrelstock.sql): у Zippy у единицы один номер.
 * У единицы без инвентарного номера инвентарным считается заводской.
 */
class Units
{
    public static function key(int $item_id, string $snumber): string {
        return $item_id . '|' . $snumber;
    }

    public static function getFactoryNo(int $item_id, string $snumber): string {
        $conn = \ZDB\DB::getConnect();
        return (string)$conn->GetOne("SELECT factory_no FROM sqs_unit WHERE item_id = " . intval($item_id) . " AND snumber = " . $conn->qstr($snumber));
    }

    public static function setFactoryNo(int $item_id, string $snumber, string $factory_no): void {
        $conn = \ZDB\DB::getConnect();
        $factory_no = trim($factory_no);
        if ($factory_no === '') {
            $conn->Execute("DELETE FROM sqs_unit WHERE item_id = " . intval($item_id) . " AND snumber = " . $conn->qstr($snumber));
            return;
        }
        $conn->Execute("INSERT INTO sqs_unit (item_id, snumber, factory_no) VALUES (" . intval($item_id) . ", " . $conn->qstr($snumber) . ", " . $conn->qstr($factory_no) . ")
                        ON DUPLICATE KEY UPDATE factory_no = VALUES(factory_no)");
    }

    /**
     * Заводские номера всех единиц: ['item_id|snumber' => factory_no]
     */
    public static function factoryMap(): array {
        $map = [];
        foreach (\ZDB\DB::getConnect()->Execute("SELECT item_id, snumber, factory_no FROM sqs_unit") as $r) {
            $map[self::key((int)$r['item_id'], $r['snumber'])] = $r['factory_no'];
        }
        return $map;
    }

    /**
     * Условие SQL «единица s (store_stock) с товаром i подходит под поиск»: название, инвентарный или заводской номер
     */
    public static function searchWhere(string $text): string {
        $text = trim($text);
        if ($text === '') {
            return '1=1';
        }
        $conn = \ZDB\DB::getConnect();
        $like = $conn->qstr('%' . $text . '%');
        return "(i.itemname LIKE {$like} OR s.snumber LIKE {$like} OR EXISTS (SELECT 1 FROM sqs_unit u WHERE u.item_id = s.item_id AND u.snumber = s.snumber AND u.factory_no LIKE {$like}))";
    }

    /**
     * Единицы на складе для выдачи: ['item_id|snumber' => 'Модель, инв. 0114, зав. AB12']
     */
    public static function findInStock(int $store_id, string $text, int $limit = 30): array {
        $conn = \ZDB\DB::getConnect();
        $list = [];
        $sql = "SELECT s.item_id, s.snumber, i.itemname, u.factory_no
                FROM store_stock s
                JOIN items i ON i.item_id = s.item_id
                LEFT JOIN sqs_unit u ON u.item_id = s.item_id AND u.snumber = s.snumber
                WHERE s.store_id = " . intval($store_id) . " AND s.snumber <> '' AND " . self::searchWhere($text) . "
                GROUP BY s.item_id, s.snumber, i.itemname, u.factory_no
                HAVING SUM(s.qty) > 0
                ORDER BY CASE WHEN s.snumber = " . $conn->qstr(trim($text)) . " OR u.factory_no = " . $conn->qstr(trim($text)) . " THEN 0 ELSE 1 END, i.itemname, s.snumber
                LIMIT " . intval($limit);
        foreach ($conn->Execute($sql) as $r) {
            $list[self::key((int)$r['item_id'], $r['snumber'])] = self::label($r['itemname'], $r['snumber'], (string)$r['factory_no']);
        }
        return $list;
    }

    public static function label(string $itemname, string $snumber, string $factory_no): string {
        return $itemname . ', ' . __('sqs.unit.inv') . ' ' . $snumber . (strlen($factory_no) && $factory_no !== $snumber ? ', ' . __('sqs.unit.factory') . ' ' . $factory_no : '');
    }

    public const ON_DEMAND_YEAR = 2099;   // срок «до востребования» — договор без даты окончания или с датой в 2099 году и позже

    /**
     * Где сейчас каждая единица: [['item_id', 'itemname', 'cat_id', 'snumber', 'factory_no', 'kind' (store|out|off), 'stores',
     *   'document_id', 'document_number', 'date', 'customer_id', 'contract_id', 'contract_number', 'contract_date', 'enddate' (0 — до востребования), 'referrer'], ...]
     * На складе — есть остаток; иначе по последнему документу: списание (OutcomeItem) — списана, остальное — выдана.
     */
    public static function current(string $where = '1=1'): array {
        $conn = \ZDB\DB::getConnect();
        $units = $conn->Execute("SELECT s.item_id, i.itemname, i.cat_id, s.snumber, SUM(s.qty) AS qty, MAX(u.factory_no) AS factory_no,
                                        GROUP_CONCAT(CASE WHEN s.qty > 0 THEN st.storename END SEPARATOR ', ') AS stores
                                 FROM store_stock s
                                 JOIN items i ON i.item_id = s.item_id
                                 JOIN stores st ON st.store_id = s.store_id
                                 LEFT JOIN sqs_unit u ON u.item_id = s.item_id AND u.snumber = s.snumber
                                 WHERE s.snumber <> '' AND {$where}
                                 GROUP BY s.item_id, i.itemname, i.cat_id, s.snumber");
        $last = [];
        foreach ($conn->Execute("SELECT x.item_id, x.snumber, x.document_id, x.meta_name FROM (
                                     SELECT s.item_id, s.snumber, d.document_id, m.meta_name,
                                            ROW_NUMBER() OVER (PARTITION BY s.item_id, s.snumber ORDER BY d.document_date DESC, d.document_id DESC) rn
                                     FROM entrylist e
                                     JOIN store_stock s ON s.stock_id = e.stock_id
                                     JOIN items i ON i.item_id = s.item_id
                                     JOIN documents d ON d.document_id = e.document_id
                                     JOIN metadata m ON m.meta_id = d.meta_id
                                     WHERE s.snumber <> '' AND {$where}) x WHERE x.rn = 1") as $r) {
            $last[self::key((int)$r['item_id'], $r['snumber'])] = $r;
        }
        $list = [];
        foreach ($units as $u) {
            $row = ['item_id' => (int)$u['item_id'], 'itemname' => $u['itemname'], 'cat_id' => (int)$u['cat_id'], 'snumber' => $u['snumber'],
                    'factory_no' => (string)$u['factory_no'], 'kind' => 'store', 'stores' => (string)$u['stores'], 'document_id' => 0, 'document_number' => '',
                    'date' => 0, 'customer_id' => 0, 'contract_id' => 0, 'contract_number' => '', 'contract_date' => 0, 'enddate' => 0, 'referrer' => ''];
            $l = $last[self::key($row['item_id'], $row['snumber'])] ?? null;
            $doc = $l ? \App\Entity\Doc\Document::load($l['document_id']) : null;
            if ($doc) {
                $row['document_id'] = (int)$doc->document_id;
                $row['document_number'] = $doc->document_number;
                $row['date'] = $doc->document_date;
            }
            if ($u['qty'] <= 0) {
                $row['kind'] = $l && $l['meta_name'] == 'OutcomeItem' ? 'off' : 'out';
                if ($row['kind'] == 'out' && $doc) {
                    $row['customer_id'] = (int)$doc->customer_id;
                    $contract = intval($doc->headerdata['contract_id'] ?? 0) > 0 ? \App\Entity\Contract::load($doc->headerdata['contract_id']) : null;
                    if ($contract) {
                        $end = intval($contract->enddate);
                        $row['contract_id'] = (int)$contract->contract_id;
                        $row['contract_number'] = $contract->contract_number;
                        $row['contract_date'] = $contract->createdon;
                        $row['enddate'] = ($end > 0 && date('Y', $end) < self::ON_DEMAND_YEAR) ? $end : 0;
                        $row['referrer'] = $contract->shortdesc;
                    }
                }
            }
            $list[] = $row;
        }
        return $list;
    }

    /**
     * Выданные единицы со сроком, который истек или истекает в ближайшие $days дней, — для напоминаний; сначала самые просроченные
     */
    public static function dueSoon(int $days = 14): array {
        $limit = strtotime(date('Y-m-d')) + $days * 86400;
        $list = array_values(array_filter(self::current(), fn($u) => $u['kind'] == 'out' && $u['enddate'] > 0 && $u['enddate'] < $limit));
        usort($list, fn($a, $b) => $a['enddate'] <=> $b['enddate']);
        return $list;
    }
}
