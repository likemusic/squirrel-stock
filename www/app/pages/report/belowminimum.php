<?php

namespace App\Pages\Report;

use App\Helper as H;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Label;
use Zippy\Html\Panel;

/**
 * SquirrelStock: позиции ниже минимального остатка («Мин. количество» в карточке позиции) — список для закупки.
 * Остаток, минимум, сколько не хватает до минимума, последнее поступление (дата, от кого).
 * В отличие от виджета на главной странице Zippy, в списке и позиции, которых на складе не было ни разу.
 * С отбором по складу остаток и минимум сравниваются по этому складу.
 */
class BelowMinimum extends \App\Pages\Base
{
    private const RECEIPT = ['GoodsReceipt', 'IncomeItem'];

    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowReport('BelowMinimum')) {
            return;
        }

        $this->add(new Form('filter'));
        $this->filter->add(new DropDownChoice('store', [0 => __('common.vsi')] + \App\Entity\Store::getList(), 0));
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
        $store = intval($this->filter->store->getValue());
        $cat = intval($this->filter->cat->getValue());

        $where = 'i.disabled <> 1 AND i.minqty > 0';
        if ($cat > 0) {
            $cats = \App\Entity\Category::load($cat)->getChildren();
            $cats[] = $cat;
            $where .= ' AND i.cat_id IN (' . implode(',', array_map('intval', $cats)) . ')';
        }
        $stockWhere = $store > 0 ? "WHERE store_id = {$store}" : '';
        $sql = "SELECT i.item_id, i.itemname, i.item_code, i.msr, i.minqty, c.cat_name, COALESCE(t.qty, 0) AS qty
                FROM items i
                LEFT JOIN item_cat c ON c.cat_id = i.cat_id
                LEFT JOIN (SELECT item_id, SUM(qty) AS qty FROM store_stock {$stockWhere} GROUP BY item_id) t ON t.item_id = i.item_id
                WHERE {$where} AND COALESCE(t.qty, 0) < i.minqty
                ORDER BY c.cat_name, i.itemname";

        $rows = [];
        foreach ($conn->Execute($sql) as $r) {
            $rows[$r['item_id']] = [
                'cat'      => $r['cat_name'] ?? '',
                'name'     => $r['itemname'],
                'code'     => $r['item_code'],
                'msr'      => $r['msr'],
                'qty'      => H::fqty($r['qty']),
                'minqty'   => H::fqty($r['minqty']),
                'short'    => H::fqty(doubleval($r['minqty']) - doubleval($r['qty'])),
                'lastdate' => '',
                'lastfrom' => '',
            ];
        }

        if ($rows) {
            // последнее поступление: самый поздний приход по позиции (с отбором по складу — на этот склад)
            $sql = "SELECT st.item_id, d.document_date, c.customer_name
                    FROM entrylist e
                    JOIN documents d ON d.document_id = e.document_id
                    JOIN metadata m ON m.meta_id = d.meta_id
                    JOIN store_stock st ON st.stock_id = e.stock_id
                    LEFT JOIN customers c ON c.customer_id = d.customer_id
                    WHERE m.meta_name IN ('" . implode("', '", self::RECEIPT) . "') AND e.quantity > 0
                      AND st.item_id IN (" . implode(',', array_keys($rows)) . ")" . ($store > 0 ? " AND st.store_id = {$store}" : '') . "
                    ORDER BY d.document_date DESC, d.document_id DESC";
            foreach ($conn->Execute($sql) as $r) {
                $row = &$rows[$r['item_id']];
                if ($row['lastdate'] === '') {
                    $row['lastdate'] = H::fd(strtotime($r['document_date']));
                    $row['lastfrom'] = $r['customer_name'] ?? '';
                }
                unset($row);
            }
        }

        $report = new \App\Report('report/belowminimum.tpl');
        return $report->generate([
            'store'   => $store > 0 ? $this->filter->store->getValueName() : '',
            'date'    => H::fd(time()),
            'empty'   => count($rows) == 0,
            '_detail' => array_values($rows),
        ]);
    }
}
