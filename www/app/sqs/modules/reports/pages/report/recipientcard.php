<?php

namespace App\Pages\Report;

use App\Entity\Customer;
use App\Entity\Doc\Document;
use App\Helper as H;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Label;
use Zippy\Html\Panel;

/**
 * SquirrelStock: карточка получателя — все по одному подопечному на одной странице (ответ на вопрос «сколько выдано одному подопечному»):
 * контакты и «кем направлен» (краткое описание договоров), оборудование на руках (единицы и мелкое, договор, срок, «Продлить»),
 * выданные расходники за период (по позициям и по месяцам, за вычетом возвратов), история выдач и возвратов со ссылкой на документ,
 * когда можно следующую выдачу по интервалам выдачи.
 * Расходники — позиции без серийного номера, выданные не по договору (выданное по договору — оборудование). Возврат идет в месяц выдачи,
 * на основании которой создан (как в «Получателях по месяцам»). Разделы оборудования и интервалов — когда включены модули equipment и consumables.
 * Адрес: Report/RecipientCard&arg=<customer_id>; ссылка у имени получателя в журнале выдач и в справочнике контрагентов (событие customerCardUrl).
 */
class RecipientCard extends \App\Pages\Base
{
    public function __construct($customer_id = 0) {
        parent::__construct();
        if (false == \App\ACL::checkShowReport('RecipientCard')) {
            return;
        }

        $this->add(new Form('filter'));
        $this->filter->add(new AutocompleteTextInput('cust'))->onText($this, 'OnAutoCustomer');
        $this->filter->add(new Date('from', strtotime(date('Y-m-01', strtotime('-11 month')))));
        $this->filter->add(new Date('to', time()));
        $this->filter->add(new SubmitButton('onreport'))->onClick($this, 'OnSubmit');

        $this->add(new Panel('detail'))->setVisible(false);
        $this->detail->add(new Label('preview'));

        $c = intval($customer_id) > 0 ? Customer::load(intval($customer_id)) : null;
        if ($c != null) {
            $this->filter->cust->setKey($c->customer_id);
            $this->filter->cust->setText($c->customer_name);
            $this->OnSubmit(null);
        }
    }

    public function OnAutoCustomer($sender) {
        return Customer::getList(trim($sender->getText()));
    }

    /**
     * Ссылка на карточку для журнала выдач и справочника контрагентов (App\SQS\Hooks, событие customerCardUrl); '' — нет права на отчет
     */
    public static function url($customer_id): string {
        if (intval($customer_id) <= 0 || !\App\ACL::checkShowReport('RecipientCard', false)) {
            return '';
        }
        return '/index.php?p=App/Pages/Report/RecipientCard&arg=' . intval($customer_id);
    }

    public function OnSubmit($sender) {
        $c = Customer::load(intval($this->filter->cust->getKey()));
        if ($c == null) {
            $this->setError(__('report.recipientcard.no_customer'));
            $this->detail->setVisible(false);
            return;
        }
        $html = $this->generateReport($c);
        $this->detail->preview->setText($html, true);
        // кнопка «Продлить» и ссылки на документы — только на экране, не в печати и Excel
        $export = preg_replace('#\s*<a class="sqs-extend[^"]*"[^>]*>.*?</a>#su', '', $html);
        $export = preg_replace('#<a class="sqs-doc"[^>]*>(.*?)</a>#su', '$1', $export);
        \App\Session::getSession()->setExportReport("<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>" . $export . "</body></html>");
        $this->detail->setVisible(true);
    }

