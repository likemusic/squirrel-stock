<?php

namespace App\SQS;

use App\Entity\User;

/**
 * SquirrelStock: двухфакторный вход — после пароля код из приложения-аутентификатора (App\SQS\Totp) или резервный
 * одноразовый код. Включает сам пользователь (страница App\Pages\TwoFactor), сбрасывает администратор там же.
 * Таблицы sqs_2fa (ключ, последний принятый шаг — код не принимается дважды) и sqs_2fa_backup (хеши резервных кодов) —
 * db/squirrelstock.sql. Второй шаг входа — App\Pages\TwoFactorLogin; неверные коды считает App\SQS\LoginGuard.
 * Двухфакторный вход OTP автора Zippy (код по почте, SMS, Telegram в профиле) — отдельный и здесь не используется.
 */
class TwoFactor
{
    public const BACKUP_COUNT = 10;
    public const PENDING_TTL = 300;   // секунд на ввод кода после пароля
    private const SESSION = 'sqs_2fa_pending';

    public static function enabled(int $user_id): bool {
        $conn = \ZDB\DB::getConnect();
        return intval($conn->GetOne("SELECT enabled FROM sqs_2fa WHERE user_id = {$user_id}")) == 1;
    }

    /**
     * Включить: ключ подтвержден кодом из приложения. Возвращает резервные коды (показать один раз) или null — код неверный.
     */
    public static function enable(int $user_id, string $secret, string $code): ?array {
        $step = Totp::matchStep($secret, $code);
        if ($step === null) {
            return null;
        }
        $conn = \ZDB\DB::getConnect();
        $conn->Execute("DELETE FROM sqs_2fa WHERE user_id = {$user_id}");
        $conn->Execute("INSERT INTO sqs_2fa (user_id, secret, enabled, last_step, updated) VALUES ({$user_id}, " . $conn->qstr($secret)
            . ", 1, {$step}, " . $conn->qstr(date('Y-m-d H:i:s')) . ")");
        Remember::forgetUser($user_id);   // входы «Запомнить», выданные до включения, больше не действуют
        return self::newBackupCodes($user_id);
    }

    public static function disable(int $user_id): void {
        $conn = \ZDB\DB::getConnect();
        $conn->Execute("DELETE FROM sqs_2fa WHERE user_id = {$user_id}");
        $conn->Execute("DELETE FROM sqs_2fa_backup WHERE user_id = {$user_id}");
    }

    // новые резервные коды взамен старых: XXXXX-XXXXX, в базе — только хеши
    public static function newBackupCodes(int $user_id): array {
        $conn = \ZDB\DB::getConnect();
        $conn->Execute("DELETE FROM sqs_2fa_backup WHERE user_id = {$user_id}");
        $codes = [];
        for ($i = 0; $i < self::BACKUP_COUNT; $i++) {
            $raw = substr(Totp::base32Encode(random_bytes(7)), 0, 10);
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
            $conn->Execute("INSERT INTO sqs_2fa_backup (user_id, code_hash) VALUES ({$user_id}, " . $conn->qstr(password_hash($raw, PASSWORD_DEFAULT)) . ")");
        }
        return $codes;
    }

    public static function backupLeft(int $user_id): int {
        $conn = \ZDB\DB::getConnect();
        return intval($conn->GetOne("SELECT COUNT(*) FROM sqs_2fa_backup WHERE user_id = {$user_id} AND used = 0"));
    }

    /**
     * Проверить код из приложения или резервный код; принятый код второй раз не принимается
     */
    public static function verify(int $user_id, string $code): bool {
        $conn = \ZDB\DB::getConnect();
        $row = $conn->GetRow("SELECT secret, last_step FROM sqs_2fa WHERE user_id = {$user_id} AND enabled = 1");
        if (!$row) {
            return false;
        }
        $step = Totp::matchStep($row['secret'], $code);
        if ($step !== null) {
            if ($step <= intval($row['last_step'])) {
                return false;
            }
            $conn->Execute("UPDATE sqs_2fa SET last_step = {$step} WHERE user_id = {$user_id}");
            return true;
        }
        $raw = strtoupper(preg_replace('/[\s-]/', '', $code));
        if (!preg_match('/^[A-Z2-7]{10}$/', $raw)) {
            return false;
        }
        foreach ($conn->Execute("SELECT id, code_hash FROM sqs_2fa_backup WHERE user_id = {$user_id} AND used = 0") as $b) {
            if (password_verify($raw, $b['code_hash'])) {
                $conn->Execute("UPDATE sqs_2fa_backup SET used = 1 WHERE id = " . intval($b['id']));
                return true;
            }
        }
        return false;
    }

    // после верного пароля: пользователь ждет второго шага
    public static function begin(User $user, bool $remember): void {
        $_SESSION[self::SESSION] = ['user_id' => intval($user->user_id), 'login' => $user->userlogin, 'remember' => $remember, 'time' => time()];
    }

    public static function pending(): ?array {
        $p = $_SESSION[self::SESSION] ?? null;
        if (!is_array($p) || time() - $p['time'] > self::PENDING_TTL) {
            return null;
        }
        return $p;
    }

    public static function clearPending(): void {
        unset($_SESSION[self::SESSION]);
    }

    /**
     * Завершить вход после второго шага — то же, что UserLogin::successLogin()
     */
    public static function completeLogin(User $user, bool $remember): void {
        self::clearPending();
        \App\Session::getSession()->clean();
        $user->lastactive = time();
        $user->save();
        \App\System::setUser($user);
        \App\Helper::log('Login: ' . $user->username . ' (2FA)');
        if ($remember) {
            Remember::issue($user);
        } else {
            Remember::forget();
        }
        if (($_COOKIE['branch_id'] ?? 0) > 0) {
            \App\System::getSession()->defbranch = $_COOKIE['branch_id'];
        }
        if ($user->rolename == 'admins') {
            \App\System::checkUpdate();
            \App\Helper::cleanDB();
        }
        \App\Application::RedirectHome();
    }
}
