<?php

namespace PSFS\base\types;

use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\base\types\traits\SecureTrait;

/**
 * Class AuthApi
 * @package PSFS\base\types
 */
abstract class AuthApi extends Api
{
    use SecureTrait;
    private const LEGACY_QUERY_TOKEN_PARAM = 'API_TOKEN';
    private const DEFAULT_API_TOKEN_COOKIE = 'X-API-SEC-TOKEN';
    private static bool $legacyTokenWarningLogged = false;

    public function init()
    {
        parent::init();
        if (!$this->checkAuth()) {
            throw new ApiException(t('Not authorized'), 401);
        }
    }

    /**
     * Check service authentication
     * @return bool
     */
    private function checkAuth()
    {
        $namespace = explode('\\', $this->getModelTableMap());
        $module = strtolower($namespace[0]);
        $secret = Config::getInstance()->get($module . '.api.secret');
        if (null === $secret) {
            $secret = Config::getInstance()->get("api.secret");
        }
        if (null === $secret) {
            $auth = true;
        } else {
            $token = $this->resolveApiToken();
            $auth = SecurityHelper::checkToken($token ?: '', $secret, $module);
        }

        return $auth || $this->isAdmin();
    }

    /**
     * Resolve API token from secure transport first.
     * Header and secure cookies are preferred. Query string token is deprecated and controlled.
     * @return string
     */
    private function resolveApiToken(): string
    {
        $request = Request::getInstance();
        $token = $this->extractHeaderToken($request);
        if (!empty($token)) {
            return $token;
        }

        $cookieName = Config::getParam('api.token.cookie', self::DEFAULT_API_TOKEN_COOKIE);
        $cookieToken = $request->getCookie($cookieName);
        if (!empty($cookieToken)) {
            return $cookieToken;
        }

        if (!array_key_exists(self::LEGACY_QUERY_TOKEN_PARAM, $this->query)) {
            return '';
        }

        $legacyCompat = (bool)Config::getParam('api.query_token.compat', true);
        if (!$legacyCompat) {
            Logger::log(
                '[AuthApi] Legacy API token in query string has been rejected by policy',
                LOG_WARNING,
                ['param' => self::LEGACY_QUERY_TOKEN_PARAM, 'route' => Request::requestUri()]
            );
            return '';
        }

        if (!self::$legacyTokenWarningLogged) {
            Logger::log(
                '[AuthApi] API token in query string is deprecated. Use header X-API-SEC-TOKEN or secure cookie.',
                LOG_WARNING,
                ['param' => self::LEGACY_QUERY_TOKEN_PARAM, 'route' => Request::requestUri()]
            );
            self::$legacyTokenWarningLogged = true;
        }

        return (string)$this->query[self::LEGACY_QUERY_TOKEN_PARAM];
    }

    /**
     * Read header only from transport headers/server vars, not from query parameter shims.
     * @param Request $request
     * @return string
     */
    private function extractHeaderToken(Request $request): string
    {
        $token = $request->getServer('HTTP_X_API_SEC_TOKEN');
        if (is_string($token) && $token !== '') {
            return $token;
        }
        if ($request->hasHeader(self::HEADER_API_TOKEN)) {
            return (string)($request->getHeader(self::HEADER_API_TOKEN) ?: '');
        }
        return '';
    }
}
