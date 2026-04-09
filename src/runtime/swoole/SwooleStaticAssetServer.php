<?php

namespace PSFS\runtime\swoole;

class SwooleStaticAssetServer
{
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

    public function tryServe(object $response): bool
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
}
