<?php

namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;

/**
 * @package PSFS\base\types\helpers
 */
class RequestHelper
{
    public static function getCorsHeaders(): array
    {
        $headers = [
            'Access-Control-Allow-Methods',
            'Access-Control-Allow-Headers',
            'Access-Control-Allow-Origin',
            'Access-Control-Expose-Headers',
            'Origin',
            'X-Requested-With',
            'Content-Type',
            'Accept',
            'Authorization',
            'Cache-Control',
            'Content-Language',
            'Accept-Language',
            'X-API-SEC-TOKEN',
            'X-API-USER-TOKEN',
            'X-API-LANG',
            'X-FIELD-TYPE',
        ];
        $extraHeaders = Config::getParam('cors.headers', '');
        if (!empty($extraHeaders)) {
            $headers = array_merge($headers, explode(',', $extraHeaders));
        }
        $headers = array_map('trim', $headers);
        $headers = array_filter($headers, static function ($header) {
            return $header !== '';
        });
        return array_unique($headers);
    }


    public static function resolveAllowedOrigin(?string $origin, mixed $corsEnabled): ?string
    {
        $normalizedOrigin = self::normalizeOrigin($origin);
        if (empty($corsEnabled) || empty($normalizedOrigin)) {
            return null;
        }

        if ($corsEnabled === '*') {
            return '*';
        }

        if (is_array($corsEnabled)) {
            return self::resolveOriginFromArray($normalizedOrigin, $corsEnabled);
        }

        $corsEnabled = trim((string)$corsEnabled);
        if ($corsEnabled === '') {
            return null;
        }

        // Backward compatibility with regex-based config.
        if ($corsEnabled[0] === '/' && str_ends_with($corsEnabled, '/')) {
            return preg_match($corsEnabled, $normalizedOrigin) === 1 ? $normalizedOrigin : null;
        }

        return self::resolveOriginFromCsvAllowlist($normalizedOrigin, $corsEnabled);
    }


    public static function normalizeOrigin(?string $origin): ?string
    {
        if (empty($origin)) {
            return null;
        }

        $parsed = parse_url(trim($origin));
        if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            return null;
        }

        $scheme = strtolower((string)$parsed['scheme']);
        $host = strtolower((string)$parsed['host']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $normalized = $scheme . '://' . $host;
        if (!empty($parsed['port'])) {
            $normalized .= ':' . (int)$parsed['port'];
        }
        return $normalized;
    }

    /**
     * @param string $normalizedOrigin
     * @param array $allowlist
     * @return string|null
     */
    private static function resolveOriginFromArray(string $normalizedOrigin, array $allowlist): ?string
    {
        foreach ($allowlist as $allowedOrigin) {
            $normalizedAllowed = self::normalizeOrigin((string)$allowedOrigin);
            if (!empty($normalizedAllowed) && $normalizedAllowed === $normalizedOrigin) {
                return $normalizedOrigin;
            }
        }
        return null;
    }

