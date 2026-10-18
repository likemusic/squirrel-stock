<?php

namespace App\Pages\Report;

use App\Helper as H;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Panel;

/**
 * SquirrelStock: акт списания за месяц в формате бухгалтерии хосписа.
 * Строки — позиции, колонки — выдачи «Получатель дд.мм.гггг» (выдачи одному получателю в один день — одна колонка),
 * в ячейках количество по расходным накладным за вычетом возвратов, справа итог по позиции, внизу итог по выдаче.
 * Акты на расходники и на оборудование — отдельные (ответ хосписа 3.1): оборудование — единицы с инвентарным номером и все,
 * что выдано по договору (мелкое оборудование количеством), расходники — остальное.
 */
class IssueAct extends \App\Pages\Base
{
    public const ALL = 0;
    public const CONSUMABLES = 1;
    public const EQUIPMENT = 2;
    private const PRINT_COLS = 10;   // выдач в куске акта на печати (альбомный лист A4)

    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowReport('IssueAct')) {
            return;
        }

        $this->add(new Form('filter'));
        $this->filter->add(new DropDownChoice('year', \App\Util::getYears(), intval(date('Y'))));
        $this->filter->add(new DropDownChoice('month', \App\Util::getMonth(), intval(date('n'))));
        $this->filter->add(new DropDownChoice('cat', [0 => __('common.vsi')] + \App\Entity\Category::getList(true), 0));
        $this->filter->add(new DropDownChoice('kind', [self::CONSUMABLES => __('report.issueact.kind_consumables'), self::EQUIPMENT => __('report.issueact.kind_equipment'),
                                                       self::ALL => __('report.issueact.kind_all')], self::CONSUMABLES));
        $this->filter->add(new TextInput('title', self::defaultTitle(self::CONSUMABLES)));
        $this->filter->add(new CheckBox('allitems'));
        $this->filter->add(new SubmitButton('onreport'))->onClick($this, 'OnSubmit');

        $this->add(new Panel('detail'))->setVisible(false);
        $this->detail->add(new Label('preview'));
    }

    private static function defaultTitle(int $kind): string {
        return __([self::ALL => 'report.issueact.default_title', self::CONSUMABLES => 'report.issueact.title_consumables', self::EQUIPMENT => 'report.issueact.title_equipment'][$kind] ?? 'report.issueact.default_title');
    }

    public function OnSubmit($sender) {
        // заголовок по умолчанию следует за выбором «расходники / оборудование», введенный вручную остается
        $title = trim($this->filter->title->getText());
        if ($title == '' || in_array($title, array_map([self::class, 'defaultTitle'], [self::ALL, self::CONSUMABLES, self::EQUIPMENT]), true)) {
            $this->filter->title->setText(self::defaultTitle(intval($this->filter->kind->getValue())));
        }
        [$html, $export] = $this->generateReport();
        $this->detail->preview->setText($html, true);
        \App\Session::getSession()->setExportReport("<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>" . $export . "</body></html>");
        $this->detail->setVisible(true);
    }

    private function generateReport() {
        $conn = \ZDB\DB::getConnect();
        $year = intval($this->filter->year->getValue());
        $month = intval($this->filter->month->getValue());
        $cat = intval($this->filter->cat->getValue());
        $kind = intval($this->filter->kind->getValue());
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from));

        $where = "m.meta_name IN ('GoodsIssue', 'ReturnIssue') AND d.document_date BETWEEN '{$from}' AND '{$to}'";
        if ($cat > 0) {
            $where .= ' AND ' . \App\SQS\Categories::sqlIn($cat);
        }
        // возврат относится к колонке выдачи, на основании которой создан; договор — в заголовке выдачи или самого возврата
        $contract = fn($t) => "CAST(REGEXP_SUBSTR(REGEXP_SUBSTR({$t}.content, '<contract_id>[0-9]+</contract_id>'), '[0-9]+') AS UNSIGNED)";
        if ($kind != self::ALL) {
            // оборудование — единица с номером (не партия расходника, App\SQS\SerialStock::unitSql) или выдача по договору
            $equipment = "(" . \App\SQS\SerialStock::unitSql('st') . " OR COALESCE({$contract('d')}, 0) > 0 OR COALESCE({$contract('base')}, 0) > 0)";
            $where .= ' AND ' . ($kind == self::EQUIPMENT ? $equipment : "NOT {$equipment}");
        }
        $sql = "SELECT COALESCE(base.document_date, d.document_date) AS issue_date,
                       COALESCE(base.customer_id, d.customer_id) AS customer_id,
                       i.item_id, i.itemname, SUM(-e.quantity) AS qty
                FROM entrylist e
                JOIN documents d ON d.document_id = e.document_id
                JOIN metadata m ON m.meta_id = d.meta_id
                LEFT JOIN documents base ON m.meta_name = 'ReturnIssue' AND base.document_id = d.parent_id
                JOIN store_stock st ON st.stock_id = e.stock_id
                JOIN items i ON i.item_id = st.item_id
                WHERE {$where}
                GROUP BY 1, 2, 3, 4
                HAVING SUM(-e.quantity) <> 0";

        $cols = [];    // "дата|получатель" => заголовок
        $cells = [];   // item_id => [колонка => количество]
        $names = [];
        $customers = [];
        foreach ($conn->Execute($sql) as $r) {
            $cid = intval($r['customer_id']);
            if (!isset($customers[$cid])) {
                $customers[$cid] = $cid > 0 ? (\App\Entity\Customer::load($cid)->customer_name ?? '') : '';
            }
            $date = date('Y-m-d', strtotime($r['issue_date']));
            $col = $date . '|' . $customers[$cid];
            $cols[$col] = trim($customers[$cid] . ' ' . H::fd(strtotime($date)));
            $cells[$r['item_id']][$col] = ($cells[$r['item_id']][$col] ?? 0) + $r['qty'];
            $names[$r['item_id']] = $r['itemname'];
        }
        // колонка, у которой все количества взаимно погасились (выдача вернулась целиком), в акт не попадает
        foreach (array_keys($cols) as $col) {
            $sum = 0;
            foreach ($cells as $row) {
                $sum += abs($row[$col] ?? 0);
            }
            if ($sum == 0) {
                unset($cols[$col]);
            }
        }
        foreach ($cells as $id => $row) {
            if (array_sum(array_map('abs', $row)) == 0) {
                unset($cells[$id], $names[$id]);
            }
        }
        ksort($cols);
        if ($this->filter->allitems->isChecked()) {
            $iwhere = 'disabled <> 1' . ($cat > 0 ? ' AND ' . \App\SQS\Categories::sqlIn($cat, 'cat_id') : '');
            foreach (\App\Entity\Item::find($iwhere) as $item) {
                $names[$item->item_id] = $names[$item->item_id] ?? $item->itemname;
            }
        }
        // названия — как в 1С (App\SQS\Item1C): одноименные позиции 1С (разные цены) в акте — одна строка
        $n1c = \App\SQS\Item1C::names(array_keys($names));
        $byName = [];
        foreach ($names as $id => $name) {
            $name = $n1c[$id] ?? $name;
            $byName[$name] = $byName[$name] ?? [];
            foreach ($cells[$id] ?? [] as $col => $q) {
                $byName[$name][$col] = ($byName[$name][$col] ?? 0) + $q;
            }
        }
        uksort($byName, fn($a, $b) => strcasecmp($a, $b));

        $rows = [];
        $coltotal = array_fill_keys(array_keys($cols), 0);
        foreach ($byName as $name => $namecells) {
            $row = ['name' => $name, 'cells' => [], 'total' => 0];
            foreach (array_keys($cols) as $col) {
                $q = $namecells[$col] ?? 0;
                $row['cells'][] = ['qty' => $q != 0 ? H::fqty($q) : ''];
                $row['total'] += $q;
                $coltotal[$col] += $q;
            }
            $row['total'] = H::fqty($row['total']);
            $rows[] = $row;
        }
        $header = [];
        $n = 1;
        foreach ($cols as $title) {
            $header[] = ['title' => $title, 'n' => $n++];
        }

        $months = \App\Util::getMonth();
        $report = new \App\Report('report/issueact.tpl');
        $data = [
            'heading' => __('report.issueact.heading', ['title' => trim($this->filter->title->getText()), 'month' => mb_strtolower($months[$month]), 'year' => $year]),
            'cols'    => $header,
            'rows'    => $rows,
            'totals'  => array_map(fn($q) => ['qty' => H::fqty($q)], array_values($coltotal)),
            'total'   => H::fqty(array_sum($coltotal)),
            'issues'  => count($cols),
        ];
        // на экране и в Excel — одна таблица; на печать, если выдач больше PRINT_COLS, — куски с наименованием в каждом
        $html = $report->generate($data);
        return [count($header) > self::PRINT_COLS ? $report->generate($data + ['print' => true, 'parts' => self::printParts($data)]) : $html, $html];
    }

    // куски акта для печати: колонки выдач по PRINT_COLS, «Итого» по позиции — в последнем куске
    private static function printParts(array $data): array {
        $parts = [];
        $chunks = array_chunk(array_keys($data['cols']), self::PRINT_COLS);
        foreach ($chunks as $k => $idx) {
            $last = $k == count($chunks) - 1;
            $pick = fn(array $a) => array_values(array_intersect_key($a, array_flip($idx)));
            $parts[] = [
                'part'   => __('report.issueact.print_part', ['from' => $idx[0] + 1, 'to' => end($idx) + 1]),
                'cols'   => $pick($data['cols']),
                'rows'   => array_map(fn($r) => ['cells' => $pick($r['cells'])] + $r, $data['rows']),
                'totals' => $pick($data['totals']),
                'last'   => $last,
                'total'  => $data['total'],
                'span'   => count($idx) + ($last ? 2 : 1),
            ];
        }
        return $parts;
    }
}
