<?php

namespace App\SQS;

use App\Entity\Item;

/**
 * SquirrelStock: остаток единицы с серийным (инвентарным) номером на дату документа. Ядро при запрете минуса сверяет
 * только общий остаток позиции на сегодня, поэтому у модели с несколькими единицами выдавался уже выданный номер, а
 * документ задним числом уводил номер в минус на прошлые даты. Вызывается из проведения документов, которые списывают
 * со склада: расходная накладная, списание, перемещение, возврат поставщику, недостача по инвентаризации.
 */
class SerialStock
{
    /**
     * Сколько единиц номера $item->snumber не хватает, чтобы списать $qty со склада $store_id датой $date: остаток на
     * конец каждого дня с $date по последний документ (без самого документа $document_id) не должен уйти в минус.
     * 0 — хватает (или номер не задан, позиция без серийного учета, минус разрешен в настройках).
     */
    public static function shortage(Item $item, float $qty, int $store_id, int $date, int $document_id = 0): float {
        if ($item->useserial != 1 || strlen((string)$item->snumber) == 0 || \App\System::getOption('common', 'allowminus') == 1) {
            return 0;
        }
        $conn = \ZDB\DB::getConnect();
        $rows = $conn->GetAll("SELECT DATE(d.document_date) AS dd, SUM(e.quantity) AS q FROM entrylist e
            JOIN documents d ON d.document_id = e.document_id JOIN store_stock s ON s.stock_id = e.stock_id
            WHERE s.item_id = " . intval($item->item_id) . " AND s.snumber = " . $conn->qstr($item->snumber) . " AND s.store_id = " . intval($store_id) . "
              AND e.document_id <> " . intval($document_id) . " GROUP BY DATE(d.document_date) ORDER BY dd");
        $day = date('Y-m-d', $date);
        $run = 0.0;
        $min = null;
        foreach ($rows as $r) {
            if ($r['dd'] >= $day && $min === null && $r['dd'] > $day) {
                $min = $run;   // в день документа движений не было: остаток в этот день — на конец предыдущего
            }
            $run += doubleval($r['q']);
            if ($r['dd'] >= $day) {
                $min = $min === null ? $run : min($min, $run);
            }
        }
        $min = $min ?? $run;
        return max(0.0, round($qty - $min, 4));
    }

    /**
     * Условие SQL «позиция $i (items) ведется партиями» — Item::serialMode() 1 или 2, по XML в items.detail. Номер в
     * store_stock.snumber у такой позиции — партия расходника, а не единица оборудования.
     */
    public static function batchSql(string $i = 'i'): string {
        $common = intval(\App\System::getOption('common', 'usesnumber'));
        if ($common == 0) {
            return '0=1';
        }
        $d = "COALESCE({$i}.detail, '')";   // NULL в условии дал бы NULL, и NOT батча выкинул бы единицу
        $own = "{$d} REGEXP '<snmode>[123]</snmode>'";
        return "({$d} LIKE '%<useserial>1</useserial>%' AND ({$d} REGEXP '<snmode>[12]</snmode>'"
            . ($common == 1 || $common == 2 ? " OR NOT {$own}" : '') . '))';
    }

    /**
     * Режим серий для проверок строки документа: у позиции с учетом серий — ее (Item::serialMode), у остальных — общий из
     * настроек, как в формах Zippy до режима у позиции (там проверки без флажка useserial смотрели на общий режим).
     */
    public static function rowMode(Item $item): int {
        return $item->serialMode() ?: intval(\App\System::getOption('common', 'usesnumber'));
    }

    private static array $used = [];

    /**
     * Режим серий $mode (2 — срок годности, 3 — серийные номера) встречается в базе: общий из настроек или у позиции с учетом
     * серий. Для шаблонов форм (флаги usesnumberdate, usesnumberitem в App\Pages\Base): поле срока годности или ввод списком
     * номеров нужны, если такой режим есть хоть у одной позиции. При выключенных сериях — нет.
     */
    public static function modeUsed(int $mode): bool {
        $common = intval(\App\System::getOption('common', 'usesnumber'));
        if ($common == 0) {
            return false;
        }
        if ($common == $mode) {
            return true;
        }
        if (!isset(self::$used[$mode])) {
            self::$used[$mode] = (bool)\ZDB\DB::getConnect()->GetOne("SELECT 1 FROM items WHERE disabled <> 1
                AND detail LIKE '%<useserial>1</useserial>%' AND detail LIKE '%<snmode>" . intval($mode) . "</snmode>%' LIMIT 1");
        }
        return self::$used[$mode];
    }

    private static ?bool $mixed = null;

    /**
     * В базе встречаются разные режимы серий: у какой-то включенной позиции с учетом серий режим не тот, что в настройках.
     * Тогда подпись поля номера в строке документа зависит от позиции (см. rowFields).
     */
    public static function mixed(): bool {
        $common = intval(\App\System::getOption('common', 'usesnumber'));
        if ($common == 0) {
            return false;
        }
        if (self::$mixed === null) {
            self::$mixed = (bool)\ZDB\DB::getConnect()->GetOne("SELECT 1 FROM items WHERE disabled <> 1
                AND detail LIKE '%<useserial>1</useserial>%' AND detail REGEXP '<snmode>[123]</snmode>'
                AND detail NOT LIKE '%<snmode>{$common}</snmode>%' LIMIT 1");
        }
        return self::$mixed;
    }

    /**
     * Поля строки документа по позиции строки $item (null — позиция не выбрана): подпись поля номера и видимость поля
     * «годен до». Шаблоны форм берут их из переменных sqs_snlabel и sqs_sdate (App\Pages\Base::beforeRender), выбор позиции
     * (OnChangeItem, AJAX) меняет их на месте. Без разных режимов в базе подпись прежняя — по общему режиму; при общем
     * режиме «срок годности» поле срока видно всегда, как в Zippy.
     */
    public static function rowFields(\Zippy\Html\WebPage $page, ?Item $item, bool $ajax = false): void {
        $common = intval(\App\System::getOption('common', 'usesnumber'));
        if ($item != null && $item->item_id > 0 && self::mixed()) {
            $mode = self::rowMode($item);
        } else {
            $mode = self::mixed() ? 0 : $common;
        }
        $label = $mode == 3 ? __('common.seriinyi_nomer') : ($mode == 0 ? __('sqs.snmode.snumber_any') : __('common.seriia_partiia_vyrobnyka'));
        $sdate = $common == 2 || ($item != null && $item->item_id > 0 && self::rowMode($item) == 2);
        $page->_tvars['sqs_snlabel'] = $label;
        $page->_tvars['sqs_sdate'] = $sdate;
        if (!$ajax) {
            return;
        }
        $response = \Zippy\WebApplication::$app->getResponse();
        if (self::mixed()) {
            $response->addAjaxResponse(['id' => 'sqssnlabel', 'type' => 'Label', 'data' => $label, 'ishtml' => false]);
        }
        $date = $page->editdetail->getComponent('editsdate');   // поле срока есть только в формах прихода
        if ($date != null && $common != 2 && self::modeUsed(2)) {
            $response->addAjaxResponse(['id' => 'editsdate', 'type' => 'Visible', 'visible' => $sdate]);   // и подпись, и блок с data-label
            if (!$sdate) {
                $date->setText('');   // срок, введенный для прежней позиции, не уходит в строку без срока
            }
        }
    }

    /**
     * Условие SQL «строка остатка $s (store_stock) с позицией $i — единица оборудования»: есть номер, и позиция ведется не
     * партиями. Пока режимы позиций не заданы и общий режим — серийные номера, это прежнее «snumber <> ''».
     */
    public static function unitSql(string $s = 's', string $i = 'i'): string {
        return "{$s}.snumber <> '' AND NOT " . self::batchSql($i);
    }

    /**
     * Проверка при проведении: номера не хватает — исключение. Именно исключение, а не false из Execute(): проведение
     * (Document::updateStatus) не смотрит, что вернул Execute(), и документ помечался проведенным без движений.
     */
    public static function check(Item $item, float $qty, int $store_id, \App\Entity\Doc\Document $doc): void {
        if (self::shortage($item, $qty, $store_id, intval($doc->document_date), intval($doc->document_id)) > 0) {
            throw new \Exception(__('sqs.serial_not_on_date', ['snumber' => $item->snumber, 'itemname' => $item->itemname, 'date' => date('d.m.Y', $doc->document_date)]));
        }
    }
}
