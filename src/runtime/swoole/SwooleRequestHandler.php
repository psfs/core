<?php

namespace PSFS\runtime\swoole;

use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\SingletonRegistry;
use PSFS\base\Template;
use PSFS\base\extension\CustomTranslateExtension;
use PSFS\base\types\helpers\EventHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\ResponseHeaderHelper;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\exception\RequestTerminationException;
use PSFS\base\runtime\RuntimeMode;
use PSFS\Dispatcher;
use Throwable;

class SwooleRequestHandler
{
    public const RAW_BODY_SERVER_KEY = 'PSFS_RAW_BODY';
    private const STATIC_MIME_MAP = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'map' => 'application/json; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
    ];

    public function handle(object $request, object $response): void
    {
        RuntimeMode::enableSwoole();
        $contextId = $this->hydrateSuperglobals($request);
        $this->resetRuntimeState();

        if ($this->tryServeStaticAsset($response)) {
            $this->cleanupRuntimeState($contextId);
            return;
        }

        $statusCode = 200;
        $body = '';
        ob_start();
        try {
            $dispatcher = Dispatcher::getInstance();
            $result = $dispatcher->run($_SERVER['REQUEST_URI'] ?? '/');
            $buffered = (string)ob_get_clean();
            $body = $buffered;
            if ($body === '' && is_string($result)) {
                $body = $result;
            }
        } catch (RequestTerminationException) {
            $body = (string)ob_get_clean();
        } catch (Throwable $throwable) {
            $body = (string)ob_get_clean();
            $statusCode = 500;
            if ($body === '') {
                $body = 'Internal server error';
            }
        } finally {
            if (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        $nativeHeaders = headers_list();
        $headers = $this->mergeHeaders(ResponseHelper::$headers_sent, $nativeHeaders);
        $this->ensureSessionCookieHeader($headers);
        $statusCode = $this->resolveStatusCode($headers, $statusCode);
        $this->emitResponse($response, $statusCode, $headers, $body);
        $this->cleanupRuntimeState($contextId);
    }

    public function emitResponse(object $response, int $statusCode, array $headers, string $body): void
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
                foreach ($values as $cookieLine) {
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

    private function resetRuntimeState(): void
    {
        ResponseHelper::$headers_sent = [];
        Inspector::reset();
        EventHelper::clear(EventHelper::EVENT_END_REQUEST);
        SingletonRegistry::clear();
        Dispatcher::dropInstance();
        Request::dropInstance();
        Security::dropInstance();
        Template::dropInstance();
        CustomTranslateExtension::resetRuntimeState();
    }

    private function cleanupRuntimeState(string $contextId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        if (function_exists('session_id')) {
            @session_id('');
        }
        $_SESSION = [];
        ResponseHelper::$headers_sent = [];
        EventHelper::clear(EventHelper::EVENT_END_REQUEST);
        SingletonRegistry::clear();
        CustomTranslateExtension::resetRuntimeState();
        if (function_exists('header_remove')) {
            @header_remove();
        }
        if (isset($_SERVER[SingletonRegistry::CONTEXT_SESSION]) && $_SERVER[SingletonRegistry::CONTEXT_SESSION] === $contextId) {
            unset($_SERVER[SingletonRegistry::CONTEXT_SESSION]);
        }
        unset($_SERVER[self::RAW_BODY_SERVER_KEY]);
    }

    private function hydrateSuperglobals(object $request): string
    {
        $server = is_array($request->server ?? null) ? $request->server : [];
        $headers = is_array($request->header ?? null) ? $request->header : [];
        $get = is_array($request->get ?? null) ? $request->get : [];
        $post = is_array($request->post ?? null) ? $request->post : [];
        $cookie = is_array($request->cookie ?? null) ? $request->cookie : [];
        $files = is_array($request->files ?? null) ? $request->files : [];
        $this->syncSessionIdWithIncomingCookie($cookie);

        $rawBody = '';
        if (method_exists($request, 'rawContent')) {
            $rawBody = (string)$request->rawContent();
        }

        $requestUri = (string)($server['request_uri'] ?? '/');
        $queryString = (string)($server['query_string'] ?? http_build_query($get));
        $method = strtoupper((string)($server['request_method'] ?? 'GET'));
        $host = (string)($headers['host'] ?? ($server['server_name'] ?? 'localhost'));
        $serverName = (string)explode(':', $host)[0];
        $serverPort = (int)($server['server_port'] ?? 8080);
        $httpsHeader = strtolower((string)($headers['x-forwarded-proto'] ?? ''));
        $scheme = $httpsHeader === 'https' ? 'https' : 'http';
        $https = $scheme === 'https' ? 'on' : '';
        $contextId = 'psfs-swoole-' . str_replace('.', '', uniqid('', true));

        $_SERVER = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $requestUri,
            'QUERY_STRING' => $queryString,
            'PATH_INFO' => parse_url($requestUri, PHP_URL_PATH) ?: '/',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => $serverName,
            'SERVER_PORT' => $serverPort,
            'HTTP_HOST' => $host,
            'REMOTE_ADDR' => (string)($server['remote_addr'] ?? ''),
            'HTTPS' => $https,
            'REQUEST_SCHEME' => $scheme,
            SingletonRegistry::CONTEXT_SESSION => $contextId,
            RuntimeMode::ENV_KEY => RuntimeMode::MODE_SWOOLE,
            self::RAW_BODY_SERVER_KEY => $rawBody,
        ];
        foreach ($headers as $headerName => $headerValue) {
            $normalized = strtoupper(str_replace('-', '_', (string)$headerName));
            $_SERVER['HTTP_' . $normalized] = (string)$headerValue;
            if ($normalized === 'CONTENT_TYPE') {
                $_SERVER['CONTENT_TYPE'] = (string)$headerValue;
            }
            if ($normalized === 'CONTENT_LENGTH') {
                $_SERVER['CONTENT_LENGTH'] = (string)$headerValue;
            }
        }
        $this->hydrateBasicAuthServerVars($headers);

        $_GET = $get;
        $_POST = $post;
        $_COOKIE = $cookie;
        $_FILES = $files;
        $_REQUEST = array_merge($_GET, $_POST);

        return $contextId;
    }

    private function hydrateBasicAuthServerVars(array $headers): void
    {
        $authorization = (string)($headers['authorization'] ?? '');
        if ($authorization === '') {
            unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['AUTH_TYPE']);
            return;
        }
        if (preg_match('/^Basic\s+(.+)$/i', $authorization, $matches) !== 1) {
            unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['AUTH_TYPE']);
            return;
        }
        $decoded = base64_decode(trim((string)$matches[1]), true);
        if (!is_string($decoded) || !str_contains($decoded, ':')) {
            unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['AUTH_TYPE']);
            return;
        }
        [$user, $pass] = explode(':', $decoded, 2);
        $_SERVER['AUTH_TYPE'] = 'Basic';
        $_SERVER['PHP_AUTH_USER'] = $user;
        $_SERVER['PHP_AUTH_PW'] = $pass;
    }

    private function resolveStatusCode(array $headers, int $default): int
    {
        $statusKey = ResponseHeaderHelper::normalizeHeaderKey(ResponseHeaderHelper::HTTP_STATUS_HEADER);
        $statusLine = (string)($headers[$statusKey] ?? '');
        if ($statusLine !== '' && preg_match('/\b(\d{3})\b/', $statusLine, $matches) === 1) {
            return (int)$matches[1];
        }
        return $default;
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

    private function ensureSessionCookieHeader(array &$headers): void
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

    private function mergeHeaders(array $responseHelperHeaders, array $nativeHeaderLines): array
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

    private function tryServeStaticAsset(object $response): bool
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return false;
        }
        $uriPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if ($uriPath === '' || $uriPath === '/') {
            return false;
        }
        $relativePath = ltrim(rawurldecode($uriPath), '/');
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            return false;
        }
        $publicRoot = realpath(WEB_DIR);
        if ($publicRoot === false) {
            return false;
        }
        $candidate = realpath($publicRoot . DIRECTORY_SEPARATOR . $relativePath);
        if ($candidate === false || !is_file($candidate)) {
            return false;
        }
        if (!str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR) && $candidate !== $publicRoot) {
            return false;
        }
        if (method_exists($response, 'status')) {
            $response->status(200);
        }
        if (method_exists($response, 'header')) {
            $response->header('Content-Type', $this->resolveStaticMimeType($candidate));
        }
        if ($method === 'HEAD') {
            if (method_exists($response, 'end')) {
                $response->end('');
            }
            return true;
        }
        $payload = file_get_contents($candidate);
        if ($payload === false) {
            if (method_exists($response, 'status')) {
                $response->status(500);
            }
            if (method_exists($response, 'end')) {
                $response->end('Internal server error');
            }
            return true;
        }
        if (method_exists($response, 'end')) {
            $response->end($payload);
        }
        return true;
    }

    private function resolveStaticMimeType(string $path): string
    {
        $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        if ($extension !== '' && array_key_exists($extension, self::STATIC_MIME_MAP)) {
            return self::STATIC_MIME_MAP[$extension];
        }
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            if (is_string($detected) && trim($detected) !== '') {
                return $detected;
            }
        }
        return self::STATIC_MIME_MAP[$extension] ?? 'application/octet-stream';
    }

    private function syncSessionIdWithIncomingCookie(array $cookies): void
    {
        if (!function_exists('session_name') || !function_exists('session_id')) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        $sessionName = session_name();
        if ($sessionName === '') {
            return;
        }
        $incomingSessionId = $cookies[$sessionName] ?? null;
        if (!is_string($incomingSessionId) || trim($incomingSessionId) === '') {
            @session_id('');
            return;
        }
        @session_id($incomingSessionId);
    }
}
