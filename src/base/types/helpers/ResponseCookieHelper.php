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

    public static function renderSetCookieHeaderValue(array $payload): string
    {
        $name = (string)($payload['name'] ?? '');
        $value = (string)($payload['value'] ?? '');
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];

        $parts = [$name . '=' . rawurlencode($value)];
        if (!empty($options['expires'])) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s \\G\\M\\T', (int)$options['expires']);
        }
        $parts[] = 'Path=' . (string)($options['path'] ?? '/');
        if (!empty($options['domain'])) {
            $parts[] = 'Domain=' . (string)$options['domain'];
        }
        if (!empty($options['secure'])) {
            $parts[] = 'Secure';
        }
        if (!empty($options['httponly'])) {
            $parts[] = 'HttpOnly';
        }
        $sameSite = (string)($options['samesite'] ?? 'Lax');
        if ($sameSite !== '') {
            $parts[] = 'SameSite=' . $sameSite;
        }

        return implode('; ', $parts);
    }

    /**
     * @return array{name:string,value:string,expires:int,path:string,domain:string,secure:bool,httponly:bool,samesite:string,max_age:int}
     */
    public static function parseSetCookieHeaderValue(string $line): ?array
    {
        $parts = array_map('trim', explode(';', $line));
        if ($parts === [] || !str_contains((string)$parts[0], '=')) {
            return null;
        }
        [$name, $value] = explode('=', (string)$parts[0], 2);
        $cookie = [
            'name' => trim($name),
            'value' => rawurldecode(trim($value)),
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
            'samesite' => 'Lax',
            'max_age' => 0,
        ];
        if ($cookie['name'] === '') {
            return null;
        }
        for ($index = 1, $count = count($parts); $index < $count; $index++) {
            $fragment = (string)$parts[$index];
            if ($fragment === '') {
                continue;
            }
            if (!str_contains($fragment, '=')) {
                $flag = strtolower($fragment);
                if ($flag === 'secure') {
                    $cookie['secure'] = true;
                    continue;
                }
                if ($flag === 'httponly') {
                    $cookie['httponly'] = true;
                }
                continue;
            }
            [$key, $value] = explode('=', $fragment, 2);
            $key = strtolower(trim($key));
            $value = trim($value);
            if ($key === 'expires') {
                $parsed = strtotime($value);
                $cookie['expires'] = $parsed === false ? 0 : $parsed;
                continue;
            }
            if ($key === 'max-age') {
                $cookie['max_age'] = max(0, (int)$value);
                continue;
            }
            if ($key === 'path') {
                $cookie['path'] = $value !== '' ? $value : '/';
                continue;
            }
            if ($key === 'domain') {
                $cookie['domain'] = $value;
                continue;
            }
            if ($key === 'samesite') {
                $cookie['samesite'] = self::normalizeSameSite($value);
            }
        }
        return $cookie;
    }

    public static function buildSessionCookieHeaderValue(string $sessionName, string $sessionId, array $params): string
    {
        $lifetime = max(0, (int)($params['lifetime'] ?? 0));
        $payload = [
            'name' => $sessionName,
            'value' => $sessionId,
            'options' => [
                'expires' => $lifetime > 0 ? time() + $lifetime : 0,
                'path' => (string)($params['path'] ?? '/'),
                'domain' => (string)($params['domain'] ?? ''),
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? false),
                'samesite' => self::normalizeSameSite((string)($params['samesite'] ?? 'Lax')),
            ],
        ];

        $line = self::renderSetCookieHeaderValue($payload);
        if ($lifetime > 0) {
            $line .= '; Max-Age=' . $lifetime;
        }
        return $line;
    }
}
