<?php

namespace PSFS\runtime\swoole;

final class UiDevelopmentHttpProxy
{
    private const HOP_BY_HOP_HEADERS = [
        'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization',
        'te', 'trailer', 'transfer-encoding', 'upgrade',
    ];

    public function forward(UiDevelopmentProxyTarget $target, string $requestUri): ?array
    {
        $parts = parse_url($target->upstream);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $ssl = ($parts['scheme'] ?? 'http') === 'https';
        $port = (int)($parts['port'] ?? ($ssl ? 443 : 80));
        $client = new \Swoole\Coroutine\Http\Client((string)$parts['host'], $port, $ssl);
        $client->set(['timeout' => 10, 'keep_alive' => false]);
        $client->setHeaders($this->requestHeaders($parts));
        $client->setMethod((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $body = (string)($_SERVER[SwooleRequestHandler::RAW_BODY_SERVER_KEY] ?? '');
        if ($body !== '') {
            $client->setData($body);
        }

        $success = $client->execute($requestUri);
        if (!$success || $client->statusCode < 0) {
            $client->close();
            return null;
        }

        $result = [
            'status' => $client->statusCode,
            'headers' => $this->responseHeaders(is_array($client->headers) ? $client->headers : []),
            'body' => (string)$client->body,
        ];
        $client->close();
        return $result;
    }

    private function requestHeaders(array $upstream): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            if (in_array($name, self::HOP_BY_HOP_HEADERS, true) || in_array($name, ['authorization', 'cookie', 'host'], true)) {
                continue;
            }
            $headers[$this->headerName($name)] = (string)$value;
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string)$_SERVER['CONTENT_TYPE'];
        }
        $host = (string)$upstream['host'];
        if (isset($upstream['port'])) {
            $host .= ':' . (int)$upstream['port'];
        }
        $headers['Host'] = $host;
        $headers['X-Forwarded-Host'] = (string)($_SERVER['HTTP_HOST'] ?? '');
        $headers['X-Forwarded-Proto'] = (string)($_SERVER['REQUEST_SCHEME'] ?? 'http');
        $headers['X-Forwarded-For'] = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        return $headers;
    }

    private function responseHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            $normalized = strtolower((string)$name);
            if (in_array($normalized, self::HOP_BY_HOP_HEADERS, true) || $normalized === 'set-cookie') {
                continue;
            }
            $result[$normalized] = $value;
        }
        return $result;
    }

    private function headerName(string $name): string
    {
        return implode('-', array_map(static fn(string $part): string => ucfirst($part), explode('-', $name)));
    }
}