    private function generateReport(Customer $c): string {
        $from = date('Y-m-d', $this->filter->from->getDate());
        $to = date('Y-m-d', $this->filter->to->getDate());
        $cid = intval($c->customer_id);
        $modules = \App\SQS\Modules::names();

        $data = [
            'name'     => $c->customer_name,
            'phone'    => (string)$c->phone,
            'email'    => (string)$c->email,
            'address'  => (string)$c->address,
            'comment'  => trim(strip_tags((string)$c->comment)),
            'tags'     => implode(', ', \App\Entity\Tag::getTags(\App\Entity\Tag::TYPE_CUSTOMER, $cid)),
            'period'   => H::fd(strtotime($from)) . ' - ' . H::fd(strtotime($to)),
            'date'     => H::fd(time()),
            '_sent'    => $this->referrers($cid),
            'equipment' => in_array('equipment', $modules, true),
            'intervals' => in_array('consumables', $modules, true),
        ];
        $data['hassent'] = count($data['_sent']) > 0;
        if ($data['equipment']) {
            $data['_units'] = $this->units($cid);
            $data['hasunits'] = count($data['_units']) > 0;
        }
        $data += $this->consumables($cid, $from, $to);
        $data['_history'] = $this->history($cid, $from, $to);
        $data['hashistory'] = count($data['_history']) > 0;
        if ($data['intervals']) {
            $data['_next'] = $this->nextIssues($cid);
            $data['hasnext'] = count($data['_next']) > 0;
        }

        $report = new \App\Report('report/recipientcard.tpl');
        return $report->generate($data);
    }

    /**
     * «Кем направлен» — краткое описание договоров получателя, сначала последний договор
     */
    private function referrers(int $cid): array {
        $out = [];
        foreach (\App\Entity\Contract::find('customer_id = ' . $cid, 'createdon desc, contract_id desc') as $ct) {
            if (trim((string)$ct->shortdesc) === '') {
                continue;
            }
            $out[] = ['sent' => $ct->shortdesc, 'contract' => __('report.recipientcard.contract_of', ['number' => $ct->contract_number, 'date' => H::fd($ct->createdon)])];
        }
        return $out;
    }

    // строка договора и срока для оборудования на руках
    private static function contractCells(int $contract_id): array {
        $row = ['contract' => '', 'term' => '', 'sent' => '', 'overdue' => false, 'extend' => ''];
        $ct = $contract_id > 0 ? \App\Entity\Contract::load($contract_id) : null;
        if ($ct == null) {
            return $row;
        }
        $end = intval($ct->enddate);
        $ondemand = $end == 0 || date('Y', $end) >= \App\SQS\Units::ON_DEMAND_YEAR;
        $row['contract'] = __('report.recipientcard.contract_of', ['number' => $ct->contract_number, 'date' => H::fd($ct->createdon)]) . \App\SQS\Units::holderNote($ct);
        $row['sent'] = $ct->shortdesc;
        $row['term'] = $ondemand ? __('report.recipientcard.until_demand') : H::fd($end);
        $row['overdue'] = !$ondemand && $end < strtotime(date('Y-m-d'));
        if (\App\ACL::checkShowSer('EquipmentIssue', false)) {
            $row['extend'] = '/index.php?p=App/Pages/Service/EquipmentExtend&arg=' . intval($ct->contract_id) . '/card';
        }
        return $row;
    }

    /**
     * Оборудование на руках: единицы (App\SQS\Units::current) и мелкое (Units::smallOnHand), выданные этому получателю
     */
    private function units(int $cid): array {
        $out = [];
        foreach (\App\SQS\Units::current() as $u) {
            if ($u['kind'] != 'out' || $u['customer_id'] != $cid) {
                continue;
            }
            $out[] = ['itemname' => $u['itemname'], 'snumber' => $u['snumber'], 'factory' => $u['factory_no'] !== $u['snumber'] ? $u['factory_no'] : '', 'qty' => '1',
                      'date' => H::fd($u['date']), 'document' => $u['document_number'], 'docurl' => self::docUrl($u['document_id'])] + self::contractCells($u['contract_id']);
        }
        foreach (\App\SQS\Units::smallOnHand(0, $cid) as $s) {
            $out[] = ['itemname' => $s['itemname'], 'snumber' => '', 'factory' => '', 'qty' => H::fqty($s['qty']),
                      'date' => H::fd($s['date']), 'document' => $s['document_number'], 'docurl' => self::docUrl($s['document_id'])] + self::contractCells($s['contract_id']);
        }
        usort($out, fn($a, $b) => [$a['itemname'], $a['snumber']] <=> [$b['itemname'], $b['snumber']]);
        return $out;
    }

