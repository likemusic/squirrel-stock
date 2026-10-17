<?php

namespace App\Pages\Service;

use App\Entity\Customer;
use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Helper as H;
use Zippy\Html\DataList\ArrayDataSource;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextArea;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;

/**
 * SquirrelStock: выдача расходников одной короткой формой — основная ежедневная операция хосписа, в том числе с телефона.
 * Получатель (подсказка или новый), дата, позиции с количеством (подсказка — только то, что есть на складе, с остатком),
 * примечание, «Провести» — создается проведенная расходная накладная по цене 0. Позиции с серийным учетом
 * (оборудование) выдаются формой «Выдача оборудования по договору».
 */
class ConsumablesIssue extends \App\Pages\Base
{
    public array $_rows = [];   // [item_id => DataItem(item_id, name, msr, qty)]

    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowSer('ConsumablesIssue')) {
            return;
        }

        $f = $this->add(new Form('cform'));
        $f->add(new AutocompleteTextInput('customer'))->onText($this, 'onAutoCustomer');
        $f->add(new Date('date', time()));
        $f->add(new DropDownChoice('store', \App\Entity\Store::getList(), H::getDefStore()));
        $f->add(new AutocompleteTextInput('item'))->onText($this, 'onAutoItem');
        $f->add(new TextInput('qty', '1'));
        $f->add(new SubmitButton('additem'))->onClick($this, 'onAddItem');   // не Button: тот уходит GET-запросом без данных формы
        $f->add(new DataView('rows', new ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_rows')), $this, 'rowOnRow'));
        $f->add(new TextArea('notes'));
        $f->add(new SubmitButton('issue'))->onClick($this, 'onIssue');
        $f->rows->Reload();
        $this->_tvars['onestore'] = count(\App\Entity\Store::getList()) <= 1;
    }

    public function onAutoCustomer($sender) {
        return Customer::getList($sender->getText(), 1);
    }

    /**
     * Условие SQL «строка остатка s годится для выдачи»: у позиции, которая ведется партиями, — партия не просрочена
     */
    private static function usableSql(): string {
        return "NOT (" . \App\SQS\SerialStock::batchSql() . " AND s.sdate IS NOT NULL AND s.sdate < CURDATE())";
    }

    /**
     * Расходники с остатком на складе: id => «название, код — остаток N ед.». Расходник — позиция без серийного учета или
     * с учетом партиями (Item::serialMode() 1 или 2; просроченные партии не считаются); оборудование по номерам — нет.
     */
    public static function findInStock(int $store, string $text, int $limit = 30): array {
        $conn = \ZDB\DB::getConnect();
        $like = $conn->qstr('%' . trim($text) . '%');
        $sql = "SELECT i.item_id, i.itemname, i.item_code, i.msr, SUM(s.qty) AS qty
                FROM items i JOIN store_stock s ON s.item_id = i.item_id AND s.store_id = {$store}
                WHERE i.disabled <> 1 AND (COALESCE(i.detail, '') NOT LIKE '%<useserial>1</useserial>%' OR " . \App\SQS\SerialStock::batchSql() . ")
                  AND " . self::usableSql() . "
                  AND (i.itemname LIKE {$like} OR i.item_code LIKE {$like} OR i.bar_code LIKE {$like})
                GROUP BY i.item_id, i.itemname, i.item_code, i.msr
                HAVING SUM(s.qty) > 0
                ORDER BY i.itemname, i.item_id
                LIMIT {$limit}";
        $list = [];
        foreach ($conn->Execute($sql) as $r) {
            $list[$r['item_id']] = $r['itemname'] . ($r['item_code'] !== '' ? ', ' . $r['item_code'] : '')
                . ' — ' . __('sqs.cissue.in_stock', ['qty' => H::fqty($r['qty']), 'msr' => $r['msr']]);
        }
        return $list;
    }

    private function stockQty(int $item_id): float {
        $conn = \ZDB\DB::getConnect();
        return doubleval($conn->GetOne('SELECT COALESCE(SUM(s.qty), 0) FROM store_stock s JOIN items i ON i.item_id = s.item_id WHERE s.item_id = ' . $item_id
            . ' AND s.store_id = ' . intval($this->cform->store->getValue()) . ' AND ' . self::usableSql()));
    }

    /**
     * Строки документа по позиции: у позиции, которая ведется партиями, — по партиям, сначала с ближайшим сроком годности,
     * партии без срока — после (как Item::getNearestSerie), просроченные не выдаются; у остальных — одна строка без номера
     */
    private function lines(Item $item, float $qty, int $store): array {
        $mode = $item->serialMode();
        if ($mode != 1 && $mode != 2) {
            $item->quantity = $qty;
            return [$item];
        }
        $conn = \ZDB\DB::getConnect();
        $rows = $conn->GetAll("SELECT s.snumber, MAX(s.sdate) AS sdate, SUM(s.qty) AS qty FROM store_stock s JOIN items i ON i.item_id = s.item_id
                               WHERE s.item_id = " . intval($item->item_id) . " AND s.store_id = {$store} AND s.qty > 0 AND " . self::usableSql() . "
                               GROUP BY s.snumber ORDER BY MAX(s.sdate) IS NULL, MAX(s.sdate), MIN(s.stock_id)");
        $lines = [];
        foreach ($rows as $r) {
            if ($qty <= 0) {
                break;
            }
            $take = min($qty, doubleval($r['qty']));
            $line = clone $item;
            $line->snumber = (string)$r['snumber'];
            $line->sdate = strlen((string)$r['sdate']) ? strtotime($r['sdate']) : '';
            $line->quantity = $take;
            $lines[] = $line;
            $qty -= $take;
        }
        return $lines;
    }

    public function onAutoItem($sender) {
        return self::findInStock(intval($this->cform->store->getValue()), $sender->getText());
    }

    public function onAddItem($sender) {
        $f = $this->cform;
        $item_id = intval($f->item->getKey());
        if ($item_id <= 0) {
            if ($sender !== null && trim($f->item->getText()) !== '') {
                $this->setError(__('sqs.cissue.pick_item'));
            }
            return;
        }
        $qty = doubleval(str_replace(',', '.', $f->qty->getText()));
        if ($qty <= 0) {
            $this->setError(__('sqs.cissue.bad_qty'));
            return;
        }
        $item = Item::load($item_id);
        $total = $qty + ($this->_rows[$item_id]->qty ?? 0);
        $stock = $this->stockQty($item_id);
        if ($total > $stock) {
            $this->setError(__('sqs.cissue.not_enough', ['item' => $item->itemname, 'qty' => H::fqty($stock), 'msr' => $item->msr]));
            return;
        }
        // интервал выдачи (App\SQS\IssueInterval): получатель уже получал эту позицию недавно — предупреждение, выдать можно
        $warn = \App\SQS\IssueInterval::warning(intval($f->customer->getKey()), $item_id, intval($f->date->getDate()));
        if ($warn !== null) {
            $this->setWarn($warn);
        }
        // та же позиция еще раз — количество складывается
        $this->_rows[$item_id] = new \App\DataItem(['item_id' => $item_id, 'name' => $item->itemname, 'msr' => $item->msr, 'qty' => $total, 'warn' => $warn ?? '']);
        $f->item->setKey(0);
        $f->item->setText('');
        $f->qty->setText('1');
        $f->rows->Reload();
    }

    public function rowOnRow($row) {
        $r = $row->getDataItem();
        $row->add(new Label('rowname', $r->name));
        $row->add(new Label('rowwarn', $r->warn ?? ''))->setVisible(strlen($r->warn ?? '') > 0);
        $row->add(new Label('rowqty', H::fqty($r->qty) . ' ' . $r->msr));
        $row->add(new ClickLink('delrow', $this, 'onDelRow'));
    }

    public function onDelRow($sender) {
        unset($this->_rows[$sender->getOwner()->getDataItem()->item_id]);
        $this->cform->rows->Reload();
    }

    public function onIssue($sender) {
        $f = $this->cform;
        $this->onAddItem(null);   // выбранная, но не добавленная кнопкой позиция
        if ($this->isError()) {
            return;
        }
        $name = trim($f->customer->getText());
        $store = intval($f->store->getValue());
        if ($name === '') {
            $this->setError(__('sqs.cissue.no_customer'));
            return;
        }
        if (count($this->_rows) == 0) {
            $this->setError(__('sqs.cissue.no_items'));
            return;
        }
        foreach ($this->_rows as $r) {   // остаток мог измениться, пока заполнялась форма
            $stock = $this->stockQty($r->item_id);
            if ($r->qty > $stock) {
                $this->setError(__('sqs.cissue.not_enough', ['item' => $r->name, 'qty' => H::fqty($stock), 'msr' => $r->msr]));
                return;
            }
        }

        // интервал выдачи: получатель мог быть выбран после позиций — проверка всех строк; выдача не запрещается
        $warnings = [];
        foreach ($this->_rows as $r) {
            $w = \App\SQS\IssueInterval::warning(intval($f->customer->getKey()), $r->item_id, intval($f->date->getDate()));
            if ($w !== null) {
                $warnings[] = $w;
            }
        }

        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {
            $customer = $f->customer->getKey() > 0 ? Customer::load($f->customer->getKey()) : null;
            if ($customer == null) {
                $customer = new Customer();
                $customer->customer_name = $name;
                $customer->type = 1;
                $customer->save();
            }

            $doc = Document::create('GoodsIssue');
            $doc->document_number = $doc->nextNumber();
            $doc->document_date = $f->date->getDate();
            $doc->customer_id = $customer->customer_id;
            $doc->notes = trim($f->notes->getText());
            $doc->headerdata['customer_name'] = $customer->customer_name;
            $doc->headerdata['store'] = $store;
            $doc->headerdata['store_name'] = $f->store->getValueName();
            $doc->headerdata['payment'] = 0;
            $doc->headerdata['payed'] = 0;
            $doc->headerdata['pricetype'] = 'price1';
            $doc->headerdata['sqs_interval_checked'] = 1;   // предупреждение показано здесь, хук после проведения его не повторяет
            $items = [];
            foreach (array_values($this->_rows) as $r) {
                $item = Item::load($r->item_id);
                $item->price = 0;
                foreach ($this->lines($item, doubleval($r->qty), $store) as $line) {
                    $line->rowid = count($items) + 1;
                    $items[$line->rowid] = $line;
                }
            }
            $doc->packDetails('detaildata', $items);
            $doc->amount = 0;
            $doc->payamount = 0;
            $doc->payed = 0;
            $doc->save();
            $doc->updateStatus(Document::STATE_NEW);
            $doc->updateStatus(Document::STATE_EXECUTED);
            $conn->CommitTrans();
        } catch (\Throwable $e) {
            $conn->RollbackTrans();
            $this->setError($e->getMessage());
            return;
        }

        $this->setSuccess(__('sqs.cissue.done', ['doc' => $doc->document_number, 'customer' => $customer->customer_name, 'n' => count($this->_rows)]));
        if ($warnings) {
            $this->setWarn(implode('; ', $warnings));
        }
        // дата и склад остаются: обычно выдают несколько получателей подряд
        $this->_rows = [];
        $f->rows->Reload();
        $f->customer->setKey(0);
        $f->customer->setText('');
        $f->notes->setText('');
    }
}
