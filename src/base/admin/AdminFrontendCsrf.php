<?php

namespace PSFS\base\admin;

use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\exception\ApiException;

/** Session-bound CSRF token for JSON Admin v2 mutations. */
final class AdminFrontendCsrf
{
    public const HEADER = 'X-PSFS-CSRF';
    private const SESSION_KEY = 'admin_frontend_v2_csrf';

    public static function issue(): string
    {
        $security = Security::getInstance();
        $token = (string) $security->getSessionKey(self::SESSION_KEY);
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $security->setSessionKey(self::SESSION_KEY, $token)->updateSession();
        }

        return $token;
    }

    public static function assertValid(): void
    {
        if (Security::isTest()) {
            return;
        }
        $expected = (string) Security::getInstance()->getSessionKey(self::SESSION_KEY);
        $actual = (string) Request::header(self::HEADER, '');
        if ($expected === '' || $actual === '' || !hash_equals($expected, $actual)) {
            throw new ApiException(t('Invalid CSRF token'), 403);
        }
    }
}
