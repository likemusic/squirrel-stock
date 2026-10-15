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
        \App\Session::getSession()->setExportReport("<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>" . $html . "</body></html>");
        $this->detail->setVisible(true);
    }

    private function generateReport() {
        $conn = \ZDB\DB::getConnect();
        $where = "s.snumber <> ''";
        $text = mb_strtolower(trim($this->filter->searchtext->getText()));
        $cat = intval($this->filter->cat->getValue());
        if ($cat > 0) {
            $where .= " AND i.cat_id = {$cat}";
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

        $detail = [];
        $today = strtotime(date('Y-m-d'));
        foreach ($units as $u) {
            $row = ['itemname' => $u['itemname'], 'snumber' => $u['snumber'], 'where' => '', 'customer' => '', 'phone' => '', 'address' => '',
                    'contract' => '', 'date' => '', 'term' => '', 'sent' => '', 'overdue' => false, 'document' => ''];
            $l = $last[$u['item_id'] . '|' . $u['snumber']] ?? null;
            $doc = $l ? \App\Entity\Doc\Document::load($l['document_id']) : null;
            if ($doc) {
                $row['date'] = H::fd($doc->document_date);
                $row['document'] = $doc->document_number;
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
                if ($doc && $doc->customer_id > 0) {
                    $c = \App\Entity\Customer::load($doc->customer_id);
                    $row['customer'] = $c->customer_name ?? '';
                    $row['phone'] = $c->phone ?? '';
                    $row['address'] = $c->address ?? '';
                }
                $contract = intval($doc->headerdata['contract_id'] ?? 0) > 0 ? \App\Entity\Contract::load($doc->headerdata['contract_id']) : null;
                if ($contract) {
                    $row['contract'] = $contract->contract_number . ' ' . __('common.vid_2', ['date' => H::fd($contract->createdon)]);
                    $row['sent'] = $contract->shortdesc;
                    $end = intval($contract->enddate);
                    $row['term'] = ($end == 0 || date('Y', $end) >= 2099) ? __('report.equipmentwhere.until_demand') : H::fd($end);
                    $row['overdue'] = $end > 0 && date('Y', $end) < 2099 && $end < $today;
                }
            }
            if ($state == 'active' && $kind == 'off' || $state != 'active' && $state != 'all' && $state != $kind) {
                continue;
            }
            // поиск: модель, инвентарный номер или получатель
            if ($text != '' && !str_contains(mb_strtolower($row['itemname'] . ' ' . $row['snumber'] . ' ' . $row['customer']), $text)) {
                continue;
            }
            $row['order'] = ['out' => 0, 'store' => 1, 'off' => 2][$kind];
            $detail[] = $row;
        }
        // сначала выданное (у кого что), затем склады, затем списанное
        usort($detail, fn($a, $b) => [$a['order'], $a['where'], $a['customer'], $a['itemname'], $a['snumber']] <=> [$b['order'], $b['where'], $b['customer'], $b['itemname'], $b['snumber']]);

        $report = new \App\Report('report/equipmentwhere.tpl');
        return $report->generate(['_detail' => $detail, 'cnt' => count($detail), 'date' => H::fd(time())]);
    }
}
