<?php

namespace PSFS\base;

final class AdminFrontendVersionResolver
{
    private const LEGACY = 'legacy';
    private const V2 = 'v2';

    /**
     * @return AdminFrontendVersionRedirect|null
     */
    public function resolve(
        string $method,
        string $requestUri,
        mixed $configuredVersion,
        mixed $frontendMount = '/admin-v2'
    ): ?AdminFrontendVersionRedirect
    {
        $parts = parse_url($requestUri);
        $path = (string)($parts['path'] ?? '/');
        if (!$this->isEligibleRequest($method, $path)) {
            return null;
        }

        [$override, $query] = $this->extractOverride((string)($parts['query'] ?? ''));
        $version = $override ?? $this->normalizeVersion($configuredVersion) ?? self::LEGACY;
        if ($version !== self::V2) {
            return null;
        }

        $mount = $this->normalizeMount($frontendMount);
        if ($mount === null) {
            return null;
        }

        $remainingPath = substr($path, strlen('/admin'));
        $location = $mount . ($remainingPath === '' ? '/' : $remainingPath);
        if ($query !== '') {
            $location .= '?' . $query;
        }

        return new AdminFrontendVersionRedirect($location);
    }

    private function isEligibleRequest(string $method, string $path): bool
    {
        if (!in_array(strtoupper($method), ['GET', 'HEAD'], true)
            || $path === '/admin-v2'
            || str_starts_with($path, '/admin-v2/')
            || ($path !== '/admin' && !str_starts_with($path, '/admin/'))
            || $this->isTechnicalPath($path)
            || $this->hasStaticExtension($path)) {
            return false;
        }

        return true;
    }

    private function isTechnicalPath(string $path): bool
    {
        if (str_starts_with($path, '/admin/api/v2/')) {
            return true;
        }

        if (in_array($path, ['/admin/login', '/admin/setup', '/admin/config/params', '/admin/routes/show', '/admin/routes/gen'], true)) {
            return true;
        }
        if (str_starts_with($path, '/admin/locale/') || str_starts_with($path, '/admin/assets/')) {
            return true;
        }

        return preg_match('#^/admin/[^/]+/swagger-ui$#', $path) === 1;
    }

    private function hasStaticExtension(string $path): bool
    {
        return preg_match('/\.(?:css|js|map|json|svg|png|jpe?g|gif|webp|ico|woff2?|ttf)$/i', $path) === 1;
    }

    private function normalizeVersion(mixed $version): ?string
    {
        return is_string($version) && in_array($version, [self::LEGACY, self::V2], true) ? $version : null;
    }

    private function normalizeMount(mixed $mount): ?string
    {
        if (!is_string($mount) || preg_match('#^/[A-Za-z0-9][A-Za-z0-9/_-]*$#', $mount) !== 1) {
            return null;
        }

        return rtrim($mount, '/');
    }

    /**
     * @return array{0:string|null,1:string}
     */
    private function extractOverride(string $query): array
    {
        if ($query === '') {
            return [null, ''];
        }

        $override = null;
        $remaining = [];
        foreach (explode('&', $query) as $pair) {
            [$encodedKey, $encodedValue] = array_pad(explode('=', $pair, 2), 2, '');
            if (urldecode($encodedKey) !== '__front') {
                $remaining[] = $pair;
                continue;
            }

            $candidate = $this->normalizeVersion(urldecode($encodedValue));
            if ($candidate !== null) {
                $override = $candidate;
            }
        }

        return [$override, implode('&', $remaining)];
    }
}

final class AdminFrontendVersionRedirect
{
    public function __construct(
        public readonly string $location,
        public readonly int $statusCode = 302
    ) {
    }
}
