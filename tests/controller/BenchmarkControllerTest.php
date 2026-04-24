<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\TestCase;
use PSFS\controller\BenchmarkController;
use PSFS\controller\ConfigController;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Route;
use ReflectionMethod;

class BenchmarkControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('PSFS_BENCHMARK_ENABLED');
        unset($_ENV['PSFS_BENCHMARK_ENABLED'], $_SERVER['PSFS_BENCHMARK_ENABLED']);
    }

    public function testBenchmarkEnableFlagRequiresOne(): void
    {
        putenv('PSFS_BENCHMARK_ENABLED=0');
        $this->assertFalse(BenchmarkController::isBenchmarkEnabled());

        putenv('PSFS_BENCHMARK_ENABLED=1');
        $this->assertTrue(BenchmarkController::isBenchmarkEnabled());
    }

    public function testRouteAttributesAreDeclared(): void
    {
        $ping = new ReflectionMethod(BenchmarkController::class, 'ping');
        $meta = new ReflectionMethod(BenchmarkController::class, 'metadata');

        $this->assertSame('/_bench/ping', $ping->getAttributes(Route::class)[0]->newInstance()->value);
        $this->assertSame('/_bench/metadata', $meta->getAttributes(Route::class)[0]->newInstance()->value);
        $this->assertSame('GET', $ping->getAttributes(HttpMethod::class)[0]->newInstance()->value);
        $this->assertSame('GET', $meta->getAttributes(HttpMethod::class)[0]->newInstance()->value);
    }

    public function testBuildMetadataPayloadResolvesRuntimeMetadata(): void
    {
        $payload = BenchmarkController::buildMetadataPayload();
        $method = new ReflectionMethod(ConfigController::class, 'config');

        $this->assertTrue($payload['ok']);
        $this->assertIsArray($payload['engine_stats']);
        $this->assertSame('/admin/config', $payload['route']);
        $this->assertSame('GET', $payload['http']);
        $this->assertFalse($payload['deprecated']);
        $this->assertSame('config', $method->getName());
    }

    public function testPingAndMetadataMethodsReturn404WhenBenchmarkDisabled(): void
    {
        putenv('PSFS_BENCHMARK_ENABLED=0');
        $probe = $this->newProbeController();

        $ping = $probe->ping();
        $metadata = $probe->metadata();

        $this->assertSame(404, $ping['status']);
        $this->assertSame('benchmark_disabled', $ping['response']['error']);
        $this->assertSame(404, $metadata['status']);
        $this->assertSame('benchmark_disabled', $metadata['response']['error']);
    }

    public function testPingAndMetadataMethodsReturn200WhenBenchmarkEnabled(): void
    {
        putenv('PSFS_BENCHMARK_ENABLED=1');
        $probe = $this->newProbeController();

        $ping = $probe->ping();
        $metadata = $probe->metadata();

        $this->assertSame(200, $ping['status']);
        $this->assertTrue($ping['response']['ok']);
        $this->assertSame(200, $metadata['status']);
        $this->assertTrue($metadata['response']['ok']);
        $this->assertArrayHasKey('engine_stats', $metadata['response']);
    }

    private function newProbeController(): BenchmarkController
    {
        $class = new class extends BenchmarkController {
            public function json($response, $statusCode = 200)
            {
                return ['status' => $statusCode, 'response' => $response];
            }
        };
        $reflection = new \ReflectionClass($class);
        /** @var BenchmarkController $instance */
        $instance = $reflection->newInstanceWithoutConstructor();
        return $instance;
    }
}
