<?php

namespace App\Pages\Report;

use App\Entity\Item;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Label;
use Zippy\Html\Panel;

/**
 * SquirrelStock: «Получатель × месяц» — кто в каком месяце получал (по расходным накладным за вычетом возвратов).
 * В ячейке отметка «х», количество единиц или число выдач. Отбор по категории или позиции (например, гастростомы)
 * и по тегу получателя (признаки получателей — теги контрагента: «подопечный», «снят с опеки», «гастростома»...).
 */
class RecipientMonths extends \App\Pages\Base
{
    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowReport('RecipientMonths')) {
            return;
        }

        $this->add(new Form('filter'));
        $this->filter->add(new Date('from', strtotime(date('Y-m-01', strtotime('-11 month')))));
        $this->filter->add(new Date('to', time()));
        $this->filter->add(new DropDownChoice('cat', [0 => __('common.vsi')] + \App\Entity\Category::getList(true), 0));
        $this->filter->add(new AutocompleteTextInput('item'))->onText($this, 'OnAutoItem');
        $tags = \App\Entity\Tag::getTags(\App\Entity\Tag::TYPE_CUSTOMER);
        $this->filter->add(new DropDownChoice('ctag', ['' => __('common.vsi')] + array_combine($tags, $tags), ''));
        $this->filter->add(new DropDownChoice('mode', [
            'mark' => __('report.recipientmonths.mode_mark'),
            'qty'  => __('report.recipientmonths.mode_qty'),
            'docs' => __('report.recipientmonths.mode_docs'),
        ], 'mark'));
        $this->filter->add(new CheckBox('allcust'));
        $this->filter->add(new SubmitButton('onreport'))->onClick($this, 'OnSubmit');

        $this->add(new Panel('detail'))->setVisible(false);
        $this->detail->add(new Label('preview'));
    }

    public function OnAutoItem($sender) {
        return Item::findArrayAC(trim($sender->getText()));
    }

    public function OnSubmit($sender) {
        $html = $this->generateReport();
        $this->detail->preview->setText($html, true);
        \App\Session::getSession()->setExportReport("<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>" . $html . "</body></html>");
        $this->detail->setVisible(true);
    }

    private function generateReport() {
        $conn = \ZDB\DB::getConnect();
        $from = date('Y-m-01', $this->filter->from->getDate());
        $to = date('Y-m-d', $this->filter->to->getDate());
        $cat = intval($this->filter->cat->getValue());
        $item = intval($this->filter->item->getKey());
        $mode = $this->filter->mode->getValue();
        $tag = (string)$this->filter->ctag->getValue();
        $tagwhere = $tag === '' ? '' : ' AND {col} IN (SELECT item_id FROM taglist WHERE tag_type = ' . \App\Entity\Tag::TYPE_CUSTOMER . ' AND tag_name = ' . $conn->qstr($tag) . ')';

        $where = "m.meta_name IN ('GoodsIssue', 'ReturnIssue') AND d.document_date BETWEEN '{$from}' AND '{$to}' AND d.customer_id > 0";
        if ($cat > 0) {
            $where .= ' AND ' . \App\SQS\Categories::sqlIn($cat);
        }
        if ($item > 0) {
            $where .= " AND i.item_id = {$item}";
        }
        $where .= str_replace('{col}', 'd.customer_id', $tagwhere);
        // возврат идет в месяц выдачи, на основании которой создан; число выдач — по расходным накладным
        $sql = "SELECT d.customer_id, DATE_FORMAT(COALESCE(base.document_date, d.document_date), '%Y-%m') AS ym,
                       SUM(-e.quantity) AS qty,
                       COUNT(DISTINCT CASE WHEN m.meta_name = 'GoodsIssue' THEN d.document_id END) AS docs
                FROM entrylist e
                JOIN documents d ON d.document_id = e.document_id
                JOIN metadata m ON m.meta_id = d.meta_id
                LEFT JOIN documents base ON m.meta_name = 'ReturnIssue' AND base.document_id = d.parent_id
                JOIN store_stock st ON st.stock_id = e.stock_id
                JOIN items i ON i.item_id = st.item_id
                WHERE {$where}
                GROUP BY 1, 2
                HAVING SUM(-e.quantity) <> 0";

        $months = [];
        for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 month', $t)) {
            $months[date('Y-m', $t)] = date('m.Y', $t);
        }
        $data = [];
        foreach ($conn->Execute($sql) as $r) {
            $data[$r['customer_id']][$r['ym']] = $r;
        }
        $names = [];
        if ($this->filter->allcust->isChecked()) {
            foreach (\App\Entity\Customer::find('status = 0' . str_replace('{col}', 'customer_id', $tagwhere)) as $c) {
                $names[$c->customer_id] = $c->customer_name;
            }
        }
        foreach (array_keys($data) as $cid) {
            $names[$cid] = $names[$cid] ?? (\App\Entity\Customer::load($cid)->customer_name ?? '');
        }
        uasort($names, fn($a, $b) => strcasecmp($a, $b));

        $rows = [];
        $coltotal = array_fill_keys(array_keys($months), 0);
        foreach ($names as $cid => $name) {
            $cells = [];
            $marks = 0;
            foreach (array_keys($months) as $ym) {
                $r = $data[$cid][$ym] ?? null;
                $v = '';
                if ($r != null) {
                    $v = $mode == 'qty' ? \App\Helper::fqty($r['qty']) : ($mode == 'docs' ? $r['docs'] : 'х');
                    $coltotal[$ym]++;
                    $marks++;
                }
                $cells[] = ['v' => $v];
            }
            $rows[] = ['name' => $name, 'cells' => $cells, 'months' => $marks];
        }

        $report = new \App\Report('report/recipientmonths.tpl');
        return $report->generate([
            'period'  => \App\Helper::fd(strtotime($from)) . ' - ' . \App\Helper::fd(strtotime($to)),
            'filter'  => trim(($item > 0 ? $this->filter->item->getText() : ($cat > 0 ? $this->filter->cat->getValueName() : '')) . ($tag !== '' ? ' #' . $tag : '')),
            'months'  => array_map(fn($m) => ['m' => $m], array_values($months)),
            'rows'    => $rows,
            'totals'  => array_map(fn($n) => ['n' => $n], array_values($coltotal)),
            'recipients' => count(array_filter($rows, fn($r) => $r['months'] > 0)),
        ]);
    }
}
