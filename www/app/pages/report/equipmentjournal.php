<?php

namespace App\Pages\Report;

use App\Helper as H;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Label;
use Zippy\Html\Panel;

/**
 * SquirrelStock: журнал выдачи оборудования за период — строка на каждую выданную единицу (товар с серийным,
 * то есть инвентарным, номером): договор, получатель, кем направлен, дата выдачи, срок, дата возврата.
 * Дата возврата — ближайший документ «Повернення від покупця» по этой единице после выдачи.
 */
class EquipmentJournal extends \App\Pages\Base
{
    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowReport('EquipmentJournal')) {
            return;
        }

        $this->add(new Form('filter'));
        $this->filter->add(new Date('from', strtotime(date('Y-01-01'))));
        $this->filter->add(new Date('to', time()));
        $this->filter->add(new DropDownChoice('cat', [0 => __('common.vsi')] + \App\Entity\Category::getList(true), 0));
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
        $from = date('Y-m-d', $this->filter->from->getDate());
        $to = date('Y-m-d', $this->filter->to->getDate());
        $cat = intval($this->filter->cat->getValue());
        $where = "m.meta_name = 'GoodsIssue' AND s.snumber <> '' AND d.document_date BETWEEN '{$from}' AND '{$to}'";
        if ($cat > 0) {
            $where .= ' AND ' . \App\SQS\Categories::sqlIn($cat);
        }
        $sql = "SELECT d.document_id, d.document_date, i.itemname, s.snumber,
                       (SELECT MIN(rd.document_date)
                        FROM entrylist re
                        JOIN store_stock rs ON rs.stock_id = re.stock_id
                        JOIN documents rd ON rd.document_id = re.document_id
                        JOIN metadata rm ON rm.meta_id = rd.meta_id
                        WHERE rm.meta_name = 'ReturnIssue' AND rs.item_id = s.item_id AND rs.snumber = s.snumber
                          AND rd.document_date >= d.document_date) AS returned
                FROM entrylist e
                JOIN documents d ON d.document_id = e.document_id
                JOIN metadata m ON m.meta_id = d.meta_id
                JOIN store_stock s ON s.stock_id = e.stock_id
                JOIN items i ON i.item_id = s.item_id
                WHERE {$where} AND e.quantity < 0
                ORDER BY d.document_date, d.document_id, i.itemname, s.snumber";

        $detail = [];
        $n = 1;
        foreach ($conn->Execute($sql) as $r) {
            $doc = \App\Entity\Doc\Document::load($r['document_id']);
            $c = $doc->customer_id > 0 ? \App\Entity\Customer::load($doc->customer_id) : null;
            $contract = intval($doc->headerdata['contract_id'] ?? 0) > 0 ? \App\Entity\Contract::load($doc->headerdata['contract_id']) : null;
            $term = '';
            if ($contract) {
                $end = intval($contract->enddate);
                $term = ($end == 0 || date('Y', $end) >= 2099) ? __('report.equipmentwhere.until_demand') : H::fd($end);
            }
            $detail[] = [
                'n'             => $n++,
                'contract'      => $contract->contract_number ?? '',
                'contract_date' => $contract ? H::fd($contract->createdon) : '',
                'customer'      => $c->customer_name ?? '',
                'contact'       => trim(($c->address ?? '') . ' ' . ($c->phone ?? '')),
                'sent'          => $contract->shortdesc ?? '',
                'itemname'      => $r['itemname'],
                'snumber'       => $r['snumber'],
                'date'          => H::fd(strtotime($r['document_date'])),
                'term'          => $term,
                'returned'      => $r['returned'] ? H::fd(strtotime($r['returned'])) : '',
                'notes'         => $doc->notes,
            ];
        }

        $report = new \App\Report('report/equipmentjournal.tpl');
        return $report->generate(['_detail' => $detail, 'period' => H::fd(strtotime($from)) . ' - ' . H::fd(strtotime($to))]);
    }
}
