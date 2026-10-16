<?php

namespace App\Pages\Service;

use App\Entity\Contract;
use App\Helper as H;
use App\SQS\Units;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;

/**
 * SquirrelStock: продлить срок пользования оборудованием — новая дата окончания договора («Срок до» или «до востребования»).
 * Штатно договор «В работе» не редактируется (Справочники → Договоры): пришлось бы переводить его в «Новый» и обратно.
 * Здесь меняется только дата окончания, статус остается; в комментарии договора — было → стало, кто и когда.
 * Открывается кнопкой «Продлить» из напоминания на главной (App\SQS\EquipmentDue), из отчета «Оборудование: где сейчас» и из карточки получателя;
 * в меню нет. Право — как у «Выдачи оборудования по договору».
 * Адрес: Service/EquipmentExtend&arg=<contract_id>[/where|/card] — после сохранения возврат на главную, в отчет (where) или в карточку получателя договора (card).
 */
class EquipmentExtend extends \App\Pages\Base
{
    private int $contract_id = 0;
    private string $back = '';
    private int $customer_id = 0;

    public function __construct($contract_id = 0, $back = '') {
        parent::__construct();
        if (false == \App\ACL::checkShowSer('EquipmentIssue')) {
            return;
        }
        $contract = Contract::load(intval($contract_id));
        if ($contract == null) {
            \App\Application::RedirectHome();
            return;
        }
        $this->contract_id = intval($contract->contract_id);
        $this->back = in_array($back, ['where', 'card'], true) ? $back : '';
        $this->customer_id = intval($contract->customer_id);

        $end = intval($contract->enddate);
        $ondemand = self::onDemand($end);
        $customer = \App\Entity\Customer::load($contract->customer_id);
        $units = array_filter(Units::current(), fn($u) => $u['kind'] == 'out' && $u['contract_id'] == $this->contract_id);
        $this->_tvars['contract'] = __('report.equipmentwhere.contract_of', ['number' => $contract->contract_number, 'date' => H::fd($contract->createdon)]);
        $this->_tvars['customer'] = $customer->customer_name ?? '';
        $this->_tvars['units'] = array_values(array_map(fn($u) => ['label' => Units::label($u['itemname'], $u['snumber'], $u['factory_no'])], $units));
        // мелкое оборудование на руках по этому договору — количеством
        foreach (Units::smallOnHand(0, intval($contract->customer_id)) as $r) {
            if ($r['contract_id'] == $this->contract_id) {
                $this->_tvars['units'][] = ['label' => $r['itemname'] . ', ' . __('report.equipmentwhere.small_pieces') . ': ' . H::fqty($r['qty'])];
            }
        }
        $this->_tvars['term'] = self::termText($end);

        $f = $this->add(new Form('eform'));
        $f->add(new Date('enddate', $ondemand ? 0 : $end));
        $f->add(new CheckBox('ondemand', $ondemand));
        $f->add(new SubmitButton('save'))->onClick($this, 'onSave');
        $this->_tvars['backurl'] = $this->backUrl();
    }

    private static function onDemand(int $end): bool {
        return $end == 0 || date('Y', $end) >= Units::ON_DEMAND_YEAR;
    }

    private static function termText(int $end): string {
        return self::onDemand($end) ? __('report.equipmentwhere.until_demand') : H::fd($end);
    }

    private function backUrl(): string {
        if ($this->back == 'card') {
            return '/index.php?p=App/Pages/Report/RecipientCard&arg=' . $this->customer_id;
        }
        return $this->back == 'where' ? '/index.php?p=App/Pages/Report/EquipmentWhere' : '/index.php?p=App/Pages/Main';
    }

    public function onSave($sender) {
        $contract = Contract::load($this->contract_id);
        if ($contract == null) {
            return;
        }
        $f = $this->eform;
        $new = $f->ondemand->isChecked() ? strtotime(EquipmentIssue::ON_DEMAND) : intval($f->enddate->getDate());
        if ($new <= 0) {
            $this->setError(__('sqs.eqissue.no_enddate'));
            return;
        }
        $old = intval($contract->enddate);
        $text = __('sqs.extend.note', ['from' => self::termText($old), 'to' => self::termText($new)]);
        if (self::termText($old) !== self::termText($new)) {
            $contract->enddate = $new;   // только дата окончания: статус и остальные поля договора не меняются
            $contract->save();
            $msg = new \App\Entity\Message();
            $msg->message = $text;
            $msg->created = time();
            $msg->user_id = \App\System::getUser()->user_id;
            $msg->item_id = $contract->contract_id;
            $msg->item_type = \App\Entity\Message::TYPE_CONTRACT;
            $msg->save();
        }
        \App\System::setSuccessMsg(__('sqs.extend.done', ['number' => $contract->contract_number, 'term' => self::termText($new)]));
        if ($this->back == 'card') {
            \App\Application::Redirect('\\App\\Pages\\Report\\RecipientCard', $this->customer_id);
            return;
        }
        \App\Application::Redirect($this->back == 'where' ? '\\App\\Pages\\Report\\EquipmentWhere' : '\\App\\Pages\\Main');
    }
}
