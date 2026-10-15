<?php

namespace App\Modules\OCStore;

use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Entity\Customer;
use App\System;
use Zippy\Binding\PropertyBinding as Prop;
use Zippy\Html\DataList\ArrayDataSource;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use App\Application as App;

class Orders extends \App\Pages\Base
{
    public $_neworders = array();
    public $_eorders   = array();

    public function __construct() {
        parent::__construct();

        if (strpos(System::getUser()->modules, 'ocstore') === false && System::getUser()->rolename != 'admins') {
            System::setErrorMsg(__('common.nemaie_prava_dostupu_do_storinky'));

            App::RedirectError();
            return;
        }

        $modules = System::getOptions("modules");
        $statuses = System::getSession()->statuses;
        if (is_array($statuses) == false) {
            $statuses = array();
            $this->setWarn(__('mod.ocstore.orders.natysnit_pereviryty_ziednannia'));
        }

        $defpaytype=intval($modules['ocpaytype']??0);
        $defstore=intval($modules['ocstore']);
        $defmf=intval($modules['ocmf']??0);
           
        
        $this->add(new Form('filter'))->onSubmit($this, 'filterOnSubmit');
        $this->filter->add(new DropDownChoice('status', $statuses, 0));
        $this->add(new Form('filter2'))->onSubmit($this, 'onImport');
        $pt=[];
        $pt[1] = __('mod.ocstore.orders.oplata_zrazu_peredplata');
        $pt[2] = __('common.postoplata');
        $pt[3] = __('mod.ocstore.orders.oplata_v_cheku_abo_vn');
        $pt[4] = __('mod.ocstore.orders.tilky_spysaty_zi_skladu');
          
        $this->filter2->add(new DropDownChoice('paytype',$pt, $defpaytype));
         
        $this->add(new DataView('neworderslist', new ArrayDataSource(new Prop($this, '_neworders')), $this, 'noOnRow'));

        
        $this->add(new ClickLink('refreshbtn'))->onClick($this, 'onRefresh');
        $this->add(new Form('updateform'))->onSubmit($this, 'exportOnSubmit');
        $this->updateform->add(new DataView('orderslist', new ArrayDataSource(new Prop($this, '_eorders')), $this, 'expRow'));
        $this->updateform->add(new DropDownChoice('estatus', $statuses, 0));

        $this->add(new ClickLink('checkconn'))->onClick($this, 'onCheck');

    }

    public function filterOnSubmit($sender) {
   
        if(strlen(System::getSession()->octoken)==0) {
            Helper::connect();
        }
        $modules = System::getOptions("modules");

        $status = $this->filter->status->getValue();

        $this->_neworders = array();
        $fields = array(
            'status_id' => $status,
        );
        $url = $modules['ocsite'] . '/index.php?route=api/zstore/orders&' . System::getSession()->octoken;
        if($modules['ocv4']==1) {
            $url = $modules['ocsite'] . '/index.php?route=api/zstore.orders&' . System::getSession()->octoken;
        }
        $json = Helper::do_curl_request($url, $fields);
        if ($json === false) {
            return;
        }
        $data = json_decode($json, true);
        if (!isset($data)) {
            $this->setError(__('mod.ocstore.items.nevirna_vidpovid'));
         //   \App\Helper::log($json);
            return;
        }
        if ($data['error'] == "") {
            $conn = \ZDB\DB::getConnect();


            foreach ($data['orders'] as $ocorder) {


                $cnt  = $conn->getOne("select count(*) from documents_view where (meta_name='Order' or meta_name='TTN') and content like '%<ocorder>{$ocorder['order_id']}</ocorder>%'  and (CURRENT_DATE - INTERVAL 1 MONTH) < document_date  ")  ;

                if (intval($cnt) > 0) { //уже импортирован
                    continue;
                }
                foreach ($ocorder['_products_'] as $product) {
                    $code = trim($product['sku']);
                    if ($code == "") {
                        $this->setWarn(__('mod.ocstore.orders.ne_zadano_artykul_tovara_v', ['name' => $product['name']]) . $ocorder['order_id']);
                    }
                }

                $order = new \App\DataItem($ocorder);

                $this->_neworders[$ocorder['order_id']] = $order;
            }

            $this->neworderslist->Reload();
        } else {
            $data['error']  = str_replace("'", "`", $data['error']) ;

            $this->setErrorTopPage($data['error']);
        }
    }

