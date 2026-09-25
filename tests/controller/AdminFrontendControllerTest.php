<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\controller\AdminFrontendController;

class AdminFrontendControllerTest extends TestCase
{
    protected function setUp(): void
    {
        AdminFrontendController::setTest(true);
        Security::setTest(true);
        Router::dropInstance();
    }

    protected function tearDown(): void
    {
        AdminFrontendController::setTest(false);
        Security::setTest(false);
        Security::dropInstance();
        Router::dropInstance();
    }

    #[RunInSeparateProcess]
    public function testBootstrapReturnsTheAdminShellContract(): void
    {
        $response = json_decode((new AdminFrontendController())->bootstrap(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('identity', $response);
        self::assertArrayHasKey('locale', $response);
        self::assertNotEmpty($response['locales']);
        self::assertNotEmpty($response['csrfToken']);
        self::assertIsArray($response['menu']);
        restore_error_handler();
    }

    #[RunInSeparateProcess]
    public function testInvalidLocaleReturnsTheValidationEnvelope(): void
    {
        $response = json_decode((new AdminFrontendController())->changeLocale('xx_XX'), true, 512, JSON_THROW_ON_ERROR);

        self::assertFalse($response['ok']);
        self::assertNull($response['data']);
        self::assertSame(['locale' => ['Invalid locale']], $response['errors']);
        restore_error_handler();
    }

    #[RunInSeparateProcess]
    public function testValidLocaleReturnsTheUpdatedLocaleEnvelope(): void
    {
        $response = json_decode((new AdminFrontendController())->changeLocale('en_US'), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok']);
        self::assertSame(['locale' => 'en_US'], $response['data']);
        self::assertSame([], $response['errors']);
        restore_error_handler();
    }
}
