<?php

namespace App\Modules\OCStore;

use App\System;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextArea;
use Zippy\Html\Form\TextInput;
use App\Application as App;

class Options extends \App\Pages\Base
{
    public function __construct() {
        parent::__construct();

        if (strpos(System::getUser()->modules, 'ocstore') === false && System::getUser()->rolename != 'admins') {
            System::setErrorMsg(__('common.nemaie_prava_dostupu_do_storinky'));

            App::RedirectError();
            return;
        }

        $modules = System::getOptions("modules");

        $form = $this->add(new Form("cform"));
        $form->add(new TextInput('site', $modules['ocsite']));
        $form->add(new TextInput('apiname', $modules['ocapiname']));
        $form->add(new CheckBox('ssl', $modules['ocssl'] ?? 0));

        $form->add(new CheckBox('insertcust', $modules['ocinsertcust']));
        $form->add(new CheckBox('dostore', $modules['ocdostore']));
        $form->add(new CheckBox('reserve', $modules['ocreserve']));

        $form->add(new CheckBox('v4', $modules['ocv4'] ?? 0));
        $form->add(new TextArea('key', $modules['ockey']));

        $form->add(new DropDownChoice('defpricetype', \App\Entity\Item::getPriceTypeList(), $modules['ocpricetype']));
        $form->add(new DropDownChoice('salesource', \App\Helper::getSaleSources(), $modules['ocsalesource']??0));
        $form->add(new DropDownChoice('defmf',\App\Entity\MoneyFund::getList(), $modules['ocmf']??0));
        $form->add(new DropDownChoice('defstore',\App\Entity\Store::getList(), $modules['ocstoreid']??0));
      
        $pt=[];
        $pt[1] = __('common.oplata_na_storoni_im');
        $pt[2] = __('common.postoplata');
        $pt[3] = __('common.oplata_kasovym_chekom_rf_abo');
        $pt[4] = __('mod.ocstore.options.tilky_spysaty_zi_skladu_cherez');
      
        $form->add(new DropDownChoice('defpaytype',$pt, $modules['ocpaytype']??0));

        $form->add(new SubmitButton('save'))->onClick($this, 'saveOnClick');
    }


    public function saveOnClick($sender) {
        $site = $this->cform->site->getText();
        $apiname = $this->cform->apiname->getText();
        $key = $this->cform->key->getText();

        $pricetype = $this->cform->defpricetype->getValue();
        $salesource = $this->cform->salesource->getValue();
        $mf = $this->cform->defmf->getValue();
        $store = $this->cform->defstore->getValue();
        $paytype = intval($this->cform->defpaytype->getValue() );

        $ssl = $this->cform->ssl->isChecked() ? 1 : 0;
        $v4 = $this->cform->v4->isChecked() ? 1 : 0;
        $insertcust = $this->cform->insertcust->isChecked() ? 1 : 0;
        $reserve = $this->cform->reserve->isChecked() ? 1 : 0;
        $dostore = $this->cform->dostore->isChecked() ? 1 : 0;


        if (strlen($pricetype) < 2) {

            $this->setError(__('common.ne_vkazano_typ_tsiny'));
            return;
        }

        if ( $paytype==0) {

            $this->setError(__('common.ne_vkazano_typ_oplaty'));
            return;
        }
        if ( $paytype==1 && $mf==0) {

            $this->setError(__('common.ne_vkazano_kasu'));
            return;
        }
        if ( $paytype==4 && $store==0) {

            $this->setError(__('mod.ocstore.options.ne_vkazano_sklad'));
            return;
        }

        if ( ($reserve==1 || $dostore==1 ) && $store==0 ) {

            $this->setError(__('mod.ocstore.options.ne_vkazano_sklad'));
            return;
        }

        $site = trim($site, '/');

        $modules = System::getOptions("modules");

        $modules['ocsite'] = $site;
        $modules['ocapiname'] = $apiname;
        $modules['ockey'] = $key;

        $modules['ocpricetype'] = $pricetype;
        $modules['ocsalesource'] = $salesource;
       
        $modules['ocssl'] = $ssl;
        $modules['ocv4'] = $v4;
        $modules['ocinsertcust'] = $insertcust;
        $modules['ocdostore'] = $dostore;
        $modules['ocreserve'] = $reserve;


        $modules['ocmf'] = $mf;
        $modules['ocstoreid'] = $store;
        $modules['ocpaytype'] = $paytype;

        System::setOptions("modules", $modules);


        $this->setSuccess(__('common.zberezheno'));

        \App\Modules\OCStore\Helper::connect();


    }

}
//2Ru8ToJTb4ZoH8qgk1oh64mSRVC2chloDDSeD2SMY8g1n1JJ8dlXGUwF06FZl2qUmrQF0H8Kru7gSpW7O4kHRd2zX2wUGUUqBd2joQQbS0cP8frArUFxgNBCBppRUjlbZqbhaAhBaIPQUA24ykK7DjjsVKALcaYXr6RqmPCcmAEvHqMRwE088O00hx8F2ANoUrxCVHifygaTh4K2bdXCkVTVefiaDdeEaBCsAIW4ctrXZmLhtUUF8kmFdvVnXeTh