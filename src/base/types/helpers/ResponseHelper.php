<?php

namespace PSFS\base\types\helpers;

use Exception;
use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\exception\GeneratorException;
use PSFS\base\exception\RouterException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Template;
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
                    (array_key_exists('expire', $cookie)) ? $cookie["expire"] : 1440,
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
    public static function setAuthHeaders(bool $isPublic = true)
    {
        if ($isPublic) {
            unset($_SERVER["PHP_AUTH_USER"]);
            unset($_SERVER["PHP_AUTH_PW"]);
            header_remove("Authorization");
        } elseif (!self::isTest()) {
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

    /**
     * @param Exception|NULL $exception
     * @param bool $isJson
     * @return string
     * @throws RouterException
     * @throws GeneratorException
     */
    public static function httpNotFound(Exception $exception = NULL, $isJson = false)
    {
        if(self::isTest()) {
            return 404;
        }
        Inspector::stats('[Router] Throw not found exception', Inspector::SCOPE_DEBUG);
        if (NULL === $exception) {
            Logger::log('Not found page thrown without previous exception', LOG_WARNING);
            $exception = new Exception(t('Page not found'), 404);
        }
        $template = Template::getInstance()->setStatus($exception->getCode());
        if ($isJson || false !== stripos(Request::getInstance()->getServer('CONTENT_TYPE'), 'json')) {
            $response = new JsonResponse(null, false, 0, 0, $exception->getMessage());
            return $template->output(json_encode($response), 'application/json');
        }

        $notFoundRoute = Config::getParam('route.404');
        if(null !== $notFoundRoute) {
            Request::getInstance()->redirect(Router::getInstance()->getRoute($notFoundRoute, true));
        } else {
            return $template->render('error.html.twig', array(
                'exception' => $exception,
                'trace' => $exception->getTraceAsString(),
                'error_page' => TRUE,
            ));
        }
    }
}
