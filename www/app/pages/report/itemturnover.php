<?php

namespace App\Pages\Report;

use App\Helper as H;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Label;
use Zippy\Html\Panel;

/**
 * SquirrelStock: оборот по позициям за период («Отбор» в книге Excel хосписа).
 * На начало, приход, выдано, возвращено, списано, прочее (перемещения, инвентаризация и др.), на конец, число выдач.
 * С отбором по получателю — только выдано, возвращено и число выдач этому получателю.
 */
class ItemTurnover extends \App\Pages\Base
{
    // тип документа → колонка
    private const RECEIPT = ['GoodsReceipt', 'IncomeItem', 'ProdReceipt'];
    private const ISSUE = 'GoodsIssue';
    private const RETURN = 'ReturnIssue';
    private const WRITEOFF = 'OutcomeItem';

    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowReport('ItemTurnover')) {
            return;
        }

        $this->add(new Form('filter'));
        $this->filter->add(new Date('from', strtotime(date('Y-m-01'))));
        $this->filter->add(new Date('to', time()));
        $this->filter->add(new DropDownChoice('cat', [0 => __('common.vsi')] + \App\Entity\Category::getList(true), 0));
        $this->filter->add(new AutocompleteTextInput('cust'))->onText($this, 'OnAutoCustomer');
        $this->filter->add(new SubmitButton('onreport'))->onClick($this, 'OnSubmit');

        $this->add(new Panel('detail'))->setVisible(false);
        $this->detail->add(new Label('preview'));
    }

    public function OnAutoCustomer($sender) {
        return \App\Entity\Customer::getList(trim($sender->getText()));
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
        $cust = intval($this->filter->cust->getKey());

        $where = '1 = 1';
        if ($cat > 0) {
            $where .= " AND i.cat_id = {$cat}";
        }
        if ($cust > 0) {
            $where .= " AND d.customer_id = {$cust} AND m.meta_name IN ('" . self::ISSUE . "', '" . self::RETURN . "')";
        }
        $sql = "SELECT i.item_id, i.itemname, i.item_code, m.meta_name, d.document_id,
                       d.document_date < '{$from}' AS before_period, SUM(e.quantity) AS qty
                FROM entrylist e
                JOIN documents d ON d.document_id = e.document_id
                JOIN metadata m ON m.meta_id = d.meta_id
                JOIN store_stock st ON st.stock_id = e.stock_id
                JOIN items i ON i.item_id = st.item_id
                WHERE {$where} AND d.document_date <= '{$to}'
                GROUP BY i.item_id, i.itemname, i.item_code, m.meta_name, d.document_id, before_period";

        $rows = [];
        foreach ($conn->Execute($sql) as $r) {
            $id = $r['item_id'];
            $row = &$rows[$id];
            if ($row === null) {
                $row = ['name' => $r['itemname'], 'code' => $r['item_code'], 'start' => 0, 'receipt' => 0, 'issued' => 0,
                        'returned' => 0, 'writeoff' => 0, 'other' => 0, 'issues' => 0];
            }
            $q = doubleval($r['qty']);
            if ($r['before_period']) {
                $row['start'] += $q;
            } elseif ($r['meta_name'] == self::ISSUE) {
                $row['issued'] -= $q;
                $row['issues']++;
            } elseif ($r['meta_name'] == self::RETURN) {
                $row['returned'] += $q;
            } elseif ($r['meta_name'] == self::WRITEOFF) {
                $row['writeoff'] -= $q;
            } elseif (in_array($r['meta_name'], self::RECEIPT, true) && $q > 0) {
                $row['receipt'] += $q;
            } else {
                $row['other'] += $q;
            }
            unset($row);
        }

        $out = [];
        foreach ($rows as $row) {
            $row['end'] = $row['start'] + $row['receipt'] - $row['issued'] + $row['returned'] - $row['writeoff'] + $row['other'];
            $moved = $row['receipt'] != 0 || $row['issued'] != 0 || $row['returned'] != 0 || $row['writeoff'] != 0 || $row['other'] != 0;
            if (!$moved && ($cust > 0 || $row['start'] == 0)) {
                continue;   // без движения за период и без остатка
            }
            foreach (['start', 'receipt', 'issued', 'returned', 'writeoff', 'other', 'end'] as $f) {
                $row[$f] = $row[$f] != 0 ? H::fqty($row[$f]) : '';
            }
            $row['issues'] = $row['issues'] ?: '';
            $out[] = $row;
        }
        usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        $report = new \App\Report('report/itemturnover.tpl');
        return $report->generate([
            'period'   => H::fd(strtotime($from)) . ' - ' . H::fd(strtotime($to)),
            'customer' => $cust > 0 ? $this->filter->cust->getText() : '',
            'stock'    => $cust == 0,
            '_detail'  => $out,
        ]);
    }
}