    public function noOnRow($row) {
        $order = $row->getDataItem();

        $row->add(new Label('number', $order->order_id));
        $row->add(new Label('customer', $order->firstname . ' ' . $order->lastname));
        $row->add(new Label('amount', \App\Helper::fa($order->total)));
        $row->add(new Label('comment', $order->comment));
        $row->add(new Label('date', \App\Helper::fdt(strtotime($order->date_modified))));
    }

    public function onImport($sender) {
        $pt=$sender->paytype->getValue() ;
        if($pt==0){
            $this->setError(__('common.ne_vkazano_typ_oplaty'))  ;
            return;
        } 
        if($sender->paytype->getValue() ==4) {
            $this->onOutcome( );            
        }   else{
            $this->onOrder( ); 
        }
        
    }
    public function onOrder(  ) {
        $defpaytype = $this->filter2->paytype->getValue() ;
            
        $modules = System::getOptions("modules");
        $defstore=intval($modules['ocstoreid']);
        $defmf=intval($modules['ocmf']);
 
        $i = 0;
        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();

        try{     
           foreach ($this->_neworders as $shoporder) {


            $neworder = Document::create('Order');
            $neworder->document_date = strtotime($shoporder->date_added);
  
            $neworder->document_number = $neworder->nextNumber();
            if (strlen($neworder->document_number) == 0) {
                $neworder->document_number = 'OC00001';
            }
            $total =0;
            $j=0;           //товары
            $tlist = array();
            foreach ($shoporder->_products_ as $product) {
                //ищем по артикулу
                if (strlen($product['sku']) == 0) {
                    continue;
                }
                $code = Item::qstr($product['sku']);

                $tovar = Item::getFirst('item_code=' . $code);
                if ($tovar == null) {

                    $this->setWarn(__('common.ne_znaideno_artykul_tovara_v', ['name' => $product['name']]). $shoporder->order_id);
                    continue;
                }
                $tovar->quantity = $product['quantity'];
                $tovar->price = str_replace(',', '.', $product['price']);
                $desc = '';
                if (is_array($product['_options_'])) {
                    foreach ($product['_options_'] as $k => $v) {
                        $desc = $desc . $k . ':' . $v . ';';
                    }
                }
                //$tovar->octoreoptions = serialize($product['_options_']);
                $tovar->desc = $desc;
                $j++;
                $tovar->rowid = $j;
                $total  = $total +  ($tovar->quantity * $tovar->price) ;
                $tlist[$j] = $tovar;
            }
            if(count($tlist)==0) {
                continue;
            }
            $neworder->packDetails('detaildata', $tlist);
            $neworder->amount = \App\Helper::fa($total);
            $neworder->payamount = \App\Helper::fa($shoporder->total);

            $neworder->headerdata['totaldisc']  = $neworder->amount - $neworder->payamount;


            $neworder->headerdata['outnumber'] = $shoporder->order_id;
            $neworder->headerdata['ocorder'] = $shoporder->order_id;
            $neworder->headerdata['ocorderback'] = 0;
            $neworder->headerdata['pricetype'] = 'price1';
            $neworder->headerdata['salesource'] = $modules['ocsalesource'];
            $neworder->headerdata['doreserve'] = $modules['ocreserve'];
            $neworder->headerdata['dostore'] = $modules['ocdostore'];
            $neworder->headerdata['paytype'] = $defpaytype;  
            $neworder->headerdata['paytypename'] = $this->filter2->paytype->getValueName() ;  
            $neworder->headerdata['payment'] = $defmf ; 
            if($neworder->headerdata['paytype']==2) {
                $neworder->headerdata['waitpay'] =1;   //ждет оплату
            }
            $neworder->headerdata['store'] = $defstore ; 
            $neworder->headerdata['dostore'] = $defstore ; 
            $neworder->headerdata['reserve'] = $defstore ; 
      
            $neworder->notes = __('mod.ocstore.orders.oc_nomer', ['order_id' => $shoporder->order_id]);

            $neworder->headerdata['occlient'] = $shoporder->firstname . ' ' . $shoporder->lastname;
            $neworder->notes .= __('mod.ocstore.orders.kliient') . $shoporder->firstname . ' ' . $shoporder->lastname . ";";
            if( $modules['ocinsertcust'] == 1  && strlen($shoporder->telephone ??'' )>0 ) {
                $cust=null;
 
                $phone=\App\Util::handlePhone($shoporder->telephone);
                
                if ($shoporder->customer_id > 0 ) {
                    $cust = Customer::getFirst("detail like '%<shopcust_id>{$shoporder->customer_id}</shopcust_id>%'");
                }
                if ($cust == null) {
                    $cust = Customer::getByPhone($phone) ;
                }   
     
                         
                if ($cust == null) {
                    $cust = new Customer();
                    $cust->customer_name = trim($shoporder->lastname . ' ' . $shoporder->firstname);
                    $cust->address = $shoporder->shipping_city . ' ' . $shoporder->shipping_address_1;
                    $cust->type = Customer::TYPE_BAYER;
                    $cust->phone = $phone;
                    $cust->email = $shoporder->email;
                    $cust->comment = __('mod.ocstore.orders.kliient_opencart');
                    $cust->save();
                }
                
                if ($cust != null) {
                    if ($shoporder->customer_id > 0) {
                       $cust->shopcust_id = $shoporder->customer_id;
                       $cust->save();
                    }
                    
                    $neworder->customer_id = $cust->customer_id;
                }
            }
            if (strlen($shoporder->email) > 0) {
                $neworder->notes .= " Email:" . $shoporder->email . ";";
            }
            if (strlen($shoporder->telephone) > 0) {
                $neworder->notes .= __('mod.ocstore.orders.tel') . $shoporder->telephone . ";";
                $neworder->headerdata['phone'] = $shoporder->telephone;            
            }
            $neworder->notes .= __('common.adresa') . $shoporder->shipping_city . ' ' . $shoporder->shipping_address_1 . ";";
            $neworder->notes .= __('mod.ocstore.orders.oplata') . $shoporder->payment_method . ";";
            $neworder->notes .= __('mod.ocstore.orders.komentar') . $shoporder->comment . ";";
            
            $neworder->headerdata['ship_address']  = $shoporder->shipping_city . ' ' . $shoporder->shipping_address_1  ;
            
            if($modules['ocmf'] >0) {
               $neworder->headerdata['payment'] = $modules['ocmf'];
        
            }
            if ($neworder->headerdata['paytype'] == 2) {
                $neworder->setHD('waitpay',1); 
            }        
           
        
                $neworder->save();
                
                 
                $neworder->updateStatus(Document::STATE_NEW);
      
                $neworder->updateStatus(\App\Entity\Doc\Document::STATE_WAIT);
              
                if($neworder->headerdata['store']>0) {
                    $neworder->reserve();   //если задан  склад резервируем товары
                }  
      
           
            $i++;
        }
        
           $conn->CommitTrans();
          
        } catch(\Throwable $ee){
            global $logger;
            $conn->RollbackTrans();
           
            $this->setError($ee->getMessage());

            $logger->error( $ee->getMessage() . " OCStore " );
                 
           
            return;
        }        
        
        $this->setInfo(__('mod.ocstore.orders.importovano_zamovlen', ['i' => $i]));
        
        $this->_neworders = array();
        $this->neworderslist->Reload();
    }

