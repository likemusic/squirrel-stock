<?php

namespace App\Pages\Service;

use App\SQS\Release;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;

/**
 * SquirrelStock: обновление программы — версия, история обновлений, файлы сайта против списка файлов пакета
 * (config/manifest.txt): недостающие и измененные (пакет распакован не полностью), остатки старых версий (удаляются
 * кнопкой), прочие файлы не из пакета (не трогаются). Только для роли admins: кнопка удаляет файлы сайта.
 */
class AppUpdate extends \App\Pages\Base
{
    private const SHOW = 200;   // столько путей в каждом списке, остальные — числом

    public function __construct() {
        parent::__construct();
        if (\App\System::getUser()->rolename != 'admins') {
            \App\System::setErrorMsg(__('common.do_storinky_maie_dostup_tilky'));
            \App\Application::RedirectError();
            return;
        }
        $this->add(new Form('cform'));
        $this->cform->add(new SubmitButton('clean'))->onClick($this, 'onClean');
        $this->refresh();
    }

    public function onClean($sender) {
        [$done, $failed] = Release::removeStale();
        if ($failed) {
            $this->setError(__('service.appupdate.clean_failed', ['n' => count($failed), 'done' => $done]));
        } else {
            $this->setSuccess(__('service.appupdate.cleaned', ['n' => $done]));
        }
        $this->refresh();
    }

    private function refresh(): void {
        $this->_tvars['version'] = Release::version();
        $this->_tvars['history'] = array_map(fn($h) => [
            'from' => $h['from'] !== '' ? $h['from'] : __('service.appupdate.unknown'),
            'to'   => $h['to'],
            'at'   => \App\Helper::fdt(strtotime($h['at'])),
        ], array_reverse(Release::history()));
        $chk = Release::check();
        $this->_tvars['nomanifest'] = $chk === null;
        $this->_tvars['broken'] = false;
        foreach (['missing', 'changed', 'stale', 'foreign'] as $k) {
            $list = $chk[$k] ?? [];
            $this->_tvars[$k] = array_map(fn($p) => ['path' => $p], array_slice($list, 0, self::SHOW));
            $this->_tvars[$k . '_n'] = count($list);
            $this->_tvars[$k . '_more'] = count($list) > self::SHOW ? __('service.appupdate.more', ['n' => count($list) - self::SHOW]) : '';
        }
        $this->_tvars['broken'] = $this->_tvars['missing_n'] + $this->_tvars['changed_n'] > 0;
        $this->_tvars['ok'] = $chk !== null && !$this->_tvars['broken'] && $this->_tvars['stale_n'] == 0;
        $this->cform->setVisible($this->_tvars['stale_n'] > 0);
    }
}
