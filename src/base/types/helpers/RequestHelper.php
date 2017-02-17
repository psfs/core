<?php
namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;

class RequestHelper
{
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
                    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
                    header("Access-Control-Allow-Headers: Access-Control-Allow-Methods, Access-Control-Allow-Headers, Access-Control-Allow-Origin, Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-SEC-TOKEN, X-API-USER-TOKEN");
                }
                if (Request::getInstance()->getMethod() == 'OPTIONS') {
                    Logger::log('Returning OPTIONS header confirmation for CORS pre flight requests');
                    header("HTTP/1.1 200 OK");
                    exit();
                }
            }
        }
    }
}