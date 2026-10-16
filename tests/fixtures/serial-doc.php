<?php
/**
 * Провести документ, списывающий одну единицу с серийным номером с основного склада, — без формы (tests/specs/serial-on-date.spec.js):
 *  - RetCustIssue: возврат поставщику (в форму строки попадают только из прихода от поставщика);
 *  - Inventory: инвентаризация, где единица по учету есть, а по факту нет (автосписание недостачи).
 * Запуск в web-контейнере от www-data: php serial-doc.php <тип> <дата Y-m-d> <item_id> <номер>
 * Вывод: «ok <document_id>» или «error <текст>»; непроведенный документ не сохраняется.
 */
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
require '/var/www/html/init.php';

use App\Entity\Doc\Document;

[$_, $type, $date, $item_id, $snumber] = $argv;
\App\System::setUser(\App\Entity\User::getByLogin('admin'));
$item = \App\Entity\Item::load(intval($item_id));
$item->snumber = $snumber;
$item->quantity = 1;
$item->price = 0;
$item->rowid = 1;
$doc = Document::create($type);
$doc->document_number = $doc->nextNumber();
$doc->document_date = strtotime($date);
$doc->headerdata['store'] = 1;
$doc->headerdata['storeemp'] = 0;
if ($type == 'Inventory') {
    $item->qfact = 0;
    $doc->headerdata['autoincome'] = 0;
    $doc->headerdata['autooutcome'] = 1;
} else {
    $doc->headerdata['payment'] = 0;
    $doc->headerdata['payed'] = 0;
}
$doc->packDetails('detaildata', [1 => $item]);
$doc->amount = 0;

$conn = \ZDB\DB::getConnect();
$conn->BeginTrans();
try {
    $doc->save();
    $doc->updateStatus(Document::STATE_NEW);
    $doc->updateStatus(Document::STATE_EXECUTED);
    $conn->CommitTrans();
    echo 'ok ' . $doc->document_id . "\n";
} catch (\Throwable $e) {
    $conn->RollbackTrans();
    echo 'error ' . $e->getMessage() . "\n";
}
