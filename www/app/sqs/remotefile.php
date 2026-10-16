<?php

namespace App\SQS;

/**
 * SquirrelStock: скачивание файла по адресу из данных пользователя (колонка «картинка» импорта позиций).
 * Раньше адрес шел в file_get_contents() как есть: из ячейки Excel читался локальный файл сервера (путь, file://)
 * и делался запрос к любому адресу, в том числе внутри сети. Теперь:
 *  - только http и https, без имени и пароля в адресе;
 *  - имя хоста разрешается один раз (только IPv4), все его адреса должны быть публичными, и curl идет на проверенный
 *    адрес (CURLOPT_RESOLVE) — второго разрешения имени нет, подмена ответа DNS между проверкой и запросом не поможет;
 *  - без перенаправлений и прокси, таймаут, предел размера.
 * Ошибка — исключение с причиной (для отчета импорта).
 */
class RemoteFile
{
    public const MAX_BYTES = 1048576;
    public const TIMEOUT = 10;

    // не публичные адреса IPv4, которые filter_var с FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE пропускает
    private const DENY = ['100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'];

    public static function publicIp(string $ip): bool {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        $n = ip2long($ip);
        foreach (self::DENY as $cidr) {
            [$net, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int)$bits);
            if (($n & $mask) == (ip2long($net) & $mask)) {
                return false;
            }
        }
        return true;
    }

    public static function fetch(string $url, int $maxBytes = self::MAX_BYTES): string {
        $url = trim($url);
        $u = parse_url($url);
        $scheme = strtolower((string)($u['scheme'] ?? ''));
        if (!is_array($u) || !in_array($scheme, ['http', 'https'], true) || empty($u['host']) || isset($u['user']) || isset($u['pass'])) {
            throw new \Exception(__('sqs.remote.bad_scheme'));
        }
        $host = strtolower($u['host']);
        $port = (int)($u['port'] ?? ($scheme == 'https' ? 443 : 80));
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
            $ips = [trim($host, '[]')];
        } else {
            $ips = @gethostbynamel($host) ?: [];
            if (!$ips) {
                throw new \Exception(__('sqs.remote.not_found', ['host' => $host]));
            }
        }
        foreach ($ips as $ip) {
            if (!self::publicIp($ip)) {
                throw new \Exception(__('sqs.remote.not_public', ['ip' => $ip]));
            }
        }
        $data = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ips[0]],
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_MAXFILESIZE => $maxBytes,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$data, $maxBytes) {
                $data .= $chunk;
                return strlen($data) > $maxBytes ? 0 : strlen($chunk);   // 0 — прервать: больше предела
            },
        ]);
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        if (strlen($data) > $maxBytes) {
            throw new \Exception(__('sqs.remote.too_big', ['mb' => round($maxBytes / 1048576, 1)]));
        }
        if ($ok === false) {
            throw new \Exception(__('sqs.remote.failed', ['error' => $err]));
        }
        if ($code != 200) {
            throw new \Exception(__('sqs.remote.status', ['code' => $code]));
        }
        return $data;
    }
}
