<?php

namespace App\Pages\Report;

use App\Helper as H;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Panel;

/**
 * SquirrelStock: акт списания за месяц в формате бухгалтерии хосписа.
 * Строки — позиции, колонки — выдачи «Получатель дд.мм.гггг» (выдачи одному получателю в один день — одна колонка),
 * в ячейках количество по расходным накладным за вычетом возвратов, справа итог по позиции, внизу итог по выдаче.
 */
class IssueAct extends \App\Pages\Base
{
    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowReport('IssueAct')) {
            return;
        }

        $this->add(new Form('filter'));
        $this->filter->add(new DropDownChoice('year', \App\Util::getYears(), intval(date('Y'))));
        $this->filter->add(new DropDownChoice('month', \App\Util::getMonth(), intval(date('n'))));
        $this->filter->add(new DropDownChoice('cat', [0 => __('common.vsi')] + \App\Entity\Category::getList(true), 0));
        $this->filter->add(new TextInput('title', __('report.issueact.default_title')));
        $this->filter->add(new CheckBox('allitems'));
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
        $year = intval($this->filter->year->getValue());
        $month = intval($this->filter->month->getValue());
        $cat = intval($this->filter->cat->getValue());
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from));

        $where = "m.meta_name IN ('GoodsIssue', 'ReturnIssue') AND d.document_date BETWEEN '{$from}' AND '{$to}'";
        if ($cat > 0) {
            $where .= ' AND ' . \App\SQS\Categories::sqlIn($cat);
        }
        // возврат относится к колонке выдачи, на основании которой создан
        $sql = "SELECT COALESCE(base.document_date, d.document_date) AS issue_date,
                       COALESCE(base.customer_id, d.customer_id) AS customer_id,
                       i.item_id, i.itemname, SUM(-e.quantity) AS qty
                FROM entrylist e
                JOIN documents d ON d.document_id = e.document_id
                JOIN metadata m ON m.meta_id = d.meta_id
                LEFT JOIN documents base ON m.meta_name = 'ReturnIssue' AND base.document_id = d.parent_id
                JOIN store_stock st ON st.stock_id = e.stock_id
                JOIN items i ON i.item_id = st.item_id
                WHERE {$where}
                GROUP BY 1, 2, 3, 4
                HAVING SUM(-e.quantity) <> 0";

        $cols = [];    // "дата|получатель" => заголовок
        $cells = [];   // item_id => [колонка => количество]
        $names = [];
        $customers = [];
        foreach ($conn->Execute($sql) as $r) {
            $cid = intval($r['customer_id']);
            if (!isset($customers[$cid])) {
                $customers[$cid] = $cid > 0 ? (\App\Entity\Customer::load($cid)->customer_name ?? '') : '';
            }
            $date = date('Y-m-d', strtotime($r['issue_date']));
            $col = $date . '|' . $customers[$cid];
            $cols[$col] = trim($customers[$cid] . ' ' . H::fd(strtotime($date)));
            $cells[$r['item_id']][$col] = ($cells[$r['item_id']][$col] ?? 0) + $r['qty'];
            $names[$r['item_id']] = $r['itemname'];
        }
        // колонка, у которой все количества взаимно погасились (выдача вернулась целиком), в акт не попадает
        foreach (array_keys($cols) as $col) {
            $sum = 0;
            foreach ($cells as $row) {
                $sum += abs($row[$col] ?? 0);
            }
            if ($sum == 0) {
                unset($cols[$col]);
            }
        }
        foreach ($cells as $id => $row) {
            if (array_sum(array_map('abs', $row)) == 0) {
                unset($cells[$id], $names[$id]);
            }
        }
        ksort($cols);
        if ($this->filter->allitems->isChecked()) {
            $iwhere = 'disabled <> 1' . ($cat > 0 ? ' AND ' . \App\SQS\Categories::sqlIn($cat, 'cat_id') : '');
            foreach (\App\Entity\Item::find($iwhere) as $item) {
                $names[$item->item_id] = $names[$item->item_id] ?? $item->itemname;
            }
        }
        uasort($names, fn($a, $b) => strcasecmp($a, $b));

        $rows = [];
        $coltotal = array_fill_keys(array_keys($cols), 0);
        foreach ($names as $id => $name) {
            $row = ['name' => $name, 'cells' => [], 'total' => 0];
            foreach (array_keys($cols) as $col) {
                $q = $cells[$id][$col] ?? 0;
                $row['cells'][] = ['qty' => $q != 0 ? H::fqty($q) : ''];
                $row['total'] += $q;
                $coltotal[$col] += $q;
            }
            $row['total'] = H::fqty($row['total']);
            $rows[] = $row;
        }
        $header = [];
        $n = 1;
        foreach ($cols as $title) {
            $header[] = ['title' => $title, 'n' => $n++];
        }

        $months = \App\Util::getMonth();
        $report = new \App\Report('report/issueact.tpl');
        return $report->generate([
            'heading' => __('report.issueact.heading', ['title' => trim($this->filter->title->getText()), 'month' => mb_strtolower($months[$month]), 'year' => $year]),
            'cols'    => $header,
            'rows'    => $rows,
            'totals'  => array_map(fn($q) => ['qty' => H::fqty($q)], array_values($coltotal)),
            'total'   => H::fqty(array_sum($coltotal)),
            'issues'  => count($cols),
        ]);
    }
}
