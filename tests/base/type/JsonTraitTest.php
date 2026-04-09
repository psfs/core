<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\types\traits\JsonTrait;

#[RunTestsInSeparateProcesses]
class JsonTraitTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
    }

    public function testJsonEncodesArrayAndSetsStatusAndContentType(): void
    {
        Config::save([
            'profiling.enable' => false,
            'output.json.strict_numbers' => false,
            'json.encodeUTF8' => false,
        ], []);
        Config::getInstance()->loadConfigData(true);

        $harness = new JsonTraitHarness();
        $payload = ['id' => '123', 'name' => 'Neo'];
        $result = $harness->json($payload, 201);

        $this->assertJson($result);
        $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($payload, $decoded);
        $this->assertSame('application/json', $harness->lastContentType);
        $this->assertSame(201, $harness->lastStatus);
    }

    public function testJsonSupportsJsonResponseAndProfilingWrapperAndStrictNumbers(): void
    {
        Config::save([
            'profiling.enable' => true,
            'output.json.strict_numbers' => true,
            'json.encodeUTF8' => false,
            'log.level' => 'DEBUG',
        ], []);
        Config::getInstance()->loadConfigData(true);

        $harness = new JsonTraitHarness();
        $response = new JsonResponse(['value' => '42'], true);
        $result = $harness->json($response, 200);

        $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertArrayHasKey('profiling', $decoded);
    }

    public function testJsonpUsesJavascriptContentType(): void
    {
        Config::save(['profiling.enable' => false], []);
        Config::getInstance()->loadConfigData(true);

        $harness = new JsonTraitHarness();
        $harness->jsonp(['ok' => true]);

        $this->assertSame('application/javascript', $harness->lastContentType);
        $this->assertJson($harness->lastOutput);
    }
}

class JsonTraitHarness
{
    use JsonTrait;

    public string $lastOutput = '';
    public string $lastContentType = '';
    public int $lastStatus = 0;

    public function output($output = '', $contentType = 'text/html', array $cookies = array())
    {
        $this->lastOutput = (string)$output;
        $this->lastContentType = (string)$contentType;
        return $this->lastOutput;
    }

    public function setStatus($status = null)
    {
        $this->lastStatus = (int)$status;
        return $this;
    }
}