    //только  списание
    public function onOutcome( ) {
        $modules = System::getOptions("modules");
       
        $store=intval($modules['ocstoreid']);
        $kassa=intval($modules['ocmf']);
        
        
        if ($store == 0) {
            $this->setError(__('mod.ocstore.orders.ne_zadano_sklad'));
            return;
        }
        if ($kassa == 0) {
            $this->setError(__('mod.ocstore.orders.ne_zadano_kasu'));
            return;
        }
        $allowminus = \App\System::getOption("common", "allowminus");

        if ($allowminus != 1) {
            foreach ($this->_neworders as $shoporder) {

                foreach ($shoporder->_products_ as $product) {
                    //ищем по артикулу
                    if (strlen($product['sku']) == 0) {
                        continue;
                    }
                    $code = Item::qstr($product['sku']);

                    $tovar = Item::getFirst('item_code=' . $code);
                    if ($tovar == null) {

                        $this->setWarn(__('common.ne_znaideno_artykul_tovara_v', ['name' => $product['name']]) . $shoporder->order_id);
                        continue;
                    }
                    $tovar->quantity = $product['quantity'];

                    $qty = $tovar->getQuantity($store);
                    if ($qty < $tovar->quantity) {
                        $this->setError(__('common.na_skladi_vsoho').\App\Helper::fqty($qty).__('common.tmts_spysannia_u_minus_zaboroneno', ['itemname' => $tovar->itemname]));
                        return;
                    }
                }
            }
        }
        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {

            $i = 0;
            foreach ($this->_neworders as $shoporder) {


                $neworder = Document::create('TTN');
                $neworder->document_date = time();
                $neworder->headerdata['sent_date'] = time();
                $neworder->headerdata['delivery_date'] = time()+(3600*24);
                $neworder->document_number = $neworder->nextNumber();
                if (strlen($neworder->document_number) == 0) {
                    $neworder->document_number = __('mod.ocstore.orders.ttn_00001');
                }

                //товары
                $j=0;
                $totalpr = 0;
                $tlist = array();
                foreach ($shoporder->_products_ as $product) {
                    //ищем по артикулу
                    if (strlen($product['sku']) == 0) {
                        continue;
                    }
                    $code = Item::qstr($product['sku']);

                    $tovar = Item::getFirst('item_code=' . $code);
                    if ($tovar == null) {

                        $this->setWarn(__('common.ne_znaideno_artykul_tovara_v', ['name' => $product['name']]) . $shoporder->order_id);
                        continue;
                    }
                    $tovar->quantity = $product['quantity'];
                    $tovar->price = \App\Helper::fa($product['price']);
                    $totalpr += ($tovar->quantity * $tovar->price);
                    $j++;
                    $tovar->rowid = $j;

                    $tlist[$j] = $tovar;
                }
                $neworder->packDetails('detaildata', $tlist);

                $neworder->headerdata['store'] = $store;
                $neworder->headerdata['store_name'] = \App\Entity\Store::load($store)->storename ?? '';
                
                $neworder->headerdata['ocorder'] = $shoporder->order_id;
                $neworder->headerdata['outnumber'] = $shoporder->order_id;



                $neworder->amount = \App\Helper::fa($totalpr);

                if ($shoporder->total > $totalpr) {
                    $neworder->headerdata['ship_amount'] = $shoporder->total - $totalpr;
                    $neworder->headerdata['delivery'] = Document::DEL_SELF;
                    $neworder->headerdata['delivery_name'] = __('doc.document.samovyviz');
                }

                $neworder->payamount = 0;
                $neworder->payed = 0;
                $neworder->notes = __('mod.ocstore.orders.oc_nomer_2', ['order_id' => $shoporder->order_id]);
                $neworder->notes .= __('mod.ocstore.orders.kliient_2') . $shoporder->firstname . ' ' . $shoporder->lastname . ";";
                if (strlen($shoporder->email) > 0) {
                    $neworder->notes .= " Email:" . $shoporder->email . ";";
                }
                if (strlen($shoporder->telephone) > 0) {
                    $neworder->notes .= __('common.tel') . $shoporder->telephone . ";";
                }
                $neworder->notes .= __('common.adresa') . $shoporder->shipping_city . ' ' . $shoporder->shipping_address_1 . ";";
                $neworder->notes .= __('mod.ocstore.orders.komentar') . $shoporder->comment . ";";
                $neworder->save();
                $neworder->updateStatus(Document::STATE_NEW);
                $neworder->updateStatus(Document::STATE_EXECUTED);
                $neworder->updateStatus(Document::STATE_DELIVERED);

                $i++;
            }

            $conn->CommitTrans();


        } catch(\Throwable $ee) {
            global $logger;
            $conn->RollbackTrans();


            $this->setError($ee->getMessage());

            $logger->error($ee->getMessage() . " OCStore ");
            return;
        }

        $this->setInfo(__('mod.ocstore.orders.importovano_zamovlen', ['i' => $i]));

        $this->_neworders = array();
        $this->neworderslist->Reload();
    }

