<?php
namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\Dispatcher;

class ResponseHelper
{

    /**
     * Method that sets the cookie headers
     * @param $cookies
     */
    public static function setCookieHeaders($cookies)
    {
        if (!empty($cookies) && is_array($cookies)) {
            foreach ($cookies as $cookie) {
                setcookie($cookie["name"],
                    $cookie["value"],
                    (array_key_exists('expire', $cookie)) ? $cookie["expire"] : NULL,
                    (array_key_exists('path', $cookie)) ? $cookie["path"] : "/",
                    (array_key_exists('domain', $cookie)) ? $cookie["domain"] : Request::getInstance()->getRootUrl(FALSE),
                    (array_key_exists('secure', $cookie)) ? $cookie["secure"] : FALSE,
                    (array_key_exists('http', $cookie)) ? $cookie["http"] : FALSE
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
        } else {
            header('Authorization:');
        }
    }

    /**
     * Método que establece el status code
     * @param string $status_code
     */
    public static function setStatusHeader($status_code = null)
    {
        if (NULL !== $status_code) {
            header($status_code);
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
        if (Config::getParam('debug', true)) {
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