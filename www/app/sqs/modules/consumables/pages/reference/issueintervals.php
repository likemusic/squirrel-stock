<?php

namespace App\Pages\Reference;

use Zippy\Html\DataList\ArrayDataSource;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;

/**
 * SquirrelStock: справочник интервалов выдачи (App\SQS\IssueInterval) — «не чаще раза в N дней» для категории
 * (вместе с подкатегориями) или для позиции. При выдаче раньше срока — предупреждение, выдать можно.
 */
class IssueIntervals extends \App\Pages\Base
{
    public array $_rules = [];

    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowRef('IssueIntervals')) {
            return;
        }

        $this->add(new Form('catform'));
        $this->catform->add(new DropDownChoice('cat', \App\Entity\Category::getList(true), 0));
        $this->catform->add(new TextInput('catdays'));
        $this->catform->add(new SubmitButton('catsave'))->onClick($this, 'onSaveCat');

        $this->add(new Form('itemform'));
        $this->itemform->add(new AutocompleteTextInput('item'))->onText($this, 'onAutoItem');
        $this->itemform->add(new TextInput('itemdays'));
        $this->itemform->add(new SubmitButton('itemsave'))->onClick($this, 'onSaveItem');

        $this->add(new DataView('rules', new ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_rules')), $this, 'ruleOnRow'));
        $this->reload();
    }

    public function onAutoItem($sender) {
        return \App\Entity\Item::findArrayAC($sender->getText());
    }

    public function onSaveCat($sender) {
        $this->save('cat', intval($this->catform->cat->getValue()), $this->catform->catdays->getText());
        $this->catform->catdays->setText('');
    }

    public function onSaveItem($sender) {
        $this->save('item', intval($this->itemform->item->getKey()), $this->itemform->itemdays->getText());
        $this->itemform->item->setKey(0);
        $this->itemform->item->setText('');
        $this->itemform->itemdays->setText('');
    }

    // пустое или 0 — правило снимается
    private function save(string $kind, int $ref, string $days): void {
        if (false == \App\ACL::checkEditRef('IssueIntervals')) {
            return;
        }
        if ($ref <= 0) {
            $this->setError(__($kind == 'cat' ? 'sqs.interval.pick_cat' : 'sqs.interval.pick_item'));
            return;
        }
        $days = intval(trim($days));
        $conn = \ZDB\DB::getConnect();
        $conn->Execute("DELETE FROM sqs_issue_interval WHERE kind = '{$kind}' AND ref_id = {$ref}");
        if ($days > 0) {
            $conn->Execute("INSERT INTO sqs_issue_interval (kind, ref_id, days) VALUES ('{$kind}', {$ref}, {$days})");
        }
        $this->reload();
    }

    private function reload(): void {
        $conn = \ZDB\DB::getConnect();
        $cats = \App\Entity\Category::getList(true);
        $this->_rules = [];
        foreach ($conn->Execute("SELECT r.kind, r.ref_id, r.days, i.itemname, i.item_code FROM sqs_issue_interval r
                                 LEFT JOIN items i ON r.kind = 'item' AND i.item_id = r.ref_id ORDER BY r.kind, r.ref_id") as $r) {
            $name = $r['kind'] == 'cat' ? ($cats[$r['ref_id']] ?? '#' . $r['ref_id']) : trim($r['itemname'] . ', ' . $r['item_code'], ', ');
            $this->_rules[] = new \App\DataItem(['kind' => $r['kind'], 'ref_id' => intval($r['ref_id']), 'days' => intval($r['days']), 'name' => $name]);
        }
        usort($this->_rules, fn($a, $b) => [$a->kind, $a->name] <=> [$b->kind, $b->name]);
        $this->rules->Reload();
    }

    public function ruleOnRow($row) {
        $r = $row->getDataItem();
        $row->add(new Label('rulekind', __($r->kind == 'cat' ? 'common.katehoriia' : 'sqs.interval.item')));
        $row->add(new Label('rulename', $r->name));
        $row->add(new Label('ruledays', (string)$r->days));
        $row->add(new ClickLink('ruledel', $this, 'onDelete'));
    }

    public function onDelete($sender) {
        $r = $sender->getOwner()->getDataItem();
        $this->save($r->kind, $r->ref_id, '0');
    }
}
