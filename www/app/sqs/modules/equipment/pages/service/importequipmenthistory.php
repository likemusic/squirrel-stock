<?php

namespace App\Pages\Service;

use App\Helper as H;
use App\SQS\EqHistory;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\File;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Label;

/**
 * SquirrelStock: импорт истории выдачи оборудования из Google-таблиц (перепись и журнал, .xlsx), разбор — App\SQS\EqHistory.
 * «Проверить» только разбирает и показывает отчет: что будет создано, ошибки (строка не импортируется) и заметки
 * (импортируется с допущением). «Записать» доступна после проверки и разбирает те же файлы заново перед записью.
 */
class ImportEquipmentHistory extends \App\Pages\Base
{
    public array $_files = [];   // [имя файла => путь к копии во временной папке]

    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowSer('ImportEquipmentHistory')) {
            return;
        }

        $form = $this->add(new Form('iform'));
        $form->add(new File('census'));
        $form->add(new File('journal'));
        $form->add(new DropDownChoice('store', \App\Entity\Store::getList(), H::getDefStore()));
        $form->add(new CheckBox('liftperiod', false));
        $form->add(new SubmitButton('check'))->onClick($this, 'onCheck');
        $form->add(new SubmitButton('write'))->onClick($this, 'onWrite');
        $form->write->setVisible(false);
        $this->add(new Label('report', '', true));
    }

    public function onCheck($sender) {
        $this->dropFiles();
        // копии файлов от проверок, после которых не было записи (в таблицах персональные данные): старше суток — удалить
        foreach (glob(sys_get_temp_dir() . '/sqs-eqh-*') ?: [] as $old) {
            if (filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }
        foreach (['census', 'journal'] as $f) {
            $file = $this->iform->{$f}->getFile();
            if (strlen($file['tmp_name'] ?? '') == 0) {
                continue;
            }
            $copy = tempnam(sys_get_temp_dir(), 'sqs-eqh-');
            move_uploaded_file($file['tmp_name'], $copy) || copy($file['tmp_name'], $copy);
            $this->_files[basename($file['name'])] = $copy;
        }
        if (count($this->_files) == 0) {
            $this->setError(__('common.ne_obrano_fail'));
            return;
        }
        $eh = $this->analyze();
        if ($eh) {
            $this->report->setText($this->reportHtml($eh, null), true);
            $this->iform->write->setVisible(count($eh->plan) > 0);
        }
    }

    public function onWrite($sender) {
        $store = intval($this->iform->store->getValue());
        if ($store == 0) {
            $this->setError(__('common.ne_obrano_sklad'));
            return;
        }
        $eh = $this->analyze();
        if (!$eh) {
            return;
        }
        try {
            $res = $eh->apply($store, $this->iform->liftperiod->isChecked());
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return;
        }
        $this->report->setText($this->reportHtml($eh, $res), true);
        $this->iform->write->setVisible(false);
        $this->dropFiles();
        $this->setSuccess(__('sqs.eqhist.written', $res));
    }

    private function analyze(): ?EqHistory {
        $eh = new EqHistory();
        try {
            foreach ($this->_files as $name => $path) {
                $eh->readFile($path, $name);
            }
            $eh->analyze();
        } catch (\Throwable $e) {
            $this->setError(__('sqs.eqhist.bad_file') . ': ' . $e->getMessage());
            return null;
        }
        return $eh;
    }

    private function dropFiles(): void {
        foreach ($this->_files as $path) {
            @unlink($path);
        }
        $this->_files = [];
    }

    private static function e($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    // отчет: сводка, ошибки и заметки, недостающие единицы, пропущенные выдачи без номеров
    private function reportHtml(EqHistory $eh, ?array $res): string {
        $h = '<h5>' . __($res === null ? 'sqs.eqhist.report_check' : 'sqs.eqhist.report_written') . '</h5>';
        $h .= '<table class="table table-sm w-auto" id="eqh-summary">';
        foreach ($eh->rowsRead as $file => $n) {
            $h .= '<tr><td>' . self::e($file) . '</td><td class="text-end">' . $n . '</td></tr>';
        }
        $s = $eh->stat;
        $rows = ['issues' => $s['issues'], 'merged' => $s['merged'], 'customers' => $s['customers'], 'contracts' => $s['contracts'],
                 'income' => $s['income'], 'issue' => $s['issue'], 'return' => $s['return'], 'writeoff' => $s['writeoff'],
                 'done' => $s['done'], 'errors' => $s['errors'], 'notes' => $s['notes']];
        if ($res !== null) {
            $rows = ['income' => $res['income'], 'issue' => $res['issue'], 'return' => $res['return'], 'writeoff' => $res['writeoff'], 'failed' => $res['failed'],
                     'done' => $s['done'], 'errors' => count(array_filter($eh->problems, fn($p) => $p['level'] == 'error')), 'notes' => $s['notes']];
        }
        foreach ($rows as $k => $v) {
            $h .= '<tr data-stat="' . $k . '"><td>' . __('sqs.eqhist.stat_' . $k) . '</td><td class="text-end">' . $v . '</td></tr>';
        }
        $h .= '</table>';
        if (count($eh->period)) {
            $h .= '<p class="text-warning" id="eqh-period">' . self::e(__('sqs.eqhist.period_log', EqHistory::periodParams($eh->period))) . '</p>';
        }

        if (count($eh->missing)) {
            $h .= '<h6>' . __('sqs.eqhist.missing_title') . '</h6><table class="table table-sm w-auto" id="eqh-missing">';
            foreach ($eh->missing as $n => [$sheet, $item]) {
                $h .= '<tr><td>' . self::e($n) . '</td><td>' . self::e($item) . '</td><td>' . self::e($sheet) . '</td></tr>';
            }
            $h .= '</table>';
        }
        if (count($eh->onhand)) {
            $h .= '<h6>' . __('sqs.eqhist.onhand_title') . '</h6><table class="table table-sm w-auto" id="eqh-onhand">';
            foreach ($eh->onhand as $item => $n) {
                $h .= '<tr><td>' . self::e($item) . '</td><td class="text-end">' . $n . '</td></tr>';
            }
            $h .= '</table>';
        }
        if (count($eh->skipped)) {
            $h .= '<h6>' . __('sqs.eqhist.skipped_title') . '</h6><table class="table table-sm w-auto" id="eqh-skipped">';
            foreach ($eh->skipped as $sheet => $items) {
                foreach ($items as $item => $n) {
                    $h .= '<tr><td>' . self::e($sheet) . '</td><td>' . self::e($item) . '</td><td class="text-end">' . $n . '</td></tr>';
                }
            }
            $h .= '</table>';
        }
        if (count($eh->unitOnly)) {
            $h .= '<h6>' . __('sqs.eqhist.unitonly_title') . '</h6><table class="table table-sm w-auto" id="eqh-unitonly">';
            foreach ($eh->unitOnly as $sheet => $n) {
                $h .= '<tr><td>' . self::e($sheet) . '</td><td class="text-end">' . $n . '</td></tr>';
            }
            $h .= '</table>';
        }
        if (count($eh->problems)) {
            $problems = $eh->problems;
            usort($problems, fn($a, $b) => [$a['level'] == 'error' ? 0 : 1, $a['sheet'], $a['row']] <=> [$b['level'] == 'error' ? 0 : 1, $b['sheet'], $b['row']]);
            $h .= '<h6>' . __('sqs.eqhist.problems_title') . '</h6><table class="table table-sm" id="eqh-problems"><tr><th></th><th>'
                . __('sqs.eqhist.col_sheet') . '</th><th>' . __('sqs.eqhist.col_row') . '</th><th>' . __('sqs.eqhist.col_contract') . '</th><th>'
                . __('sqs.eqhist.col_unit') . '</th><th>' . __('sqs.eqhist.col_reason') . '</th></tr>';
            foreach ($problems as $p) {
                $cls = $p['level'] == 'error' ? 'text-danger' : 'text-muted';
                $h .= '<tr class="' . $p['level'] . '"><td class="' . $cls . '">' . __('sqs.eqhist.level_' . $p['level']) . '</td><td>' . self::e($p['sheet']) . '</td><td>'
                    . ($p['row'] ?: '') . '</td><td>' . self::e($p['contract']) . '</td><td>' . self::e($p['unit']) . '</td><td>' . self::e($p['text']) . '</td></tr>';
            }
            $h .= '</table>';
        }
        return $h;
    }
}
