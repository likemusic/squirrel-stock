<?php

namespace App\SQS;

/**
 * SquirrelStock: ограничение подбора пароля. Неудачные попытки входа пишутся в sqs_loginfail (db/squirrelstock.sql);
 * после MAX_LOGIN неудач по логину или MAX_IP по адресу за WINDOW минут вход временно не проверяется.
 * Удачный вход очищает попытки этого логина. Счетчик в Zippy жил в сессии и обнулялся с новой сессией.
 */
class LoginGuard
{
    public const WINDOW = 15;    // минут
    public const MAX_LOGIN = 5;
    public const MAX_IP = 20;

    /**
     * Через сколько минут можно снова пробовать; 0 — можно сейчас
     */
    public static function blockedMinutes(string $login, string $ip): int {
        $conn = \ZDB\DB::getConnect();
        $since = $conn->qstr(date('Y-m-d H:i:s', time() - self::WINDOW * 60));
        $wait = 0;
        foreach ([['login', $login, self::MAX_LOGIN], ['ip', $ip, self::MAX_IP]] as [$field, $v, $max]) {
            $rows = $conn->GetCol("SELECT createdon FROM sqs_loginfail WHERE {$field} = " . $conn->qstr($v)
                . " AND createdon > {$since} ORDER BY createdon DESC LIMIT {$max}");
            if (count($rows) >= $max) {
                // блокировка до тех пор, пока самая старая из последних $max попыток не выйдет из окна
                $wait = max($wait, (int)ceil((strtotime(end($rows)) + self::WINDOW * 60 - time()) / 60));
            }
        }
        return max(0, $wait);
    }

    public static function fail(string $login, string $ip): void {
        $conn = \ZDB\DB::getConnect();
        $conn->Execute('DELETE FROM sqs_loginfail WHERE createdon < ' . $conn->qstr(date('Y-m-d H:i:s', time() - 86400)));
        $conn->Execute('INSERT INTO sqs_loginfail (login, ip, createdon) VALUES (' . $conn->qstr(mb_substr($login, 0, 100)) . ', '
            . $conn->qstr($ip) . ', ' . $conn->qstr(date('Y-m-d H:i:s')) . ')');
    }

    public static function success(string $login): void {
        $conn = \ZDB\DB::getConnect();
        $conn->Execute('DELETE FROM sqs_loginfail WHERE login = ' . $conn->qstr($login));
    }
}
