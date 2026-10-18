<?php

namespace App\SQS;

/**
 * SquirrelStock: ошибка базы данных без текста запроса в сообщении.
 * ADOdb кладет в сообщение исключения текст ошибки СУБД вместе с запросом, а программа показывает getMessage()
 * пользователю: index.php при необработанном исключении, страницы — в setError() и toastr, в том числе в ответе AJAX.
 * Этот класс ADOdb создает вместо ADODB_Exception (init.php, $ADODB_EXCEPTION): полный текст сразу пишется в журнал
 * (logs/), а сообщение заменяется общим на языке интерфейса. Поля ADODB_Exception (sql, msg, getCode) не меняются.
 * В журнале значения скрыты (mask): в запросах ФИО, телефоны, адреса подопечных и контрагентов, примечания документов.
 */
class DbException extends \ADODB_Exception
{
    private static $inside = false;

    public function __construct($dbms, $fn, $errno, $errmsg, $p1, $p2, $thisConnection) {
        parent::__construct($dbms, $fn, $errno, $errmsg, $p1, $p2, $thisConnection);
        global $logger;
        if ($logger) {
            $logger->error(self::mask($this->message));
        }
        $text = 'Database error. Details are in the program log (logs folder).';
        // перевод сам может обратиться к базе (словари модулей): при повторной ошибке — текст без перевода
        if (!self::$inside) {
            self::$inside = true;
            try {
                // нет права у пользователя базы (хостинг, права урезаны вручную): какой команды — понятно и без журнала
                $text = in_array((int)$errno, [1044, 1142, 1143], true) && preg_match('/^([A-Z][A-Z ]*?) command denied/', (string)$errmsg, $m)
                    ? __('sqs.db_rights', ['command' => $m[1]]) : __('sqs.db_error');
            } catch (\Throwable $e) {
            } finally {
                self::$inside = false;
            }
        }
        $this->message = $text;
    }

    /**
     * Текст для журнала: строковые значения в запросе — '…(длина)', кроме дат; в тексте ошибки СУБД — значение после
     * «Duplicate entry», «value:» и подобных. Имена таблиц и полей, числа и форма запроса остаются — по ним ищется ошибка.
     */
    public static function mask(string $message): string {
        $literal = "'((?:[^'\\\\]|\\\\.|'')*)'";
        $hide = fn($m) => preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $m[1]) ? $m[0] : "'…(" . mb_strlen($m[1]) . ")'";
        $at = strpos($message, '] in EXECUTE("');
        $head = $at === false ? $message : substr($message, 0, $at);
        $sql = $at === false ? '' : substr($message, $at);
        $head = preg_replace_callback('/(?<=entry |value: |value |string: |for )' . $literal . '/u', $hide, $head) ?? $head;
        $sql = preg_replace_callback('/' . $literal . '/su', $hide, $sql) ?? '] in EXECUTE(…)';
        return $head . $sql;
    }
}
