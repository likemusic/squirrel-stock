<?php

namespace App;

/**
 * Класс  для  рендеринга  печатных  форм
 */
class Report
{
    private $_template;

    /**
     * Путь к  файлу  шаблона
     *
     * @param mixed $template
     */
    public function __construct($template) {
        $this->_template = $template;
    }

    /**
     * Генерация  простой формы
     *
     * @param array $header Массив  с даннымы  шапки
     * @param mixed $removeendline  убирать перевод  строки
     */
    public function generate(array $header,$removeendline=true) {
        gc_enable();
        gc_collect_cycles();
        
        $dir = 'templates';

        // SquirrelStock: печатная форма модуля из его папки (App\SQS\Modules); в профиле благотворительной организации
        // сначала форма профиля printforms/charity/<шаблон> — модуля или основная (App\SQS\Profile)
        $fp = null;
        if (\App\SQS\Profile::charity()) {
            $charity = 'charity/' . $this->_template;
            $fp = \App\SQS\Modules::printform($charity) ?? (is_file(_ROOT . $dir . '/printforms/' . $charity) ? _ROOT . $dir . '/printforms/' . $charity : null);
        }
        $fp = $fp ?? \App\SQS\Modules::printform($this->_template) ?? (_ROOT . $dir . '/printforms/' . $this->_template);
        $fp_c = str_replace(".tpl", "_custom.tpl", $fp) ;    //кастомный  шаблон
        if(is_file($fp_c)) {
            $template = @file_get_contents($fp_c);
        } else {
            $template = @file_get_contents($fp);
        }


        if (strlen($template) == 0) {
            return __('app.report.fail_formy_druku') . $this->_template . __('app.report.ne_znaidenyi');
        }
        $header['t'] = $header['t'] ?? new \App\Translator();   // перевод в печатных формах: {{#t}}ключ{{/t}}
        $m = new \Mustache_Engine();
        $html = $m->render($template, $header);
        if($removeendline) {
          $html = str_replace("\n", "", $html);
          $html = str_replace("\r", "", $html);
        }
        return $html;
    }

}
