<?php

namespace App\Pages\Service;

use App\Entity\Contract;
use App\Entity\Customer;
use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Helper as H;
use App\SQS\Units;
use Zippy\Html\DataList\ArrayDataSource;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextArea;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;

/**
 * SquirrelStock: выдача оборудования одной формой — получатель, договор и единицы.
 * При оформлении создаются штатные сущности Zippy: договор в состоянии «В работе» (срок — дата окончания,
 * «до востребования» — 31.12.2099, «кем направлен» — краткое описание) и проведенная расходная накладная
 * по этому договору с ценой 0. Новый получатель создается контрагентом; телефон и адрес, если указаны, обновляются.
 */
class EquipmentIssue extends \App\Pages\Base
{
    public const ON_DEMAND = '2099-12-31';

    public array $_units = [];   // ['item_id|snumber' => DataItem(item_id, snumber, label)]

    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowSer('EquipmentIssue')) {
            return;
        }

        $f = $this->add(new Form('iform'));
        $f->add(new AutocompleteTextInput('customer'))->onText($this, 'onAutoCustomer');
        $f->customer->onChange($this, 'onCustomer', true);   // AJAX: полная перезагрузка стерла бы уже введенные поля
        $f->add(new TextInput('phone'));
        $f->add(new TextInput('address'));
        $f->add(new TextInput('contract_number', self::nextContractNumber()));
        $f->add(new Date('date', time()));
        $f->add(new Date('enddate'));
        $f->add(new CheckBox('ondemand', true));
        $f->add(new TextInput('referrer'));
        $f->add(new DropDownChoice('store', \App\Entity\Store::getList(), H::getDefStore()));
        $f->add(new AutocompleteTextInput('unit'))->onText($this, 'onAutoUnit');
        $f->add(new SubmitButton('addunit'))->onClick($this, 'onAddUnit');   // не Button: тот уходит GET-запросом без данных формы
        $f->add(new DataView('units', new ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_units')), $this, 'unitOnRow'));
        $f->add(new TextArea('notes'));
        $f->add(new SubmitButton('issue'))->onClick($this, 'onIssue');
        $f->units->Reload();
    }

    // номер договора по образцу последнего: «234/26» → «235/26»; иначе «1/<год>»
    public static function nextContractNumber(): string {
        $conn = \ZDB\DB::getConnect();
        $yy = date('y');
        $max = 0;
        foreach ($conn->GetCol("SELECT contract_number FROM contracts WHERE contract_number LIKE " . $conn->qstr('%/' . $yy)) as $n) {
            $max = max($max, intval($n));
        }
        return ($max + 1) . '/' . $yy;
    }

    public function onAutoCustomer($sender) {
        return Customer::getList($sender->getText(), 1);
    }

    public function onCustomer($sender) {
        $c = Customer::load($sender->getKey());
        if ($c) {
            $this->iform->phone->setText($c->phone);
            $this->iform->address->setText($c->address);
            $this->addAjaxResponse("$('#phone').val(" . json_encode((string)$c->phone) . "); $('#address').val(" . json_encode((string)$c->address) . ");");
        }
    }

    public function onAutoUnit($sender) {
        $list = Units::findInStock(intval($this->iform->store->getValue()), $sender->getText());
        return array_diff_key($list, $this->_units);
    }

    public function onAddUnit($sender) {
        $key = $this->iform->unit->getKey();
        if (!$key || isset($this->_units[$key])) {
            return;
        }
        [$item_id, $snumber] = explode('|', $key, 2);
        $this->_units[$key] = new \App\DataItem(['item_id' => (int)$item_id, 'snumber' => $snumber, 'label' => $this->iform->unit->getText()]);
        $this->iform->unit->setKey(0);
        $this->iform->unit->setText('');
        $this->iform->units->Reload();
    }

    public function unitOnRow($row) {
        $u = $row->getDataItem();
        $row->add(new Label('unitlabel', $u->label));
        $row->add(new ClickLink('delunit', $this, 'onDelUnit'));
    }

    public function onDelUnit($sender) {
        $u = $sender->getOwner()->getDataItem();
        unset($this->_units[Units::key($u->item_id, $u->snumber)]);
        $this->iform->units->Reload();
    }

    public function onIssue($sender) {
        $f = $this->iform;
        $this->onAddUnit(null);   // выбранная, но не добавленная кнопкой единица
        $name = trim($f->customer->getText());
        $number = trim($f->contract_number->getText());
        $store = intval($f->store->getValue());
        $date = $f->date->getDate();
        $enddate = $f->ondemand->isChecked() ? strtotime(self::ON_DEMAND) : $f->enddate->getDate();
        if ($name === '') {
            $this->setError(__('sqs.eqissue.no_customer'));
            return;
        }
        if ($number === '') {
            $this->setError(__('sqs.eqissue.no_number'));
            return;
        }
        if (!$enddate) {
            $this->setError(__('sqs.eqissue.no_enddate'));
            return;
        }
        if (count($this->_units) == 0) {
            $this->setError(__('sqs.eqissue.no_units'));
            return;
        }
        $conn = \ZDB\DB::getConnect();
        if ($conn->GetOne("SELECT COUNT(*) FROM contracts WHERE contract_number = " . $conn->qstr($number)) > 0) {
            $this->setError(__('sqs.eqissue.number_exists', ['number' => $number]));
            return;
        }

        $conn->BeginTrans();
        try {
            $customer = $f->customer->getKey() > 0 ? Customer::load($f->customer->getKey()) : null;
            if ($customer == null) {
                $customer = new Customer();
                $customer->customer_name = $name;
                $customer->type = 1;
            }
            if (strlen(trim($f->phone->getText()))) {
                $customer->phone = trim($f->phone->getText());
            }
            if (strlen(trim($f->address->getText()))) {
                $customer->address = trim($f->address->getText());
            }
            $customer->save();

            $contract = new Contract();
            $contract->contract_number = $number;
            $contract->customer_id = $customer->customer_id;
            $contract->createdon = $date;
            $contract->enddate = $enddate;
            $contract->shortdesc = trim($f->referrer->getText());
            $contract->creator_id = \App\System::getUser()->user_id;
            $contract->user_id = \App\System::getUser()->user_id;
            $contract->username = \App\System::getUser()->username;
            $contract->state = Contract::STATE_INWORK;
            $contract->save();

            $doc = Document::create('GoodsIssue');
            $doc->document_number = $doc->nextNumber();
            $doc->document_date = $date;
            $doc->customer_id = $customer->customer_id;
            $doc->notes = trim($f->notes->getText());
            $doc->headerdata['customer_name'] = $customer->customer_name;
            $doc->headerdata['contract_id'] = $contract->contract_id;
            $doc->headerdata['store'] = $store;
            $doc->headerdata['store_name'] = $f->store->getValueName();
            $doc->headerdata['payment'] = 0;
            $doc->headerdata['payed'] = 0;
            $doc->headerdata['pricetype'] = 'price1';
            $items = [];
            foreach (array_values($this->_units) as $i => $u) {
                $item = Item::load($u->item_id);
                $item->quantity = 1;
                $item->snumber = $u->snumber;
                $item->price = 0;
                $item->rowid = $i + 1;
                $items[$item->rowid] = $item;
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

        $this->setSuccess(__('sqs.eqissue.done', ['number' => $number, 'doc' => $doc->document_number, 'customer' => $customer->customer_name]));
        $this->_units = [];
        $f->units->Reload();
        $f->customer->setKey(0);
        $f->customer->setText('');
        $f->phone->setText('');
        $f->address->setText('');
        $f->referrer->setText('');
        $f->notes->setText('');
        $f->contract_number->setText(self::nextContractNumber());
    }
}
