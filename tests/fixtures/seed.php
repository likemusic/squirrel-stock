<?php
/**
 * Подготовка данных теста без интерфейса: позиции, контрагенты, договоры, проведенные документы — сущностями ядра,
 * как это делают формы справочников и документов (savedocOnClick: save, STATE_NEW, STATE_EXECUTED в транзакции).
 * Для тестов, где эти данные — фон, а не предмет проверки. Совпадение с формами проверяет tests/specs/seed-parity.spec.js.
 *
 * Запуск в web-контейнере от www-data: php seed.php < data.json (helpers/seed.js)
 * Вход (JSON):
 *   items:     [{ code, name, msr = 'шт', serial, snmode, minqty, cat_id }]
 *   customers: [{ name, type = 1 (1 — получатель, 2 — поставщик), phone, address }]
 *   contracts: [{ number, customer, from, to, note }] — в состоянии «В работе»; customer — имя контрагента
 *   docs:      [{ type: IncomeItem | GoodsReceipt | GoodsIssue | OutcomeItem | MoveItem, date, customer, contract,
 *                 tostore, notes, draft, rows: [{ code, qty, price = 0, sn, sdate }] }]
 * Документы проводятся по порядку; первый отказ проведения — конец работы (этот документ не сохраняется).
 * Вывод (JSON): { items: {code: id}, customers: {name: id}, contracts: {number: id}, docs: [{ id, number }], error }
 */
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
require '/var/www/html/init.php';

use App\Entity\Contract;
use App\Entity\Customer;
use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Entity\Store;

$in = json_decode(stream_get_contents(STDIN), true);
$out = ['items' => [], 'customers' => [], 'contracts' => [], 'docs' => [], 'error' => null];
\App\System::setUser(\App\Entity\User::getByLogin('admin'));
$conn = \ZDB\DB::getConnect();

$date = fn($d) => strtotime($d);
$customerId = function ($name) use (&$out, $conn) {
    if (isset($out['customers'][$name])) {
        return $out['customers'][$name];
    }
    $id = intval($conn->GetOne('select customer_id from customers where customer_name = ' . $conn->qstr($name)));
    if ($id == 0) {
        throw new \Exception("контрагент «{$name}» не найден");
    }
    return $id;
};
$itemByCode = function ($code) use ($conn) {
    $item = Item::getFirst('item_code = ' . $conn->qstr($code));
    if (!$item instanceof Item) {
        throw new \Exception("позиция {$code} не найдена");
    }
    return $item;
};
$storeName = fn($id) => (string)$conn->GetOne('select storename from stores where store_id = ' . intval($id));

