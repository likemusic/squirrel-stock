<?php

namespace App\Pages\Reference;

use App\Helper as H;
use App\SQS\Units;
use Zippy\Html\DataList\ArrayDataSource;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;

/**
 * SquirrelStock: справочник единиц оборудования — инвентарный и заводской номер, где сейчас единица.
 * Единица появляется с приходом товара с серийным (инвентарным) номером; здесь задается заводской номер.
 * Поиск — по модели, инвентарному или заводскому номеру, получателю.
 */
class EquipmentUnits extends \App\Pages\Base
{
    public const MAX_ROWS = 300;

    public array $_list = [];
    public string $_editkey = '';

    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowRef('EquipmentUnits')) {
            return;
        }
        $this->add(new Form('filter'))->onSubmit($this, 'onSearch');
        $this->filter->add(new TextInput('searchtext'));
        $this->filter->add(new DropDownChoice('kind', [
            'active' => __('sqs.units.kind_active'),
            'store'  => __('sqs.units.kind_store'),
            'out'    => __('sqs.units.kind_out'),
            'off'    => __('sqs.units.kind_off'),
            'all'    => __('common.vsi'),
        ], 'active'));

        $this->add(new Label('count'));
        $this->add(new DataView('units', new ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_list')), $this, 'unitOnRow'));

        $this->add(new Form('editform'))->setVisible(false);
        $this->editform->add(new Label('editunit'));
        $this->editform->add(new TextInput('editfactory'));
        $this->editform->add(new SubmitButton('save'))->onClick($this, 'onSave');
        $this->editform->add(new SubmitButton('cancel'))->onClick($this, 'onCancel');

        $this->onSearch(null);
    }

    public function onSearch($sender) {
        $text = mb_strtolower(trim($this->filter->searchtext->getText()));
        $kind = $this->filter->kind->getValue();
        $customers = [];
        $list = [];
        foreach (Units::current() as $u) {
            if ($kind == 'active' && $u['kind'] == 'off' || !in_array($kind, ['active', 'all']) && $u['kind'] != $kind) {
                continue;
            }
            $u['customer'] = '';
            if ($u['customer_id'] > 0) {
                $customers[$u['customer_id']] ??= (\App\Entity\Customer::load($u['customer_id'])->customer_name ?? '');
                $u['customer'] = $customers[$u['customer_id']];
            }
            if ($text !== '' && !str_contains(mb_strtolower($u['itemname'] . ' ' . $u['snumber'] . ' ' . $u['factory_no'] . ' ' . $u['customer']), $text)) {
                continue;
            }
            $list[] = new \App\DataItem($u);
        }
        usort($list, fn($a, $b) => [$a->itemname, $a->snumber] <=> [$b->itemname, $b->snumber]);
        $this->count->setText(count($list) > self::MAX_ROWS ? __('sqs.units.count_limited', ['count' => count($list), 'max' => self::MAX_ROWS]) : __('sqs.units.count', ['count' => count($list)]));
        $this->_list = array_slice($list, 0, self::MAX_ROWS);
        $this->units->Reload();
    }

    public function unitOnRow($row) {
        $u = $row->getDataItem();
        $row->add(new Label('itemname', $u->itemname));
        $row->add(new Label('snumber', $u->snumber));
        $row->add(new Label('factory', $u->factory_no));
        if ($u->kind == 'store') {
            $where = $u->stores;
        } elseif ($u->kind == 'off') {
            $where = __('sqs.units.written_off');
        } else {
            $where = trim($u->customer . ($u->contract_number ? ', ' . __('sqs.units.contract') . ' ' . $u->contract_number : '')
                     . ($u->enddate ? ', ' . __('sqs.units.until') . ' ' . H::fd($u->enddate) : ''));
        }
        $row->add(new Label('where', $where));
        $row->add(new ClickLink('edit', $this, 'onEdit'));
    }

    public function onEdit($sender) {
        $u = $sender->getOwner()->getDataItem();
        $this->_editkey = Units::key($u->item_id, $u->snumber);
        $this->editform->editunit->setText(Units::label($u->itemname, $u->snumber, ''));
        $this->editform->editfactory->setText($u->factory_no);
        $this->editform->setVisible(true);
    }

    public function onSave($sender) {
        [$item_id, $snumber] = explode('|', $this->_editkey, 2);
        Units::setFactoryNo((int)$item_id, $snumber, $this->editform->editfactory->getText());
        $this->editform->setVisible(false);
        $this->onSearch(null);
        $this->setSuccess(__('sqs.units.saved'));
    }

    public function onCancel($sender) {
        $this->editform->setVisible(false);
    }
}
