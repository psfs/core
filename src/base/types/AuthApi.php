<?php

namespace PSFS\base\types;

use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\base\types\traits\SecureTrait;

/**
 * @package PSFS\base\types
 */
abstract class AuthApi extends Api
{
    use SecureTrait;

    private const LEGACY_QUERY_TOKEN_PARAM = 'API_TOKEN';
    private const DEFAULT_API_TOKEN_COOKIE = 'X-API-SEC-TOKEN';
    private const TOKEN_SOURCE_HEADER = 'header';
    private const TOKEN_SOURCE_COOKIE = 'cookie';
    private const TOKEN_SOURCE_QUERY_LEGACY = 'query_legacy';
    private static bool $legacyTokenWarningLogged = false;
    private static array $tokenSourceTelemetry = [];
    private static array $invalidTokenTelemetry = [];

    public function init()
    {
        parent::init();
        if (!$this->checkAuth()) {
            throw new ApiException(t('Not authorized'), 401);
        }
    }

    /**
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
     * @return string
     */
    private function resolveApiToken(): string
    {
        $request = Request::getInstance();
        $token = $this->sanitizeToken($this->extractHeaderToken($request), self::TOKEN_SOURCE_HEADER);
        if (!empty($token)) {
            self::trackTokenSource(self::TOKEN_SOURCE_HEADER);
            return $token;
        }

        $cookieName = Config::getParam('api.token.cookie', self::DEFAULT_API_TOKEN_COOKIE);
        $cookieToken = $this->sanitizeToken((string)$request->getCookie($cookieName), self::TOKEN_SOURCE_COOKIE);
        if (!empty($cookieToken)) {
            self::trackTokenSource(self::TOKEN_SOURCE_COOKIE);
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

        $legacyToken = $this->sanitizeToken(
            (string)$this->query[self::LEGACY_QUERY_TOKEN_PARAM],
            self::TOKEN_SOURCE_QUERY_LEGACY
        );
        if ($legacyToken !== '') {
            self::trackTokenSource(self::TOKEN_SOURCE_QUERY_LEGACY);
        }
        return $legacyToken;
    }

    /**
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

    private function sanitizeToken(string $token, string $source): string
    {
        $token = trim($token);
        if ('' === $token) {
            return '';
        }

        // Reject control chars and whitespace to avoid header/query parsing ambiguity.
        if (preg_match('/[\x00-\x1F\x7F\s]/', $token) === 1) {
            if (!array_key_exists($source, self::$invalidTokenTelemetry)) {
                self::$invalidTokenTelemetry[$source] = true;
                Logger::log('[AuthApi][InvalidToken] rejected malformed token', LOG_WARNING, ['source' => $source]);
            }
            return '';
        }

        return $token;
    }

    private static function trackTokenSource(string $source): void
    {
        self::$tokenSourceTelemetry[$source] = (self::$tokenSourceTelemetry[$source] ?? 0) + 1;
    }

    /**
     * @return array{sources:array<string,int>,invalid:array<string,bool>}
     */
    public static function getTokenTelemetry(): array
    {
        return [
            'sources' => self::$tokenSourceTelemetry,
            'invalid' => self::$invalidTokenTelemetry,
        ];
    }

    public static function resetTokenTelemetry(): void
    {
        self::$tokenSourceTelemetry = [];
        self::$invalidTokenTelemetry = [];
        self::$legacyTokenWarningLogged = false;
    }
}