    private static function docUrl($document_id): string {
        return '/index.php?p=App/Pages/Register/DocList&arg=' . intval($document_id);
    }

    /**
     * Расходники за период: позиции без серийного номера, выданные не по договору, за вычетом возвратов — по позициям и по месяцам
     */
    private function consumables(int $cid, string $from, string $to): array {
        $conn = \ZDB\DB::getConnect();
        $contract = fn($t) => "CAST(REGEXP_SUBSTR(REGEXP_SUBSTR({$t}.content, '<contract_id>[0-9]+</contract_id>'), '[0-9]+') AS UNSIGNED)";
        $sql = "SELECT d.document_id, m.meta_name, i.item_id, i.itemname, i.msr, SUM(e.quantity) AS qty,
                       DATE_FORMAT(COALESCE(base.document_date, d.document_date), '%Y-%m') AS ym,
                       {$contract('d')} AS contract_id, {$contract('base')} AS base_contract
                FROM entrylist e
                JOIN documents d ON d.document_id = e.document_id
                JOIN metadata m ON m.meta_id = d.meta_id
                LEFT JOIN documents base ON m.meta_name = 'ReturnIssue' AND base.document_id = d.parent_id
                JOIN store_stock st ON st.stock_id = e.stock_id
                JOIN items i ON i.item_id = st.item_id
                WHERE m.meta_name IN ('GoodsIssue', 'ReturnIssue') AND d.customer_id = {$cid}
                  AND (st.snumber = '' OR " . \App\SQS\SerialStock::batchSql() . ")
                  AND d.document_date BETWEEN '{$from}' AND '{$to}'
                GROUP BY d.document_id, m.meta_name, i.item_id, i.itemname, i.msr, ym, contract_id, base_contract";

        $months = [];
        for ($t = strtotime(date('Y-m-01', strtotime($from))); $t <= strtotime($to); $t = strtotime('+1 month', $t)) {
            $months[date('Y-m', $t)] = date('m.Y', $t);
        }
        $items = [];
        $docs = [];
        foreach ($conn->Execute($sql) as $r) {
            if (intval($r['contract_id']) > 0 || intval($r['base_contract']) > 0) {
                continue;   // по договору — оборудование
            }
            $id = intval($r['item_id']);
            $it = $items[$id] ?? ['itemname' => $r['itemname'], 'msr' => $r['msr'], 'issued' => 0, 'returned' => 0, 'm' => []];
            $q = -floatval($r['qty']);   // выдача — минус по складу, возврат — плюс
            if ($r['meta_name'] == 'GoodsIssue') {
                $it['issued'] += $q;
                $docs[$r['document_id']] = $r['ym'];
            } else {
                $it['returned'] -= $q;
            }
            $it['m'][$r['ym']] = ($it['m'][$r['ym']] ?? 0) + $q;
            $items[$id] = $it;
        }
        uasort($items, fn($a, $b) => strnatcasecmp($a['itemname'], $b['itemname']));
        // месяцы — с первого, в котором была выдача: пустые в начале периода не растягивают таблицу
        $used = array_merge(array_values($docs), ...array_map(fn($it) => array_keys(array_filter($it['m'], fn($v) => $v != 0)), array_values($items)));
        if ($used) {
            $first = min($used);
            $months = array_filter($months, fn($ym) => $ym >= $first, ARRAY_FILTER_USE_KEY);
        }

        $rows = [];
        $mrows = [];
        $coltotal = array_fill_keys(array_keys($months), 0);
        $total = 0;
        foreach ($items as $it) {
            $net = $it['issued'] - $it['returned'];
            $total += $net;
            $rows[] = ['itemname' => $it['itemname'], 'msr' => $it['msr'], 'issued' => H::fqty($it['issued']),
                       'returned' => $it['returned'] != 0 ? H::fqty($it['returned']) : '', 'net' => H::fqty($net)];
            $cells = [];
            foreach (array_keys($months) as $ym) {
                $v = $it['m'][$ym] ?? 0;
                $coltotal[$ym] += $v;
                $cells[] = ['v' => $v != 0 ? H::fqty($v) : ''];
            }
            $mrows[] = ['itemname' => $it['itemname'], 'cells' => $cells, 'net' => H::fqty($net)];
        }
        $mdocs = array_count_values($docs);

        return [
            '_items'    => $rows,
            'hasitems'  => count($rows) > 0,
            'issues'    => count($docs),
            'total'     => H::fqty($total),
            '_months'   => array_map(fn($m) => ['m' => $m], array_values($months)),
            '_mrows'    => $mrows,
            '_mtotals'  => array_map(fn($ym) => ['n' => $coltotal[$ym] != 0 ? H::fqty($coltotal[$ym]) : ''], array_keys($months)),
            '_mdocs'    => array_map(fn($ym) => ['n' => $mdocs[$ym] ?? ''], array_keys($months)),
        ];
    }

