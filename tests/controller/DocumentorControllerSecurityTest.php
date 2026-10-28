<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PSFS\controller\DocumentorController;

class DocumentorControllerSecurityTest extends TestCase
{
    private const ENV = ['PSFS_API_DOC_MEMORY_LIMIT', 'PSFS_API_DOC_MAX_EXECUTION_TIME'];

    private string $memoryLimit;
    private string $maxExecutionTime;

    protected function setUp(): void
    {
        $this->memoryLimit = (string)ini_get('memory_limit');
        $this->maxExecutionTime = (string)ini_get('max_execution_time');
        $this->clearEnv();
        (new \ReflectionProperty(DocumentorController::class, 'docsCache'))->setValue(null, []);
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->memoryLimit);
        ini_set('max_execution_time', $this->maxExecutionTime);
        $this->clearEnv();
        (new \ReflectionProperty(DocumentorController::class, 'docsCache'))->setValue(null, []);
    }

    private function clearEnv(): void
    {
        foreach (self::ENV as $name) {
            putenv($name);
        }
    }

    private function controller(): DocumentorController
    {
        return (new \ReflectionClass(DocumentorController::class))->newInstanceWithoutConstructor();
    }

    private function prepare(): void
    {
        (new \ReflectionMethod(DocumentorController::class, 'prepareDocumentationRuntime'))->invoke($this->controller());
    }

    public static function knownTypes(): array
    {
        return [['psfs'], ['swagger'], ['postman'], ['openapi'], ['html'], ['SWAGGER'], [' Postman ']];
    }

    #[DataProvider('knownTypes')]
    public function testKnownDocumentationTypesAreAccepted(string $type): void
    {
        self::assertSame(strtolower(trim($type)), DocumentorController::resolveDocType($type));
    }

    public function testUnknownTypesFallBackToTheDefaultOne(): void
    {
        foreach (['random-' . uniqid(), '../../etc/passwd', '<script>', '', 'swagger2'] as $type) {
            self::assertSame('psfs', DocumentorController::resolveDocType($type), $type);
        }
    }

    public function testTheInProcessCacheIsBounded(): void
    {
        $store = new \ReflectionMethod(DocumentorController::class, 'storeApiDoc');
        $controller = $this->controller();

        for ($i = 0; $i < 60; $i++) {
            $store->invoke($controller, ['type' => 'swagger', 'cache_ttl' => 300, 'cache_key' => 'domain:swagger:' . $i], ['n' => $i]);
        }

        $cache = (new \ReflectionProperty(DocumentorController::class, 'docsCache'))->getValue();
        self::assertCount(16, $cache);
        self::assertArrayHasKey('domain:swagger:59', $cache, 'The newest entries are kept');
        self::assertArrayNotHasKey('domain:swagger:0', $cache, 'The oldest entries are evicted');
    }

    public function testACachedDocumentIsServedUntilItExpires(): void
    {
        $cached = new \ReflectionMethod(DocumentorController::class, 'cachedApiDoc');
        $property = new \ReflectionProperty(DocumentorController::class, 'docsCache');
        $request = ['cache_ttl' => 300, 'download' => false, 'cache_key' => 'domain:swagger:v1'];
        $controller = $this->controller();

        self::assertNull($cached->invoke($controller, $request), 'nothing cached yet');

        $property->setValue(null, ['domain:swagger:v1' => ['doc' => ['info' => 'cached'], 'expires' => time() + 60]]);
        self::assertSame(['info' => 'cached'], $cached->invoke($controller, $request));
        self::assertNull($cached->invoke($controller, array_merge($request, ['download' => true])), 'downloads are never served from the cache');
        self::assertNull($cached->invoke($controller, array_merge($request, ['cache_ttl' => 0])), 'a zero ttl disables the cache');

        $property->setValue(null, ['domain:swagger:v1' => ['doc' => ['info' => 'old'], 'expires' => time() - 1]]);
        self::assertNull($cached->invoke($controller, $request), 'an expired document is dropped');
        self::assertArrayNotHasKey('domain:swagger:v1', $property->getValue());
    }

    public function testMemoryIsRaisedToABoundedValueAndNotToUnlimited(): void
    {
        ini_set('memory_limit', '128M');

        $this->prepare();

        self::assertSame('512M', ini_get('memory_limit'));
    }

    public function testAHigherMemoryLimitIsNeverLowered(): void
    {
        ini_set('memory_limit', '2G');

        $this->prepare();

        self::assertSame('2G', ini_get('memory_limit'));
    }

    public function testMemoryLimitIsConfigurableAndUnlimitedIsTheDeprecatedSwitch(): void
    {
        ini_set('memory_limit', '128M');
        putenv('PSFS_API_DOC_MEMORY_LIMIT=1G');
        $this->prepare();
        self::assertSame('1G', ini_get('memory_limit'));

        ini_set('memory_limit', '128M');
        putenv('PSFS_API_DOC_MEMORY_LIMIT=-1');
        $this->prepare();
        self::assertSame('-1', ini_get('memory_limit'));
    }

    public function testExecutionTimeIsBoundedUnlessLegacySwitchIsUsed(): void
    {
        ini_set('max_execution_time', '30');
        $this->prepare();
        self::assertSame('120', ini_get('max_execution_time'));

        putenv('PSFS_API_DOC_MAX_EXECUTION_TIME=0');
        $this->prepare();
        self::assertSame('0', ini_get('max_execution_time'));
    }
}
