<?php

namespace App\Pages\Report;

use App\Helper as H;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Panel;

/**
 * SquirrelStock: оборудование — где сейчас каждая единица (товар с серийным, то есть инвентарным, номером).
 * Есть остаток на складе — единица на этом складе (основной склад, «Ремонт», «К списанию» — состояние задается складом).
 * Остатка нет — по последнему документу: выдана получателю (договор, срок, кем направлен — краткое описание договора)
 * или списана. Срок «до востребования» — договор без даты окончания или с датой в 2099 году и позже.
 */
class EquipmentWhere extends \App\Pages\Base
{
    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowReport('EquipmentWhere')) {
            return;
        }

        $this->add(new Form('filter'));
        $this->filter->add(new TextInput('searchtext'));
        $this->filter->add(new DropDownChoice('cat', [0 => __('common.vsi')] + \App\Entity\Category::getList(true), 0));
        $this->filter->add(new DropDownChoice('state', [
            'active' => __('report.equipmentwhere.state_active'),
            'out'    => __('report.equipmentwhere.state_out'),
            'store'  => __('report.equipmentwhere.state_store'),
            'off'    => __('report.equipmentwhere.state_off'),
            'all'    => __('common.vsi'),
        ], 'active'));
        $this->filter->add(new SubmitButton('onreport'))->onClick($this, 'OnSubmit');

        $this->add(new Panel('detail'))->setVisible(false);
        $this->detail->add(new Label('preview'));
    }

    public function OnSubmit($sender) {
        $html = $this->generateReport();
        $this->detail->preview->setText($html, true);
        // кнопка «Продлить» — только на экране, не в печати и Excel
        $export = preg_replace('#\s*<a class="sqs-extend[^"]*"[^>]*>.*?</a>#su', '', $html);
        \App\Session::getSession()->setExportReport("<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>" . $export . "</body></html>");
        $this->detail->setVisible(true);
    }

    // продлить срок договора (App\Pages\Service\EquipmentExtend) — для выданного по договору
    private static function extendUrl($contract_id): string {
        return \App\ACL::checkShowSer('EquipmentIssue', false) ? '/index.php?p=App/Pages/Service/EquipmentExtend&arg=' . intval($contract_id) . '/where' : '';
    }

    private function generateReport() {
        $conn = \ZDB\DB::getConnect();
        $where = "s.snumber <> ''";
        $text = mb_strtolower(trim($this->filter->searchtext->getText()));
        $cat = intval($this->filter->cat->getValue());
        if ($cat > 0) {
            $where .= ' AND ' . \App\SQS\Categories::sqlIn($cat);
        }
        $state = $this->filter->state->getValue();

        $units = $conn->Execute("SELECT s.item_id, i.itemname, s.snumber, SUM(s.qty) AS qty,
                                        GROUP_CONCAT(CASE WHEN s.qty > 0 THEN st.storename END SEPARATOR ', ') AS stores
                                 FROM store_stock s
                                 JOIN items i ON i.item_id = s.item_id
                                 JOIN stores st ON st.store_id = s.store_id
                                 WHERE {$where}
                                 GROUP BY s.item_id, i.itemname, s.snumber");
        // последний документ по каждой единице
        $last = [];
        foreach ($conn->Execute("SELECT x.item_id, x.snumber, x.document_id, x.meta_name FROM (
                                     SELECT s.item_id, s.snumber, d.document_id, m.meta_name,
                                            ROW_NUMBER() OVER (PARTITION BY s.item_id, s.snumber ORDER BY d.document_date DESC, d.document_id DESC) rn
                                     FROM entrylist e
                                     JOIN store_stock s ON s.stock_id = e.stock_id
                                     JOIN items i ON i.item_id = s.item_id
                                     JOIN documents d ON d.document_id = e.document_id
                                     JOIN metadata m ON m.meta_id = d.meta_id
                                     WHERE {$where}) x WHERE x.rn = 1") as $r) {
            $last[$r['item_id'] . '|' . $r['snumber']] = $r;
        }

        // документы, получатели и договоры — пакетно (App\SQS\Units::docs: без загрузки документа на каждую единицу)
        $docs = \App\SQS\Units::docs(array_column($last, 'document_id'));
        $customers = \App\SQS\Units::customers(array_column($docs, 'customer_id'));
        $contracts = \App\SQS\Units::contracts(array_column($docs, 'contract_id'));

        $detail = [];
        $today = strtotime(date('Y-m-d'));
        $factory = \App\SQS\Units::factoryMap();   // заводские номера единиц (sqs_unit)
        foreach ($units as $u) {
            $row = ['itemname' => $u['itemname'], 'snumber' => $u['snumber'], 'factory' => $factory[\App\SQS\Units::key((int)$u['item_id'], $u['snumber'])] ?? '', 'where' => '', 'customer' => '', 'phone' => '', 'address' => '',
                    'contract' => '', 'date' => '', 'term' => '', 'sent' => '', 'overdue' => false, 'document' => '', 'extend' => ''];
            $l = $last[$u['item_id'] . '|' . $u['snumber']] ?? null;
            $doc = $l ? ($docs[(int)$l['document_id']] ?? null) : null;
            if ($doc) {
                $row['date'] = H::fd($doc['document_date']);
                $row['document'] = $doc['document_number'];
            }
            if ($u['qty'] > 0) {
                $kind = 'store';
                $row['where'] = $u['stores'];
            } elseif ($l && $l['meta_name'] == 'OutcomeItem') {
                $kind = 'off';
                $row['where'] = __('report.equipmentwhere.written_off');
            } else {
                $kind = 'out';
                $row['where'] = __('report.equipmentwhere.issued');
                if ($doc && $doc['customer_id'] > 0) {
                    $c = $customers[$doc['customer_id']] ?? null;
                    $row['customer'] = $c->customer_name ?? '';
                    $row['phone'] = $c->phone ?? '';
                    $row['address'] = $c->address ?? '';
                }
                $contract = $doc ? ($contracts[$doc['contract_id']] ?? null) : null;
                if ($contract) {
                    $row['contract'] = __('report.equipmentwhere.contract_of', ['number' => $contract->contract_number, 'date' => H::fd($contract->createdon)]);
                    $row['sent'] = $contract->shortdesc;
                    $end = intval($contract->enddate);
                    $row['term'] = ($end == 0 || date('Y', $end) >= 2099) ? __('report.equipmentwhere.until_demand') : H::fd($end);
                    $row['overdue'] = $end > 0 && date('Y', $end) < 2099 && $end < $today;
                    $row['extend'] = self::extendUrl($contract->contract_id);
                }
            }
            if ($state == 'active' && $kind == 'off' || $state != 'active' && $state != 'all' && $state != $kind) {
                continue;
            }
            // поиск: модель, инвентарный или заводской номер, получатель
            if ($text != '' && !str_contains(mb_strtolower($row['itemname'] . ' ' . $row['snumber'] . ' ' . $row['factory'] . ' ' . $row['customer']), $text)) {
                continue;
            }
            $row['order'] = ['out' => 0, 'store' => 1, 'off' => 2][$kind];
            $detail[] = $row;
        }
        // сначала выданное (у кого что), затем склады, затем списанное
        usort($detail, fn($a, $b) => [$a['order'], $a['where'], $a['customer'], $a['itemname'], $a['snumber']] <=> [$b['order'], $b['where'], $b['customer'], $b['itemname'], $b['snumber']]);

        $small = in_array($state, ['active', 'out', 'all']) ? $this->smallOnHand($cat, $text) : [];

        $report = new \App\Report('report/equipmentwhere.tpl');
        return $report->generate(['_detail' => $detail, 'cnt' => count($detail), 'date' => H::fd(time()),
                                  '_small' => $small, 'hassmall' => count($small) > 0, 'smallcnt' => H::fqty(array_sum(array_column($small, 'n')))] + $this->summary($detail));
    }

    /**
     * Мелкое оборудование на руках (ответ хосписа 1.13): позиции без серийного номера, выданные расходной накладной по договору
     * (так их заводит импорт истории, App\SQS\EqHistory). На руках = выдано по договорам минус возвращено (возврат по такой
     * выдаче или с договором), по получателю и позиции; показываются строки, где осталось больше нуля.
     * Договор, срок и «кем направлен» — по последней выдаче.
     */
    private function smallOnHand(int $cat, string $text): array {
        $conn = \ZDB\DB::getConnect();
        $where = "s.snumber = '' AND m.meta_name IN ('GoodsIssue', 'ReturnIssue')";
        if ($cat > 0) {
            $where .= ' AND ' . \App\SQS\Categories::sqlIn($cat);
        }
        // договор — из заголовка документа (<contract_id>N</contract_id>), чтобы не загружать каждую выдачу расходников
        $docs = $conn->Execute("SELECT d.document_id, d.parent_id, d.customer_id, d.document_date, d.document_number, m.meta_name, s.item_id, i.itemname,
                                       SUM(e.quantity) AS qty,
                                       CAST(REGEXP_SUBSTR(REGEXP_SUBSTR(d.content, '<contract_id>[0-9]+</contract_id>'), '[0-9]+') AS UNSIGNED) AS contract_id
                                FROM entrylist e
                                JOIN store_stock s ON s.stock_id = e.stock_id
                                JOIN items i ON i.item_id = s.item_id
                                JOIN documents d ON d.document_id = e.document_id
                                JOIN metadata m ON m.meta_id = d.meta_id
                                WHERE {$where} AND d.customer_id > 0
                                GROUP BY d.document_id, d.parent_id, d.customer_id, d.document_date, d.document_number, m.meta_name, s.item_id, i.itemname
                                ORDER BY d.document_date, d.document_id");
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
            $r = $rows[$k] ?? ['customer_id' => intval($d['customer_id']), 'itemname' => $d['itemname'], 'qty' => 0, 'last' => null];
            $r['qty'] -= floatval($d['qty']);   // выдача — минус по складу, возврат — плюс
            if ($d['meta_name'] == 'GoodsIssue') {
                $r['last'] = $d;
            }
            $rows[$k] = $r;
        }

        $rows = array_filter($rows, fn($r) => $r['qty'] > 0 && $r['last'] != null);
        $customers = \App\SQS\Units::customers(array_column($rows, 'customer_id'));
        $contracts = \App\SQS\Units::contracts(array_map(fn($r) => $r['last']['contract_id'], $rows));
        $out = [];
        foreach ($rows as $r) {
            $c = $customers[$r['customer_id']] ?? null;
            $row = ['itemname' => $r['itemname'], 'customer' => $c->customer_name ?? '', 'phone' => $c->phone ?? '', 'address' => $c->address ?? '',
                    'qty' => H::fqty($r['qty']), 'date' => H::fd(strtotime($r['last']['document_date'])), 'document' => $r['last']['document_number'],
                    'contract' => '', 'term' => '', 'sent' => '', 'overdue' => false, 'extend' => ''];
            $contract = $contracts[intval($r['last']['contract_id'])] ?? null;
            if ($contract) {
                $end = intval($contract->enddate);
                $row['contract'] = __('report.equipmentwhere.contract_of', ['number' => $contract->contract_number, 'date' => H::fd($contract->createdon)]);
                $row['sent'] = $contract->shortdesc;
                $row['term'] = ($end == 0 || date('Y', $end) >= 2099) ? __('report.equipmentwhere.until_demand') : H::fd($end);
                $row['overdue'] = $end > 0 && date('Y', $end) < 2099 && $end < strtotime(date('Y-m-d'));
                $row['extend'] = self::extendUrl($contract->contract_id);
            }
            // поиск тот же, что у единиц: позиция или получатель
            if ($text != '' && !str_contains(mb_strtolower($row['itemname'] . ' ' . $row['customer']), $text)) {
                continue;
            }
            $row['n'] = $r['qty'];
            $out[] = $row;
        }
        usort($out, fn($a, $b) => [$a['customer'], $a['itemname']] <=> [$b['customer'], $b['itemname']]);
        return $out;
    }

    /**
     * Итог по моделям: сколько единиц выдано, на каждом складе (склады «Ремонт», «К списанию» — состояние единицы), списано, всего.
     * Считается по тем же строкам, что и список (с учетом состояния и поиска); колонки без единиц не показываются.
     */
    private function summary(array $detail): array {
        $models = [];
        $stores = [];
        foreach ($detail as $row) {
            $col = $row['order'] == 1 ? 'store:' . $row['where'] : ['issued', '', 'off'][$row['order']];
            if ($row['order'] == 1) {
                $stores[$col] = $row['where'];
            }
            $models[$row['itemname']][$col] = ($models[$row['itemname']][$col] ?? 0) + 1;
        }
        ksort($models, SORT_NATURAL | SORT_FLAG_CASE);
        uasort($stores, 'strnatcasecmp');

        $cols = [];
        if (array_filter($models, fn($m) => isset($m['issued']))) {
            $cols['issued'] = __('report.equipmentwhere.col_issued');
        }
        $cols += $stores;
        if (array_filter($models, fn($m) => isset($m['off']))) {
            $cols['off'] = __('report.equipmentwhere.col_off');
        }

        $rows = [];
        foreach ($models as $name => $m) {
            $cells = [];
            foreach (array_keys($cols) as $c) {
                $cells[] = ['v' => $m[$c] ?? ''];
            }
            $rows[] = ['name' => $name, 'cells' => $cells, 'total' => array_sum($m)];
        }

        return ['_scols' => array_map(fn($n) => ['name' => $n], array_values($cols)), '_srows' => $rows, 'scolspan' => count($cols) + 2, 'hassummary' => count($rows) > 0];
    }
}
