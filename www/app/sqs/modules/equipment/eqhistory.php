<?php

namespace App\SQS;

use App\Entity\Contract;
use App\Entity\Customer;
use App\Entity\Doc\Document;
use App\Entity\Item;

/**
 * SquirrelStock: импорт истории выдачи оборудования из Google-таблиц хосписа (выгрузка .xlsx), ZIPPY_GAPS п. 28.
 *
 * Два формата:
 *  - «Перепись оборудования»: листы по видам, строка — выдача единицы: № договора, дата, ФИО, адрес, телефон, наименование,
 *    зав. №, инв. №, дата возврата, срок пользования, пометки. Шапка в двух строках, колонки ищутся по заголовкам
 *    (с объединенными ячейками: «адрес» бывает на две колонки). Лист «СПИСАНО» — без шапки, раскладка плавает.
 *  - «Журнал выдачи оборудования»: №, дата регистрации договора, ФИО, адрес и телефон в одной ячейке, кем направлен,
 *    наименование, инв. (заводской) №, дата выдачи, дата возврата, примечание. Номера договора нет: «№/ГГ» по году регистрации.
 *
 * Правила (согласованы 15.10.2026): что однозначно — импортируется, что нет — в отчет с причиной: «ошибка» — строка
 * не импортирована, «заметка» — импортирована с допущением. Единица с инв. номером должна уже быть в базе (импорт ОСВ 01);
 * единица только с заводским номером создается товаром «по листу». Без номеров (мелочь) — на руках количеством на позицию по соответствиям «написание → позиция» (EqItemMap), остальное — пропуск со сводкой.
 * Одна выдача в обеих таблицах склеивается по единице и дате. Документы: приход единицы, которой нет в базе (накануне
 * первой выдачи), выдача по договору, возврат, списание — проводятся в хронологическом порядке. Что уже импортировано
 * (таблица sqs_eqhistory), повторно не создается.
 */
class EqHistory
{
    public const ON_DEMAND = '2099-12-31';

    public array $records = [];    // разобранные строки
    public array $problems = [];   // [level (error|note), sheet, row, contract, unit, text]
    public array $skipped = [];    // выдачи без номеров единицы: [лист => [наименование => число]]
    public array $missing = [];    // инв. номера, которых нет в базе: [номер => [лист, наименование]]
    public array $unitOnly = [];   // строки переписи только с единицей (без договора, даты и ФИО): [лист => число]
    public array $rowsRead = [];   // [файл => число строк с данными]
    public array $plan = [];       // события для записи
    public array $stat = [];
    public array $onhand = [];    // мелочь без номеров, перенесенная на руки: [позиция => штук]
    public array $unmapped = [];  // написания мелочи, которых нет в соответствиях (App\SQS\EqItemMap): [написание => строк]
    public ?array $itemMap = null; // соответствия «написание → позиция»; null — из базы
    public int $blocked = 0;      // дата актуальности из настроек, если она позже первого документа импорта

    // ---------- чтение ----------

    /**
     * Прочитать файл .xlsx: распознать формат и разобрать строки
     */
    public function readFile(string $path, string $filename): void {
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $titles = array_map(fn($s) => mb_strtolower(trim($s->getTitle())), $book->getAllSheets());
        $journal = in_array('обычные', $titles, true);
        $this->rowsRead[$filename] = 0;
        foreach ($book->getAllSheets() as $sheet) {
            $rows = self::sheetRows($sheet);
            if (count($rows) == 0) {
                continue;
            }
            $title = trim($sheet->getTitle());
            if ($journal) {
                $this->readJournal($title, $rows, self::headers($sheet, 1), $filename);
            } elseif (mb_strtolower($title) == 'списано') {
                $this->readWriteoffSheet($title, $rows, $filename);
            } else {
                $this->readCensus($title, $rows, self::headers($sheet, 2), $filename);
            }
        }
    }

