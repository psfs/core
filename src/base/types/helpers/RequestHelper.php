<?php
namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;

class RequestHelper
{
    private static function getCorsHeaders() {
        $headers = [
            "Access-Control-Allow-Methods",
            "Access-Control-Allow-Headers",
            "Access-Control-Allow-Origin",
            "Access-Control-Expose-Headers",
            "Origin",
            "X-Requested-With",
            "Content-Type",
            "Accept",
            "Authorization",
            "X-API-SEC-TOKEN",
            "X-API-USER-TOKEN",
            "X-API-LANG",
            "api_key"
        ];
        $extra_headers = Config::getParam('cors.headers', '');
        $headers = array_merge($headers, explode(',', $extra_headers));
        return array_unique($headers);
    }

    /**
     * Check CROS requests
     */
    public static function checkCORS()
    {
        Logger::log('Checking CORS');
        $corsEnabled = Config::getInstance()->get('cors.enabled');
        $request = Request::getInstance();
        if (NULL !== $corsEnabled) {
            if ($corsEnabled === '*' || preg_match($corsEnabled, $request->getServer('HTTP_REFERER'))) {
                if (!headers_sent()) {

                    // TODO include this headers in Template class output method
                    header("Access-Control-Allow-Credentials: true");
                    header("Access-Control-Allow-Origin: *");
                    header("Access-Control-Allow-Methods: GET, POST, DELETE, PUT, PATCH, OPTIONS");
                    header("Access-Control-Allow-Headers: " . implode(', ', self::getCorsHeaders()));
                }
                if (Request::getInstance()->getMethod() == 'OPTIONS') {
                    Logger::log('Returning OPTIONS header confirmation for CORS pre flight requests', LOG_DEBUG);
                    header("HTTP/1.1 200 OK");
                    exit();
                }
            }
        }
    }
}