    /**
     * Выдачи и возвраты получателя за период, новые сверху; что выдано — из строк документа
     */
    private function history(int $cid, string $from, string $to): array {
        $out = [];
        $docs = Document::find("customer_id = {$cid} AND meta_name IN ('GoodsIssue', 'ReturnIssue') AND document_date BETWEEN '{$from}' AND '{$to}'",
                               'document_date desc, document_id desc');
        foreach ($docs as $doc) {
            $lines = [];
            foreach ($doc->unpackDetails('detaildata') as $item) {
                $sn = strlen((string)($item->snumber ?? '')) ? ' (' . __('report.recipientcard.inv_short') . ' ' . $item->snumber . ')' : '';
                $lines[] = ['text' => $item->itemname . $sn . ' — ' . H::fqty($item->quantity) . ' ' . $item->msr];
            }
            $contract = intval($doc->headerdata['contract_id'] ?? 0) > 0 ? \App\Entity\Contract::load($doc->headerdata['contract_id']) : null;
            $out[] = ['date' => H::fd($doc->document_date), 'type' => $doc->meta_desc, 'number' => $doc->document_number, 'docurl' => self::docUrl($doc->document_id),
                      'state' => Document::getStateName($doc->state), '_lines' => $lines, 'notes' => (string)$doc->notes,
                      'contract' => $contract ? __('report.recipientcard.contract_of', ['number' => $contract->contract_number, 'date' => H::fd($contract->createdon)]) . \App\SQS\Units::holderNote($contract) : ''];
        }
        return $out;
    }

    /**
     * Интервалы выдачи (App\SQS\IssueInterval::nextIssues): когда можно следующую выдачу по каждому правилу
     */
    private function nextIssues(int $cid): array {
        $today = strtotime(date('Y-m-d'));
        $out = [];
        foreach (\App\SQS\IssueInterval::nextIssues($cid) as $n) {
            $wait = intval(round(($n['next'] - $today) / 86400));
            $out[] = ['what' => $n['what'] . ($n['kind'] == 'cat' ? ' (' . __('report.recipientcard.category') . ')' : ''), 'days' => $n['days'],
                      'last' => H::fd($n['last']) . ($n['kind'] == 'cat' ? ', ' . $n['lastitem'] : ''), 'next' => H::fd($n['next']),
                      'can' => $wait <= 0, 'wait' => $wait > 0 ? __('report.recipientcard.in_days', ['n' => $wait]) : __('report.recipientcard.can_now')];
        }
        return $out;
    }
}
