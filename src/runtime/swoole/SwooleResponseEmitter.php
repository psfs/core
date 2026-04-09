<?php

namespace PSFS\runtime\swoole;

use PSFS\base\types\helpers\ResponseHeaderHelper;

class SwooleResponseEmitter
{
    public function emit(object $response, int $statusCode, array $headers, string $body): void
    {
        if (method_exists($response, 'status')) {
            $response->status($statusCode);
        }

        foreach ($headers as $normalizedKey => $value) {
            if ($normalizedKey === ResponseHeaderHelper::normalizeHeaderKey(ResponseHeaderHelper::HTTP_STATUS_HEADER)) {
                continue;
            }
            $values = is_array($value) ? $value : [(string)$value];
            if ($normalizedKey === 'set-cookie') {
                $this->emitCookies($response, $values);
                continue;
            }
            $headerName = $this->normalizeOutputHeaderName((string)$normalizedKey);
            if ($headerName === '') {
                continue;
            }
            $headerValue = implode(', ', array_map(static fn($item) => (string)$item, $values));
            if (method_exists($response, 'header')) {
                $response->header($headerName, $headerValue);
            }
        }

        if (method_exists($response, 'end')) {
            $response->end($body);
        }
    }

    public function mergeHeaders(array $responseHelperHeaders, array $nativeHeaderLines): array
    {
        $merged = $responseHelperHeaders;
        foreach ($nativeHeaderLines as $line) {
            if (!is_string($line) || trim($line) === '') {
                continue;
            }
            $parsed = ResponseHeaderHelper::parseHeader($line);
            $key = $parsed['normalized_key'];
            $value = $parsed['value'];
            if (ResponseHeaderHelper::allowsMultipleValues($key)) {
                $existing = $merged[$key] ?? [];
                if (!is_array($existing)) {
                    $existing = [$existing];
                }
                if (!in_array($value, $existing, true)) {
                    $existing[] = $value;
                }
                $merged[$key] = $existing;
                continue;
            }
            if (!array_key_exists($key, $merged)) {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }

    public function ensureSessionCookieHeader(array &$headers): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $sessionId = session_id();
        if ($sessionId === '') {
            return;
        }
        $sessionName = session_name();
        if ($sessionName === '') {
            return;
        }

        $cookieKey = ResponseHeaderHelper::normalizeHeaderKey('set-cookie');
        $existing = $headers[$cookieKey] ?? [];
        if (!is_array($existing)) {
            $existing = [$existing];
        }
        foreach ($existing as $line) {
            if (is_string($line) && str_starts_with($line, $sessionName . '=')) {
                return;
            }
        }

        $params = session_get_cookie_params();
        $lifetime = (int)($params['lifetime'] ?? 0);
        $line = $sessionName . '=' . rawurlencode($sessionId);
        if ($lifetime > 0) {
            $line .= '; Expires=' . gmdate('D, d M Y H:i:s T', time() + $lifetime);
            $line .= '; Max-Age=' . $lifetime;
        }

        $path = (string)($params['path'] ?? '/');
        $line .= '; Path=' . ($path === '' ? '/' : $path);
        $domain = (string)($params['domain'] ?? '');
        if ($domain !== '') {
            $line .= '; Domain=' . $domain;
        }
        if (!empty($params['secure'])) {
            $line .= '; Secure';
        }
        if (!empty($params['httponly'])) {
            $line .= '; HttpOnly';
        }
        $sameSite = (string)($params['samesite'] ?? '');
        if ($sameSite !== '') {
            $line .= '; SameSite=' . $sameSite;
        }

        $existing[] = $line;
        $headers[$cookieKey] = $existing;
    }

    public function resolveStatusCode(array $headers, int $default): int
    {
        $statusKey = ResponseHeaderHelper::normalizeHeaderKey(ResponseHeaderHelper::HTTP_STATUS_HEADER);
        $statusLine = (string)($headers[$statusKey] ?? '');
        if ($statusLine !== '' && preg_match('/\b(\d{3})\b/', $statusLine, $matches) === 1) {
            return (int)$matches[1];
        }
        return $default;
    }

    private function emitCookies(object $response, array $cookieLines): void
    {
        foreach ($cookieLines as $cookieLine) {
            $cookie = $this->parseCookieLine((string)$cookieLine);
            if (null === $cookie) {
                continue;
            }
            if (method_exists($response, 'cookie')) {
                $response->cookie(
                    $cookie['name'],
                    $cookie['value'],
                    $cookie['expires'],
                    $cookie['path'],
                    $cookie['domain'],
                    $cookie['secure'],
                    $cookie['httponly'],
                    $cookie['samesite']
                );
            }
        }
    }

    private function parseCookieLine(string $line): ?array
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
            } elseif ($key === 'path') {
                $cookie['path'] = $value !== '' ? $value : '/';
            } elseif ($key === 'domain') {
                $cookie['domain'] = $value;
            } elseif ($key === 'samesite') {
                $cookie['samesite'] = $value;
            }
        }
        return $cookie;
    }

    private function normalizeOutputHeaderName(string $normalizedKey): string
    {
        if ($normalizedKey === '') {
            return '';
        }
        if ($normalizedKey === 'www-authenticate') {
            return 'WWW-Authenticate';
        }
        $parts = explode('-', $normalizedKey);
        $parts = array_map(static fn($part) => ucfirst(strtolower(trim($part))), $parts);
        return implode('-', $parts);
    }
}
