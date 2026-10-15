<?php

namespace App\SQS;

/**
 * SquirrelStock: вход по cookie «Запомнить» — случайный токен, а не значение, вычисляемое из данных пользователя.
 * Cookie: «селектор.значение» (случайные). В таблице sqs_remember — селектор, хеш значения, срок и отпечаток хеша пароля:
 * токен перестает действовать при смене пароля, при выходе и по истечении срока. Таблица — db/squirrelstock.sql.
 */
class Remember
{
    public const COOKIE = 'remember';
    public const DAYS = 14;

    /**
     * Выдать токен пользователю и поставить cookie
     */
    public static function issue(\App\Entity\User $user): void {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + self::DAYS * 86400;
        $conn = \ZDB\DB::getConnect();
        $conn->Execute('DELETE FROM sqs_remember WHERE expires < ' . $conn->qstr(date('Y-m-d H:i:s')));
        $conn->Execute('INSERT INTO sqs_remember (selector, user_id, token_hash, pass_fp, expires) VALUES ('
            . $conn->qstr($selector) . ', ' . intval($user->user_id) . ', ' . $conn->qstr(hash('sha256', $validator)) . ', '
            . $conn->qstr(self::passFingerprint($user)) . ', ' . $conn->qstr(date('Y-m-d H:i:s', $expires)) . ')');
        self::setCookie($selector . '.' . $validator, $expires);
    }

    /**
     * Пользователь по cookie или null; недействительная cookie удаляется
     */
    public static function check(string $cookie): ?\App\Entity\User {
        [$selector, $validator] = array_pad(explode('.', $cookie, 2), 2, '');
        if (!preg_match('/^[0-9a-f]{24}$/', $selector) || !preg_match('/^[0-9a-f]{64}$/', $validator)) {
            self::setCookie('', 1);
            return null;
        }
        $conn = \ZDB\DB::getConnect();
        $row = $conn->GetRow('SELECT user_id, token_hash, pass_fp, expires FROM sqs_remember WHERE selector = ' . $conn->qstr($selector));
        $user = $row ? \App\Entity\User::load(intval($row['user_id'])) : null;
        $valid = $row && $user && $user->disabled != 1 && strtotime($row['expires']) > time()
            && hash_equals($row['token_hash'], hash('sha256', $validator))
            && hash_equals($row['pass_fp'], self::passFingerprint($user));
        if (!$valid) {
            if ($row) {
                $conn->Execute('DELETE FROM sqs_remember WHERE selector = ' . $conn->qstr($selector));
            }
            self::setCookie('', 1);
            return null;
        }
        return $user;
    }

    /**
     * Выход: токен из cookie больше не действует
     */
    public static function forget(): void {
        $selector = explode('.', (string)($_COOKIE[self::COOKIE] ?? ''), 2)[0];
        if (preg_match('/^[0-9a-f]{24}$/', $selector)) {
            $conn = \ZDB\DB::getConnect();
            $conn->Execute('DELETE FROM sqs_remember WHERE selector = ' . $conn->qstr($selector));
        }
        self::setCookie('', 1);
    }

    // все входы «Запомнить» пользователя (например, после включения двухфакторного входа)
    public static function forgetUser(int $user_id): void {
        \ZDB\DB::getConnect()->Execute('DELETE FROM sqs_remember WHERE user_id = ' . $user_id);
    }

    // смена пароля меняет хеш в users.userpass — отпечаток перестает совпадать
    private static function passFingerprint(\App\Entity\User $user): string {
        return hash('sha256', 'sqs-remember|' . $user->user_id . '|' . $user->userpass);
    }

    private static function setCookie(string $value, int $expires): void {
        $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') == 'https';
        setcookie(self::COOKIE, $value, ['expires' => $expires, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    }
}
