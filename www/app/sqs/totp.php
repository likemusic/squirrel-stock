<?php

namespace App\SQS;

/**
 * SquirrelStock: одноразовые коды по времени (TOTP, RFC 6238; HMAC-SHA1, 6 цифр, шаг 30 секунд) — как в приложениях
 * Google Authenticator, Aegis, FreeOTP. Ключ — 20 случайных байт в base32 (RFC 4648, без '=').
 */
class Totp
{
    public const STEP = 30;
    public const DIGITS = 6;
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string {
        return self::base32Encode(random_bytes(20));
    }

    // код для шага времени (номер 30-секундного интервала с начала эпохи Unix)
    public static function code(string $secret, int $step): string {
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $bin = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string)($bin % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Шаг, которому соответствует код (с допуском ±$window шагов на расхождение часов), или null
     */
    public static function matchStep(string $secret, string $code, int $window = 1, ?int $time = null): ?int {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return null;
        }
        $now = intdiv($time ?? time(), self::STEP);
        for ($s = $now - $window; $s <= $now + $window; $s++) {
            if (hash_equals(self::code($secret, $s), $code)) {
                return $s;
            }
        }
        return null;
    }

    // ссылка otpauth:// для QR-кода
    public static function uri(string $secret, string $account, string $issuer): string {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer)
            . '&digits=' . self::DIGITS . '&period=' . self::STEP;
    }

    public static function base32Encode(string $bin): string {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::B32[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string {
        $b32 = strtoupper(preg_replace('/[\s=]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $c) {
            $v = strpos(self::B32, $c);
            if ($v === false) {
                throw new \InvalidArgumentException('Invalid base32');
            }
            $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) == 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
