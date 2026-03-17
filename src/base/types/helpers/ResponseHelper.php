<?php

namespace PSFS\base\types\helpers;

use Exception;
use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Template;
use PSFS\base\types\traits\TestTrait;
use PSFS\Dispatcher;

class ResponseHelper
{
    use TestTrait;

    public static array $headers_sent = [];

    public static function setHeader(string $header): void
    {
        $parsed = ResponseHeaderHelper::parseHeader($header);
        $key = $parsed['normalized_key'];
        $value = $parsed['value'];
        $line = $parsed['line'];

        if (ResponseHeaderHelper::allowsMultipleValues($key)) {
            if (!self::isTest()) {
                header($line, false);
            }
            self::$headers_sent[$key] = $value;
            return;
        }

        if (array_key_exists($key, self::$headers_sent) && self::$headers_sent[$key] === $value) {
            return;
        }

        if (!self::isTest()) {
            header($line, true);
        }
        self::$headers_sent[$key] = $value;
    }

    public static function dropHeader(string $header): void
    {
        $key = ResponseHeaderHelper::normalizeHeaderKey($header);
        if (array_key_exists($key, self::$headers_sent)) {
            if (!self::isTest()) {
                header_remove($header);
            }
            unset(self::$headers_sent[$key]);
        }
    }

    /**
     * Method that sets the cookie headers
     * @param $cookies
     */
    public static function setCookieHeaders($cookies): void
    {
        if (!empty($cookies) && is_array($cookies) && false === headers_sent() && !self::isTest()) {
            $isSecureRequest = self::isSecureRequest();
            $defaultDomain = Request::getInstance()->getServerName();
            foreach ($cookies as $cookie) {
                if (!is_array($cookie)) {
                    continue;
                }
                $payload = ResponseCookieHelper::buildCookiePayload($cookie, $isSecureRequest, $defaultDomain);
                if (null === $payload) {
                    continue;
                }
                setcookie($payload['name'], $payload['value'], $payload['options']);
            }
        }
    }

    public static function normalizeCookieDomain(?string $domain): ?string
    {
        return ResponseCookieHelper::normalizeCookieDomain($domain);
    }

    public static function normalizeSameSite(?string $sameSite): string
    {
        return ResponseCookieHelper::normalizeSameSite($sameSite);
    }

    public static function isSecureRequest(): bool
    {
        if (Config::getParam('force.https', false)) {
            return true;
        }

        $request = Request::getInstance();
        $https = strtolower((string)$request->getServer('HTTPS', ''));
        if (!empty($https) && $https !== 'off') {
            return true;
        }

        $scheme = strtolower((string)$request->getServer('REQUEST_SCHEME', ''));
        if ($scheme === 'https') {
            return true;
        }

        $forwardedProto = strtolower((string)$request->getServer('HTTP_X_FORWARDED_PROTO', ''));
        return str_contains($forwardedProto, 'https');
    }

    /**
     * Método que inyecta las cabeceras necesarias para la autenticación
     * @param boolean $isPublic
     */
    public static function setAuthHeaders(bool $isPublic = true): void
    {
        if ($isPublic) {
            ServerHelper::dropServerValue("PHP_AUTH_USER");
            ServerHelper::dropServerValue("PHP_AUTH_PW");
            self::dropHeader("Authorization");
        } elseif (!self::isTest()) {
            self::setHeader('Authorization:');
        }
    }

    /**
     * Método que establece el status code
     * @param string|null $statusCode
     */
    public static function setStatusHeader(string $statusCode = null): void
    {
        if (NULL !== $statusCode && !self::isTest()) {
            self::setHeader($statusCode);
        }
    }

    /**
     * Método que mete en las variables de las plantillas las cabeceras de debug
     * @param array $vars
     *
     * @return array
     */
    public static function setDebugHeaders(array $vars): array
    {
        if ((Config::getParam('debug', true) || Config::getParam('profiling.enable', false)) && !self::isTest()) {
            Logger::log('Adding debug headers to render response');
            $vars["__DEBUG__"]["includes"] = get_included_files();
            $vars["__DEBUG__"]["trace"] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            self::setHeader('X-PSFS-DEBUG-TS: ' . Dispatcher::getInstance()->getTs() . ' s');
            self::setHeader('X-PSFS-DEBUG-MEM: ' . Dispatcher::getInstance()->getMem('MBytes') . ' MBytes');
            self::setHeader('X-PSFS-DEBUG-FILES: ' . count(get_included_files()) . ' files opened');
        }

        return $vars;
    }

    /**
     * @param Exception|NULL $exception
     * @param bool $isJson
     * @return int|string
     * @throws GeneratorException
     */
    public static function httpNotFound(\Throwable $exception = null, bool $isJson = false): int|string
    {
        if (self::isTest()) {
            return 404;
        }
        Inspector::stats('[Router] Throw not found exception', Inspector::SCOPE_DEBUG);
        if (null === $exception) {
            Logger::log('Not found page thrown without previous exception', LOG_WARNING);
            $exception = new Exception(t('Page not found'), 404);
        }
        $template = Template::getInstance()->setStatus($exception->getCode());
        if (self::shouldReturnJsonNotFound($isJson)) {
            $response = new JsonResponse(null, false, 0, 0, $exception->getMessage());
            return $template->output(json_encode($response), 'application/json');
        }

        $notFoundRoute = Config::getParam('route.404');
        if (null !== $notFoundRoute) {
            Request::getInstance()->redirect(Router::getInstance()->getRoute($notFoundRoute, true));
        } else {
            return $template->render('error.html.twig', array(
                'exception' => $exception,
                'trace' => $exception->getTraceAsString(),
                'error_page' => true,
            ));
        }
        return 200;
    }

    public static function shouldReturnJsonNotFound(bool $isJson = false): bool
    {
        if ($isJson) {
            return true;
        }

        $request = Request::getInstance();
        $contentType = strtolower((string)$request->getServer('CONTENT_TYPE', ''));
        $accept = strtolower((string)$request->getServer('HTTP_ACCEPT', ''));

        return str_contains($contentType, 'json') || str_contains($accept, 'json');
    }
}
