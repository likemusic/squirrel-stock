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
        if (\App\System::getUser()->rolename != 'admins') {
            \App\System::setErrorMsg(__('common.do_storinky_maie_dostup_tilky'));
            \App\Application::RedirectError();
            return;
        }
        $this->add(new Form('bform'));
        $this->bform->add(new SubmitButton('create'))->onClick($this, 'onCreate');

        $s = BackupJob::settings();
        $this->add(new Form('sform'));
        $this->sform->add(new TextInput('local', $s['local']));
        $this->sform->add(new TextInput('network', $s['network']));
        $this->sform->add(new TextInput('cloud', $s['cloud']));
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
        $this->rform->add(new SubmitButton('restore'))->onClick($this, 'onRestore');

        $this->updateStatus();
    }

    private function updateStatus(): void {
        $s = BackupJob::settings();
        $places = [];
        $stale = [];
        foreach (BackupJob::status() as $place => $st) {
            $last = $st['last'];
            $places[] = [
                'place'   => $place,
                'name'    => __('service.backup.place_' . $place),
                'target'  => $place == 'cloud' ? $s['cloud'] : $s[$place],
                'enabled' => $st['enabled'],
                'last_ok' => $st['last_ok'] ? \App\Helper::fdt($st['last_ok']) : __('service.backup.never'),
                'error'   => $last && !$last['ok'] ? \App\Helper::fdt(strtotime($last['createdon'])) . ': ' . $last['message'] : '',
                'stale'   => $st['stale'],
            ];
            if ($st['stale']) {
                $stale[] = __('service.backup.place_' . $place);
            }
        }
        $this->_tvars['places'] = $places;
        $this->_tvars['stale'] = $stale ? __('service.backup.stale', ['places' => implode(', ', $stale), 'hours' => BackupJob::STALE_HOURS]) : '';
        $this->_tvars['haspassword'] = $s['password'] !== '';
        $this->_tvars['cloudnopass'] = $s['cloud'] !== '' && $s['password'] === '';
        // команда для планировщика этой системы: запуск каждый час, копия — если последней больше 20 часов
        $root = rtrim(_ROOT, '/\\');
        $php = preg_match('/php(\.exe)?$/i', PHP_BINARY) ? PHP_BINARY : (PHP_OS_FAMILY == 'Windows' ? 'C:\php\php.exe' : 'php');
        if (PHP_OS_FAMILY == 'Windows') {
            $this->_tvars['schedule_os'] = 'Windows';
            $this->_tvars['schedule_cmd'] = 'schtasks /Create /TN "SquirrelStock backup" /SC HOURLY /TR "\"' . $php . '\" \"' . $root . '\cli\backup.php\" --if-older=20"';
        } elseif (file_exists('/.dockerenv')) {
            $this->_tvars['schedule_os'] = 'Docker (cron)';
            $this->_tvars['schedule_cmd'] = '17 * * * * docker exec -u www-data <web-container> php cli/backup.php --if-older=20';
        } else {
            $this->_tvars['schedule_os'] = 'Linux (cron)';
            $this->_tvars['schedule_cmd'] = '17 * * * * ' . $php . ' ' . $root . '/cli/backup.php --if-older=20';
        }
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

    public function onSave($sender) {
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
        $this->onSave($sender);
        if ($this->isError()) {
            return;
        }
        $result = BackupJob::run();
        $lines = [];
        foreach ($result as $place => $r) {
            $lines[] = __('service.backup.place_' . $place) . ': ' . ($r['ok'] ? __('service.backup.ok') : $r['message']);
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
        try {
            $file = BackupJob::copyTo($dir);
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return;
        }
        $this->setSuccess(__('service.backup.copied', ['file' => $file]));
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
            $n = (new \App\SQS\Backup())->restore($file['tmp_name'], _ROOT . 'upload', $this->rform->rpassword->getText());
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return;
        }
        $this->setSuccess(__('service.backup.restored', ['n' => $n]));
    }
}
