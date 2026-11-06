<?php

class FraudLabsPro
{
    public const VERSION = '1.8.1';

    private const SCREEN_URL = 'https://api.fraudlabspro.com/v2/order/screen';
    private const FEEDBACK_URL = 'https://api.fraudlabspro.com/v2/order/feedback';
    private const RESULT_URL = 'https://api.fraudlabspro.com/v2/order/result';
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT = 15;

    public static function screen(array $fields): ?object
    {
        return self::post(self::SCREEN_URL, $fields);
    }

    public static function feedback(array $fields): ?object
    {
        return self::post(self::FEEDBACK_URL, $fields);
    }

    public static function validateLicense(string $key): ?bool
    {
        $response = self::get(self::RESULT_URL . '?format=json&id=1&key=' . rawurlencode($key));

        if ($response === null) {
            return null;
        }

        if (!isset($response->error)) {
            return true;
        }

        $acceptedCodes = [10006, 10007, 10011];

        return in_array((int) ($response->error->error_code ?? 0), $acceptedCodes, true);
    }

    private static function get(string $url): ?object
    {
        return self::request($url, null);
    }

    private static function post(string $url, array $fields): ?object
    {
        return self::request($url, $fields);
    }

    private static function request(string $url, ?array $fields): ?object
    {
        $userAgent = 'Mozilla/5.0 ZenCart/'
            . (defined('PROJECT_VERSION_MAJOR') ? PROJECT_VERSION_MAJOR : '')
            . '.' . (defined('PROJECT_VERSION_MINOR') ? PROJECT_VERSION_MINOR : '');

        $curl = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT => $userAgent,
        ];

        if ($fields === null) {
            $options[CURLOPT_HTTPGET] = true;
        } else {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($fields);
        }

        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);

        if ($body === false || curl_errno($curl) !== 0) {
            error_log('FraudLabsPro: request failed: ' . curl_error($curl));

            return null;
        }

        $decoded = json_decode($body);

        if (!is_object($decoded)) {
            error_log('FraudLabsPro: invalid JSON response');

            return null;
        }

        return $decoded;
    }

    public static function clientIp(): string
    {
        if (defined('FRAUDLABSPRO_IP_OVERRIDE') && filter_var(FRAUDLABSPRO_IP_OVERRIDE, FILTER_VALIDATE_IP)) {
            return FRAUDLABSPRO_IP_OVERRIDE;
        }

        if (defined('FRAUDLABSPRO_TRUST_PROXY') && FRAUDLABSPRO_TRUST_PROXY === 'true') {
            $headers = [
                'HTTP_CF_CONNECTING_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_REAL_IP',
                'HTTP_X_SUCURI_CLIENTIP',
            ];

            foreach ($headers as $header) {
                if (empty($_SERVER[$header])) {
                    continue;
                }

                foreach (explode(',', $_SERVER[$header]) as $candidate) {
                    $candidate = trim($candidate);

                    if (self::isPublicIp($candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        $remote = $_SERVER['REMOTE_ADDR'] ?? '';

        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
    }

    private static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    public static function flag($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $normalised = strtolower(trim($value));

            if (in_array($normalised, ['n', 'no', 'false', '0'], true)) {
                return 'N';
            }

            if (in_array($normalised, ['y', 'yes', 'true', '1'], true)) {
                return 'Y';
            }
        }

        return $value ? 'Y' : 'N';
    }

    public static function hash(string $value): string
    {
        $hash = 'fraudlabspro_' . $value;

        for ($i = 0; $i < 65536; $i++) {
            $hash = sha1('fraudlabspro_' . $hash);
        }

        return hash('sha256', $hash);
    }

    public static function result($value, string $yes, string $no, string $na): string
    {
        if ($value === null || $value === '') {
            return '<em>- ' . zen_output_string_protected($na) . ' -</em>';
        }

        $value = (string) $value;

        if ($value === 'Y') {
            return $yes;
        }

        if ($value === 'N') {
            return $no;
        }

        return zen_output_string_protected($value);
    }

    public static function countryName(string $isoCode): string
    {
        global $db;

        $code = strtoupper(trim($isoCode));

        if ($code === '') {
            return '';
        }

        $country = $db->Execute(
            'SELECT countries_name FROM ' . TABLE_COUNTRIES
            . " WHERE countries_iso_code_2 = '" . $db->prepare_input($code) . "' LIMIT 1"
        );

        return $country->EOF ? $isoCode : $country->fields['countries_name'];
    }
}
