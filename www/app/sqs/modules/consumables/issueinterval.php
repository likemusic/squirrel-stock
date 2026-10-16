<?php

namespace App\SQS;

use App\Helper as H;

/**
 * SquirrelStock: интервал выдачи — позицию (или любую позицию категории) одному получателю выдают не чаще раза в N дней
 * (например, гастростомические трубки — раз в три месяца, если не повредилась). Правила — таблица sqs_issue_interval
 * (schema.sql модуля), справочник «Інтервали видачі» (App\Pages\Reference\IssueIntervals).
 * Правило позиции важнее правила категории; правило категории действует и на подкатегории (ближайшее вверх по дереву).
 * Это предупреждение, а не запрет: выдать раньше можно (трубка повредилась).
 */
class IssueInterval
{
    /**
     * Правило для позиции: ['days' => N, 'items' => [id] | null, 'cats' => [id] | null] или null
     */
    public static function rule(int $item_id): ?array {
        $conn = \ZDB\DB::getConnect();
        $days = intval($conn->GetOne("SELECT days FROM sqs_issue_interval WHERE kind = 'item' AND ref_id = {$item_id}"));
        if ($days > 0) {
            return ['days' => $days, 'items' => [$item_id], 'cats' => null];
        }
        $cat = intval($conn->GetOne("SELECT cat_id FROM items WHERE item_id = {$item_id}"));
        $rules = [];
        foreach ($conn->Execute("SELECT ref_id, days FROM sqs_issue_interval WHERE kind = 'cat' AND days > 0") as $r) {
            $rules[intval($r['ref_id'])] = intval($r['days']);
        }
        if (!$rules) {
            return null;
        }
        $parents = [];
        foreach ($conn->Execute("SELECT cat_id, parent_id FROM item_cat") as $r) {
            $parents[intval($r['cat_id'])] = intval($r['parent_id']);
        }
        // ближайшая категория с правилом вверх по дереву
        for ($c = $cat, $guard = 0; $c > 0 && $guard < 50; $c = $parents[$c] ?? 0, $guard++) {
            if (isset($rules[$c])) {
                return ['days' => $rules[$c], 'items' => null, 'cats' => Categories::withChildren($c)];
            }
        }
        return null;
    }

    /**
     * Предупреждение, если получатель получал эту позицию (или позицию той же категории) меньше N дней назад
     * к дате $date; иначе null. $exclude — не учитывать этот документ (сам проводимый).
     */
    public static function warning(int $customer_id, int $item_id, int $date, int $exclude = 0): ?string {
        if ($customer_id <= 0 || $item_id <= 0) {
            return null;
        }
        $rule = self::rule($item_id);
        if ($rule == null) {
            return null;
        }
        $conn = \ZDB\DB::getConnect();
        $to = date('Y-m-d', $date);
        $from = date('Y-m-d', strtotime($to . ' -' . ($rule['days'] - 1) . ' days'));
        $what = $rule['items'] !== null ? 'st.item_id IN (' . implode(',', $rule['items']) . ')' : 'i.cat_id IN (' . implode(',', $rule['cats']) . ')';
        $last = $conn->GetOne("SELECT MAX(d.document_date) FROM entrylist e
                               JOIN documents d ON d.document_id = e.document_id
                               JOIN metadata m ON m.meta_id = d.meta_id
                               JOIN store_stock st ON st.stock_id = e.stock_id
                               JOIN items i ON i.item_id = st.item_id
                               WHERE m.meta_name = 'GoodsIssue' AND e.quantity < 0 AND d.customer_id = {$customer_id} AND {$what}
                                 AND d.document_date BETWEEN '{$from}' AND '{$to}' AND d.document_id <> " . intval($exclude));
        if (!$last) {
            return null;
        }
        $ago = intval(round((strtotime($to) - strtotime(date('Y-m-d', strtotime($last)))) / 86400));
        $name = (string)$conn->GetOne("SELECT itemname FROM items WHERE item_id = {$item_id}");
        return __('sqs.interval.warning', ['item' => $name, 'date' => H::fd(strtotime($last)), 'ago' => $ago, 'days' => $rule['days']]);
    }

    /**
     * Когда получателю можно следующую выдачу по каждому правилу, по которому он что-то получал (карточка получателя):
     * [['what' => позиция или категория, 'days', 'last' => дата последней выдачи, 'lastitem' => что выдали, 'next' => дата, с которой можно], ...]
     * Считается так же, как предупреждение warning(): правило позиции — эта позиция, правило категории — любая позиция категории и подкатегорий.
     */
    public static function nextIssues(int $customer_id): array {
        $conn = \ZDB\DB::getConnect();
        $out = [];
        foreach ($conn->Execute("SELECT kind, ref_id, days FROM sqs_issue_interval WHERE days > 0") as $r) {
            $ref = intval($r['ref_id']);
            if ($r['kind'] == 'item') {
                $what = "st.item_id = {$ref}";
                $name = (string)$conn->GetOne("SELECT itemname FROM items WHERE item_id = {$ref}");
            } else {
                $what = 'i.cat_id IN (' . implode(',', Categories::withChildren($ref) ?: [0]) . ')';
                $name = (string)$conn->GetOne("SELECT cat_name FROM item_cat WHERE cat_id = {$ref}");
            }
            $last = $conn->GetRow("SELECT d.document_date, i.itemname FROM entrylist e
                                   JOIN documents d ON d.document_id = e.document_id
                                   JOIN metadata m ON m.meta_id = d.meta_id
                                   JOIN store_stock st ON st.stock_id = e.stock_id
                                   JOIN items i ON i.item_id = st.item_id
                                   WHERE m.meta_name = 'GoodsIssue' AND e.quantity < 0 AND d.customer_id = " . intval($customer_id) . " AND {$what}
                                   ORDER BY d.document_date DESC, d.document_id DESC LIMIT 1");
            if (!$last) {
                continue;
            }
            $date = strtotime(date('Y-m-d', strtotime($last['document_date'])));
            $out[] = ['what' => $name, 'kind' => $r['kind'], 'days' => intval($r['days']), 'last' => $date, 'lastitem' => $last['itemname'],
                      'next' => strtotime(date('Y-m-d', $date) . ' +' . intval($r['days']) . ' days')];
        }
        usort($out, fn($a, $b) => [$a['next'], $a['what']] <=> [$b['next'], $b['what']]);
        return $out;
    }

    /**
     * Обработчик afterExecute расходной накладной (App\SQS\Hooks): предупреждение после проведения — запретить нельзя,
     * трубку выдают раньше срока, если она повредилась. Форма «Выдача расходников» предупреждает сама и ставит sqs_interval_checked.
     */
    public static function afterExecute(\App\Entity\Doc\Document $doc): void {
        if (!empty($doc->headerdata['sqs_interval_checked'])) {
            return;
        }
        $date = is_numeric($doc->document_date) ? intval($doc->document_date) : strtotime($doc->document_date);
        $warnings = [];
        foreach ($doc->unpackDetails('detaildata') as $item) {
            $w = self::warning(intval($doc->customer_id), intval($item->item_id), $date, intval($doc->document_id));
            if ($w !== null) {
                $warnings[$item->item_id] = $w;
            }
        }
        if ($warnings) {
            // текст попадает в toastr внутри кавычек JavaScript (App\Pages\Base) — без кавычек и переводов строк
            \App\System::setWarnMsg(str_replace(["'", '"', "\n", "\r"], ['’', '”', ' ', ' '], implode('; ', $warnings)));
        }
    }
}
