<?php

namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;

/**
 * Class RequestHelper
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

    /**
     * Resolves if an origin is allowed by current cors.enabled configuration.
     * Returns the origin to expose, '*' for wildcard or null when denied.
     */
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
            foreach ($corsEnabled as $allowedOrigin) {
                $normalizedAllowed = self::normalizeOrigin((string)$allowedOrigin);
                if (!empty($normalizedAllowed) && $normalizedAllowed === $normalizedOrigin) {
                    return $normalizedOrigin;
                }
            }
            return null;
        }

        $corsEnabled = trim((string)$corsEnabled);
        if ($corsEnabled === '') {
            return null;
        }

        // Backward compatibility with regex-based config.
        if ($corsEnabled[0] === '/' && str_ends_with($corsEnabled, '/')) {
            return preg_match($corsEnabled, $normalizedOrigin) === 1 ? $normalizedOrigin : null;
        }

        $entries = explode(',', $corsEnabled);
        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            // Wildcard pattern support: https://*.example.com
            if (str_contains($entry, '*')) {
                $pattern = '/^' . str_replace('\*', '[^.]+', preg_quote($entry, '/')) . '$/i';
                if (preg_match($pattern, $normalizedOrigin) === 1) {
                    return $normalizedOrigin;
                }
                continue;
            }

            $normalizedAllowed = self::normalizeOrigin($entry);
            if (!empty($normalizedAllowed) && $normalizedAllowed === $normalizedOrigin) {
                return $normalizedOrigin;
            }
        }

        return null;
    }

    /**
     * Returns origin with scheme + host (+ optional port), without path/query/fragment.
     */
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
     * Check CORS requests
     */
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
        // check for shared internet/ISP IP
        $ipAddress = ServerHelper::getServerValue('HTTP_CLIENT_IP');
        if (!empty($ipAddress) && self::validateIpAddress($ipAddress)) {
            return $ipAddress;
        }

        // check for IPs passing through proxies
        $xForwardedFor = ServerHelper::getServerValue('HTTP_X_FORWARDED_FOR');
        if (!empty($xForwardedFor)) {
            // check if multiple ips exist in var
            if (str_contains($xForwardedFor, ',')) {
                $iplist = explode(',', $xForwardedFor);
                foreach ($iplist as $ip) {
                    if (self::validateIpAddress($ip)) {
                        return $ip;
                    }
                }
            } else {
                if (self::validateIpAddress($xForwardedFor)) {
                    return $xForwardedFor;
                }
            }
        }
        $xForwarded = ServerHelper::getServerValue('HTTP_X_FORWARDED');
        if (!empty($xForwarded) && self::validateIpAddress($xForwarded)) {
            return $xForwarded;
        }
        $xClusterClientIp = ServerHelper::getServerValue('HTTP_X_CLUSTER_CLIENT_IP');
        if (!empty($xClusterClientIp) && self::validateIpAddress($xClusterClientIp)) {
            return $xClusterClientIp;
        }

        // return unreliable ip since all else failed
        return ServerHelper::getServerValue('REMOTE_ADDR');
    }

    /**
     * @param string $ipAddress
     * @param string $startRange
     * @param string $endRange
     * @return bool
     */
    public static function validateIpAddress(string $ipAddress, string $startRange = '0.0.0.0', string $endRange = '255.255.255.255'): bool
    {
        // Utiliza FILTER_VALIDATE_IP para validar el formato de la dirección IP
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return false; // La IP no es válida
        }

        // Convierte las IP a números enteros
        $ipNum = ip2long($ipAddress);
        $rangoInicioNum = ip2long($startRange);
        $rangoFinNum = ip2long($endRange);

        // Verifica si la IP está en el rango válido
        if ($ipNum >= $rangoInicioNum && $ipNum <= $rangoFinNum) {
            return true; // La IP está en el rango válido
        } else {
            return false; // La IP está fuera del rango válido
        }
    }
}
