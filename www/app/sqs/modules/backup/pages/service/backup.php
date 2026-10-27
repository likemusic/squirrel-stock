<?php

namespace App\Pages\Service;

use App\SQS\BackupJob;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;

/**
 * SquirrelStock: резервная копия — одной кнопкой (App\SQS\Backup), автоматическая по расписанию в несколько мест
 * (App\SQS\BackupJob, cli/backup.php), на флешку, восстановление. Только для роли admins.
 */
class Backup extends \App\Pages\Base
{
    public function __construct() {
        parent::__construct();
        // роль с правом на страницу (роль «Склад», cli/role-warehouse.php) — создать копию и копию на флешку; восстановление
        // и настройки автокопии (места, пароль, rclone) — только admins
        if (!\App\ACL::checkShowSer('Backup')) {
            return;
        }
        $admin = self::isAdmin();
        $this->_tvars['backupadmin'] = $admin;
        $this->add(new Form('bform'));
        $this->bform->add(new SubmitButton('create'))->onClick($this, 'onCreate');

        $s = BackupJob::settings();
        $this->add(new Form('sform'));
        $this->sform->add(new TextInput('local', $s['local']));
        $this->sform->add(new TextInput('network', $s['network']));
        $this->sform->add(new \Zippy\Html\Form\TextArea('cloud', $s['cloud']));   // облака — по одному в строке
        $this->sform->add(new TextInput('rclone', $s['rclone']));
        $this->sform->add(new TextInput('rclone_conf', $s['rclone_conf']));
        $this->sform->add(new TextInput('password'));
        $this->sform->add(new TextInput('keep', (string)$s['keep']));
        $this->sform->add(new SubmitButton('save'))->onClick($this, 'onSave');
        $this->sform->add(new SubmitButton('runnow'))->onClick($this, 'onRunNow');

        $this->add(new Form('uform'));
        $this->uform->add(new TextInput('usbpath'));
        $this->uform->add(new SubmitButton('copyto'))->onClick($this, 'onCopyTo');

        $this->add(new Form('rform'));
        $this->rform->add(new \Zippy\Html\Form\File('rfile'));
        $this->rform->add(new TextInput('rpassword'));
        $this->rform->add(new CheckBox('confirm'));
        // копия из более новой версии программы — второе подтверждение (показывается после предупреждения)
        $this->rform->add(new \Zippy\Html\Panel('newerpanel'))->setVisible(false);
        $this->rform->newerpanel->add(new CheckBox('confirmnewer'));
        $this->rform->add(new SubmitButton('restore'))->onClick($this, 'onRestore');
        $this->sform->setVisible($admin);
        $this->rform->setVisible($admin);

        $this->updateStatus();
    }

    private static function isAdmin(): bool {
        return \App\System::getUser()->rolename == 'admins';
    }

    // восстановление и настройки — только admins; формы скрыты, а запрос мимо формы — отказ здесь
    private function adminOnly(): bool {
        if (self::isAdmin()) {
            return true;
        }
        $this->setError(__('common.do_storinky_maie_dostup_tilky'));
        return false;
    }

    private function updateStatus(): void {
        $s = BackupJob::settings();
        $places = [];
        $stale = [];
        foreach (BackupJob::status() as $key => $st) {
            $last = $st['last'];
            $places[] = [
                'place'   => $key,   // local, network, cloud (не настроено) или cloud:<remote> — по строке на облако
                'name'    => __('service.backup.place_' . $st['place']),
                'target'  => $st['target'],
                'enabled' => $st['enabled'],
                'last_ok' => $st['last_ok'] ? \App\Helper::fdt($st['last_ok']) : __('service.backup.never'),
                'error'   => $last && !$last['ok'] ? \App\Helper::fdt(strtotime($last['createdon'])) . ': ' . \App\SQS\BackupException::text((string)$last['message']) : '',
                'stale'   => $st['stale'],
            ];
            if ($st['stale']) {
                $stale[] = BackupJob::label($key);
            }
        }
        $this->_tvars['places'] = $places;
        $this->_tvars['stale'] = $stale ? __('service.backup.stale', ['places' => implode(', ', $stale), 'hours' => BackupJob::STALE_HOURS]) : '';
        $this->_tvars['haspassword'] = $s['password'] !== '';
        $this->_tvars['cloudnopass'] = $s['cloud'] !== '' && $s['password'] === '';
        $sc = BackupJob::scheduleCommand();
        $this->_tvars['schedule_os'] = $sc['os'];
        $this->_tvars['schedule_cmd'] = $sc['cmd'];
    }

