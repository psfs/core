<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\CompatHelper;
use PSFS\base\types\helpers\SessionCookieHelper;

class SessionCookieHelperTest extends TestCase
{
    private const ENV = ['PSFS_SESSION_COOKIE_SAMESITE', 'PSFS_SESSION_COOKIE_SECURE'];
    private const CURRENT = ['lifetime' => 36000, 'path' => '/old', 'domain' => 'example.test', 'secure' => false, 'httponly' => false];

    protected function setUp(): void
    {
        $this->clearEnv();
        CompatHelper::reset();
    }

    protected function tearDown(): void
    {
        $this->clearEnv();
        CompatHelper::reset();
    }

    private function clearEnv(): void
    {
        foreach (self::ENV as $name) {
            putenv($name);
        }
    }

    public function testDefaultsFollowTheAuthenticationContract(): void
    {
        $params = SessionCookieHelper::buildParams(self::CURRENT, true);

        self::assertTrue($params['httponly']);
        self::assertTrue($params['secure']);
        self::assertSame('/', $params['path']);
        self::assertSame('Lax', $params['samesite']);
        self::assertSame(36000, $params['lifetime']);
        self::assertSame('example.test', $params['domain']);
    }

    public function testSecureFollowsTheRequestSchemeInAutoMode(): void
    {
        self::assertFalse(SessionCookieHelper::buildParams(self::CURRENT, false)['secure']);
        self::assertTrue(SessionCookieHelper::buildParams(self::CURRENT, true)['secure']);
    }

    public function testSecureCanBeForcedEitherWay(): void
    {
        putenv('PSFS_SESSION_COOKIE_SECURE=true');
        self::assertTrue(SessionCookieHelper::buildParams(self::CURRENT, false)['secure']);

        putenv('PSFS_SESSION_COOKIE_SECURE=false');
        self::assertFalse(SessionCookieHelper::buildParams(self::CURRENT, true)['secure']);
    }

    public function testSameSiteValuesAreNormalisedAndInvalidOnesFallBackToLax(): void
    {
        foreach (['strict' => 'Strict', 'LAX' => 'Lax', 'Strict' => 'Strict', 'nonsense' => 'Lax'] as $configured => $expected) {
            putenv('PSFS_SESSION_COOKIE_SAMESITE=' . $configured);
            self::assertSame($expected, SessionCookieHelper::buildParams(self::CURRENT, true)['samesite'], $configured);
        }
    }

    public function testSameSiteNoneNeedsASecureCookie(): void
    {
        putenv('PSFS_SESSION_COOKIE_SAMESITE=None');
        self::assertSame('None', SessionCookieHelper::buildParams(self::CURRENT, true)['samesite']);
        self::assertSame('Lax', SessionCookieHelper::buildParams(self::CURRENT, false)['samesite']);
    }

    public function testLegacySwitchLeavesSameSiteUnset(): void
    {
        putenv('PSFS_SESSION_COOKIE_SAMESITE=legacy');
        $params = SessionCookieHelper::buildParams(self::CURRENT, true);

        self::assertArrayNotHasKey('samesite', $params);
        self::assertTrue($params['httponly'], 'HttpOnly is not part of the legacy switch');
    }

    public function testApplyDoesNothingOnceTheSessionIsActiveOrHeadersWereSent(): void
    {
        // PHPUnit has already written output, so headers are sent: the cookie parameters must stay untouched
        $before = session_get_cookie_params();
        self::assertFalse(SessionCookieHelper::apply(true));
        self::assertSame($before, session_get_cookie_params());
    }
}
