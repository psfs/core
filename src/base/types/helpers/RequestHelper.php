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
        $headers = array_merge($headers, explode(',', $extraHeaders));
        return array_unique($headers);
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
            if ($corsEnabled === '*' || preg_match($corsEnabled, $request->getServer('HTTP_REFERER'))) {
                if (!headers_sent()) {
                    // TODO include this headers in Template class output method
                    header('Access-Control-Allow-Credentials: true');
                    header('Access-Control-Allow-Origin: ' . Request::getInstance()->getServer('HTTP_ORIGIN', '*'));
                    header('Vary: Origin');
                    header('Access-Control-Allow-Methods: GET, POST, DELETE, PUT, PATCH, OPTIONS, HEAD');
                    header('Access-Control-Allow-Headers: ' . implode(', ', self::getCorsHeaders()));
                }
                if (Request::getInstance()->getMethod() === Request::VERB_OPTIONS) {
                    Logger::log('Returning OPTIONS header confirmation for CORS pre flight requests', LOG_DEBUG);
                    header('HTTP/1.1 200 OK');
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
        if (!empty($_SERVER['HTTP_CLIENT_IP']) && self::validateIpAddress($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        // check for IPs passing through proxies
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // check if multiple ips exist in var
            if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false) {
                $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                foreach ($iplist as $ip) {
                    if (self::validateIpAddress($ip)) {
                        return $ip;
                    }
                }
            } else {
                if (self::validateIpAddress($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    return $_SERVER['HTTP_X_FORWARDED_FOR'];
                }
            }
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED']) && self::validateIpAddress($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        }
        if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && self::validateIpAddress($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && self::validateIpAddress($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        }
        if (!empty($_SERVER['HTTP_FORWARDED']) && self::validateIpAddress($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        }

        // return unreliable ip since all else failed
        return $_SERVER['REMOTE_ADDR'];
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
