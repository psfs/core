<?php

namespace PSFS\base\types\helpers;

class ResponseCookieHelper
{
    public static function buildCookiePayload(array $cookie, bool $isSecureRequest, ?string $defaultDomain): ?array
    {
        if (!array_key_exists('name', $cookie) || !array_key_exists('value', $cookie)) {
            return null;
        }

        $httpOnly = array_key_exists('httpOnly', $cookie)
            ? (bool)$cookie['httpOnly']
            : ((array_key_exists('http', $cookie)) ? (bool)$cookie['http'] : true);
        $secure = array_key_exists('secure', $cookie)
            ? (bool)$cookie['secure']
            : $isSecureRequest;
        $sameSite = self::normalizeSameSite((string)($cookie['sameSite'] ?? $cookie['samesite'] ?? 'Lax'));
        $cookieDomain = self::normalizeCookieDomain((string)($cookie['domain'] ?? $defaultDomain));

        if ($sameSite === 'None' && $secure === false) {
            $secure = true;
        }

        $options = [
            'expires' => array_key_exists('expire', $cookie) ? (int)$cookie['expire'] : 0,
            'path' => (string)($cookie['path'] ?? '/'),
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];
        if (!empty($cookieDomain)) {
            $options['domain'] = $cookieDomain;
        }

        return [
            'name' => (string)$cookie['name'],
            'value' => (string)$cookie['value'],
            'options' => $options,
        ];
    }

    public static function normalizeCookieDomain(?string $domain): ?string
    {
        if (empty($domain)) {
            return null;
        }

        $domain = trim($domain);
        if ($domain === '') {
            return null;
        }

        if (str_contains($domain, '://')) {
            $parsed = parse_url($domain);
            if (!is_array($parsed) || empty($parsed['host'])) {
                return null;
            }
            $domain = (string)$parsed['host'];
        }

        if (str_contains($domain, ':')) {
            [$domain] = explode(':', $domain, 2);
        }

        $domain = strtolower(trim($domain));
        if ($domain === '' || $domain === 'localhost' || filter_var($domain, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $domain;
    }

    public static function normalizeSameSite(?string $sameSite): string
    {
        $value = strtolower(trim((string)$sameSite));
        if ($value === 'strict') {
            return 'Strict';
        }
        if ($value === 'none') {
            return 'None';
        }
        return 'Lax';
    }
}

