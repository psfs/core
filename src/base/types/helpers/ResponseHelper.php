<?php
namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\types\traits\TestTrait;
use PSFS\Dispatcher;

class ResponseHelper
{
    use TestTrait;
    /**
     * Method that sets the cookie headers
     * @param $cookies
     */
    public static function setCookieHeaders($cookies)
    {
        if (!empty($cookies) && is_array($cookies) && false === headers_sent() && !self::isTest()) {
            foreach ($cookies as $cookie) {
                setcookie($cookie["name"],
                    $cookie["value"],
                    (array_key_exists('expire', $cookie)) ? $cookie["expire"] : NULL,
                    (array_key_exists('path', $cookie)) ? $cookie["path"] : "/",
                    (array_key_exists('domain', $cookie)) ? $cookie["domain"] : Request::getInstance()->getRootUrl(FALSE),
                    (array_key_exists('secure', $cookie)) ? $cookie["secure"] : FALSE,
                    (array_key_exists('http', $cookie)) ? $cookie["http"] : true
                );
            }
        }
    }

    /**
     * Método que inyecta las cabeceras necesarias para la autenticación
     * @param boolean $isPublic
     */
    public static function setAuthHeaders($isPublic = true)
    {
        if ($isPublic) {
            unset($_SERVER["PHP_AUTH_USER"]);
            unset($_SERVER["PHP_AUTH_PW"]);
            header_remove("Authorization");
        } elseif(!self::isTest()) {
            header('Authorization:');
        }
    }

    /**
     * Método que establece el status code
     * @param string $statusCode
     */
    public static function setStatusHeader($statusCode = null)
    {
        if (NULL !== $statusCode && !self::isTest()) {
            header($statusCode);
        }
    }

    /**
     * Método que mete en las variables de las plantillas las cabeceras de debug
     * @param array $vars
     *
     * @return array
     */
    public static function setDebugHeaders(array $vars)
    {
        if ((Config::getParam('debug', true) || Config::getParam('profiling.enable', false)) && !self::isTest()) {
            Logger::log('Adding debug headers to render response');
            $vars["__DEBUG__"]["includes"] = get_included_files();
            $vars["__DEBUG__"]["trace"] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            header('X-PSFS-DEBUG-TS: ' . Dispatcher::getInstance()->getTs() . ' s');
            header('X-PSFS-DEBUG-MEM: ' . Dispatcher::getInstance()->getMem('MBytes') . ' MBytes');
            header('X-PSFS-DEBUG-FILES: ' . count(get_included_files()) . ' files opened');
        }

        return $vars;
    }
}