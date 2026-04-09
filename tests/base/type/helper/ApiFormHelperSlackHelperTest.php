<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\ApiFormHelper;
use PSFS\base\types\helpers\SlackHelper;

class ApiFormHelperSlackHelperTest extends TestCase
{
    private array $configBackup = [];
    private array $serverBackup = [];
    private array $requestBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        $this->serverBackup = $_SERVER ?? [];
        $this->requestBackup = $_REQUEST ?? [];

        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/demo/notify',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
        ];

        Request::dropInstance();
        Request::getInstance()->init();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        Request::dropInstance();
        $_SERVER = $this->serverBackup;
        $_REQUEST = $this->requestBackup;
    }

    public function testCheckApiActionsBuildsFormActionsFromReflectionMetadata(): void
    {
        $actions = ApiFormHelper::checkApiActions(ApiFormHelperActionFixture::class, 'ROOT', 'demo');

        $this->assertCount(1, $actions);
        $this->assertSame('POST', $actions[0]->method);
        $this->assertSame('/admin/ROOT/demo/{id}', $actions[0]->url);
        $this->assertSame('Create item', $actions[0]->label);
    }

    public function testSlackTraceBuildsExpectedPayloadWithoutDoingNetworkCall(): void
    {
        Config::save([
            'log.slack.hook' => 'https://hooks.slack.test/demo',
            'debug' => true,
        ], []);
        Config::getInstance()->loadConfigData(true);

        $_REQUEST = ['password' => 'secret-value'];
        Request::dropInstance();
        Request::getInstance()->init();

        $helper = new SlackHelperProbe();
        $helper->trace('Boom', '/tmp/file.php', 77, ['token' => 'abc']);

        $this->assertTrue($helper->called);
        $this->assertSame('https://hooks.slack.test/demo', $helper->getUrl());
        $this->assertSame('POST', strtoupper((string)$helper->getType()));

        $params = $helper->getParams();
        $this->assertSame('PSFS Error notifier', $params['text'] ?? null);
        $attachment = $params['attachments'][0] ?? [];
        $this->assertSame('warning', $attachment['color'] ?? null);
        $this->assertSame('Boom', $attachment['title'] ?? null);
        $this->assertStringContainsString('/tmp/file.php [77]', (string)($attachment['text'] ?? ''));
        $this->assertNotEmpty($attachment['fields'][2]['value'] ?? null);
        $this->assertNotEmpty($attachment['fields'][3]['value'] ?? null);
    }
}

class ApiFormHelperActionFixture
{
    /**
     * @action create
     * @POST
     * @route /admin/{__DOMAIN__}/{__API__}/{id}
     * @label Create item
     */
    public function create(int $id = 15): void
    {
    }
}

class SlackHelperProbe extends SlackHelper
{
    public bool $called = false;

    public function callSrv()
    {
        $this->called = true;
    }
}
