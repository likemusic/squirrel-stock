<?php

namespace App\Pages;

use App\SQS\TwoFactor as TF;
use App\SQS\Totp;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;

/**
 * SquirrelStock: двухфакторный вход текущего пользователя — включить (QR-код или ключ вручную, подтверждение кодом),
 * резервные коды (показываются один раз), отключить. Администратор здесь же сбрасывает двухфакторный вход пользователю.
 * Ссылка — из профиля пользователя.
 */
class TwoFactor extends \App\Pages\Base
{
    private const SETUP = 'sqs_2fa_setup';

    public function __construct() {
        parent::__construct();
        $this->add(new Form('enableform'));
        $this->enableform->add(new TextInput('enablecode'));
        $this->enableform->add(new SubmitButton('enable'))->onClick($this, 'onEnable');

        $this->add(new Form('codeform'));
        $this->codeform->add(new TextInput('code'));
        $this->codeform->add(new SubmitButton('newcodes'))->onClick($this, 'onNewCodes');
        $this->codeform->add(new SubmitButton('disable'))->onClick($this, 'onDisable');

        $this->add(new Form('adminform'));
        $this->adminform->add(new DropDownChoice('resetuser', [], 0));
        $this->adminform->add(new SubmitButton('reset'))->onClick($this, 'onReset');

        $this->refresh();
    }

    private function user(): \App\Entity\User {
        return \App\System::getUser();
    }

    private function refresh(): void {
        $uid = intval($this->user()->user_id);
        $enabled = TF::enabled($uid);
        $this->_tvars['enabled'] = $enabled;
        $this->_tvars['backupleft'] = $enabled ? TF::backupLeft($uid) : 0;
        $this->enableform->setVisible(!$enabled);
        $this->codeform->setVisible($enabled);
        if (!$enabled) {
            $secret = $_SESSION[self::SETUP] ?? '';
            if ($secret === '') {
                $secret = $_SESSION[self::SETUP] = Totp::newSecret();
            }
            $common = \App\System::getOptions('common');
            $issuer = 'SquirrelStock' . (strlen($common['shopname'] ?? '') ? ' ' . $common['shopname'] : '');
            $this->_tvars['qr'] = \App\Util::generateQR(Totp::uri($secret, $this->user()->userlogin, $issuer), 220);
            $this->_tvars['secret'] = trim(chunk_split($secret, 4, ' '));
        }
        $this->_tvars['isadmin'] = $this->user()->rolename == 'admins';
        $this->adminform->setVisible($this->_tvars['isadmin']);
        if ($this->_tvars['isadmin']) {
            $conn = \ZDB\DB::getConnect();
            $list = [];
            foreach ($conn->Execute("SELECT u.user_id, u.userlogin FROM sqs_2fa t JOIN users u ON u.user_id = t.user_id WHERE t.enabled = 1 ORDER BY u.userlogin") as $r) {
                $list[$r['user_id']] = $r['userlogin'];
            }
            $this->adminform->resetuser->setOptionList($list);
            $this->_tvars['nousers'] = count($list) == 0;
        }
    }

    public function onEnable($sender) {
        $secret = $_SESSION[self::SETUP] ?? '';
        $codes = $secret === '' ? null : TF::enable(intval($this->user()->user_id), $secret, $this->enableform->enablecode->getText());
        $this->enableform->enablecode->setText('');
        if ($codes === null) {
            $this->setError(__('sqs.2fa.bad_code'));
            return;
        }
        unset($_SESSION[self::SETUP]);
        $this->showCodes($codes);
        $this->setSuccess(__('sqs.2fa.enabled'));
        $this->refresh();
    }

    public function onNewCodes($sender) {
        $uid = intval($this->user()->user_id);
        if (!TF::verify($uid, $this->codeform->code->getText())) {
            $this->codeform->code->setText('');
            $this->setError(__('sqs.2fa.bad_code'));
            return;
        }
        $this->codeform->code->setText('');
        $this->showCodes(TF::newBackupCodes($uid));
        $this->refresh();
    }

    public function onDisable($sender) {
        $uid = intval($this->user()->user_id);
        if (!TF::verify($uid, $this->codeform->code->getText())) {
            $this->codeform->code->setText('');
            $this->setError(__('sqs.2fa.bad_code'));
            return;
        }
        $this->codeform->code->setText('');
        TF::disable($uid);
        $this->setSuccess(__('sqs.2fa.disabled'));
        $this->refresh();
    }

    public function onReset($sender) {
        if ($this->user()->rolename != 'admins') {
            return;
        }
        $uid = intval($this->adminform->resetuser->getValue());
        if ($uid <= 0) {
            return;
        }
        TF::disable($uid);
        \App\Helper::log('2FA reset for user ' . $uid . ' by ' . $this->user()->userlogin);
        $this->setSuccess(__('sqs.2fa.reset_done', ['login' => $this->adminform->resetuser->getValueName()]));
        $this->refresh();
    }

    // резервные коды показываются один раз: после POST страница отрисовывается на GET (App\Pages\Base::afterRequest),
    // поэтому коды ждут в сессии и стираются после отрисовки GET-ответа
    private const CODES = 'sqs_2fa_codes';

    private function showCodes(array $codes): void {
        $_SESSION[self::CODES] = $codes;
    }

    public function beforeRender() {
        parent::beforeRender();
        $codes = $_SESSION[self::CODES] ?? [];
        $this->_tvars['backupcodes'] = array_map(fn($c) => ['code' => $c], $codes);
        $this->_tvars['hascodes'] = count($codes) > 0;
    }

    protected function afterRender() {
        parent::afterRender();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'GET') {
            unset($_SESSION[self::CODES]);
        }
    }
}
