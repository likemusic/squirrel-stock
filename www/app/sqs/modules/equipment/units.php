<?php

namespace App\SQS;

/**
 * SquirrelStock: единицы оборудования. Единица — товар с серийным номером Zippy (store_stock.snumber), номер — инвентарный.
 * Номер у позиции, которая ведется партиями (Item::serialMode() 1 или 2), — партия расходника, а не единица: условие
 * SerialStock::unitSql(). Заводской номер хранится отдельно (таблица sqs_unit, schema.sql модуля): у Zippy у единицы один номер.
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
                WHERE s.store_id = " . intval($store_id) . " AND " . SerialStock::unitSql() . " AND " . self::searchWhere($text) . "
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
     * Поля документов одним запросом, без Document::load: загрузка разбирает весь документ, а приход из ведомости 1С —
     * одна накладная на сотни единиц, и отчеты по единицам загружали ее заново на каждую единицу (17,8 с на объеме хосписа).
     * [document_id => ['document_number', 'document_date' (метка времени), 'customer_id', 'contract_id' (из заголовка), 'notes']]
     */
    public static function docs(array $ids): array {
        $ids = array_unique(array_filter(array_map('intval', $ids)));
        if (count($ids) == 0) {
            return [];
        }
        $list = [];
        foreach (\ZDB\DB::getConnect()->Execute("SELECT document_id, document_number, document_date, customer_id, notes,
                     CAST(REGEXP_SUBSTR(REGEXP_SUBSTR(SUBSTRING_INDEX(content, '</header>', 1), '<contract_id>[0-9]+</contract_id>'), '[0-9]+') AS UNSIGNED) AS contract_id
                     FROM documents WHERE document_id IN (" . implode(',', $ids) . ")") as $r) {
            $list[(int)$r['document_id']] = ['document_number' => $r['document_number'], 'document_date' => strtotime($r['document_date']), 'customer_id' => (int)$r['customer_id'],
                                             'contract_id' => (int)$r['contract_id'], 'notes' => (string)$r['notes']];
        }
        return $list;
    }

    // контрагенты одним запросом: [customer_id => Customer]
    public static function customers(array $ids): array {
        $ids = array_unique(array_filter(array_map('intval', $ids)));
        return count($ids) ? \App\Entity\Customer::find('customer_id IN (' . implode(',', $ids) . ')') : [];
    }

    // «Договор заключен с» (Contract::sqs_holder): представитель получателя, обычно родитель; '' — договор с самим получателем
    public static function holder(int $contract_id): string {
        $c = $contract_id > 0 ? \App\Entity\Contract::load($contract_id) : null;
        return $c ? trim((string)$c->sqs_holder) : '';
    }

    // дописка к строке договора в отчетах: «, заключен с …» или ''
    public static function holderNote(?\App\Entity\Contract $c): string {
        $h = $c ? trim((string)$c->sqs_holder) : '';
        return $h === '' ? '' : __('sqs.contract.holder_note', ['name' => $h]);
    }

    // договоры одним запросом: [contract_id => Contract]
    public static function contracts(array $ids): array {
        $ids = array_unique(array_filter(array_map('intval', $ids)));
        return count($ids) ? \App\Entity\Contract::find('contract_id IN (' . implode(',', $ids) . ')') : [];
    }

    /**
     * Где сейчас каждая единица: [['item_id', 'itemname', 'cat_id', 'snumber', 'factory_no', 'kind' (store|out|off), 'stores',
     *   'document_id', 'document_number', 'date', 'customer_id', 'contract_id', 'contract_number', 'contract_date', 'enddate' (0 — до востребования), 'referrer'], ...]
     * На складе — есть остаток; иначе по последнему расходному документу (движение со знаком минус): списание (OutcomeItem) — списана,
     * остальное — выдана. Только расходные: приход единицы бывает датирован позже ее выдачи (ведомость 1С загружена после истории выдач).
     */
    public static function current(string $where = '1=1'): array {
        $conn = \ZDB\DB::getConnect();
        $unit = SerialStock::unitSql();   // номер у позиции, которая ведется партиями, — партия расходника, не единица
        $units = $conn->Execute("SELECT s.item_id, i.itemname, i.cat_id, s.snumber, SUM(s.qty) AS qty, MAX(u.factory_no) AS factory_no,
                                        GROUP_CONCAT(CASE WHEN s.qty > 0 THEN st.storename END SEPARATOR ', ') AS stores
                                 FROM store_stock s
                                 JOIN items i ON i.item_id = s.item_id
                                 JOIN stores st ON st.store_id = s.store_id
                                 LEFT JOIN sqs_unit u ON u.item_id = s.item_id AND u.snumber = s.snumber
                                 WHERE {$unit} AND {$where}
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
                                     WHERE {$unit} AND e.quantity < 0 AND {$where}) x WHERE x.rn = 1") as $r) {
            $last[self::key((int)$r['item_id'], $r['snumber'])] = $r;
        }
        $docs = self::docs(array_column($last, 'document_id'));
        $contracts = self::contracts(array_column($docs, 'contract_id'));
        $list = [];
        foreach ($units as $u) {
            $row = ['item_id' => (int)$u['item_id'], 'itemname' => $u['itemname'], 'cat_id' => (int)$u['cat_id'], 'snumber' => $u['snumber'],
                    'factory_no' => (string)$u['factory_no'], 'kind' => 'store', 'stores' => (string)$u['stores'], 'document_id' => 0, 'document_number' => '',
                    'date' => 0, 'customer_id' => 0, 'contract_id' => 0, 'contract_number' => '', 'contract_date' => 0, 'enddate' => 0, 'referrer' => ''];
            $l = $last[self::key($row['item_id'], $row['snumber'])] ?? null;
            $doc = $l ? ($docs[(int)$l['document_id']] ?? null) : null;
            if ($doc) {
                $row['document_id'] = (int)$l['document_id'];
                $row['document_number'] = $doc['document_number'];
                $row['date'] = $doc['document_date'];
            }
            if ($u['qty'] <= 0) {
                $row['kind'] = $l && $l['meta_name'] == 'OutcomeItem' ? 'off' : 'out';
                if ($row['kind'] == 'out' && $doc) {
                    $row['customer_id'] = $doc['customer_id'];
                    $contract = $contracts[$doc['contract_id']] ?? null;
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
     * Мелкое оборудование на руках (ответ хосписа 1.13): позиции без серийного номера, выданные расходной накладной по договору
     * (так их заводит импорт истории, App\SQS\EqHistory). На руках = выдано по договорам минус возвращено (возврат по такой
     * выдаче или с договором), по получателю и позиции; в списке строки, где осталось больше нуля:
     * [['customer_id', 'item_id', 'itemname', 'qty', 'document_id', 'document_number', 'date', 'contract_id'], ...] — документ и договор последней выдачи.
     * $customer_id > 0 — только этот получатель (карточка получателя).
     */
    public static function smallOnHand(int $cat = 0, int $customer_id = 0): array {
        $conn = \ZDB\DB::getConnect();
        // без номера или с номером партии (позиция ведется партиями, App\SQS\SerialStock::batchSql)
        $where = "(s.snumber = '' OR " . SerialStock::batchSql() . ") AND m.meta_name IN ('GoodsIssue', 'ReturnIssue')";
        if ($cat > 0) {
            $where .= ' AND ' . Categories::sqlIn($cat);
        }
        $where .= $customer_id > 0 ? ' AND d.customer_id = ' . intval($customer_id) : ' AND d.customer_id > 0';
        // договор — из заголовка документа (<contract_id>N</contract_id>), чтобы не загружать каждую выдачу расходников
        $docs = $conn->Execute("SELECT d.document_id, d.parent_id, d.customer_id, d.document_date, d.document_number, m.meta_name, s.item_id, i.itemname,
                                       SUM(e.quantity) AS qty,
                                       CAST(REGEXP_SUBSTR(REGEXP_SUBSTR(d.content, '<contract_id>[0-9]+</contract_id>'), '[0-9]+') AS UNSIGNED) AS contract_id
                                FROM entrylist e
                                JOIN store_stock s ON s.stock_id = e.stock_id
                                JOIN items i ON i.item_id = s.item_id
                                JOIN documents d ON d.document_id = e.document_id
                                JOIN metadata m ON m.meta_id = d.meta_id
                                WHERE {$where}
                                GROUP BY d.document_id, d.parent_id, d.customer_id, d.document_date, d.document_number, m.meta_name, s.item_id, i.itemname
                                ORDER BY d.document_date, d.document_id, s.item_id");
        $rows = [];
        $contractOf = [];   // выдача по договору: document_id => contract_id
        foreach ($docs as $d) {
            if ($d['meta_name'] == 'GoodsIssue') {
                if (intval($d['contract_id']) <= 0) {
                    continue;   // выдача расходников
                }
                $contractOf[$d['document_id']] = intval($d['contract_id']);
            } elseif (intval($d['contract_id']) <= 0 && !isset($contractOf[$d['parent_id']])) {
                continue;   // возврат не по договору
            }
            $k = $d['customer_id'] . '|' . $d['item_id'];
            $r = $rows[$k] ?? ['customer_id' => intval($d['customer_id']), 'item_id' => intval($d['item_id']), 'itemname' => $d['itemname'], 'qty' => 0, 'last' => null];
            $r['qty'] -= floatval($d['qty']);   // выдача — минус по складу, возврат — плюс
            if ($d['meta_name'] == 'GoodsIssue') {
                $r['last'] = $d;
            }
            $rows[$k] = $r;
        }
        $out = [];
        foreach ($rows as $r) {
            if ($r['qty'] <= 0 || $r['last'] == null) {
                continue;
            }
            $out[] = ['customer_id' => $r['customer_id'], 'item_id' => $r['item_id'], 'itemname' => $r['itemname'], 'qty' => $r['qty'],
                      'document_id' => intval($r['last']['document_id']), 'document_number' => $r['last']['document_number'],
                      'date' => strtotime($r['last']['document_date']), 'contract_id' => intval($r['last']['contract_id'])];
        }
        return $out;
    }

    /**
     * Выданные единицы со сроком, который истек или истекает в ближайшие $days дней, — для напоминаний; сначала самые просроченные.
     * Мелкое оборудование на руках (smallOnHand) — по сроку договора последней выдачи, строкой с количеством ('qty'; у единиц его нет).
     */
    public static function dueSoon(int $days = 14): array {
        $limit = strtotime(date('Y-m-d')) + $days * 86400;
        $list = array_values(array_filter(self::current(), fn($u) => $u['kind'] == 'out' && $u['enddate'] > 0 && $u['enddate'] < $limit));
        $small = self::smallOnHand();
        $contracts = self::contracts(array_column($small, 'contract_id'));
        foreach ($small as $r) {
            $c = $contracts[$r['contract_id']] ?? null;
            $end = $c ? intval($c->enddate) : 0;
            if ($end <= 0 || date('Y', $end) >= self::ON_DEMAND_YEAR || $end >= $limit) {
                continue;
            }
            $list[] = ['item_id' => $r['item_id'], 'itemname' => $r['itemname'], 'snumber' => '', 'factory_no' => '', 'qty' => $r['qty'],
                       'customer_id' => $r['customer_id'], 'contract_id' => (int)$c->contract_id, 'contract_number' => $c->contract_number, 'enddate' => $end];
        }
        usort($list, fn($a, $b) => $a['enddate'] <=> $b['enddate']);
        return $list;
    }
}
