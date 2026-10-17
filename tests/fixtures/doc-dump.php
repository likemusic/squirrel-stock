<?php
/**
 * Документы в сравнимом виде (tests/specs/seed-parity.spec.js): поля документа, шапка, строки, проводки и остатки.
 * Запуск в web-контейнере от www-data: php doc-dump.php <document_id> [<document_id> ...]
 * Вывод (JSON): [{ doc, header, rows, entries, stock }]
 */
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
require '/var/www/html/init.php';

use App\Entity\Doc\Document;

$conn = \ZDB\DB::getConnect();
$out = [];
foreach (array_slice($argv, 1) as $id) {
    $doc = Document::load(intval($id));
    $rows = [];
    foreach ($doc->detaildata as $name => $list) {
        foreach ($list as $k => $item) {
            $rows[] = ['list' => $name, 'key' => $k, 'class' => get_class($item), 'data' => $item->getData()];
        }
    }
    $out[] = [
        'doc' => ['meta' => $doc->meta_name, 'state' => $doc->state, 'amount' => $doc->amount, 'payamount' => $doc->payamount,
                  'payed' => $doc->payed, 'customer_id' => $doc->customer_id, 'parent_id' => $doc->parent_id, 'notes' => $doc->notes,
                  'date' => date('Y-m-d', $doc->document_date), 'user_id' => $doc->user_id, 'branch_id' => $doc->branch_id],
        'header' => $doc->headerdata,
        'rows' => $rows,
        'entries' => $conn->GetAll("select s.item_id, e.quantity, e.cost, e.outprice, e.tag, e.createdon, s.store_id, s.snumber, s.partion,
                                    date(s.sdate) sdate from entrylist e left join store_stock s on s.stock_id = e.stock_id
                                    where e.document_id = " . intval($id) . " order by e.entry_id"),
        'statuses' => $conn->GetCol('select docstate from docstatelog where document_id = ' . intval($id) . ' order by log_id'),
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