    /**
     * Строки листа: [номер строки => [колонка (с 0) => значение]], значение — строка или ['date' => timestamp]
     */
    private static function sheetRows($sheet): array {
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $r = [];
            $it = $row->getCellIterator();
            $it->setIterateOnlyExistingCells(true);
            foreach ($it as $cell) {
                $v = $cell->getValue();
                if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $v = $v->getPlainText();
                }
                if ($v === null || (is_string($v) && trim($v) === '')) {
                    continue;
                }
                if (is_string($v) && str_starts_with($v, '=')) {
                    $v = $cell->getCalculatedValue();
                }
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                if ((is_int($v) || is_float($v)) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell) && $v > 0 && $v < 2958466) {
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($v);
                    $r[$col] = ['date' => strtotime($dt->format('Y-m-d'))];
                } elseif (is_float($v) && floor($v) == $v && abs($v) < 1e15) {
                    $r[$col] = (string)intval($v);
                } elseif (is_bool($v)) {
                    continue;
                } else {
                    $r[$col] = trim((string)$v);
                }
            }
            if (count($r)) {
                $rows[$row->getRowIndex()] = $r;
            }
        }
        return $rows;
    }

    /**
     * Заголовки колонок из первых $n строк с учетом объединенных ячеек: [колонка => текст в нижнем регистре]
     */
    private static function headers($sheet, int $n): array {
        $h = [];
        $maxcol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($r = 1; $r <= $n; $r++) {
            for ($c = 1; $c <= $maxcol; $c++) {
                $v = $sheet->getCell([$c, $r])->getValue();
                if (is_string($v) && trim($v) !== '' && !preg_match('/\d{3}/', $v)) {
                    $h[$c - 1] = trim(($h[$c - 1] ?? '') . ' ' . mb_strtolower(preg_replace('/\s+/u', ' ', $v)));
                }
            }
        }
        foreach ($sheet->getMergeCells() as $range) {
            [$from, $to] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($range);
            if ($from[1] > $n) {
                continue;
            }
            $text = $h[$from[0] - 1] ?? '';
            for ($c = $from[0]; $c <= $to[0]; $c++) {
                if ($text !== '' && !isset($h[$c - 1])) {
                    $h[$c - 1] = $text;
                }
            }
        }
        return $h;
    }

    // роли колонок переписи по заголовку
    private static function censusRoles(array $h): array {
        $roles = [];
        foreach ($h as $c => $t) {
            $role = null;
            if (str_contains($t, 'договор') || str_contains($t, '№п/п')) {
                $role = 'contract';
            } elseif (str_contains($t, 'возврат')) {
                $role = 'ret';
            } elseif (str_contains($t, 'срок')) {
                $role = 'term';
            } elseif (preg_match('/^(дата|число)$/u', $t)) {
                $role = 'date';
            } elseif (str_contains($t, 'фио') || str_contains($t, 'ф.и.о')) {
                $role = 'name';
            } elseif (str_contains($t, 'адрес')) {
                $role = 'address';
            } elseif (str_contains($t, 'телефон')) {
                $role = 'phone';
            } elseif (str_contains($t, 'наименование')) {
                $role = 'item';
            } elseif (str_contains($t, 'инв')) {
                $role = 'inv';
            } elseif (str_contains($t, 'зав')) {
                $role = 'factory';
            }
            if ($role) {
                $roles[$role][] = $c;
            }
        }
        // первые три колонки на всех листах — договор, дата, ФИО; на некоторых листах у них нет заголовка
        foreach (['contract' => 0, 'date' => 1, 'name' => 2] as $role => $c) {
            if (!isset($roles[$role]) && !in_array($c, array_merge(...array_values($roles ?: [[]])), true)) {
                $roles[$role] = [$c];
            }
        }
        return $roles;
    }

    private function readCensus(string $title, array $rows, array $h, string $file): void {
        $roles = self::censusRoles($h);
        if (!isset($roles['item']) || (!isset($roles['inv']) && !isset($roles['factory']))) {
            $this->problem('error', $title, 0, '', '', __('sqs.eqhist.bad_header'));
            return;
        }
        $known = array_merge(...array_values($roles));
        foreach ($rows as $n => $r) {
            if ($n <= 2) {
                continue;
            }
            $get = fn($role) => array_values(array_filter(array_map(fn($c) => $r[$c] ?? null, $roles[$role] ?? []), fn($v) => $v !== null));
            $rec = $this->newRecord('census', $title, $n, $file);
            $rec['contract_raw'] = self::text($get('contract')[0] ?? '');
            $rec['date_raw'] = $get('date')[0] ?? null;
            $rec['name'] = self::text($get('name')[0] ?? '');
            $rec['address'] = implode(', ', array_map([self::class, 'text'], $get('address')));
            $rec['phone'] = implode(', ', array_map([self::class, 'text'], $get('phone')));
            $rec['itemtext'] = self::text($get('item')[0] ?? '');
            $rec['inv'] = self::text($get('inv')[0] ?? '');
            $rec['factory'] = self::text($get('factory')[0] ?? '');
            $rec['ret_cells'] = $get('ret');
            $rec['term_cells'] = $get('term');
            $rec['note_cells'] = [];
            foreach ($r as $c => $v) {
                if (!in_array($c, $known, true)) {
                    $rec['note_cells'][] = $v;
                }
            }
            if (!$this->meaningful($rec)) {
                continue;
            }
            $this->rowsRead[$file]++;
            $this->parseCommon($rec);
            $this->records[] = $rec;
        }
    }

    private function readJournal(string $title, array $rows, array $h, string $file): void {
        $roles = [];
        foreach ($h as $c => $t) {
            $role = match (true) {
                $t === '№' => 'no',
                str_contains($t, 'регистрац') => 'cdate',
                str_contains($t, 'фио') => 'name',
                str_contains($t, 'адрес') => 'address',
                str_contains($t, 'направлен') => 'referrer',
                str_contains($t, 'наименование') => 'item',
                str_contains($t, 'инв') || str_contains($t, 'зав') => 'number',
                str_contains($t, 'выдачи') => 'idate',
                str_contains($t, 'возврат') => 'ret',
                str_contains($t, 'примечание') => 'note',
                default => null,
            };
            if ($role) {
                $roles[$role] = $c;
            }
        }
        foreach (['no', 'cdate', 'name', 'item', 'number', 'idate'] as $need) {
            if (!isset($roles[$need])) {
                $this->problem('error', $title, 1, '', '', __('sqs.eqhist.bad_header'));
                return;
            }
        }
        foreach ($rows as $n => $r) {
            if ($n <= 1) {
                continue;
            }
            $get = fn($role) => isset($roles[$role]) ? ($r[$roles[$role]] ?? null) : null;
            $rec = $this->newRecord('journal', $title, $n, $file);
            $rec['no'] = self::text($get('no') ?? '');
            $rec['cdate_raw'] = $get('cdate');
            $rec['date_raw'] = $get('idate');
            $rec['name'] = self::text($get('name') ?? '');
            [$rec['address'], $rec['phone']] = self::splitPhone(self::text($get('address') ?? ''));
            $rec['referrer'] = self::text($get('referrer') ?? '');
            if ($rec['referrer'] === '-') {
                $rec['referrer'] = '';
            }
            $rec['itemtext'] = self::text($get('item') ?? '');
            $rec['number'] = self::text($get('number') ?? '');
            $rec['ret_cells'] = $get('ret') !== null ? [$get('ret')] : [];
            $rec['term_cells'] = [];
            $rec['note_cells'] = $get('note') !== null ? [$get('note')] : [];
            if ($rec['name'] === '' && $rec['number'] === '' && $rec['itemtext'] === '') {
                continue;   // пустая нумерация
            }
            $this->rowsRead[$file]++;
            $this->parseJournal($rec);
            $this->records[] = $rec;
        }
    }

    /**
     * Лист «СПИСАНО»: копии строк выдачи, раскладка колонок меняется от строки к строке. Единица узнается только по
     * точному совпадению номера (в базе или в переписи); дата списания — дата в колонках после наименования.
     */
    private function readWriteoffSheet(string $title, array $rows, string $file): void {
        foreach ($rows as $n => $r) {
            $cells = array_values($r);
            if (count($cells) < 3) {
                continue;
            }
            $rec = $this->newRecord('writeoff', $title, $n, $file);
            $rec['contract_raw'] = self::text($r[0] ?? '');
            $rec['nums'] = [];
            $rec['dates'] = [];
            foreach ($r as $c => $v) {
                if ($c < 2) {
                    continue;
                }
                if (is_array($v)) {
                    $rec['dates'][] = $v['date'];
                } elseif (preg_match('/\d{2,}/', $v) && !preg_match('/[\/\\\\]/', $v) || preg_match('/^\d+$/', $v)) {
                    $rec['nums'][] = self::num($v);
                }
            }
            $this->rowsRead[$file]++;
            $this->records[] = $rec;
        }
    }

    private function newRecord(string $src, string $sheet, int $row, string $file): array {
        return ['src' => $src, 'sheet' => $sheet, 'row' => $row, 'file' => $file, 'contract' => '', 'cdate' => 0, 'idate' => 0,
                'name' => '', 'address' => '', 'phone' => '', 'referrer' => '', 'itemtext' => '', 'inv' => '', 'factory' => '', 'number' => '',
                'ret' => 0, 'off' => 0, 'enddate' => null, 'notes' => [], 'error' => false, 'unit' => null];
    }

    // строка переписи с данными выдачи, а не строка шапки, нумерации или продолжения
    private function meaningful(array $rec): bool {
        $hasDate = is_array($rec['date_raw']) || preg_match('/\d{1,2}[.,\/]\d{1,2}[.,\/]\d{2,4}/', (string)$rec['date_raw']);
        $hasName = (bool)preg_match('/\p{L}{2}/u', $rec['name']);
        if ($rec['contract_raw'] === '' && !$hasDate && !$hasName && ($rec['inv'] !== '' || $rec['factory'] !== '')) {
            $this->unitOnly[$rec['sheet']] = ($this->unitOnly[$rec['sheet']] ?? 0) + 1;   // перечень единиц без выдачи
            return false;
        }
        if ($rec['contract_raw'] === '' && !$hasDate && $rec['inv'] === '' && $rec['factory'] === '') {
            if ($rec['name'] !== '' && !preg_match('/^[\d\s.-]+$/u', $rec['name']) && $rec['itemtext'] !== '') {
                $this->problem('error', $rec['sheet'], $rec['row'], '', '', __('sqs.eqhist.no_data'));
            }
            return false;
        }
        return true;
    }

    // ---------- разбор полей ----------

    private static function text($v): string {
        if (is_array($v)) {
            return date('d.m.Y', $v['date']);
        }
        return trim(preg_replace('/\s+/u', ' ', (string)$v));
    }

    // номер единицы для сравнения: без пробелов и «№», в нижнем регистре
    public static function num($v): string {
        return mb_strtolower(preg_replace('/[\s№]+/u', '', self::text($v)));
    }

    // дата из ячейки: дата Excel или текст «дд.мм.гггг» (с запятыми и косыми чертами вместо точек, год из двух цифр)
    private static function date($v): ?int {
        if (is_array($v)) {
            return $v['date'];
        }
        if (preg_match('/(\d{1,2})\s*[.,\/]\s*(\d{1,2})\s*[.,\/]+\s*(\d{2,4})/', (string)$v, $m)) {
            $y = strlen($m[3]) == 2 ? 2000 + intval($m[3]) : intval($m[3]);
            if (checkdate(intval($m[2]), intval($m[1]), $y)) {
                return mktime(0, 0, 0, intval($m[2]), intval($m[1]), $y);
            }
        }
        return null;
    }

    public static function yearOk(int $ts): bool {
        $y = intval(date('Y', $ts));
        return $y >= 2010 && $y <= intval(date('Y')) + 1;
    }

    // телефоны (от 7 цифр) — отдельно от адреса
    private static function splitPhone(string $s): array {
        $phones = [];
        $addr = preg_replace_callback('/\+?\d[\d\s\-()]{5,}\d/u', function ($m) use (&$phones) {
            if (strlen(preg_replace('/\D/', '', $m[0])) >= 7) {
                $phones[] = trim($m[0]);
                return '';
            }
            return $m[0];
        }, $s);
        return [trim(preg_replace('/[\s,;]+$/u', '', preg_replace('/\s+/u', ' ', $addr))), implode(', ', $phones)];
    }

    /**
     * Статус из текста: ondemand, returned, off (списан), tooff (списать), recipient (умер, не под опекой), gift, note
     */
    public static function status(string $t): string {
        $t = mb_strtolower($t);
        return match (true) {
            (bool)preg_match('/до\s*вос+т?р/u', $t) => 'ondemand',
            (bool)preg_match('/списать/u', $t) => 'tooff',
            (bool)preg_match('/списан|списание/u', $t) => 'off',
            (bool)preg_match('/^(вернули|возврат)/u', $t) => 'returned',
            (bool)preg_match('/умер|не\s*под\s*опек|не\s*подопеч/u', $t) => 'recipient',
            (bool)preg_match('/безвозмездно|подарил/u', $t) => 'gift',
            default => 'note',
        };
    }

    private function parseCommon(array &$rec): void {
        // договор
        if (preg_match('/^(\d+)\s*[\/\\\\]\s*(\d{2})$/', $rec['contract_raw'], $m)) {
            $rec['contract'] = intval($m[1]) . '/' . $m[2];
        } elseif ($rec['contract_raw'] === '') {
            $this->fail($rec, __('sqs.eqhist.no_contract'));
        } else {
            $this->fail($rec, __('sqs.eqhist.bad_contract', ['value' => $rec['contract_raw']]));
        }
        // дата выдачи = дата договора
        $d = self::date($rec['date_raw']);
        if ($d === null) {
            $this->fail($rec, $rec['date_raw'] === null ? __('sqs.eqhist.no_date') : __('sqs.eqhist.bad_date', ['value' => self::text($rec['date_raw'])]));
        } elseif (!self::yearOk($d)) {
            $this->fail($rec, __('sqs.eqhist.bad_year', ['value' => date('d.m.Y', $d)]));
        } else {
            $rec['cdate'] = $rec['idate'] = $d;
        }
        $this->parseUnitCells($rec);
        $this->parseStatus($rec);
    }

    private function parseJournal(array &$rec): void {
        $cd = self::date($rec['cdate_raw']);
        if ($cd === null || !self::yearOk($cd)) {
            $this->fail($rec, $rec['cdate_raw'] === null ? __('sqs.eqhist.no_date') : __('sqs.eqhist.bad_date', ['value' => self::text($rec['cdate_raw'])]));
        } else {
            $rec['cdate'] = $cd;
        }
        if (preg_match('/^\d+$/', $rec['no']) && $cd) {
            $rec['contract'] = intval($rec['no']) . '/' . date('y', $cd);
        } elseif (preg_match('/^(\d+)\s*[\/\\\\]\s*(\d{2})$/', $rec['no'], $m)) {
            $rec['contract'] = intval($m[1]) . '/' . $m[2];
        } elseif (!$rec['error']) {
            $this->fail($rec, __('sqs.eqhist.bad_contract', ['value' => $rec['no']]));
        }
        $d = $rec['date_raw'] === null ? null : self::date($rec['date_raw']);
        if ($d !== null && self::yearOk($d)) {
            $rec['idate'] = $d;
        } elseif ($d !== null) {
            $this->fail($rec, __('sqs.eqhist.bad_year', ['value' => date('d.m.Y', $d)]));
        } elseif ($cd) {
            $rec['idate'] = $cd;
            if ($rec['date_raw'] !== null) {
                $rec['note_cells'][] = $rec['date_raw'];
            }
            $this->problem('note', $rec['sheet'], $rec['row'], $rec['contract'], $rec['number'], __('sqs.eqhist.idate_is_cdate'));
        }
        // номер единицы — инвентарный или заводской, разбирается при поиске единицы
        if (self::several($rec['number'])) {
            $this->fail($rec, __('sqs.eqhist.several_units', ['value' => $rec['number']]));
        }
        $this->parseStatus($rec);
    }

    // несколько номеров в одной ячейке
    private static function several(string $v): bool {
        return preg_match_all('/\d{3,}/', $v) >= 2 && (bool)preg_match('/[,;+\n]|\sи\s/u', $v);
    }

    private function parseUnitCells(array &$rec): void {
        foreach (['inv', 'factory'] as $k) {
            $v = $rec[$k];
            if ($v === '') {
                continue;
            }
            if (self::several($v)) {
                $this->fail($rec, __('sqs.eqhist.several_units', ['value' => $v]));
            } elseif (!preg_match('/\d/', $v)) {
                $rec['notes'][] = $v;   // «нет», «мв» и прочее — не номер
                $rec[$k] = '';
            }
        }
    }

    // статусы и даты из колонок «дата возврата», «срок пользования» и пометок
    private function parseStatus(array &$rec): void {
        $words = [];
        foreach ($rec['ret_cells'] as $v) {
            $d = self::date($v);
            if ($d !== null && (is_array($v) || preg_match('/^\D{0,12}\d{1,2}[.,\/]\d{1,2}[.,\/]\d{2,4}\D{0,3}$/u', $v))) {
                if (!self::yearOk($d)) {
                    $this->fail($rec, __('sqs.eqhist.bad_year', ['value' => date('d.m.Y', $d)]));
                } else {
                    $rec['ret'] = $d;
                    if (!is_array($v) && self::status($v) != 'note') {
                        $words[] = $v;
                    }
                }
            } else {
                $words[] = self::text($v);
            }
        }
        foreach ($rec['term_cells'] as $v) {
            $d = self::date($v);
            if (is_array($v) || ($d !== null && self::status($v) == 'note')) {
                $rec['enddate'] = $d;
            } else {
                $words[] = self::text($v);
            }
        }
        foreach ($rec['note_cells'] as $v) {
            $words[] = self::text($v);
        }
        foreach ($words as $w) {
            if ($w === '' || $w === '+' || $w === '-') {
                continue;
            }
            switch (self::status($w)) {
                case 'ondemand':
                    $rec['enddate'] = $rec['enddate'] ?? 0;
                    break;
                case 'returned':
                    $rec['returned'] = true;
                    break;
                case 'off':
                    $rec['offword'] = true;
                    $rec['notes'][] = $w;
                    break;
                case 'tooff':
                    $rec['notes'][] = $w;
                    $this->problem('note', $rec['sheet'], $rec['row'], $rec['contract'], self::unitLabel($rec), __('sqs.eqhist.to_writeoff'));
                    break;
                case 'recipient':
                    $rec['notes'][] = $w;
                    $this->problem('note', $rec['sheet'], $rec['row'], $rec['contract'], self::unitLabel($rec), __('sqs.eqhist.recipient_state', ['value' => $w]));
                    break;
                case 'gift':
                    $rec['notes'][] = $w;   // подаренное хоспису: выдается как обычное (ответ 1.10), слово — в примечание
                    break;
                default:
                    $rec['notes'][] = $w;
            }
        }
        // без даты (ответ 1.12): дата возврата выводится при составлении цепочки единицы, списание — датой импорта
        if (!empty($rec['returned']) && !$rec['ret']) {
            $rec['retunknown'] = true;
        }
        if (!empty($rec['offword'])) {
            if ($rec['ret']) {
                $rec['off'] = $rec['ret'];
            } else {
                $rec['offunknown'] = true;
            }
        }
        $rec['notes'] = array_values(array_unique($rec['notes']));
    }

    private static function unitLabel(array $rec): string {
        if (is_array($rec['unit'] ?? null)) {
            return $rec['unit']['snumber'];
        }
        return $rec['inv'] !== '' ? $rec['inv'] : ($rec['factory'] !== '' ? $rec['factory'] : $rec['number']);
    }

    private function fail(array &$rec, string $text): void {
        $rec['error'] = true;
        $this->problem('error', $rec['sheet'], $rec['row'], $rec['contract'], self::unitLabel($rec), $text);
    }

    private function problem(string $level, string $sheet, int $row, string $contract, string $unit, string $text): void {
        $this->problems[] = ['level' => $level, 'sheet' => $sheet, 'row' => $row, 'contract' => $contract, 'unit' => $unit, 'text' => $text];
    }

    // ---------- единицы ----------

    private array $censusIndex = [];   // номер (инв. или заводской) => [индексы записей переписи]

    /**
     * Единица в базе по номеру: инвентарный (store_stock.snumber) или заводской (sqs_unit); номер из 7 цифр — также с
     * потерянным ведущим нулем, если совпадение единственное. Возвращает ['item_id', 'snumber', 'itemname'] или null; 'many' — неоднозначно
     */
    public static function findUnit(string $number) {
        $conn = \ZDB\DB::getConnect();
        $find = function (string $n) use ($conn) {
            $q = $conn->qstr($n);
            $rows = $conn->GetAll("SELECT DISTINCT s.item_id, s.snumber, i.itemname FROM store_stock s JOIN items i ON i.item_id = s.item_id
                                   WHERE REPLACE(LOWER(s.snumber), ' ', '') = {$q}
                                   UNION
                                   SELECT u.item_id, u.snumber, i.itemname FROM sqs_unit u JOIN items i ON i.item_id = u.item_id
                                   WHERE REPLACE(LOWER(u.factory_no), ' ', '') = {$q}");
            return $rows;
        };
        $rows = $find($number);
        if (count($rows) == 0 && preg_match('/^\d{7}$/', $number)) {
            $rows = $find('0' . $number);
        }
        if (count($rows) > 1) {
            return 'many';
        }
        return count($rows) == 1 ? ['item_id' => (int)$rows[0]['item_id'], 'snumber' => $rows[0]['snumber'], 'itemname' => $rows[0]['itemname']] : null;
    }

    private function resolveUnits(): void {
        // перепись: инв. номер — единица в базе; только заводской — в базе или новая «по листу»
        foreach ($this->records as $i => &$rec) {
            if ($rec['src'] != 'census' || $rec['error']) {
                continue;
            }
            if ($rec['inv'] === '' && $rec['factory'] === '') {
                $this->noNumber($rec);
                continue;
            }
            if ($rec['inv'] !== '') {
                $u = self::findUnit(self::num($rec['inv']));
                if ($u === 'many') {
                    $this->fail($rec, __('sqs.eqhist.unit_ambiguous', ['value' => $rec['inv']]));
                } elseif ($u === null) {
                    $this->fail($rec, __('sqs.eqhist.inv_not_in_db', ['value' => $rec['inv']]));
                    $this->missing[$rec['inv']] = [$rec['sheet'], $rec['itemtext']];
                } else {
                    $rec['unit'] = $u;
                }
            } else {
                $u = self::findUnit(self::num($rec['factory']));
                if ($u === 'many') {
                    $this->fail($rec, __('sqs.eqhist.unit_ambiguous', ['value' => $rec['factory']]));
                } elseif ($u === null) {
                    $rec['unit'] = ['item_id' => 0, 'snumber' => $rec['factory'], 'itemname' => $rec['sheet'], 'new' => true];
                    $this->problem('note', $rec['sheet'], $rec['row'], $rec['contract'], $rec['factory'], __('sqs.eqhist.new_unit', ['item' => $rec['sheet']]));
                } else {
                    $rec['unit'] = $u;
                }
            }
        }
        unset($rec);
        foreach ($this->records as $i => $rec) {
            if ($rec['src'] == 'census') {
                foreach ([$rec['inv'], $rec['factory']] as $n) {
                    if ($n !== '') {
                        $this->censusIndex[self::num($n)][] = $i;
                    }
                }
            }
        }
        // журнал и лист «СПИСАНО»: номер — в базе или в переписи
        foreach ($this->records as &$rec) {
            if ($rec['src'] == 'census' || $rec['error']) {
                continue;
            }
            $nums = $rec['src'] == 'journal' ? ($rec['number'] === '' ? [] : [self::num($rec['number'])]) : $rec['nums'];
            if ($rec['src'] == 'journal' && count($nums) == 0) {
                $this->noNumber($rec);
                continue;
            }
            $found = [];
            $censusFailed = null;   // строка переписи с этим номером, где единица не найдена
            foreach ($nums as $n) {
                $u = self::findUnit($n);
                if (is_array($u)) {
                    $found[Units::key($u['item_id'], $u['snumber'])] = $u;
                    continue;
                }
                $idx = $this->censusIndex[$n] ?? (preg_match('/^\d{7}$/', $n) ? ($this->censusIndex['0' . $n] ?? []) : []);
                $units = [];
                foreach ($idx as $j) {
                    $cu = $this->records[$j]['unit'];
                    if (is_array($cu)) {
                        $units[self::ukey($cu)] = $cu;
                    } else {
                        $censusFailed = $this->records[$j];
                    }
                }
                if (count($units) == 1) {
                    $found[array_key_first($units)] = reset($units);
                }
            }
            if (count($found) == 1) {
                $rec['unit'] = reset($found);
            } elseif (count($found) > 1) {
                $this->fail($rec, __('sqs.eqhist.unit_ambiguous', ['value' => implode(', ', $nums)]));
            } elseif ($censusFailed) {
                $this->fail($rec, __('sqs.eqhist.census_unit_failed', ['sheet' => $censusFailed['sheet'], 'row' => $censusFailed['row']]));
            } else {
                $this->fail($rec, $rec['src'] == 'journal' ? __('sqs.eqhist.unit_not_found', ['value' => $rec['number']]) : __('sqs.eqhist.writeoff_unit_unknown'));
                if ($rec['src'] == 'journal') {
                    $this->missing[$rec['number']] = [$rec['sheet'], $rec['itemtext']];
                }
            }
        }
        unset($rec);
    }

    /**
     * Выдача без номеров единицы (мелкое оборудование, ответ 1.13): на руках — переносится количеством на позицию без
     * серийного учета, одну на лист (на листе «разное» и в журнале — по наименованию); возвращенное и списанное — пропуск
     */
    private function noNumber(array &$rec): void {
        $name = $rec['src'] == 'census' && mb_strtolower($rec['sheet']) != 'разное' ? $rec['sheet'] : $rec['itemtext'];
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($rec['ret'] || !empty($rec['retunknown']) || $rec['off'] || !empty($rec['offunknown']) || $name === '') {
            $this->skipped[$rec['sheet']][$rec['itemtext'] ?: '-'] = ($this->skipped[$rec['sheet']][$rec['itemtext'] ?: '-'] ?? 0) + 1;
            $rec['skip'] = true;
            return;
        }
        // позиция — по соответствиям «написание → позиция», при составлении плана (после склейки дублей журнала и переписи)
        $rec['qty'] = true;
        $rec['written'] = $name;
        $rec['unit'] = ['item_id' => 0, 'snumber' => '', 'itemname' => $name, 'new' => true, 'qty' => true];
    }

    // ---------- склейка и план ----------

    // ключ единицы: название товара и номер — одинаков до и после создания новой единицы (повторный импорт)
    private static function ukey(array $u): string {
        return mb_strtolower($u['itemname']) . '|' . $u['snumber'];
    }

    /**
     * Разобрать прочитанное: найти единицы, склеить дубли, составить план документов (без записи)
     */
    public function analyze(): void {
        $this->resolveUnits();

        // выдачи: одна выдача из обеих таблиц (или с двух листов) — по единице и дате
        $issues = [];
        $qtyRecs = [];
        foreach ($this->records as $rec) {
            if ($rec['error'] || !empty($rec['skip']) || $rec['src'] == 'writeoff') {
                continue;
            }
            if (!empty($rec['qty'])) {
                $qtyRecs[] = $rec;
                continue;
            }
            $key = self::ukey($rec['unit']) . '|' . date('Y-m-d', $rec['idate']);
            if (!isset($issues[$key])) {
                $issues[$key] = $rec;
                continue;
            }
            $issues[$key] = $this->merge($issues[$key], $rec);
        }

        // получатели: одно ФИО с разными адресами или телефонами
        $people = [];
        foreach ($issues as $is) {
            $k = mb_strtolower($is['name']);
            $people[$k]['address'][mb_strtolower($is['address'])] = 1;
            $people[$k]['phone'][preg_replace('/\D/', '', $is['phone'])] = 1;
            $people[$k]['rec'] = $is;
        }
        foreach ($people as $p) {
            $a = array_filter(array_keys($p['address']), 'strlen');
            $ph = array_filter(array_keys($p['phone']), 'strlen');
            if (count($a) > 1 || count($ph) > 1) {
                $this->problem('note', $p['rec']['sheet'], $p['rec']['row'], $p['rec']['contract'], self::unitLabel($p['rec']), __('sqs.eqhist.same_name_diff_contacts'));
            }
        }

        // цепочки событий по единицам в хронологическом порядке
        $today = strtotime(date('Y-m-d'));   // дата импорта — для возвратов и списаний без даты (ответ 1.12)
        $byUnit = [];
        foreach ($issues as $is) {
            $byUnit[self::ukey($is['unit'])][] = $is;
        }
        $offs = [];
        foreach ($this->records as $rec) {
            if ($rec['src'] == 'writeoff' && !$rec['error']) {
                $d = array_values(array_filter($rec['dates'], [self::class, 'yearOk']));
                if (count($d) == 0) {
                    $this->problem('note', $rec['sheet'], $rec['row'], $rec['contract'], self::unitLabel($rec), __('sqs.eqhist.off_unknown', ['date' => date('d.m.Y', $today)]));
                    $offs[self::ukey($rec['unit'])][] = ['date' => $today, 'nodate' => true, 'rec' => $rec];
                    continue;
                }
                $offs[self::ukey($rec['unit'])][] = ['date' => max($d), 'rec' => $rec];
            }
        }

        $events = [];
        foreach ($byUnit as $uk => $list) {
            usort($list, fn($a, $b) => $a['idate'] <=> $b['idate']);
            $out = false;
            $first = true;
            $offToday = null;   // «списан» без даты — списание датой импорта в конце цепочки
            foreach ($list as $k => $is) {
                if ($out === null) {
                    $this->problem('error', $is['sheet'], $is['row'], $is['contract'], self::unitLabel($is), __('sqs.eqhist.issued_after_off'));
                    continue;
                }
                if ($out) {
                    $this->problem('error', $is['sheet'], $is['row'], $is['contract'], self::unitLabel($is), __('sqs.eqhist.issued_twice'));
                    continue;
                }
                if ($is['ret'] && $is['ret'] < $is['idate']) {
                    $this->problem('error', $is['sheet'], $is['row'], $is['contract'], self::unitLabel($is), __('sqs.eqhist.return_before_issue'));
                    continue;
                }
                if ($first && !empty($is['unit']['new'])) {
                    $events[] = ['kind' => 'income', 'date' => $is['idate'] - 86400, 'unit' => $is['unit'], 'rec' => $is];
                }
                $first = false;
                $events[] = ['kind' => 'issue', 'date' => $is['idate'], 'unit' => $is['unit'], 'rec' => $is];
                $out = true;
                $ret = $is['ret'];
                $retExtra = '';
                $retNodate = false;
                if (!$ret && (!empty($is['retunknown']) || !empty($is['offunknown']))) {
                    // возврат без даты: накануне следующей выдачи этой единицы, иначе датой импорта
                    $next = $list[$k + 1] ?? null;
                    if ($next) {
                        $ret = max($is['idate'], $next['idate'] - 86400);
                        $this->problem('note', $is['sheet'], $is['row'], $is['contract'], self::unitLabel($is), __('sqs.eqhist.ret_before_next', ['date' => date('d.m.Y', $ret)]));
                    } else {
                        $ret = $today;
                        $retNodate = true;
                        $this->problem('note', $is['sheet'], $is['row'], $is['contract'], self::unitLabel($is), __('sqs.eqhist.ret_unknown', ['date' => date('d.m.Y', $ret)]));
                    }
                    $retExtra = __('sqs.eqhist.ret_unknown_doc');
                }
                if ($ret) {
                    $events[] = ['kind' => 'return', 'date' => $ret, 'unit' => $is['unit'], 'rec' => $is, 'extra' => $retExtra, 'nodate' => $retNodate];
                    $out = false;
                }
                if ($is['off']) {
                    $events[] = ['kind' => 'writeoff', 'date' => $is['off'], 'unit' => $is['unit'], 'rec' => $is];
                    $out = null;   // списана — дальше выдач быть не может
                    continue;
                }
                if (!empty($is['offunknown'])) {
                    $offToday = $is;
                }
            }
            if ($offToday && $out !== null) {
                $this->problem('note', $offToday['sheet'], $offToday['row'], $offToday['contract'], self::unitLabel($offToday), __('sqs.eqhist.off_unknown', ['date' => date('d.m.Y', $today)]));
                if ($out) {
                    $last = end($list);
                    $events[] = ['kind' => 'return', 'date' => $today, 'unit' => $last['unit'], 'rec' => $last, 'extra' => __('sqs.eqhist.ret_unknown_doc'), 'nodate' => true];
                }
                $events[] = ['kind' => 'writeoff', 'date' => $today, 'unit' => $offToday['unit'], 'rec' => $offToday, 'extra' => __('sqs.eqhist.off_unknown_doc'), 'nodate' => true];
                $out = null;
            }
            foreach ($offs[$uk] ?? [] as $o) {
                if ($out === null) {
                    continue;   // уже списана по статусу в переписи
                }
                $nd = !empty($o['nodate']);
                if ($out) {
                    $last = end($list);
                    $events[] = ['kind' => 'return', 'date' => $o['date'], 'unit' => $last['unit'], 'rec' => $last, 'nodate' => $nd, 'extra' => $nd ? __('sqs.eqhist.ret_unknown_doc') : ''];
                }
                $events[] = ['kind' => 'writeoff', 'date' => $o['date'], 'unit' => $o['rec']['unit'], 'rec' => $o['rec'], 'nodate' => $nd, 'extra' => $nd ? __('sqs.eqhist.off_unknown_doc') : ''];
                $out = null;
            }
            unset($offs[$uk]);
        }
        foreach ($offs as $list) {   // списанные единицы без выдач в таблицах
            foreach ($list as $o) {
                $nd = !empty($o['nodate']);
                $events[] = ['kind' => 'writeoff', 'date' => $o['date'], 'unit' => $o['rec']['unit'], 'rec' => $o['rec'], 'nodate' => $nd, 'extra' => $nd ? __('sqs.eqhist.off_unknown_doc') : ''];
            }
        }

        // мелочь без номеров на руках: приход количеством накануне выдачи и выдача по договору. Одна выдача из обеих таблиц:
        // строка журнала пропускается, если в переписи есть выдача без номеров по тому же договору и дате
        $censusQty = [];
        foreach ($qtyRecs as $r) {
            if ($r['src'] == 'census') {
                $censusQty[$r['contract'] . '|' . date('Y-m-d', $r['idate'])] = 1;
            }
        }
        $seen = [];
        $this->onhand = [];
        $this->unmapped = [];
        $map = $this->itemMap ?? EqItemMap::all();
        foreach ($qtyRecs as $r) {
            if ($r['src'] == 'journal' && isset($censusQty[$r['contract'] . '|' . date('Y-m-d', $r['idate'])])) {
                $this->stat['qtymerged'] = ($this->stat['qtymerged'] ?? 0) + 1;
                continue;
            }
            // варианты написания одного вида — одна позиция; неизвестное написание не заводится, а идет в отчет
            $item = EqItemMap::resolve($map, $r['written']);
            if ($item === null) {
                $this->unmapped[$r['written']] = ($this->unmapped[$r['written']] ?? 0) + 1;
                $this->problem('error', $r['sheet'], $r['row'], $r['contract'], $r['written'], __('sqs.eqhist.not_in_map', ['value' => $r['written']]));
                continue;
            }
            $r['unit']['itemname'] = $item;
            $base = $r['contract'] . '|' . self::ukey($r['unit']) . '|' . date('Y-m-d', $r['idate']);
            $n = $seen[$base] = ($seen[$base] ?? 0) + 1;   // одинаковые строки (две ванны по одному договору в один день) различаются номером
            $events[] = ['kind' => 'income', 'date' => $r['idate'] - 86400, 'unit' => $r['unit'], 'rec' => $r, 'keysuffix' => '#' . $n];
            $events[] = ['kind' => 'issue', 'date' => $r['idate'], 'unit' => $r['unit'], 'rec' => $r, 'keysuffix' => '#' . $n];
            $this->onhand[$r['unit']['itemname']] = ($this->onhand[$r['unit']['itemname']] ?? 0) + 1;
        }
        // по дате, в один день — в порядке составления: внутри цепочки единицы он верный (выдача, затем ее возврат,
        // возврат прежней выдачи — перед новой), а единицы между собой независимы
        foreach ($events as $i => &$ev) {
            $ev['seq'] = $i;
        }
        unset($ev);
        usort($events, fn($a, $b) => [$a['date'], $a['seq']] <=> [$b['date'], $b['seq']]);

        // уже импортированное
        $done = self::doneKeys();
        $this->stat = ['records' => count($this->records), 'issues' => count($issues), 'merged' => $this->stat['qtymerged'] ?? 0, 'errors' => 0, 'notes' => 0,
                       'income' => 0, 'issue' => 0, 'return' => 0, 'writeoff' => 0, 'done' => 0, 'customers' => 0, 'contracts' => 0];
        $this->plan = [];
        $newCustomers = [];
        $newContracts = [];
        foreach ($events as $e) {
            $e['key'] = self::eventKey($e);
            if (isset($done[$e['key']])) {
                $this->stat['done']++;
                continue;
            }
            $this->stat[$e['kind']]++;
            if ($e['kind'] == 'issue' && $e['rec']['enddate'] === null) {
                $this->problem('note', $e['rec']['sheet'], $e['rec']['row'], $e['rec']['contract'], self::unitLabel($e['rec']), __('sqs.eqhist.no_term'));
            }
            if ($e['kind'] == 'issue') {
                $c = $e['rec']['contract'];
                if (!isset($newContracts[$c]) && \ZDB\DB::getConnect()->GetOne("SELECT COUNT(*) FROM contracts WHERE contract_number = " . \ZDB\DB::getConnect()->qstr($c)) == 0) {
                    $newContracts[$c] = 1;
                }
                $n = mb_strtolower($e['rec']['name']);
                if (!isset($newCustomers[$n]) && self::findCustomer($e['rec']['name']) == null) {
                    $newCustomers[$n] = 1;
                }
            }
            $this->plan[] = $e;
        }
        // документы старше даты актуальности (настройки, «закрытый период») ядро сохранить не даст
        $da = intval(\App\System::getOptions('common')['actualdate'] ?? 0);
        $this->blocked = 0;
        if (count($this->plan) && $da > $this->plan[0]['date']) {
            $this->blocked = $da;
            $this->problem('error', '', 0, '', '', __('sqs.eqhist.actualdate', ['actual' => date('d.m.Y', $da), 'first' => date('d.m.Y', $this->plan[0]['date'])]));
        }
        $this->stat['customers'] = count($newCustomers);
        $this->stat['contracts'] = count($newContracts);
        $this->stat['merged'] += count(array_filter($this->records, fn($r) => !$r['error'] && empty($r['skip']) && empty($r['qty']) && $r['src'] != 'writeoff')) - count($issues);
        foreach ($this->problems as $p) {
            $this->stat[$p['level'] == 'error' ? 'errors' : 'notes']++;
        }
    }

    // одна выдача из двух источников: номер договора — из переписи, кем направлен — из журнала, остальное дополняется
    private function merge(array $a, array $b): array {
        if ($a['src'] != 'census' && $b['src'] == 'census') {
            [$a, $b] = [$b, $a];
        }
        if ($a['contract'] !== $b['contract']) {
            $this->problem('note', $b['sheet'], $b['row'], $b['contract'], self::unitLabel($b), __('sqs.eqhist.contract_differs', ['number' => $a['contract'], 'sheet' => $a['sheet'], 'row' => $a['row']]));
        }
        foreach (['address', 'phone', 'referrer', 'itemtext'] as $k) {
            if ($a[$k] === '') {
                $a[$k] = $b[$k];
            }
        }
        if ($b['ret'] && $a['ret'] && $a['ret'] != $b['ret']) {
            $this->problem('note', $b['sheet'], $b['row'], $a['contract'], self::unitLabel($b), __('sqs.eqhist.return_differs', ['date' => date('d.m.Y', $a['ret'])]));
        }
        $a['ret'] = $a['ret'] ?: $b['ret'];
        foreach (['retunknown', 'offunknown'] as $f) {
            if (!empty($b[$f])) {
                $a[$f] = true;
            }
        }
        $a['off'] = $a['off'] ?: $b['off'];
        if ($a['enddate'] === null) {
            $a['enddate'] = $b['enddate'];
        }
        $a['notes'] = array_values(array_unique(array_merge($a['notes'], $b['notes'])));
        $a['also'] = trim(($a['also'] ?? '') . ' ' . $b['sheet'] . ':' . $b['row']);
        return $a;
    }

    // ключ события для повторного импорта; дата импорта (возврат, списание без даты) в ключ не входит — завтра она другая
    private static function eventKey(array $e): string {
        $contract = in_array($e['kind'], ['issue', 'return']) || !empty($e['unit']['qty']) ? $e['rec']['contract'] : '';
        return $e['kind'] . '|' . $contract . '|' . self::ukey($e['unit']) . '|' . (!empty($e['nodate']) ? 'nodate' : date('Y-m-d', $e['date'])) . ($e['keysuffix'] ?? '');
    }

    private static function doneKeys(): array {
        $keys = [];
        foreach (\ZDB\DB::getConnect()->Execute("SELECT eventkey FROM sqs_eqhistory") as $r) {
            $keys[$r['eventkey']] = 1;
        }
        return $keys;
    }

    public static function findCustomer(string $name): ?Customer {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        return Customer::getFirst("TRIM(customer_name) = " . Customer::qstr($name));
    }

    // ---------- запись ----------

    /**
     * Создать документы по плану в хронологическом порядке. Каждое событие — отдельная транзакция; если событие не
     * записалось, следующие события этой единицы пропускаются (ошибка в отчете).
     */
    public function apply(int $store, bool $liftPeriod = false): array {
        if ($this->blocked && !$liftPeriod) {
            throw new \Exception(__('sqs.eqhist.actualdate', ['actual' => date('d.m.Y', $this->blocked), 'first' => date('d.m.Y', $this->plan[0]['date'])]));
        }
        if (!$this->blocked) {
            return $this->applyEvents($store);
        }
        // закрытый период снимается только на время записи: начало периода — на дату самой ранней записи, после — как было
        $common = \App\System::getOptions('common', true);
        $had = array_key_exists('actualdate', $common);
        $was = $common['actualdate'] ?? null;
        $common['actualdate'] = strtotime(date('Y-m-d', $this->plan[0]['date']));
        \App\System::setOptions('common', $common);
        $period = ['was' => $was, 'set' => $common['actualdate']];
        try {
            $res = $this->applyEvents($store);
        } finally {
            $common = \App\System::getOptions('common', true);
            if ($had) {
                $common['actualdate'] = $was;
            } else {
                unset($common['actualdate']);
            }
            \App\System::setOptions('common', $common);
            $period['restored'] = \App\System::getOptions('common', true)['actualdate'] ?? null;
            $this->period = $period;
            \App\Helper::log(__('sqs.eqhist.period_log', self::periodParams($period)));
        }
        return $res;
    }

    public array $period = [];   // снятие закрытого периода при записи: was, set, restored

    public static function periodParams(array $p): array {
        $d = fn($v) => $v ? date('d.m.Y', $v) : '-';
        return ['was' => $d($p['was']), 'set' => $d($p['set']), 'restored' => $d($p['restored'] ?? null)];
    }

    private function applyEvents(int $store): array {
        $conn = \ZDB\DB::getConnect();
        $res = ['income' => 0, 'issue' => 0, 'return' => 0, 'writeoff' => 0, 'failed' => 0];
        $broken = [];
        $issueDoc = [];      // ukey|contract => document_id выдачи (для возврата)
        $contractUnits = []; // contract_id => [ukey => выдана ли сейчас]
        foreach ($this->plan as $e) {
            $uk = empty($e['unit']['qty']) ? self::ukey($e['unit']) : $e['key'];   // мелочь: строки независимы
            if (isset($broken[$uk])) {
                continue;
            }
            $conn->BeginTrans();
            try {
                $docId = match ($e['kind']) {
                    'income' => $this->doIncome($e, $store),
                    'issue' => $this->doIssue($e, $store, $contractUnits),
                    'return' => $this->doReturn($e, $store, $contractUnits),
                    'writeoff' => $this->doWriteoff($e, $store),
                };
                $conn->Execute("INSERT INTO sqs_eqhistory (eventkey, document_id, createdon) VALUES (" . $conn->qstr($e['key']) . ", " . intval($docId) . ", NOW())");
                $conn->CommitTrans();
                $res[$e['kind']]++;
            } catch (\Throwable $ex) {
                $conn->RollbackTrans();
                $broken[$uk] = 1;
                $res['failed']++;
                $this->problem('error', $e['rec']['sheet'], $e['rec']['row'], $e['rec']['contract'], self::unitLabel($e['rec']),
                    __('sqs.eqhist.write_failed', ['kind' => __('sqs.eqhist.kind_' . $e['kind']), 'date' => date('d.m.Y', $e['date']), 'error' => $ex->getMessage()]));
            }
        }
        // договор закрыт, если все его единицы возвращены или списаны
        foreach ($contractUnits as $cid => $units) {
            if (count(array_filter($units)) == 0) {
                $c = Contract::load($cid);
                if ($c && $c->state != Contract::STATE_CLODED) {
                    $c->state = Contract::STATE_CLODED;
                    $c->save();
                }
            }
        }
        return $res;
    }

    // товар новой единицы — один на лист, с учетом по серийным номерам; мелочи без номеров — без серийного учета
    // (если такое название уже занято товаром с серийным учетом — с пометкой «количеством»)
    private function unitItem(array &$unit): Item {
        if ($unit['item_id'] > 0) {
            return Item::load($unit['item_id']);
        }
        $serial = empty($unit['qty']) ? 1 : 0;
        $name = $unit['itemname'];
        $item = Item::getFirst('itemname = ' . Item::qstr($name) . ' and disabled <> 1');
        if ($item != null && !$serial && $item->useserial == 1) {
            $name .= ' ' . __('sqs.eqhist.qty_item_suffix');
            $item = Item::getFirst('itemname = ' . Item::qstr($name) . ' and disabled <> 1');
        }
        if ($item == null) {
            $item = new Item();
            $item->itemname = $name;
            $item->msr = __('app.item.sht');
            $item->useserial = $serial;
            $item->save();
        }
        return $item;
    }

    private function line(array $unit, int $rowid): Item {
        $item = $this->unitItem($unit);
        $item->quantity = 1;
        $item->price = 0;
        $item->snumber = $unit['snumber'];
        $item->sdate = 0;
        $item->rowid = $rowid;
        return $item;
    }

    private function execute(Document $doc): int {
        $doc->save();
        $doc->updateStatus(Document::STATE_NEW);
        $doc->updateStatus(Document::STATE_EXECUTED);
        $doc = Document::load($doc->document_id);
        if ($doc->state != Document::STATE_EXECUTED) {
            throw new \Exception(\App\System::getErrorMsg() ?: __('sqs.eqhist.not_executed'));
        }
        return (int)$doc->document_id;
    }

    private function notes(array $rec, string $extra = ''): string {
        $n = __('sqs.eqhist.doc_notes', ['sheet' => $rec['sheet'], 'row' => $rec['row']]);
        if (count($rec['notes'])) {
            $n .= '. ' . implode('; ', $rec['notes']);
        }
        if ($extra !== '') {
            $n .= '. ' . $extra;
        }
        return $n;
    }

    private function doIncome(array $e, int $store): int {
        $unit = $e['unit'];
        $item = $this->unitItem($unit);
        $doc = Document::create('IncomeItem');
        $doc->document_number = $doc->nextNumber() ?: 'ОТ-00001';
        $doc->document_date = $e['date'];
        $doc->headerdata['store'] = $store;
        $doc->notes = $this->notes($e['rec']);
        $line = $this->line($unit, 1);
        $doc->packDetails('detaildata', [1 => $line]);
        $doc->amount = 0;
        $doc->payamount = 0;
        $doc->headerdata['payed'] = 0;
        $id = $this->execute($doc);
        if ($unit['snumber'] !== '') {
            Units::setFactoryNo((int)$item->item_id, $unit['snumber'], $unit['snumber']);
        }
        return $id;
    }

    private function doIssue(array $e, int $store, array &$contractUnits): int {
        $rec = $e['rec'];
        $customer = self::findCustomer($rec['name']);
        if ($customer == null) {
            $customer = new Customer();
            $customer->customer_name = trim(preg_replace('/\s+/u', ' ', $rec['name']));
            $customer->type = 1;
        }
        if ($rec['phone'] !== '' && trim((string)$customer->phone) === '') {
            $customer->phone = $rec['phone'];
        }
        if ($rec['address'] !== '' && trim((string)$customer->address) === '') {
            $customer->address = $rec['address'];
        }
        // без телефона — пустая строка, не NULL: подсказка контрагента показывает concat(customer_name, ' ', phone),
        // и с NULL получатель виден в ней пустой строкой
        $customer->phone = (string)$customer->phone;
        $customer->address = (string)$customer->address;
        $customer->save();

        $contract = Contract::getFirst('contract_number = ' . Contract::qstr($rec['contract']));
        if ($contract == null) {
            $contract = new Contract();
            $contract->contract_number = $rec['contract'];
            $contract->customer_id = $customer->customer_id;
            $contract->createdon = $rec['cdate'] ?: $rec['idate'];
            $contract->enddate = $rec['enddate'] ? $rec['enddate'] : strtotime(self::ON_DEMAND);
            $contract->shortdesc = $rec['referrer'];
            $contract->creator_id = \App\System::getUser()->user_id;
            $contract->user_id = \App\System::getUser()->user_id;
            $contract->username = \App\System::getUser()->username;
            $contract->state = Contract::STATE_INWORK;
            $contract->save();
        } elseif ($contract->customer_id != $customer->customer_id) {
            throw new \Exception(__('sqs.eqhist.contract_other_customer', ['number' => $rec['contract']]));
        }

        $unit = $e['unit'];
        $line = $this->line($unit, 1);
        $doc = Document::create('GoodsIssue');
        $doc->document_number = $doc->nextNumber();
        $doc->document_date = $e['date'];
        $doc->customer_id = $customer->customer_id;
        $doc->notes = $this->notes($rec);
        $doc->headerdata['customer_name'] = $customer->customer_name;
        $doc->headerdata['contract_id'] = $contract->contract_id;
        $doc->headerdata['store'] = $store;
        $doc->headerdata['payment'] = 0;
        $doc->headerdata['payed'] = 0;
        $doc->headerdata['pricetype'] = 'price1';
        $doc->packDetails('detaildata', [1 => $line]);
        $doc->amount = 0;
        $doc->payamount = 0;
        $doc->payed = 0;
        $id = $this->execute($doc);
        $contractUnits[$contract->contract_id][self::ukey($unit)] = true;
        $this->lastIssue[self::ukey($unit)] = ['doc' => $id, 'contract' => (int)$contract->contract_id, 'customer' => (int)$customer->customer_id];
        return $id;
    }

    private array $lastIssue = [];

    private function doReturn(array $e, int $store, array &$contractUnits): int {
        $unit = $e['unit'];
        $uk = self::ukey($unit);
        $last = $this->lastIssue[$uk] ?? self::issueFromDb($e);
        if (!$last) {
            throw new \Exception(__('sqs.eqhist.no_issue_for_return'));
        }
        $parent = Document::load($last['doc']);
        $doc = Document::create('ReturnIssue');
        $doc->document_number = $doc->nextNumber();
        $doc->document_date = $e['date'];
        $doc->customer_id = $parent->customer_id;
        $doc->parent_id = $parent->document_id;
        $doc->notes = $this->notes($e['rec'], $e['extra'] ?? '');
        $doc->headerdata['store'] = $store;
        $doc->headerdata['payment'] = 0;
        $doc->headerdata['payed'] = 0;
        $doc->headerdata['bonus'] = 0;
        $doc->headerdata['contract_id'] = $last['contract'];
        $doc->packDetails('detaildata', [1 => $this->line($unit, 1)]);
        $doc->amount = 0;
        $doc->payamount = 0;
        $id = $this->execute($doc);
        $contractUnits[$last['contract']][$uk] = false;
        unset($this->lastIssue[$uk]);
        return $id;
    }

    // выдача, импортированная прошлым запуском (возврат дописывается при повторном импорте)
    private static function issueFromDb(array $e): ?array {
        $conn = \ZDB\DB::getConnect();
        $issue = $e;
        $issue['kind'] = 'issue';
        $issue['date'] = $e['rec']['idate'];
        unset($issue['nodate']);   // у возврата датой импорта дата выдачи известна
        $id = $conn->GetOne("SELECT document_id FROM sqs_eqhistory WHERE eventkey = " . $conn->qstr(self::eventKey($issue)));
        $doc = $id ? Document::load($id) : null;
        return $doc ? ['doc' => (int)$doc->document_id, 'contract' => intval($doc->headerdata['contract_id'] ?? 0), 'customer' => (int)$doc->customer_id] : null;
    }

    private function doWriteoff(array $e, int $store): int {
        $doc = Document::create('OutcomeItem');
        $doc->document_number = $doc->nextNumber();
        $doc->document_date = $e['date'];
        $doc->notes = $this->notes($e['rec'], $e['extra'] ?? '');
        $doc->headerdata['store'] = $store;
        $doc->packDetails('detaildata', [1 => $this->line($e['unit'], 1)]);
        $doc->amount = 0;
        return $this->execute($doc);
    }
}
