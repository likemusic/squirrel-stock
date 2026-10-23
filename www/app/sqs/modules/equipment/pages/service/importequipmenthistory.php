<?php

namespace App\Pages\Service;

use App\Helper as H;
use App\SQS\EqFix;
use App\SQS\EqHistory;
use App\SQS\EqItemMap;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\File;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextArea;
use Zippy\Html\Label;

/**
 * SquirrelStock: импорт истории выдачи оборудования из Google-таблиц (таблицы переписи и выдачи, .xlsx), разбор — App\SQS\EqHistory.
 * «Проверить» только разбирает и показывает отчет: что будет создано, ошибки (строка не импортируется) и заметки
 * (импортируется с допущением). «Записать» доступна после проверки и пишет разобранное проверкой (если соответствия
 * с тех пор поменяли — разбирает заново) частями по ~20 секунд: после каждой части страница сама продолжает запись
 * (на хостинге запрос обрывается через 30–60 с). После обрыва — «Проверить» и «Записать» снова: записанное не дублируется.
 * Соответствия «написание → позиция» для мелочи без номеров (App\SQS\EqItemMap) правятся тут же и сохраняются
 * при проверке и записи; написания, которых в них нет, — в отчете с кнопкой «добавить в соответствия». Так же —
 * список исправлений строк таблиц (App\SQS\EqFix: ФИО, номер договора, задвоенные строки).
 */