try {
    // позиции: поля, которые справочник пишет для новой позиции из формы с пустыми полями (itemlist.php saveOnClick)
    foreach ($in['items'] ?? [] as $r) {
        $item = new Item();
        $item->itemname = $r['name'];
        $item->item_code = $r['code'];
        $item->msr = $r['msr'] ?? 'шт';
        $item->cat_id = intval($r['cat_id'] ?? 0);
        $item->useserial = !empty($r['serial']) ? 1 : 0;
        $item->snmode = intval($r['snmode'] ?? 0);
        if (isset($r['minqty'])) {
            $item->minqty = $r['minqty'];
        }
        $item->disabled = 0;
        $item->item_type = 0;
        // флажки и списки карточки, которые форма пишет всегда (пустое поле — 0)
        foreach (['isweight', 'noprint', 'autooutcome', 'autoincome', 'isnds'] as $f) {
            $item->{$f} = 0;
        }
        $item->cflist = serialize([]);   // свои поля (пустой список)
        $item->save();
        $out['items'][$r['code']] = (int)$item->item_id;
    }

    foreach ($in['customers'] ?? [] as $r) {
        $c = new Customer();
        $c->customer_name = $r['name'];
        $c->type = intval($r['type'] ?? 1);
        $c->phone = \App\Util::handlePhone($r['phone'] ?? '');
        $c->address = $r['address'] ?? '';
        $c->status = 0;
        // поля пустой формы справочника (customerlist.php saveOnClick)
        foreach (['email', 'city', 'country', 'viber', 'telega', 'addressdel', 'edrpou', 'comment', 'holding_name'] as $f) {
            $c->{$f} = '';
        }
        foreach (['jurid', 'isholding', 'holding', 'nosubs', 'allowedshop'] as $f) {
            $c->{$f} = 0;
        }
        $c->pricetype = 'price1';
        $c->save();
        $out['customers'][$r['name']] = (int)$c->customer_id;
    }

    // договор: справочник сохраняет новый (состояние 0), затем меняет состояние на «В работе»
    foreach ($in['contracts'] ?? [] as $r) {
        $k = new Contract();
        $k->contract_number = $r['number'];
        $k->customer_id = $customerId($r['customer']);
        $k->creator_id = \App\System::getUser()->user_id;
        if (!empty($r['from'])) {
            $k->createdon = $date($r['from']);
        }
        $k->enddate = empty($r['to']) ? 0 : $date($r['to']);
        $k->shortdesc = $r['note'] ?? '';
        $k->desc = '';
        $k->user_id = 0;   // ответственный не выбран
        $k->username = '';
        $k->save();
        $k->state = Contract::STATE_INWORK;
        $k->save();
        $msg = new \App\Entity\Message();   // запись в истории договора, как при смене состояния в справочнике
        $msg->message = __('reference.contractlist.zmina_statusu_na') . Contract::getStates()[$k->state];
        $msg->created = time();
        $msg->user_id = 0;
        $msg->item_id = $k->contract_id;
        $msg->item_type = \App\Entity\Message::TYPE_CONTRACT;
        $msg->save();
        $out['contracts'][$r['number']] = (int)$k->contract_id;
    }

    foreach ($in['docs'] ?? [] as $d) {
        $type = $d['type'];
        $doc = Document::create($type);
        $doc->document_number = $doc->nextNumber();
        $doc->document_date = $date($d['date']);
        $doc->notes = $d['notes'] ?? '';
        $doc->customer_id = empty($d['customer']) ? 0 : $customerId($d['customer']);
        $store = 1;
        $h = ['store' => $store, 'storeemp' => -1, 'storeempname' => ''];   // -1: в списке кладовщиков ничего не выбрано
        if ($doc->customer_id > 0 && in_array($type, ['GoodsIssue', 'GoodsReceipt'])) {   // приход и списание пишут только customer_id
            // имя — текст подсказки: «имя телефон» (Customer::getList)
            $h['customer_name'] = $d['customer'] . ' ' . $conn->GetOne('select coalesce(phone, \'\') from customers where customer_id = ' . $doc->customer_id);
        }
        $contract = empty($d['contract']) ? 0 : intval($conn->GetOne('select contract_id from contracts where contract_number = ' . $conn->qstr($d['contract'])));
        if (!empty($d['contract']) && $contract == 0) {
            throw new \Exception("договор {$d['contract']} не найден");
        }

        $rows = [];
        $total = 0;
        foreach ($d['rows'] as $i => $r) {
            $item = $itemByCode($r['code']);
            $item->quantity = doubleval($r['qty'] ?? 1);
            $item->snumber = trim($r['sn'] ?? '');
            $price = doubleval($r['price'] ?? 0);
            if ($type == 'GoodsIssue') {
                $item->price = $price;
                $item->disc = '';
                $item->pureprice = $item->getPurePrice();
                if ($item->pureprice > $item->price) {
                    $item->disc = number_format((1 - ($item->price / ($item->pureprice))) * 100, 1, '.', '');
                }
                $item->pricenonds = $item->price - $item->price * $item->nds(true);
                if (\App\SQS\SerialStock::rowMode($item) == 2 && $item->useserial == 1 && $item->snumber != '') {
                    $st = \App\Entity\Stock::getFirst("store_id={$store} and item_id={$item->item_id} and snumber=" . $conn->qstr($item->snumber));
                    if ($st instanceof \App\Entity\Stock) {
                        $item->sdate = $st->sdate;
                    }
                }
                $item->amount = \App\Helper::fa($item->price * $item->quantity);
                $total += $item->amount;
            } elseif ($type == 'GoodsReceipt' || $type == 'IncomeItem') {
                $item->price = $price;
                // пустой срок: getDate() формы дает false; приход ТМЦ и строка с режимом «срок годности» заменяют его на ''
                $item->sdate = empty($r['sdate']) ? ($type == 'IncomeItem' || \App\SQS\SerialStock::rowMode($item) == 2 ? '' : false) : $date($r['sdate']);
                if ($type == 'GoodsReceipt') {
                    $item->pricends = $item->price + $item->price * $item->nds();
                    $item->custcode = '';
                }
                $item->amount = $item->price * $item->quantity;
                $total += $item->amount;
            } elseif ($type == 'OutcomeItem') {
                $item->sum = \App\Helper::fa($item->quantity * $item->getPartion());
            }
            // строки с той же позицией и номером форма расхода складывает в одну
            $key = $item->item_id . '|' . $item->snumber;
            if ($type == 'GoodsIssue' && isset($rows[$key])) {
                $rows[$key]->quantity += $item->quantity;
                continue;
            }
            $rows[$key] = $item;
        }
        $doc->packDetails('detaildata', array_values($rows));

        if ($type == 'GoodsIssue') {
            $h += ['contract_id' => $contract, 'totaldisc' => '', 'salesource' => 0, 'payed' => 0, 'fop' => 0, 'nds' => '',
                   'payment' => 0, 'order_id' => 0, 'order' => '', 'store_name' => $storeName($store), 'pricetype' => 'price1',
                   'pricetypename' => Item::getPriceTypeList()['price1'] ?? ''];
            $doc->payamount = $total;
            $doc->payed = 0;
        } elseif ($type == 'GoodsReceipt') {
            // значения пустой формы: доставка «самовывоз» (1), стоимость доставки 0, валюта своя (0, курс 1)
            $h += ['contract_id' => $contract, 'deliverytype' => 1, 'delivery' => 0, 'storename' => $storeName($store), 'payment' => 0,
                   'val' => 0, 'valname' => '', 'rate' => 1, 'nds' => 0, 'disc' => 0, 'outnumber' => '', 'basedoc' => '',
                   'comission' => 0, 'payreq' => '', 'payamount' => $total, 'payed' => 0];
            $doc->payamount = $total;
            $doc->payed = 0;
        } elseif ($type == 'MoveItem') {
            $to = intval($d['tostore'] ?? 0);
            $h += ['tostore' => $to, 'tostorename' => $storeName($to), 'storename' => $storeName($store), 'tostoreemp' => -1, 'tostoreempname' => ''];
        } else {
            $h += ['storename' => $storeName($store)];
            if ($type == 'OutcomeItem') {
                $h['tostore'] = intval($d['tostore'] ?? 0);
            }
        }
        $doc->headerdata = array_merge($doc->headerdata, $h);   // к шапке Document::create (автор, hash, фирма)
        $doc->amount = \App\Helper::fa($total);

        $conn->BeginTrans();
        try {
            $doc->save();
            $doc->updateStatus(Document::STATE_NEW);
            if (empty($d['draft'])) {
                $doc->updateStatus(Document::STATE_EXECUTED);
                if ($type == 'GoodsIssue' && $doc->payamount > $doc->payed) {
                    $doc->updateStatus(Document::STATE_WP);
                }
            }
            $conn->CommitTrans();
        } catch (\Throwable $e) {
            $conn->RollbackTrans();
            throw new \Exception("{$type} {$doc->document_number}: " . $e->getMessage());
        }
        $out['docs'][] = ['id' => (int)$doc->document_id, 'number' => $doc->document_number];
    }
} catch (\Throwable $e) {
    $out['error'] = $e->getMessage();
}
echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
