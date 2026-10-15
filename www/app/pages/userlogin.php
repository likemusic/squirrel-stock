<?php

namespace App\Pages;

use App\Application as App;
use App\Entity\User;
use App\Helper;
use App\System;
use Zippy\Html\Form\TextInput ;

class UserLogin extends \Zippy\Html\WebPage
{
    private $_user = null;
    private $_otpcode = null;
    private $_otptime = null;
    private $_cntlogin = 0;

    public function __construct() {
        parent::__construct();
        $this->_tvars['t'] = new \App\Translator();   // перевод в шаблонах: {{#t}}ключ{{/t}}

     //   System::clean() ;
        System::getSession()->clean();


        $common = System::getOptions('common');
        \App\Session::getSession()->clipboard = null;

        $form = new \Zippy\Html\Form\Form('loginform');
        $form->add(new TextInput('userlogin'));
        $form->add(new TextInput('userlog'));
        $form->add(new TextInput('userpasswo'));
        $form->add(new TextInput('capchacode'));

        $form->add(new \Zippy\Html\Form\CheckBox('remember'));
        $form->add(new \ZCL\Captcha\Captcha('capcha'));
        $form->onSubmit($this, 'onsubmit');

        $this->add($form);
        $this->setError('');


      //  $this->_tvars['curversion'] = \App\System::CURR_VERSION ;


        $this->_tvars['appname'] = $common['shopname'];
        $this->_tvars['capcha'] = $common['capcha'] == 1;

        $this->_tvars['cron']  =  \App\System::useCron() ;
          
    }

    public function onsubmit($sender) {
        global $logger, $_config;

        $this->setError('');
        $lg = trim($sender->userlogin->getText() );
        
        if(strlen($lg) >0)  {   //обманка  для  ботов
            http_response_code(404);
            Helper::log('bot: '.$lg);
     
            die;
        }
        
        $login = $sender->userlog->getText();
        $password = $sender->userpasswo->getText();
        $sender->userpasswo->setText('');
        if ($this->_tvars['capcha'] == true) {
            $entercode = $sender->capchacode->getText();
            $capchacode = $sender->capcha->getCode();
            if (strlen($entercode) == 0 || $entercode != $capchacode) {
                $this->setError(__('app.userlogin.nevirnyi_kod_kapchi'));
                $this->counter();

                return;
            }
        }
        if ($login == '') {

            $this->setError(__('app.userlogin.vvedit_lohin'));
        } else {
            if ($password == '') {

                $this->setError(__('common.vvedit_parol'));
            }
        }

        if (strlen($login) > 0 && strlen($password) > 0) {
            // SquirrelStock: после серии неудач вход временно не проверяется (App\SQS\LoginGuard)
            $wait = \App\SQS\LoginGuard::blockedMinutes($login, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
            if ($wait > 0) {
                $this->setError(__('sqs.login.blocked', ['min' => $wait]));
                $sender->userpasswo->setText('');
                return;
            }
             
            $this->_user = Helper::login($login, $password);

            if ($this->_user instanceof User) {
                \App\SQS\LoginGuard::success($login);
                // SquirrelStock: двухфакторный вход — после пароля код (App\Pages\TwoFactorLogin); «Запомнить» — только после кода
                if (\App\SQS\TwoFactor::enabled(intval($this->_user->user_id))) {
                    \App\SQS\TwoFactor::begin($this->_user, $this->loginform->remember->isChecked());
                    App::Redirect('\\App\\Pages\\TwoFactorLogin');
                    return;
                }
                $this->successLogin() ;
                return;
            } 
            
            else {
                \App\SQS\LoginGuard::fail($login, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
                $this->setError(__('app.userlogin.nevirnyi_lohin_abo_parol'));

                $this->counter();
            }
        }

        $sender->userpasswo->setText('');
    }

    public function successLogin() {
        \App\Session::getSession()->clean();
        $this->_user->lastactive = time();
        $this->_user->save();
        System::setUser($this->_user);
        Helper::log('Login: '.$this->_user->username);
        
     //   $_SESSION['user_id'] = $this->_user->user_id; //для  использования  вне  Application
      //  $_SESSION['userlogin'] = $this->_user->userlogin; //для  использования  вне  Application

        if ($this->loginform->remember->isChecked()) {
            \App\SQS\Remember::issue($this->_user);   // SquirrelStock: случайный токен, отзывается сменой пароля и выходом
        } else {
            \App\SQS\Remember::forget();
        }
        if (($_COOKIE['branch_id'] ?? 0) > 0) {
            System::getSession()->defbranch = $_COOKIE['branch_id'];
        }
        if($this->_user->rolename=="admins") {
             System::checkUpdate()  ; 
             Helper::cleanDB()  ; 
        }
      
        
        App::RedirectHome();        
    }
 
    public function beforeRequest() {
        parent::beforeRequest();

        if (System::getUser()->user_id > 0) {
            App::RedirectHome();
        }
    }

    public function setError($msg) {


        $this->_tvars['alerterror'] = $msg;
    }

    private function counter() {
        $this->_cntlogin++;
        if ($this->_cntlogin == 5) {
            $msg = __('app.userlogin.bahato_nevdalykh_avtoryzatsii');
            $t = $this->loginform->userlogin->getText()  ;
            $t = htmlspecialchars($t) ;
            $msg .= '<br>' . $t. ', ';
            $msg .= $_SERVER['REMOTE_ADDR'] ;
         
            \App\Entity\Notify::toSystemLog($msg) ;
            \App\Entity\Notify::toAdmin($msg) ;


            $this->setError(__('app.userlogin.bahato_nevdalykh_avtoryzatsii_administra'));
            $this->loginform->setVisible(false);

        }

        //  $this->_tvars['alerterror'] = '';
    }

}