class ImportEquipmentHistory extends \App\Pages\Base
{
    public array $_files = [];   // [имя файла => путь к копии во временной папке]
    public string $_state = '';  // разбор проверки и ход записи (App\SQS\EqHistory) — файл во временной папке: в таблицах ПДн
    public string $_mapText = ''; // соответствия и исправления, с которыми сделан разбор
    public bool $_lift = false;
    public int $_store = 0;

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
        $form->add(new TextArea('itemmap', EqItemMap::toText(EqItemMap::all())));
        $form->add(new TextArea('fixes', EqFix::text()));
        $form->add(new SubmitButton('check'))->onClick($this, 'onCheck');
        $form->add(new SubmitButton('write'))->onClick($this, 'onWrite');
        $form->write->setVisible(false);
        $form->add(new SubmitButton('next'))->onClick($this, 'onNext');   // следующая часть записи (нажимается скриптом)
        $form->next->setVisible(false);
        $this->add(new Label('progress', '', true));
        $this->add(new Label('report', '', true));
    }

    public function onCheck($sender) {
        $this->dropFiles();
        // копии файлов от проверок, после которых не было записи (в таблицах персональные данные): старше суток — удалить;
        // то же при каждом входе (App\SQS\LoginGuard::purge), если импортом больше не пользуются
        \App\SQS\TempFiles::purge();
        foreach (['census', 'journal'] as $f) {
            $file = $this->iform->{$f}->getFile();
            if (strlen($file['tmp_name'] ?? '') == 0) {
                continue;
            }
            $copy = \App\SQS\TempFiles::file('sqs-eqh-');
            move_uploaded_file($file['tmp_name'], $copy) || copy($file['tmp_name'], $copy);
            $this->_files[basename($file['name'])] = $copy;
        }
        if (count($this->_files) == 0) {
            $this->setError(__('common.ne_obrano_fail'));
            return;
        }
        if (!$this->saveMap()) {
            return;
        }
        $eh = $this->analyze();
        if ($eh) {
            $this->saveState($eh);
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
        if (!$this->saveMap()) {
            return;
        }
        // разбор проверки, если соответствия с тех пор не меняли; иначе — заново
        $eh = $this->_mapText === $this->settingsText() ? $this->loadState() : null;
        $eh = $eh ?? $this->analyze();
        if (!$eh) {
            return;
        }
        $this->_store = $store;
        $this->_lift = $this->iform->liftperiod->isChecked();
        $this->writePart($eh);
    }

    public function onNext($sender) {
        $eh = $this->loadState();
        if (!$eh) {
            $this->iform->next->setVisible(false);
            $this->progress->setText('');
            $this->setError(__('sqs.eqhist.state_lost'));
            return;
        }
        $this->writePart($eh);
    }

    // часть записи; если осталось — на странице ход и скрипт, который нажимает «дальше»
    private function writePart(EqHistory $eh): void {
        $limit = \App\Helper::getKeyValInt('sqs_eqh_chunk');   // событий за часть (0 — без ограничения, только по времени); для тестов
        try {
            $res = $eh->apply($this->_store, $this->_lift, 20, $limit);
        } catch (\Throwable $e) {
            $this->iform->next->setVisible(false);
            $this->progress->setText('');
            $this->setError($e->getMessage());
            return;
        }
        $this->iform->write->setVisible(false);
        if ($res['left'] > 0) {
            $this->saveState($eh);
            $this->iform->next->setVisible(true);
            $this->progress->setText('<div class="alert alert-info" id="eqh-progress">' . self::e(__('sqs.eqhist.progress', ['done' => $res['total'] - $res['left'], 'total' => $res['total']]))
                . '</div><script>setTimeout(function () { document.getElementById("next").click(); }, 200);</script>', true);
            $this->report->setText('');
            return;
        }
        $this->iform->next->setVisible(false);
        $this->progress->setText('');
        $this->report->setText($this->reportHtml($eh, $res), true);
        $this->dropFiles();
        $this->setSuccess(__('sqs.eqhist.written', $res));
    }

    private function saveState(EqHistory $eh): void {
        if ($this->_state === '') {
            $this->_state = \App\SQS\TempFiles::file('sqs-eqh-');
        }
        file_put_contents($this->_state, serialize($eh));
        $this->_mapText = $this->settingsText();
    }

    private function settingsText(): string {
        return $this->iform->itemmap->getText() . "\n=====\n" . $this->iform->fixes->getText();
    }

    private function loadState(): ?EqHistory {
        if ($this->_state === '' || !is_file($this->_state)) {
            return null;
        }
        $eh = @unserialize(file_get_contents($this->_state), ['allowed_classes' => [EqHistory::class]]);
        return $eh instanceof EqHistory ? $eh : null;
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

    // соответствия и исправления из полей: с ошибкой в строке — не сохранять и не разбирать файлы
    private function saveMap(): bool {
        $p = EqItemMap::parse($this->iform->itemmap->getText());
        if (count($p['errors'])) {
            $this->setError(__('sqs.eqhist.map_bad_lines', ['lines' => implode(', ', array_keys($p['errors']))]));
            return false;
        }
        $f = EqFix::parse($this->iform->fixes->getText());
        if (count($f['errors'])) {
            $this->setError(__('sqs.eqhist.fix_bad_lines', ['lines' => implode(', ', array_keys($f['errors']))]));
            return false;
        }
        EqItemMap::save($p['map']);
        $this->iform->itemmap->setText(EqItemMap::toText($p['map']));
        EqFix::save($this->iform->fixes->getText());
        return true;
    }

    private function dropFiles(): void {
        foreach ($this->_files as $path) {
            @unlink($path);
        }
        $this->_files = [];
        if ($this->_state !== '') {
            @unlink($this->_state);
            $this->_state = '';
        }
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
                 'done' => $s['done'], 'noinv' => $s['noinv'], 'consumable' => $s['consumable'], 'fixed' => $s['fixed'], 'errors' => $s['errors'], 'notes' => $s['notes']];
        if ($res !== null) {
            $rows = ['income' => $res['income'], 'issue' => $res['issue'], 'return' => $res['return'], 'writeoff' => $res['writeoff'], 'failed' => $res['failed'], 'skipped' => $res['skipped'],
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
        if (count($eh->unmapped)) {
            // кнопка дописывает в поле соответствий строки «написание = написание» — остается поправить позицию справа
            $lines = implode("\n", array_map(fn($w) => $w . ' = ' . $w, array_keys($eh->unmapped)));
            $h .= '<h6>' . __('sqs.eqhist.unmapped_title') . '</h6><table class="table table-sm w-auto" id="eqh-unmapped">';
            foreach ($eh->unmapped as $w => $n) {
                $h .= '<tr><td>' . self::e($w) . '</td><td class="text-end">' . $n . '</td></tr>';
            }
            $h .= '</table><button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="eqh-unmapped-add" data-lines="' . self::e($lines) . '"'
                . ' onclick="var t=document.getElementById(\'itemmap\');t.value=(t.value.trim()?t.value.replace(/\\s+$/,\'\')+\'\\n\':\'\')+this.dataset.lines;t.focus();">'
                . __('sqs.eqhist.unmapped_add') . '</button>';
        }
        if (count($eh->noInv)) {
            // кровати, концентраторы без номера: кому, что, когда, договор — номер вписать в таблицу и проверить снова
            $h .= '<h6>' . __('sqs.eqhist.noinv_title') . '</h6><table class="table table-sm w-auto" id="eqh-noinv"><tr><th>'
                . __('sqs.eqhist.col_sheet') . '</th><th>' . __('sqs.eqhist.col_row') . '</th><th>' . __('sqs.eqhist.col_contract') . '</th><th>'
                . __('sqs.eqhist.col_date') . '</th><th>' . __('sqs.eqhist.col_recipient') . '</th><th>' . __('sqs.eqhist.col_written') . '</th><th>'
                . __('sqs.eqhist.col_item') . '</th></tr>';
            foreach ($eh->noInv as $r) {
                $h .= '<tr><td>' . self::e($r['sheet']) . '</td><td>' . $r['row'] . '</td><td>' . self::e($r['contract']) . '</td><td>' . date('d.m.Y', $r['date'])
                    . '</td><td>' . self::e($r['name']) . '</td><td>' . self::e($r['written']) . '</td><td>' . self::e($r['item']) . '</td></tr>';
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
        if (count($eh->consumables)) {
            $h .= '<h6>' . __('sqs.eqhist.consumable_title') . '</h6><table class="table table-sm w-auto" id="eqh-consumable">';
            foreach ($eh->consumables as $w => $n) {
                $h .= '<tr><td>' . self::e($w) . '</td><td class="text-end">' . $n . '</td></tr>';
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
