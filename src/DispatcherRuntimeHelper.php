<?php

namespace PSFS;

class DispatcherRuntimeHelper
{
    /**
     * @param array<string> $setupAllowedPaths
     */
    public static function resolveTargetUri(mixed $uri, ?string $actualUri): string
    {
        return (string)($uri ?? $actualUri ?? '/');
    }

    public static function resolveActualRequestUri(mixed $uri, mixed $currentRequestUri): string
    {
        $requestUri = (string)$currentRequestUri;
        if ($requestUri !== '') {
            return $requestUri;
        }
        if (null !== $uri) {
            return (string)$uri;
        }
        return '/';
    }

    public static function isFileTargetUri(string $targetUri): bool
    {
        $path = (string)parse_url($targetUri, PHP_URL_PATH);
        if ($path === '') {
            return false;
        }
        return preg_match('/\.[a-z0-9]{2,4}$/i', $path) === 1;
    }

    public static function isUnitTestExecution(): bool
    {
        return defined('PSFS_UNIT_TESTING_EXECUTION') && true === PSFS_UNIT_TESTING_EXECUTION;
    }

    /**
     * @param array<string> $setupAllowedPaths
     */
    public static function canRunSetupRoute(string $targetUri, mixed $uri, array $setupAllowedPaths): bool
    {
        return self::isSetupRouteAllowed($targetUri, $setupAllowedPaths) && (!self::isUnitTestExecution() || null !== $uri);
    }

    /**
     * @param array<string> $setupAllowedPaths
     */
    public static function isSetupRouteAllowed(?string $uri, array $setupAllowedPaths): bool
    {
        if (!is_string($uri) || '' === $uri) {
            return false;
        }

        $path = parse_url($uri, PHP_URL_PATH);
        if (null === $path || '' === $path) {
            return false;
        }

        $normalizedPath = rtrim($path, '/');
        if ('' === $normalizedPath) {
            $normalizedPath = '/';
        }

        return in_array($normalizedPath, $setupAllowedPaths, true);
    }
}
