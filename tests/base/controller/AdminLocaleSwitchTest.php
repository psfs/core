<?php

namespace PSFS\tests\base\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\controller\UserController;

class AdminLocaleSwitchTest extends TestCase
{
    private array $serverBackup = [];
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->sessionBackup = $_SESSION ?? [];

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/admin/locale/es_ES',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
            'HTTP_REFERER' => 'http://localhost:8080/admin/setup',
        ];
        $_SESSION = [];

        ResponseHelper::setTest(true);
        ResponseHelper::$headers_sent = [];
        Request::dropInstance();
        Security::dropInstance();
        Request::getInstance()->init();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_SESSION = $this->sessionBackup;
        ResponseHelper::$headers_sent = [];
        Request::dropInstance();
        Security::dropInstance();
    }

    public function testSwitchAdminLocalePersistsSessionAndRedirectsToReferer(): void
    {
        UserController::getInstance('switchAdminLocale')->switchAdminLocale('es_ES');

        $security = Security::getInstance();
        $this->assertSame('es', $security->getSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY));
        $this->assertSame('es_ES', $security->getSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY));
        $this->assertSame('http://localhost:8080/admin/setup', ResponseHelper::$headers_sent['location'] ?? null);
    }

    public function testSwitchAdminLocaleFallsBackToDefaultForInvalidValues(): void
    {
        UserController::getInstance('switchAdminLocale')->switchAdminLocale('bad-locale');

        $security = Security::getInstance();
        $this->assertSame('en', $security->getSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY));
        $this->assertSame('en_US', $security->getSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY));
    }

    public function testSwitchAdminLocaleNormalizesLanguageAlias(): void
    {
        UserController::getInstance('switchAdminLocale')->switchAdminLocale('es');

        $security = Security::getInstance();
        $this->assertSame('es', $security->getSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY));
        $this->assertSame('es_ES', $security->getSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY));
    }
}
