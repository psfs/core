<?php

namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\types\traits\Helper\ResponseNotFoundTrait;
use PSFS\base\types\traits\Helper\ResponseRequestTrait;
use PSFS\base\types\traits\TestTrait;
use PSFS\Dispatcher;

class ResponseHelper
{
    use TestTrait;
    use ResponseRequestTrait;
    use ResponseNotFoundTrait;

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


    /**
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
     * @param string|null $statusCode
 */
    public static function setStatusHeader(string $statusCode = null): void
    {
        if (NULL !== $statusCode && !self::isTest()) {
            self::setHeader($statusCode);
        }
    }

    /**
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

}
