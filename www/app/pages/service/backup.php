<?php

namespace App\Pages\Service;

use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;

/**
 * SquirrelStock: резервная копия одной кнопкой и восстановление (App\SQS\Backup). Только для роли admins.
 */
class Backup extends \App\Pages\Base
{
    public function __construct() {
        parent::__construct();
        if (\App\System::getUser()->rolename != 'admins') {
            \App\System::setErrorMsg(__('common.do_storinky_maie_dostup_tilky'));
            \App\Application::RedirectError();
            return;
        }
        $this->add(new Form('bform'));
        $this->bform->add(new SubmitButton('create'))->onClick($this, 'onCreate');

        $this->add(new Form('rform'));
        $this->rform->add(new \Zippy\Html\Form\File('rfile'));
        $this->rform->add(new CheckBox('confirm'));
        $this->rform->add(new SubmitButton('restore'))->onClick($this, 'onRestore');
    }

    public function onCreate($sender) {
        $zip = (new \App\SQS\Backup())->create(_ROOT . 'upload');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="squirrelstock-' . date('Y-m-d-Hi') . '.zip"');
        header('Content-Length: ' . filesize($zip));
        readfile($zip);
        unlink($zip);
        die;
    }

    public function onRestore($sender) {
        $file = $this->rform->rfile->getFile();
        if (strlen($file['tmp_name'] ?? '') == 0) {
            $this->setError(__('common.ne_obrano_fail'));
            return;
        }
        if (!$this->rform->confirm->isChecked()) {
            $this->setError(__('service.backup.confirm_needed'));
            return;
        }
        try {
            $n = (new \App\SQS\Backup())->restore($file['tmp_name'], _ROOT . 'upload');
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return;
        }
        $this->setSuccess(__('service.backup.restored', ['n' => $n]));
    }
}
