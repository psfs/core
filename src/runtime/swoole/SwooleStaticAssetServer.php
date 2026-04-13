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
        if (!$this->isSupportedMethod($method)) {
            return false;
        }

        $relativePath = $this->resolveRelativePathFromRequest();
        if ($relativePath === null) {
            return false;
        }

        $candidate = $this->resolveCandidateAssetPath($relativePath);
        if ($candidate === null) {
            return false;
        }

        $this->setResponseStatus($response, 200);
        $this->setResponseHeader($response, 'Content-Type', $this->resolveStaticMimeType($candidate));

        if ($method === 'HEAD') {
            $this->endResponse($response, '');
            return true;
        }

        $payload = file_get_contents($candidate);
        if ($payload === false) {
            $this->setResponseStatus($response, 500);
            $this->endResponse($response, 'Internal server error');
            return true;
        }

        $this->endResponse($response, $payload);
        return true;
    }

    private function isSupportedMethod(string $method): bool
    {
        return in_array($method, ['GET', 'HEAD'], true);
    }

    private function resolveRelativePathFromRequest(): ?string
    {
        $uriPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if ($uriPath === '' || $uriPath === '/') {
            return null;
        }

        $relativePath = ltrim(rawurldecode($uriPath), '/');
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            return null;
        }

        return $relativePath;
    }

    private function resolveCandidateAssetPath(string $relativePath): ?string
    {
        $publicRoot = realpath(WEB_DIR);
        if ($publicRoot === false) {
            return null;
        }

        $candidate = realpath($publicRoot . DIRECTORY_SEPARATOR . $relativePath);
        if ($candidate === false || !is_file($candidate)) {
            return null;
        }

        if (!str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR) && $candidate !== $publicRoot) {
            return null;
        }

        return $candidate;
    }

    private function setResponseStatus(object $response, int $status): void
    {
        if (method_exists($response, 'status')) {
            $response->status($status);
        }
    }

    private function setResponseHeader(object $response, string $name, string $value): void
    {
        if (method_exists($response, 'header')) {
            $response->header($name, $value);
        }
    }

    private function endResponse(object $response, string $payload): void
    {
        if (method_exists($response, 'end')) {
            $response->end($payload);
        }
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
