<?php

namespace App\Pages\Service;

use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Helper as H;
use App\SQS\Item1C;
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
 * Приход — один документ «Оприбуткування ТМЦ», сразу проведенный, на «дату оприходования» из формы; пусто — на дату
 * ведомости (конец ее периода). При первом переносе с историей выдачи — дата раньше первой выдачи; если она раньше начала
 * актуального периода, администратор отмечает «снять ограничение на время записи». Названия остаются как в 1С.
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
        $form->add(new Date('date'));   // дата оприходования; пусто — дата ведомости
        $form->add(new \Zippy\Html\Form\CheckBox('liftperiod', false))->setVisible(\App\System::getUser()->rolename == 'admins');
        $form->add(new DropDownChoice('cat', [0 => __('common.ne_vybrano')] + \App\Entity\Category::getList(true), 0));
        $form->add(new SubmitButton('import'))->onClick($this, 'onImport');
        $this->add(new \Zippy\Html\Label('report', '', true));
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
            $rows = \App\SQS\SpreadsheetFile::load($file['tmp_name'])->getActiveSheet()->toArray(null, true, false, false);
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
        $date = trim($this->iform->date->getText()) === '' ? self::osvDate($rows) : $this->iform->date->getDate();
        if (!$date) {
            $this->setError(__('service.import1c.no_date'));
            return;
        }
        $this->_date = $date;
        // начало актуального периода (закрытый период): документ раньше него ядро не сохранит
        $common = \App\System::getOptions('common', true);
        $actual = intval($common['actualdate'] ?? 0);
        $lift = $actual > $date;
        if ($lift && !($this->iform->liftperiod->isChecked() && \App\System::getUser()->rolename == 'admins')) {
            $this->setError(__('service.import1c.before_actual', ['date' => date('d.m.Y', $date), 'actual' => date('d.m.Y', $actual)]));
            return;
        }
        if ($lift) {
            $common['actualdate'] = strtotime(date('Y-m-d', $date));
            \App\System::setOptions('common', $common);
        }

        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {
            $msg = $account == '01' ? $this->importFixed($rows, $store, basename($file['name'])) : $this->importMaterials($rows, $store, basename($file['name']));
            $conn->CommitTrans();
            $this->setSuccess($msg . ', ' . __('service.import1c.dated', ['date' => date('d.m.Y', $date)]));
            $this->report->setText($this->reportHtml(), true);
        } catch (\Throwable $e) {
            $conn->RollbackTrans();
            $this->setError($e->getMessage());
        } finally {
            if ($lift) {   // начало периода — как было, даже если импорт прервался
                $common = \App\System::getOptions('common', true);
                $common['actualdate'] = $actual;
                \App\System::setOptions('common', $common);
                $msg = __('service.import1c.period_log', ['actual' => date('d.m.Y', $actual), 'date' => date('d.m.Y', $date)]);
                \App\Helper::log($msg);
                $this->setWarn($msg);
            }
        }
    }

    public int $_date = 0;

    /**
     * Дата ведомости — конец ее периода из заголовка (в строке с названием или отдельной строкой): «за 26.05.26 - 31.05.26», «за 01.01.24», «за Сентябрь 2026 г.»,
     * «за 1 квартал 2026 г.», «за 2026 г.»; null — не нашлась
     */
    public static function osvDate(array $rows): ?int {
        $months = ['январ' => 1, 'феврал' => 2, 'март' => 3, 'апрел' => 4, 'ма' => 5, 'июн' => 6, 'июл' => 7, 'август' => 8, 'сентябр' => 9, 'октябр' => 10, 'ноябр' => 11, 'декабр' => 12];
        foreach (array_slice($rows, 0, 12) as $r) {
            $a = trim((string)($r[0] ?? ''));
            if (!preg_match('/(^|\s)за\s+(.+)$/u', $a, $m)) {
                continue;
            }
            $p = mb_strtolower($m[2]);
            if (preg_match_all('/(\d{1,2})\.(\d{1,2})\.(\d{2,4})/', $p, $all, PREG_SET_ORDER)) {
                [, $d, $mo, $y] = end($all);
                $y = strlen($y) == 2 ? 2000 + intval($y) : intval($y);
                return checkdate(intval($mo), intval($d), $y) ? strtotime(sprintf('%04d-%02d-%02d', $y, $mo, $d)) : null;
            }
            if (!preg_match('/(\d{4})/', $p, $ym)) {
                continue;
            }
            $y = intval($ym[1]);
            $mo = 12;
            if (preg_match('/([1-4])\s*квартал/u', $p, $q)) {
                $mo = intval($q[1]) * 3;
            } elseif (preg_match('/([1-2])\s*полугоди/u', $p, $q)) {
                $mo = intval($q[1]) * 6;
            } else {
                foreach ($months as $stem => $n) {
                    if (preg_match('/(^|\s)' . $stem . '[а-я]*\s+\d{4}/u', $p)) {
                        $mo = $n;
                        break;
                    }
                }
            }
            return strtotime(date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $y, $mo))));
        }
        return null;
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

        // одноименные позиции 1С: строки ведомости с одинаковым названием сопоставляются позициям по цене (App\SQS\Item1C)
        $groups = [];
        foreach ($list as $i => $p) {
            $p['price'] = ($p['qty'] ?? 0) > 0 ? H::fa(($p['sum'] ?? 0) / $p['qty']) : null;
            $groups[$p['name']][$i] = $p;
        }
        $created = 0;
        $details = [];
        $this->_ambiguous = [];
        $this->_notes = [];
        foreach ($groups as $name => $rows) {
            $cands = Item1C::candidates($name);
            if (count($cands) == 0) {
                // позиция из импорта до отметок «из 1С» — по точному названию, если она одна на это название
                $legacy = Item::find('itemname = ' . Item::qstr(Item1C::itemName($name)) . ' and disabled <> 1 and item_id not in (select item_id from sqs_item1c)');
                if (count($legacy) == 1 && count($rows) == 1) {
                    $cands = [['item_id' => (int)reset($legacy)->item_id, 'itemname' => Item1C::itemName($name), 'price1c' => null]];
                } elseif (count($legacy) > 0) {
                    $cands = array_map(fn($it) => ['item_id' => (int)$it->item_id, 'itemname' => $it->itemname, 'price1c' => null], array_values($legacy));
                    $this->_ambiguous[] = ['name' => $name, 'rows' => $rows, 'cands' => $cands];
                    continue;
                }
            }
            $m = Item1C::match($rows, $cands);
            if ($m['ambiguous']) {
                $this->_ambiguous[] = ['name' => $name, 'rows' => $rows, 'cands' => $cands];
                continue;
            }
            foreach ($m['match'] as $i => $item_id) {
                $p = $rows[$i];
                if ($item_id > 0) {
                    $item = Item::load($item_id);
                } else {
                    $item = $this->newItem($name, false);
                    $created++;
                }
                Item1C::set((int)$item->item_id, $name, $p['price']);
                if ($item->itemname !== Item1C::itemName($name)) {
                    $this->_notes[] = __('service.import1c.renamed', ['name' => $item->itemname, 'name1c' => $name]);
                }
                if (in_array($i, $m['drift'], true)) {
                    $old = array_values(array_filter($cands, fn($c) => $c['item_id'] == $item_id))[0]['price1c'] ?? 0;
                    $this->_notes[] = __('service.import1c.price_drift', ['name' => $name, 'old' => H::fa($old), 'new' => $p['price']]);
                }
                if (($p['qty'] ?? 0) > 0) {
                    $item->quantity = $p['qty'];
                    $item->price = $p['price'];
                    $item->snumber = '';
                    $item->sdate = 0;
                    $details[] = $item;
                }
            }
        }
        $doc = $this->receipt($details, $store, $filename);

        return __('service.import1c.done_materials', ['items' => count($list), 'created' => $created, 'received' => count($details), 'doc' => $doc])
            . (count($this->_ambiguous) ? '. ' . __('service.import1c.ambiguous_count', ['n' => array_sum(array_map(fn($a) => count($a['rows']), $this->_ambiguous))]) : '');
    }

    public array $_ambiguous = [];   // [['name', 'rows' => [строки ведомости], 'cands' => [позиции]]]
    public array $_notes = [];

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
     * Позиция основных средств по названию из 1С (отметка sqs_item1c), иначе по точному названию; новая — с учетом по серийным номерам
     */
    private function findOrCreate(string $name, bool $serial): array {
        $cands = Item1C::candidates($name);
        $item = count($cands) ? Item::load($cands[0]['item_id']) : Item::getFirst('itemname = ' . Item::qstr(Item1C::itemName($name)) . ' and disabled <> 1');
        $new = false;
        if ($item == null) {
            $item = $this->newItem($name, $serial);
            $new = true;
        } elseif ($serial && ($item->useserial != 1 || in_array($item->serialMode(), [1, 2]))) {
            $item->useserial = 1;
            if ($item->serialMode() != 3) {
                $item->snmode = 3;   // общий режим — партии: основному средству режим серийных номеров задается явно
            }
            $item->save();
        }
        Item1C::set((int)$item->item_id, $name);

        return [$item, $new];
    }

    // новая позиция: единица «шт», категория из формы
    private function newItem(string $name, bool $serial): Item {
        $item = new Item();
        $item->itemname = $name;
        $item->msr = __('app.item.sht');
        $item->cat_id = intval($this->iform->cat->getValue());
        $item->useserial = $serial ? 1 : 0;
        if ($serial && $item->serialMode() != 3) {
            $item->snmode = 3;   // общий режим — партии: основному средству режим серийных номеров задается явно
        }
        $item->save();
        return $item;
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
        $doc->document_date = $this->_date;
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

    private static function e($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    // неоднозначные одноименные позиции (не оприходованы) и заметки
    private function reportHtml(): string {
        $h = '';
        if (count($this->_ambiguous)) {
            $h .= '<h6>' . __('service.import1c.ambiguous_title') . '</h6><p class="text-muted">' . __('service.import1c.ambiguous_help') . '</p>';
            $h .= '<table class="table table-sm w-auto" id="i1c-ambiguous">';
            foreach ($this->_ambiguous as $a) {
                $rows = implode('<br>', array_map(fn($p) => self::e(__('service.import1c.amb_row', ['qty' => $p['qty'] ?? '-', 'price' => $p['price'] ?? '-'])), $a['rows']));
                $cands = implode('<br>', array_map(fn($c) => self::e(__('service.import1c.amb_cand', ['id' => $c['item_id'], 'name' => $c['itemname'], 'price' => $c['price1c'] === null ? '-' : H::fa($c['price1c'])])), $a['cands']));
                $h .= '<tr><td>' . self::e($a['name']) . '</td><td>' . $rows . '</td><td>' . $cands . '</td></tr>';
            }
            $h .= '</table>';
        }
        if (count($this->_notes)) {
            $h .= '<h6>' . __('service.import1c.notes_title') . '</h6><ul id="i1c-notes">';
            foreach ($this->_notes as $n) {
                $h .= '<li>' . self::e($n) . '</li>';
            }
            $h .= '</ul>';
        }
        return $h;
    }
}
