<?php

namespace App\Pages\Service;

use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Helper as H;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;

/**
 * SquirrelStock: импорт номенклатуры и остатков из оборотно-сальдовой ведомости 1С (.xls, .xlsx).
 *  - Счет 10 (материалы): на позицию две строки — «Название | сумма» и «Кол-во | количество».
 *    Позиция ищется по точному названию, иначе создается; остаток приходуется по цене сумма / количество.
 *  - Счет 01 (основные средства): строка на единицу «Название, инв.№: номер». Товар создается с учетом
 *    по серийным номерам, единица приходуется с инвентарным номером. Номер, который уже есть на складе, пропускается.
 * Приход — один документ «Оприбуткування ТМЦ» на дату из формы, сразу проведенный. Названия остаются как в 1С.
 */
class Import1C extends \App\Pages\Base
{
    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowSer('Import1C')) {
            return;
        }

        $form = $this->add(new Form('iform'));
        $form->add(new \Zippy\Html\Form\File('filename'));
        $form->add(new DropDownChoice('store', \App\Entity\Store::getList(), H::getDefStore()));
        $form->add(new Date('date', time()));
        $form->add(new DropDownChoice('cat', [0 => __('common.ne_vybrano')] + \App\Entity\Category::getList(true), 0));
        $form->add(new SubmitButton('import'))->onClick($this, 'onImport');
    }

    public function onImport($sender) {
        $file = $this->iform->filename->getFile();
        if (strlen($file['tmp_name'] ?? '') == 0) {
            $this->setError(__('common.ne_obrano_fail'));
            return;
        }
        $store = intval($this->iform->store->getValue());
        if ($store == 0) {
            $this->setError(__('common.ne_obrano_sklad'));
            return;
        }
        try {
            $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name'])->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            $this->setError(__('service.import1c.bad_file'));
            return;
        }
        $account = null;
        foreach ($rows as $r) {
            if (preg_match('/по сч[её]ту:\s*(\d+)/u', (string)($r[0] ?? ''), $m)) {
                $account = $m[1];
                break;
            }
        }
        if ($account === null) {
            $this->setError(__('service.import1c.not_osv'));
            return;
        }

        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {
            $msg = $account == '01' ? $this->importFixed($rows, $store, basename($file['name'])) : $this->importMaterials($rows, $store, basename($file['name']));
            $conn->CommitTrans();
            $this->setSuccess($msg);
        } catch (\Throwable $e) {
            $conn->RollbackTrans();
            $this->setError($e->getMessage());
        }
    }

    private static function num($v): ?float {
        $v = str_replace([' ', ','], ['', '.'], trim((string)$v));
        return is_numeric($v) ? doubleval($v) : null;
    }

    private function importMaterials(array $rows, int $store, string $filename): string {
        $list = [];
        $pending = null;
        $started = false;
        foreach ($rows as $r) {
            $a = trim((string)($r[0] ?? ''));
            if (in_array('Дебет', array_map(fn($v) => trim((string)$v), $r), true)) {
                $started = true;
                continue;
            }
            if (!$started || $a === '' || preg_match('/по сч[её]ту:/u', $a)) {
                continue;
            }
            if (in_array($a, ['Итого', 'Итого развернутое'], true)) {
                $pending = 'total';
                continue;
            }
            if (in_array($a, ['Кол-во', 'Количество'], true)) {
                if (is_int($pending)) {
                    $list[$pending]['qty'] = self::num($r[1] ?? '');
                }
                $pending = null;
                continue;
            }
            if ($a === 'Показатели') {
                continue;
            }
            $list[] = ['name' => preg_replace('/\s+/u', ' ', $a), 'sum' => self::num($r[1] ?? ''), 'qty' => null];
            $pending = count($list) - 1;
        }

        $created = 0;
        $details = [];
        foreach ($list as $p) {
            [$item, $new] = $this->findOrCreate($p['name'], false);
            $created += $new ? 1 : 0;
            if (($p['qty'] ?? 0) > 0) {
                $item->quantity = $p['qty'];
                $item->price = H::fa(($p['sum'] ?? 0) / $p['qty']);
                $item->snumber = '';
                $item->sdate = 0;
                $details[] = $item;
            }
        }
        $doc = $this->receipt($details, $store, $filename);

        return __('service.import1c.done_materials', ['items' => count($list), 'created' => $created, 'received' => count($details), 'doc' => $doc]);
    }

    private function importFixed(array $rows, int $store, string $filename): string {
        $conn = \ZDB\DB::getConnect();
        $units = 0;
        $skipped = 0;
        $created = 0;
        $details = [];
        foreach ($rows as $r) {
            if (!preg_match('/^\s*(.*?)\s*,\s*инв\.\s*№:\s*(\S+)\s*$/u', (string)($r[0] ?? ''), $m)) {
                continue;
            }
            $units++;
            [$item, $new] = $this->findOrCreate(preg_replace('/\s+/u', ' ', $m[1]), true);
            $created += $new ? 1 : 0;
            $inv = $m[2];
            $onstock = $conn->GetOne('select coalesce(sum(qty), 0) from store_stock where item_id = ' . intval($item->item_id) . ' and snumber = ' . $conn->qstr($inv));
            if ($onstock > 0) {
                $skipped++;
                continue;
            }
            $unit = clone $item;
            $unit->quantity = 1;
            $unit->price = 0;
            $unit->snumber = $inv;
            $unit->sdate = 0;
            $details[] = $unit;
        }
        $doc = $this->receipt($details, $store, $filename);

        return __('service.import1c.done_fixed', ['units' => $units, 'created' => $created, 'received' => count($details), 'skipped' => $skipped, 'doc' => $doc]);
    }

    /**
     * Позиция по точному названию; новая — с единицей «шт», категорией из формы и, для основных средств, учетом по серийным номерам
     */
    private function findOrCreate(string $name, bool $serial): array {
        $item = Item::getFirst('itemname = ' . Item::qstr($name) . ' and disabled <> 1');
        if ($item != null) {
            if ($serial && $item->useserial != 1) {
                $item->useserial = 1;
                $item->save();
            }
            return [$item, false];
        }
        $item = new Item();
        $item->itemname = $name;
        $item->msr = __('app.item.sht');
        $item->cat_id = intval($this->iform->cat->getValue());
        $item->useserial = $serial ? 1 : 0;
        $item->save();

        return [$item, true];
    }

    /**
     * Проведенный документ «Оприбуткування ТМЦ» на дату из формы; номер документа или '-', если приходовать нечего
     */
    private function receipt(array $details, int $store, string $filename): string {
        if (count($details) == 0) {
            return '-';
        }
        $doc = Document::create('IncomeItem');
        $doc->document_number = $doc->nextNumber();
        if (strlen($doc->document_number) == 0) {
            $doc->document_number = 'ОТ-00001';
        }
        $doc->document_date = $this->iform->date->getDate();
        $doc->headerdata['store'] = $store;
        $doc->notes = __('service.import1c.doc_notes', ['file' => $filename]);
        $amount = 0;
        $list = [];
        foreach ($details as $i => $item) {
            $list[$i + 1] = $item;
            $amount += $item->quantity * $item->price;
        }
        $doc->packDetails('detaildata', $list);
        $doc->amount = H::fa($amount);
        $doc->payamount = 0;
        $doc->headerdata['payed'] = 0;
        $doc->save();
        $doc->updateStatus(Document::STATE_NEW);
        $doc->updateStatus(Document::STATE_EXECUTED);

        return $doc->document_number;
    }
}
