<?php

namespace App\SQS;

use App\Entity\Doc\Document;

/**
 * SquirrelStock: журнал правок документов — что изменилось при сохранении (было → стало, кто, когда).
 * В Zippy есть только журнал смены статусов (docstatelog); старые значения полей не хранились.
 * Сравниваются существенные поля: номер, дата, контрагент, склад, примечание, сумма и строки (позиция, серия,
 * количество, цена). Таблица sqs_doclog — db/squirrelstock.sql.
 */
class DocAudit
{
    // изменения содержимого проведенного документа запрещены: проводки остались бы от старого содержимого
    public const CONTENT = ['date', 'customer', 'store', 'rows'];

    private static array $pending = [];

    /**
     * Обработчик beforeSave (App\SQS\Hooks): изменения существующего документа; содержимое проведенного менять нельзя —
     * проводки остались бы от старого содержимого, сначала отменить проведение
     */
    public static function beforeSave(Document $doc): void {
        if ($doc->document_id <= 0) {
            return;
        }
        $stored = Document::load($doc->document_id);
        if ($stored == null) {
            return;
        }
        $changes = self::diff(self::snapshot($stored), self::snapshot($doc));
        if (self::contentChanged($changes) && $doc->isExecuted()) {
            throw new \Exception(__('sqs.doclog.executed_locked', ['number' => $stored->document_number]));
        }
        self::setPending($doc, $changes);
    }

    // обработчик afterSave: изменения, найденные в beforeSave, — в журнал
    public static function afterSave(Document $doc): void {
        self::writePending($doc);
    }

    public static function snapshot(Document $doc): array {
        $rows = [];
        foreach ($doc->unpackDetails('detaildata') as $r) {
            $key = intval($r->item_id ?? 0) . '|' . ($r->snumber ?? '');
            $rows[$key] = [
                'name'  => trim(($r->itemname ?? '') . (strlen($r->snumber ?? '') ? ' ' . $r->snumber : '')),
                'qty'   => doubleval($rows[$key]['qty'] ?? 0) + doubleval($r->quantity ?? 0),
                'price' => doubleval($r->price ?? 0),
            ];
        }
        ksort($rows);

        return [
            'number'   => (string)$doc->document_number,
            'date'     => $doc->document_date ? date('Y-m-d', is_numeric($doc->document_date) ? $doc->document_date : strtotime($doc->document_date)) : '',
            'customer' => intval($doc->customer_id),
            'store'    => intval($doc->headerdata['store'] ?? 0),
            'notes'    => trim((string)$doc->notes),
            'amount'   => round(doubleval($doc->amount), 2),
            'rows'     => $rows,
        ];
    }

    /**
     * Список изменений: [['field' => ..., 'old' => текст, 'new' => текст], ...]
     */
    public static function diff(array $old, array $new): array {
        $changes = [];
        foreach (['number', 'date', 'customer', 'store', 'notes', 'amount'] as $f) {
            if ($old[$f] != $new[$f]) {
                $changes[] = ['field' => $f, 'old' => self::show($f, $old[$f]), 'new' => self::show($f, $new[$f])];
            }
        }
        foreach (array_unique(array_merge(array_keys($old['rows']), array_keys($new['rows']))) as $key) {
            $a = $old['rows'][$key] ?? null;
            $b = $new['rows'][$key] ?? null;
            if ($a === null || $b === null || $a['qty'] != $b['qty'] || $a['price'] != $b['price']) {
                $changes[] = ['field' => 'rows', 'item' => ($b ?? $a)['name'],
                    'old' => $a ? self::row($a) : '—', 'new' => $b ? self::row($b) : '—'];
            }
        }

        return $changes;
    }

    public static function contentChanged(array $changes): bool {
        foreach ($changes as $c) {
            if (in_array($c['field'], self::CONTENT, true)) {
                return true;
            }
        }
        return false;
    }

    // изменения между beforeSave и afterSave: свойства сущностей ZDB попадают в поля таблицы, поэтому — отдельно
    public static function setPending(Document $doc, array $changes): void {
        self::$pending[spl_object_id($doc)] = $changes;
    }

    public static function writePending(Document $doc): void {
        $changes = self::$pending[spl_object_id($doc)] ?? [];
        unset(self::$pending[spl_object_id($doc)]);
        if (!$changes) {
            return;
        }
        $conn = \ZDB\DB::getConnect();
        // время программы, не базы: NOW() в базе может быть в другом поясе (в Docker — UTC)
        $conn->Execute('INSERT INTO sqs_doclog (document_id, user_id, createdon, changes) VALUES (' . intval($doc->document_id) . ', '
            . intval(\App\System::getUser()->user_id) . ', ' . $conn->qstr(date('Y-m-d H:i:s')) . ', ' . $conn->qstr(json_encode($changes, JSON_UNESCAPED_UNICODE)) . ')');
    }

    /**
     * Журнал правок документа для просмотра: [['date' =>, 'user' =>, 'text' =>], ...]
     */
    public static function getList(int $document_id): array {
        $conn = \ZDB\DB::getConnect();
        $list = [];
        foreach ($conn->Execute("SELECT l.createdon, l.changes, u.username FROM sqs_doclog l LEFT JOIN users_view u ON u.user_id = l.user_id
                                 WHERE l.document_id = {$document_id} ORDER BY l.id") as $r) {
            foreach (json_decode($r['changes'], true) ?: [] as $c) {
                $label = $c['field'] == 'rows' ? $c['item'] : __('sqs.doclog.' . $c['field']);
                $list[] = ['date' => \App\Helper::fdt(strtotime($r['createdon'])), 'user' => $r['username'] ?? '',
                           'text' => $label . ': ' . $c['old'] . ' → ' . $c['new']];
            }
        }
        return $list;
    }

    private static function show(string $field, $v): string {
        if ($field == 'customer') {
            return $v > 0 ? (\App\Entity\Customer::load($v)->customer_name ?? (string)$v) : '—';
        }
        if ($field == 'store') {
            return $v > 0 ? (\App\Entity\Store::load($v)->storename ?? (string)$v) : '—';
        }
        if ($field == 'date') {
            return $v ? \App\Helper::fd(strtotime($v)) : '—';
        }
        return (string)$v === '' ? '—' : (string)$v;
    }

    private static function row(array $r): string {
        return \App\Helper::fqty($r['qty']) . ' × ' . \App\Helper::fa($r['price']);
    }
}
