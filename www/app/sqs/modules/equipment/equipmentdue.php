<?php

namespace App\SQS;

use App\Helper as H;

/**
 * SquirrelStock: напоминания на главной странице — выданное оборудование, у которого срок пользования истек
 * или истекает в ближайшие DAYS дней (ответ хосписа 1.4), и администратору — давно не было резервной копии.
 * Только переменные шаблона, без компонентов страницы.
 */
class EquipmentDue
{
    public const DAYS = 14;

    public static function tvars(): array {
        $today = strtotime(date('Y-m-d'));
        $rows = [];
        $customers = [];
        $canExtend = \App\ACL::checkShowSer('EquipmentIssue', false);   // продлить срок (App\Pages\Service\EquipmentExtend)
        foreach (Units::dueSoon(self::DAYS) as $u) {
            $c = $customers[$u['customer_id']] ??= \App\Entity\Customer::load($u['customer_id']);
            $days = intval(round(($u['enddate'] - $today) / 86400));
            $rows[] = [
                'customer' => $c->customer_name ?? '',
                'phone'    => $c->phone ?? '',
                'unit'     => Units::label($u['itemname'], $u['snumber'], $u['factory_no']),
                'contract' => $u['contract_number'],
                'extend'   => $canExtend ? '/index.php?p=App/Pages/Service/EquipmentExtend&arg=' . intval($u['contract_id']) : '',
                'enddate'  => H::fd($u['enddate']),
                'overdue'  => $days < 0,
                'days'     => $days < 0 ? __('sqs.due.overdue', ['days' => -$days]) : ($days == 0 ? __('sqs.due.today') : __('sqs.due.left', ['days' => $days])),
            ];
        }
        return ['sqs_due' => $rows, 'sqs_due_show' => count($rows) > 0, 'sqs_due_days' => self::DAYS];
    }

    // блок главной страницы (событие mainBlocks)
    public static function mainBlock(): string {
        $tv = self::tvars();
        return $tv['sqs_due_show'] ? (new \App\Report('sqs/equipmentdue.tpl'))->generate($tv, false) : '';
    }
}
