<?php

namespace App\SQS;

/**
 * SquirrelStock: ошибка базы данных без текста запроса в сообщении.
 * ADOdb кладет в сообщение исключения текст ошибки СУБД вместе с запросом, а программа показывает getMessage()
 * пользователю: index.php при необработанном исключении, страницы — в setError() и toastr, в том числе в ответе AJAX.
 * Этот класс ADOdb создает вместо ADODB_Exception (init.php, $ADODB_EXCEPTION): полный текст сразу пишется в журнал
 * (logs/), а сообщение заменяется общим на языке интерфейса. Поля ADODB_Exception (sql, msg, getCode) не меняются.
 */
class DbException extends \ADODB_Exception
{
    private static $inside = false;

    public function __construct($dbms, $fn, $errno, $errmsg, $p1, $p2, $thisConnection) {
        parent::__construct($dbms, $fn, $errno, $errmsg, $p1, $p2, $thisConnection);
        global $logger;
        if ($logger) {
            $logger->error($this->message);
        }
        $text = 'Database error. Details are in the program log (logs folder).';
        // перевод сам может обратиться к базе (словари модулей): при повторной ошибке — текст без перевода
        if (!self::$inside) {
            self::$inside = true;
            try {
                $text = __('sqs.db_error');
            } catch (\Throwable $e) {
            } finally {
                self::$inside = false;
            }
        }
        $this->message = $text;
    }
}
