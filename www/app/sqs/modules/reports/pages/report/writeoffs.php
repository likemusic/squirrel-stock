<?php

namespace App\Pages\Report;

use App\Helper as H;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Panel;

/**
 * SquirrelStock: списания за период с причинами — для бухгалтерии. Причина — примечание документа «Списание ТМЦ»
 * (ответ хосписа 3.2: справочник причин не нужен, «они слишком разные бывают»).
 * Строка — позиция документа: дата, документ, склад, позиция, инвентарный номер, количество, причина; отбор по категории и по тексту причины.
 */
class WriteOffs extends \App\Pages\Base
{
    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowReport('WriteOffs')) {
            return;
        }

        $this->add(new Form('filter'));
        $this->filter->add(new Date('from', strtotime(date('Y-m-01'))));
        $this->filter->add(new Date('to', time()));
        $this->filter->add(new DropDownChoice('cat', [0 => __('common.vsi')] + \App\Entity\Category::getList(true), 0));
        $this->filter->add(new TextInput('reason'));
        $this->filter->add(new SubmitButton('onreport'))->onClick($this, 'OnSubmit');

        $this->add(new Panel('detail'))->setVisible(false);
        $this->detail->add(new Label('preview'));
    }

    public function OnSubmit($sender) {
        $html = $this->generateReport();
        $this->detail->preview->setText($html, true);
        // в Excel номер документа — текстом, без ссылки
        $export = preg_replace('#<a class="sqs-doc"[^>]*>(.*?)</a>#su', '$1', $html);
        \App\Session::getSession()->setExportReport("<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>" . $export . "</body></html>");
        $this->detail->setVisible(true);
    }

    private function generateReport() {
        $conn = \ZDB\DB::getConnect();
        $from = date('Y-m-d', $this->filter->from->getDate());
        $to = date('Y-m-d', $this->filter->to->getDate());
        $cat = intval($this->filter->cat->getValue());
        $reason = trim($this->filter->reason->getText());

        $where = "m.meta_name = 'OutcomeItem' AND d.document_date BETWEEN '{$from}' AND '{$to}'";
        if ($cat > 0) {
            $where .= ' AND ' . \App\SQS\Categories::sqlIn($cat);
        }
        if ($reason != '') {
            $where .= ' AND d.notes LIKE ' . $conn->qstr('%' . $reason . '%');
        }
        $sql = "SELECT d.document_id, d.document_number, d.document_date, d.notes, s.storename, i.itemname, i.msr, st.snumber, SUM(-e.quantity) AS qty
                FROM entrylist e
                JOIN documents d ON d.document_id = e.document_id
                JOIN metadata m ON m.meta_id = d.meta_id
                JOIN store_stock st ON st.stock_id = e.stock_id
                JOIN stores s ON s.store_id = st.store_id
                JOIN items i ON i.item_id = st.item_id
                WHERE {$where}
                GROUP BY d.document_id, d.document_number, d.document_date, d.notes, s.storename, i.itemname, i.msr, st.snumber
                HAVING SUM(-e.quantity) <> 0
                ORDER BY d.document_date, d.document_id, i.itemname, st.snumber";

        $rows = [];
        $docs = [];
        $total = 0;
        foreach ($conn->Execute($sql) as $r) {
            $docs[$r['document_id']] = true;
            $total += $r['qty'];
            $rows[] = [
                'date'     => H::fd(strtotime($r['document_date'])),
                'number'   => $r['document_number'],
                'docurl'   => '/index.php?p=App/Pages/Register/DocList&arg=' . intval($r['document_id']),
                'store'    => $r['storename'],
                'itemname' => $r['itemname'],
                'snumber'  => $r['snumber'],
                'qty'      => H::fqty($r['qty']),
                'msr'      => $r['msr'],
                'reason'   => $r['notes'],
            ];
        }

        $report = new \App\Report('report/writeoffs.tpl');
        return $report->generate([
            'period'  => H::fd(strtotime($from)) . ' — ' . H::fd(strtotime($to)),
            '_rows'   => $rows,
            'hasrows' => count($rows) > 0,
            'docs'    => count($docs),
            'total'   => H::fqty($total),
            'date'    => H::fd(time()),
        ]);
    }
}
