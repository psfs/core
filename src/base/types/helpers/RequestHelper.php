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
    private static function getCorsHeaders() {
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
     * Check CROS requests
     */
    public static function checkCORS()
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
    public static function getIpAddress() {
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
     * Ensures an ip address is both a valid IP and does not fall within
     * a private network range.
     */
    public static function validateIpAddress($ipAddress) {
        if (strtolower($ipAddress) === 'unknown') {
            return false;
        }

        // generate ipv4 network address
        $ipAddress = ip2long($ipAddress);

        // if the ip is set and not equivalent to 255.255.255.255
        if ($ipAddress !== false && $ipAddress !== -1) {
            // make sure to get unsigned long representation of ip
            // due to discrepancies between 32 and 64 bit OSes and
            // signed numbers (ints default to signed in PHP)
            $ipAddress = sprintf('%u', $ipAddress);
            // do private network range checking
            if ($ipAddress >= 0 && $ipAddress <= 50331647) {
                return false;
            }
            if ($ipAddress >= 167772160 && $ipAddress <= 184549375) {
                return false;
            }
            if ($ipAddress >= 2130706432 && $ipAddress <= 2147483647) {
                return false;
            }
            if ($ipAddress >= 2851995648 && $ipAddress <= 2852061183) {
                return false;
            }
            if ($ipAddress >= 2886729728 && $ipAddress <= 2887778303) {
                return false;
            }
            if ($ipAddress >= 3221225984 && $ipAddress <= 3221226239) {
                return false;
            }
            if ($ipAddress >= 3232235520 && $ipAddress <= 3232301055) {
                return false;
            }
            if ($ipAddress >= 4294967040) {
                return false;
            }
        }
        return true;
    }
}