    /**
     * @param string $normalizedOrigin
     * @param string $csvAllowlist
     * @return string|null
     */
    private static function resolveOriginFromCsvAllowlist(string $normalizedOrigin, string $csvAllowlist): ?string
    {
        $entries = explode(',', $csvAllowlist);
        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if (self::entryMatchesOrigin($entry, $normalizedOrigin)) {
                return $normalizedOrigin;
            }
        }
        return null;
    }

    /**
     * @param string $entry
     * @param string $normalizedOrigin
     * @return bool
     */
    private static function entryMatchesOrigin(string $entry, string $normalizedOrigin): bool
    {
        // Wildcard pattern support: https://*.example.com
        if (str_contains($entry, '*')) {
            $pattern = '/^' . str_replace('\*', '[^.]+', preg_quote($entry, '/')) . '$/i';
            return preg_match($pattern, $normalizedOrigin) === 1;
        }

        $normalizedAllowed = self::normalizeOrigin($entry);
        return !empty($normalizedAllowed) && $normalizedAllowed === $normalizedOrigin;
    }


    public static function checkCORS(): void
    {
        Inspector::stats('[RequestHelper] Checking CORS', Inspector::SCOPE_DEBUG);
        $corsEnabled = Config::getParam('cors.enabled');
        $request = Request::getInstance();
        if (NULL !== $corsEnabled) {
            $origin = $request->getServer('HTTP_ORIGIN');
            $allowedOrigin = self::resolveAllowedOrigin($origin, $corsEnabled);
            if ($allowedOrigin !== null) {
                if (!headers_sent()) {
                    // TODO include these headers in Template class output method
                    if ($allowedOrigin === '*') {
                        ResponseHelper::setHeader('Access-Control-Allow-Origin: *');
                    } else {
                        ResponseHelper::setHeader('Access-Control-Allow-Credentials: true');
                        ResponseHelper::setHeader('Access-Control-Allow-Origin: ' . $allowedOrigin);
                        ResponseHelper::setHeader('Vary: Origin');
                    }
                    ResponseHelper::setHeader('Access-Control-Allow-Methods: GET, POST, DELETE, PUT, PATCH, OPTIONS, HEAD');
                    ResponseHelper::setHeader('Access-Control-Allow-Headers: ' . implode(', ', self::getCorsHeaders()));
                }
                if ($request->getMethod() === Request::VERB_OPTIONS) {
                    Logger::log('Returning OPTIONS header confirmation for CORS pre flight requests', LOG_DEBUG);
                    ResponseHelper::setStatusHeader('HTTP/1.1 204 No Content');
                    exit();
                }
            }
        }
    }

    /**
     * @return mixed
     */
    public static function getIpAddress(): mixed
    {
        $directClientIp = self::extractSingleValidIp('HTTP_CLIENT_IP');
        if (null !== $directClientIp) {
            return $directClientIp;
        }
        $forwardedChain = self::extractForwardedForIp();
        if (null !== $forwardedChain) {
            return $forwardedChain;
        }
        foreach (['HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP'] as $serverKey) {
            $candidate = self::extractSingleValidIp($serverKey);
            if (null !== $candidate) {
                return $candidate;
            }
        }
        // return unreliable ip since all else failed
        return ServerHelper::getServerValue('REMOTE_ADDR');
    }

    private static function extractSingleValidIp(string $serverKey): ?string
    {
        $value = trim((string)ServerHelper::getServerValue($serverKey));
        if ($value === '' || !self::validateIpAddress($value)) {
            return null;
        }
        return $value;
    }

    private static function extractForwardedForIp(): ?string
    {
        $xForwardedFor = trim((string)ServerHelper::getServerValue('HTTP_X_FORWARDED_FOR'));
        if ($xForwardedFor === '') {
            return null;
        }
        $ipList = str_contains($xForwardedFor, ',') ? explode(',', $xForwardedFor) : [$xForwardedFor];
        foreach ($ipList as $ip) {
            $candidate = trim($ip);
            if (self::validateIpAddress($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * @param string $ipAddress
     * @param string $startRange
     * @param string $endRange
     * @return bool
     */
    public static function validateIpAddress(string $ipAddress, string $startRange = '0.0.0.0', string $endRange = '255.255.255.255'): bool
    {
        // Use FILTER_VALIDATE_IP to validate IP format.
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return false; // Invalid IP.
        }

        // Convert IP addresses to integers.
        $ipNum = ip2long($ipAddress);
        $rangoInicioNum = ip2long($startRange);
        $rangoFinNum = ip2long($endRange);

        // Validate that IP is inside the accepted range.
        if ($ipNum >= $rangoInicioNum && $ipNum <= $rangoFinNum) {
            return true; // IP is in range.
        } else {
            return false; // IP is out of range.
        }
    }
}
