<?php

namespace App\Pages;

use App\Application as App;
use App\SQS\LoginGuard;
use App\SQS\TwoFactor;
use Zippy\Html\Form\TextInput;

/**
 * SquirrelStock: второй шаг входа — код из приложения-аутентификатора или резервный код. Сюда ведет UserLogin после
 * верного пароля, если у пользователя включен двухфакторный вход (App\SQS\TwoFactor). Неверные коды считает LoginGuard.
 */
class TwoFactorLogin extends \Zippy\Html\WebPage
{
    public function __construct() {
        parent::__construct();
        $this->_tvars['t'] = new \App\Translator();
        $common = \App\System::getOptions('common');
        $this->_tvars['appname'] = $common['shopname'] ?? '';
        $this->_tvars['alerterror'] = '';

        $form = new \Zippy\Html\Form\Form('codeform');
        $form->add(new TextInput('code'));
        $form->onSubmit($this, 'onSubmit');
        $this->add($form);
    }

    public function beforeRequest() {
        parent::beforeRequest();
        if (\App\System::getUser()->user_id > 0) {
            App::RedirectHome();
            return;
        }
        if (TwoFactor::pending() === null) {   // нет верного пароля или истекло время на ввод кода
            TwoFactor::clearPending();
            App::Redirect('\\App\\Pages\\UserLogin');
        }
    }

    public function onSubmit($sender) {
        $p = TwoFactor::pending();
        if ($p === null) {
            App::Redirect('\\App\\Pages\\UserLogin');
            return;
        }
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $wait = LoginGuard::blockedMinutes($p['login'], $ip);
        if ($wait > 0) {
            TwoFactor::clearPending();
            $this->_tvars['alerterror'] = __('sqs.login.blocked', ['min' => $wait]);
            return;
        }
        $code = trim($sender->code->getText());
        $sender->code->setText('');
        if (!TwoFactor::verify($p['user_id'], $code)) {
            LoginGuard::fail($p['login'], $ip);
            $this->_tvars['alerterror'] = __('sqs.2fa.bad_code');
            return;
        }
        LoginGuard::success($p['login']);
        $user = \App\Entity\User::load($p['user_id']);
        if ($user == null || $user->disabled == 1) {
            TwoFactor::clearPending();
            App::Redirect('\\App\\Pages\\UserLogin');
            return;
        }
        TwoFactor::completeLogin($user, (bool)$p['remember']);
    }
}
