<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Security;
use PSFS\base\config\Config;
use PSFS\base\types\Controller;
use PSFS\controller\AdminFrontendController;

final class AdminFrontendControllerTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        Security::setTest(true);
        Controller::setTest(true);
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        Security::setTest(false);
        Security::dropInstance();
        Controller::setTest(false);
    }

    public function testBootstrapPublishesTheAuthenticatedSpaContract(): void
    {
        $config = $this->configBackup;
        $config['default.language'] = 'es_ES';
        $config['i18n.locales'] = 'en_US, es_ES, invalid-locale, en_US';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $controller = new AdminFrontendController();
        $sentinel = static fn(): bool => false;
        set_error_handler($sentinel);
        try {
            $response = json_decode($controller->bootstrap(), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            $previousHandler = set_error_handler($sentinel);
            restore_error_handler();
            if ($previousHandler !== $sentinel) {
                restore_error_handler();
            }
            restore_error_handler();
        }

        self::assertSame('HTTP/1.0 200 OK', $controller->getStatusCode());
        self::assertSame('', $response['identity']['username']);
        self::assertSame('es_ES', $response['locale']);
        self::assertSame(['en_US', 'es_ES'], $response['locales']);
        self::assertIsString($response['csrfToken']);
        self::assertArrayHasKey('menu', $response);
    }

    public function testChangeLocaleRejectsUnsupportedLocale(): void
    {
        $config = $this->configBackup;
        $config['i18n.locales'] = 'en_US,es_ES';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $controller = new AdminFrontendController();
        $response = json_decode($controller->changeLocale('fr_FR'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('HTTP/1.0 422', $controller->getStatusCode());
        self::assertFalse($response['ok']);
        self::assertSame(['Invalid locale'], $response['errors']['locale']);
    }

    public function testChangeLocalePersistsAConfiguredLocale(): void
    {
        $config = $this->configBackup;
        $config['i18n.locales'] = 'en_US,es_ES';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $controller = new AdminFrontendController();
        $response = json_decode($controller->changeLocale('es_ES'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('HTTP/1.0 200 OK', $controller->getStatusCode());
        self::assertTrue($response['ok']);
        self::assertSame(['locale' => 'es_ES'], $response['data']);
        self::assertSame([], $response['errors']);
    }
}
