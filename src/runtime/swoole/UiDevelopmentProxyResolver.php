<?php

namespace PSFS\runtime\swoole;

final class UiDevelopmentProxyResolver
{
    public function resolve(string $requestUri, mixed $configuredPath, mixed $developmentUpstream): ?UiDevelopmentProxyTarget
    {
        $mount = $this->normalizeMount($configuredPath);
        $upstream = $this->normalizeUpstream($developmentUpstream);
        $path = explode('?', $requestUri, 2)[0] ?: '/';

        if ($mount === null || $upstream === null || !$this->matches($path, $mount)) {
            return null;
        }

        return new UiDevelopmentProxyTarget($mount, $upstream);
    }

    private function normalizeMount(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || $value[0] !== '/') {
            return null;
        }
        if (($value !== '/' && str_ends_with($value, '/')) || str_contains($value, '?') || str_contains($value, '#')) {
            return null;
        }
        return $value;
    }

    private function normalizeUpstream(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $parts = parse_url($value);
        if (!is_array($parts)
            || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
            return null;
        }
        return rtrim($value, '/');
    }

    private function matches(string $path, string $mount): bool
    {
        return $mount === '/' || $path === $mount || str_starts_with($path, $mount . '/');
    }
}
