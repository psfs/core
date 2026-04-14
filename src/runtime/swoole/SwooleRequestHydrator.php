<?php

namespace PSFS\runtime\swoole;

use PSFS\base\SingletonRegistry;
use PSFS\base\runtime\RuntimeMode;

class SwooleRequestHydrator
{
    /**
     * @return string Context id
     */
    public function hydrate(object $request): string
    {
        $server = $this->extractRequestArray($request, 'server');
        $headers = $this->extractRequestArray($request, 'header');
        $get = $this->extractRequestArray($request, 'get');
        $post = $this->extractRequestArray($request, 'post');
        $cookie = $this->extractRequestArray($request, 'cookie');
        $files = $this->extractRequestArray($request, 'files');

        $this->syncSessionIdWithIncomingCookie($cookie);

        $rawBody = $this->extractRawBody($request);
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
            SwooleRequestHandler::RAW_BODY_SERVER_KEY => $rawBody,
        ];

        $this->appendHeadersToServer($headers);
        $this->hydrateBasicAuthServerVars($headers);

        $_GET = $get;
        $_POST = $post;
        $_COOKIE = $cookie;
        $_FILES = $files;
        $_REQUEST = array_merge($_GET, $_POST);

        return $contextId;
    }

    private function extractRequestArray(object $request, string $property): array
    {
        $value = $request->{$property} ?? null;
        return is_array($value) ? $value : [];
    }

    private function extractRawBody(object $request): string
    {
        if (!method_exists($request, 'rawContent')) {
            return '';
        }
        return (string)$request->rawContent();
    }

    private function appendHeadersToServer(array $headers): void
    {
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
        if (false === $decoded || !str_contains($decoded, ':')) {
            unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['AUTH_TYPE']);
            return;
        }
        [$user, $pass] = explode(':', $decoded, 2);
        $_SERVER['AUTH_TYPE'] = 'Basic';
        $_SERVER['PHP_AUTH_USER'] = $user;
        $_SERVER['PHP_AUTH_PW'] = $pass;
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