    public function onCheck($sender) {

        Helper::connect();
        \App\Application::Redirect("\\App\\Modules\\OCStore\\Orders");
    }

    public function onRefresh($sender) {

        $this->_eorders = Document::find("meta_name='Order' and content like '%<ocorderback>0</ocorderback>%' and state <> " . Document::STATE_NEW);
        $this->updateform->orderslist->Reload();
    }

    public function expRow($row) {
        $order = $row->getDataItem();
        $row->add(new CheckBox('ch', new Prop($order, 'ch')));
        $row->add(new Label('number2', $order->document_number));
        $row->add(new Label('number3', $order->headerdata['ocorder']));
        $row->add(new Label('date2', \App\Helper::fd($order->document_date)));
        $row->add(new Label('amount2', $order->amount));
        $row->add(new Label('customer2', $order->headerdata['occlient']));
        $row->add(new Label('state', Document::getStateName($order->state)));
    }

    public function exportOnSubmit($sender) {
        $modules = System::getOptions("modules");

        $st = $this->updateform->estatus->getValue();
        if ($st == 0) {

            $this->setError(__('mod.ocstore.orders.ne_obrano_status'));
            return;
        }
        $elist = array();
        foreach ($this->_eorders as $order) {
            if ($order->ch == false) {
                continue;
            }
            $elist[$order->headerdata['ocorder']] = $st;
        }
        if (count($elist) == 0) {

            $this->setError(__('common.ne_obrano_order'));
            return;
        }
        $data = json_encode($elist);

        $fields = array(
            'data' => $data
        );
        $url = $modules['ocsite'] . '/index.php?route=api/zstore/updateorder&' . System::getSession()->octoken;
        if($modules['ocv4']==1) {
            $url = $modules['ocsite'] . '/index.php?route=api/zstore.updateorder&' . System::getSession()->octoken;
        }

        $json = Helper::do_curl_request($url, $fields);
        if ($json === false) {
            return;
        }
        $data = json_decode($json, true);

        if ($data['error'] != "") {
            $data['error']  = str_replace("'", "`", $data['error']) ;

            $this->setErrorTopPage($data['error']);
            return;
        }

        $this->setSuccess(__('common.onovleno_2').count($elist).__('common.zamovlen'));

        foreach ($this->_eorders as $order) {
            if ($order->ch == false) {
                continue;
            }
            $order->headerdata['ocorderback'] = 1;
            $order->save();
        }


        $this->_eorders = Document::find("meta_name='Order' and content like '%<ocorderback>0</ocorderback>%' and state <> " . Document::STATE_NEW);
        $this->updateform->orderslist->Reload();
    }

}
