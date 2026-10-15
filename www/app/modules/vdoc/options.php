<?php

namespace App\Modules\VDoc;

use App\System;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Link\ClickLink;
use App\Application as App;

class Options extends \App\Pages\Base
{
    public function __construct() {
        parent::__construct();

        if (strpos(System::getUser()->modules, 'vdoc') === false && System::getUser()->rolename != 'admins') {
            System::setErrorMsg(__('common.nemaie_prava_dostupu_do_storinky'));

            App::RedirectError();
            return;
        }

        $modules = System::getOptions("modules");

        $form = $this->add(new Form("cform"));
        $form->add(new TextInput('clientid', $modules['vdclientid']));
       
        $form->onSubmit($this, 'saveapiOnClick');

        $this->add(new ClickLink("check", $this, "onCheck")) ;

    }

    public function saveapiOnClick($sender) {

        $modules = System::getOptions("modules");

        $modules['vdclientid'] = trim($this->cform->clientid->getText());
     
        System::setOptions("modules", $modules);
        $this->setSuccess(__('common.zberezheno'));

    }

    public function onCheck($sender) {
        list($code, $result) =        Helper::connect(trim($this->cform->clientid->getText()));
        if($code=='ok') {
            System::getSession()->pltoken = $result;
            $this->setSuccess(__('common.uspishne_ziednannia')) ;
        }
        if($code=='error') {
            $this->setError($result) ;

            return;
        }

 

    }


}