    public function onCreate($sender) {
        \App\SQS\Limits::raise('backup create');   // большая база: дамп и архив дольше 30 с хостинга
        try {
            $zip = (new \App\SQS\Backup())->create(_ROOT . 'upload');
        } catch (\Throwable $e) {
            $this->failed($e);   // нет прав у пользователя базы, нет временной папки и т. п. — на странице, а не вместо нее
            return;
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="squirrelstock-' . date('Y-m-d-Hi') . '.zip"');
        header('Content-Length: ' . filesize($zip));
        readfile($zip);
        unlink($zip);
        die;
    }

    public function onSave($sender) {
        if (!$this->adminOnly()) {
            return;
        }
        $f = $this->sform;
        $s = ['local' => $f->local->getText(), 'network' => $f->network->getText(), 'cloud' => $f->cloud->getText(),
              'rclone' => $f->rclone->getText(), 'rclone_conf' => $f->rclone_conf->getText(), 'keep' => $f->keep->getText()];
        if (trim($s['local']) === '') {
            $this->setError(__('service.backup.no_folder'));
            return;
        }
        if (is_dir($s['local']) && BackupJob::insideWebRoot($s['local'])) {
            $this->setError(__('service.backup.folder_in_webroot', ['dir' => $s['local']]));
            return;
        }
        // пароль меняется, только если введен новый: поле не показывает сохраненный
        if ($f->password->getText() !== '') {
            $s['password'] = $f->password->getText();
            $f->password->setText('');
        }
        BackupJob::saveSettings($s);
        $this->setSuccess(__('service.backup.saved'));
        $this->updateStatus();
    }

    public function onRunNow($sender) {
        if (!$this->adminOnly()) {
            return;
        }
        $this->onSave($sender);
        if ($this->isError()) {
            return;
        }
        \App\SQS\Limits::raise('backup run');
        $result = BackupJob::run();
        $lines = [];
        foreach ($result as $place => $r) {
            $lines[] = BackupJob::label($place) . ': ' . ($r['ok'] ? __('service.backup.ok') : $r['message']);
        }
        if (array_filter($result, fn($r) => !$r['ok'])) {
            $this->setError(implode('; ', $lines));
        } else {
            $this->setSuccess(implode('; ', $lines));
        }
        $this->updateStatus();
    }

    public function onCopyTo($sender) {
        $dir = trim($this->uform->usbpath->getText());
        if ($dir === '') {
            $this->setError(__('service.backup.no_folder'));
            return;
        }
        \App\SQS\Limits::raise('backup copy');
        try {
            $file = BackupJob::copyTo($dir);
        } catch (\Throwable $e) {
            $this->failed($e);
            return;
        }
        $this->setSuccess(__('service.backup.copied', ['file' => $file]));
    }

    // ошибка копии на странице: своя (BackupException, TempFilesException) — ее текстом, иная — общим текстом, а сама
    // (с путями сервера) — в журнал; без перехвата фреймворк выводил текст исключения с путем вместо страницы
    private function failed(\Throwable $e): void {
        if ($e instanceof \App\SQS\BackupException || $e instanceof \App\SQS\TempFilesException) {
            $this->setError($e->getMessage());
            return;
        }
        global $logger;
        $logger?->error($e);
        $this->setError(__('service.backup.create_failed'));
    }

    public function onRestore($sender) {
        if (!$this->adminOnly()) {
            return;
        }
        $file = $this->rform->rfile->getFile();
        if (strlen($file['tmp_name'] ?? '') == 0) {
            $this->setError(__('common.ne_obrano_fail'));
            return;
        }
        if (!$this->rform->confirm->isChecked()) {
            $this->setError(__('service.backup.confirm_needed'));
            return;
        }
        \App\SQS\Limits::raise('backup restore', 'sqs.limits.restore_unchanged');
        try {
            $newerOk = $this->rform->newerpanel->isVisible() && $this->rform->newerpanel->confirmnewer->isChecked();
            [$n, $skipped] = (new \App\SQS\Backup())->restore($file['tmp_name'], _ROOT . 'upload', $this->rform->rpassword->getText(), $newerOk);
        } catch (\App\SQS\BackupException $e) {
            global $logger;
            $logger?->warning('SquirrelStock restore: ' . $e->getMessage());
            if ($e->key == 'service.backup.rollback_failed') {
                // база неполная: страница программы не отрисуется, сообщение с путем к страховочной копии потерялось бы
                \App\SQS\Limits::page('sqs-restore-failed', $e->getMessage(), '', __('sqs.limits.back'));
                exit;
            }
            if ($e->key == 'service.backup.newer') {
                $this->rform->newerpanel->setVisible(true);
                $this->rform->newerpanel->confirmnewer->setChecked(false);
            }
            $this->setError($e->getMessage());
            return;
        } catch (\Throwable $e) {
            // в журнал — до вывода на страницу: после сбоя посреди дампа базы может не хватать таблиц, и страница не отрисуется
            global $logger;
            $logger?->error('SquirrelStock restore: ' . $e->getMessage());
            $this->setError($e->getMessage());
            return;
        }
        $this->rform->newerpanel->setVisible(false);
        $this->setSuccess(__('service.backup.restored', ['n' => $n]) . ($skipped ? '. ' . __('service.backup.skipped', ['n' => $skipped]) : ''));
    }
}
