<?php

namespace PSFS\tests\base\benchmark;

use PHPUnit\Framework\TestCase;
use PSFS\base\benchmark\HttpLoadRunner;
use PSFS\base\benchmark\RuntimeMatrixRunner;
use RuntimeException;

class RuntimeMatrixRunnerTest extends TestCase
{
    private string $tmpRoot;
    private string $configFile;
    private string $composeFile;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-runtime-matrix-' . uniqid('', true);
        mkdir($this->tmpRoot, 0775, true);
        $this->outputDir = $this->tmpRoot . DIRECTORY_SEPARATOR . 'out';
        $this->configFile = $this->tmpRoot . DIRECTORY_SEPARATOR . 'config.json';
        $this->composeFile = $this->tmpRoot . DIRECTORY_SEPARATOR . 'docker-compose.yml';

        file_put_contents($this->configFile, json_encode(['debug' => true], JSON_PRETTY_PRINT) . PHP_EOL);
        file_put_contents($this->composeFile, "services:\n  php:\n    image: test\n");
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmpRoot);
    }

    public function testBuildScenariosReturnsExpectedMatrixSize(): void
    {
        $runner = $this->newFakeRunner();
        $scenarios = $runner->buildScenarios();

        $this->assertCount(16, $scenarios);
        $ids = array_map(
            static fn (array $s) => implode('|', [$s['runtime'], (int)$s['debug'], (int)$s['opcache'], (int)$s['redis']]),
            $scenarios
        );
        $this->assertCount(16, array_unique($ids));
    }

    public function testRuntimeMapUsesProjectEnvPorts(): void
    {
        file_put_contents(
            $this->tmpRoot . DIRECTORY_SEPARATOR . '.env',
            "HOST_PORT=19008\nHOST_PORT_SWOOLE=19011\n"
        );

        $runner = $this->newFakeRunner();
        $property = new \ReflectionProperty(RuntimeMatrixRunner::class, 'runtimeMap');
        $runtimeMap = $property->getValue($runner);

        $this->assertSame('http://127.0.0.1:19008', $runtimeMap['php-s']['base_url']);
        $this->assertSame('http://127.0.0.1:19011', $runtimeMap['swoole']['base_url']);
    }

    public function testRunWritesReportsAndRestoresConfig(): void
    {
        $runner = $this->newFakeRunner();
        $report = $runner->run();

        $this->assertSame(16, $report['summary']['scenario_count']);
        $this->assertFileExists($this->outputDir . DIRECTORY_SEPARATOR . 'latest.json');
        $this->assertFileExists($this->outputDir . DIRECTORY_SEPARATOR . 'latest.md');

        $restored = json_decode((string)file_get_contents($this->configFile), true);
        $this->assertTrue($restored['debug']);
    }

    public function testRunInitializesMissingConfigFileForCleanCheckout(): void
    {
        unlink($this->configFile);

        $runner = $this->newFakeRunner();
        $report = $runner->run();

        $this->assertSame(16, $report['summary']['scenario_count']);
        $this->assertFileExists($this->configFile);
        $this->assertSame([], json_decode((string)file_get_contents($this->configFile), true));
    }

    public function testRunRestoresConfigWhenProfileFails(): void
    {
        $runner = $this->newFakeRunner(true);
        $before = (string)file_get_contents($this->configFile);

        try {
            $runner->run();
            self::fail('Expected RuntimeException not thrown');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('forced profile failure', $exception->getMessage());
        }

        $after = (string)file_get_contents($this->configFile);
        $this->assertSame($before, $after);
    }

    public function testHttpLoadAndPercentilesAreCalculated(): void
    {
        $loadRunner = new HttpLoadRunner(2);
        $direct = $loadRunner->run('data://text/plain,{"ok":true}', 2, 8, 'L1', 'scenario');
        $this->assertSame(8, $direct['completed']);
        $this->assertGreaterThan(0.0, $direct['error_rate']);
        $this->assertGreaterThanOrEqual(0.0, $loadRunner->percentile([1.0, 2.0, 3.0], 95));

        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $this->configFile,
            $this->outputDir,
            [['name' => 'L1', 'concurrency' => 2, 'requests' => 8]],
            1,
            2,
            2
        ) extends RuntimeMatrixRunner {
            public function exposeRunHttpLoad(string $url, int $concurrency, int $requests): array
            {
                return $this->runHttpLoad($url, $concurrency, $requests, 'L1', 'scenario');
            }

            public function exposePercentile(array $samples, int $percent): float
            {
                return $this->percentile($samples, $percent);
            }
        };

        $result = $runner->exposeRunHttpLoad('data://text/plain,{"ok":true}', 2, 8);

        $this->assertSame(8, $result['completed']);
        $this->assertGreaterThan(0.0, $result['error_rate']);
        $this->assertGreaterThanOrEqual(0.0, $runner->exposePercentile([1.0, 2.0, 3.0], 95));
    }

    public function testWaitForHealthAndContainerStatsHelpers(): void
    {
        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $this->configFile,
            $this->outputDir
        ) extends RuntimeMatrixRunner {
            public function exposeWaitForHealth(string $url, int $timeout): void
            {
                $this->waitForHealth($url, $timeout);
            }

            public function exposeCollectStats(string $service): array
            {
                return $this->collectContainerStats($service);
            }

            protected function runDockerComposeCommand(string $arguments, array $env = []): string
            {
                return '';
            }
        };

        $runner->exposeWaitForHealth('data://text/plain,{"ok":true}', 1);
        $stats = $runner->exposeCollectStats('php');
        $this->assertSame('n/a', $stats['cpu']);
        $this->assertSame('n/a', $stats['mem']);
    }

    public function testRunCommandWrapperThrowsOnFailure(): void
    {
        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $this->configFile,
            $this->outputDir
        ) extends RuntimeMatrixRunner {
            public function exposeRunCommand(string $command): string
            {
                return $this->runCommand($command);
            }
        };

        $output = $runner->exposeRunCommand('printf "ok"');
        $this->assertSame('ok', $output);

        $this->expectException(RuntimeException::class);
        $runner->exposeRunCommand('sh -lc "exit 7"');
    }

    public function testRunFailsWhenScenarioMatrixIsInvalid(): void
    {
        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $this->configFile,
            $this->outputDir
        ) extends RuntimeMatrixRunner {
            public function buildScenarios(): array
            {
                return [];
            }

            protected function prepareBenchmarkRoutes(): void
            {
            }

            protected function restoreRuntimeDefaults(string $opcache): void
            {
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expected 16');
        $runner->run();
    }

    public function testWaitForHealthFailsWhenResponseNeverBecomesHealthy(): void
    {
        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $this->configFile,
            $this->outputDir
        ) extends RuntimeMatrixRunner {
            public function exposeWaitForHealth(string $url, int $timeout): void
            {
                $this->waitForHealth($url, $timeout);
            }
        };

        $this->expectException(RuntimeException::class);
        $runner->exposeWaitForHealth('data://text/plain,{"ok":false}', 1);
    }

    public function testCollectContainerStatsParsesCpuAndMemory(): void
    {
        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $this->configFile,
            $this->outputDir
        ) extends RuntimeMatrixRunner {
            public function exposeCollectStats(string $service): array
            {
                return $this->collectContainerStats($service);
            }

            protected function runDockerComposeCommand(string $arguments, array $env = []): string
            {
                return "container-id\n";
            }

            protected function runCommand(string $command, array $env = []): string
            {
                return "12.50%|48.1MiB / 256MiB\n";
            }
        };

        $stats = $runner->exposeCollectStats('php');
        $this->assertSame('12.50%', $stats['cpu']);
        $this->assertSame('48.1MiB / 256MiB', $stats['mem']);
    }

    public function testWarmupLoadProfileAndComposeHelpersAreExercised(): void
    {
        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $this->configFile,
            $this->outputDir
        ) extends RuntimeMatrixRunner {
            public array $commands = [];

            public function exposeWarmup(): void
            {
                $this->warmup('http://127.0.0.1', 1, 1);
            }

            public function exposeLoadProfile(): array
            {
                return $this->runLoadProfile('http://127.0.0.1', 'L1', 's1', 1, 1);
            }

            public function exposeRecreate(): void
            {
                $this->recreateRuntimeService('php', true);
            }

            public function exposeRestore(): void
            {
                $this->restoreRuntimeDefaults('');
            }

            public function exposeCompose(): void
            {
                $this->runDockerComposeCommand('ps');
                $this->prepareBenchmarkRoutes();
            }

            protected function runHttpLoad(
                string $url,
                int $concurrency,
                int $requests,
                string $profileName,
                string $scenarioId
            ): array {
                return [
                    'profile' => $profileName,
                    'requests' => $requests,
                    'completed' => $requests,
                    'concurrency' => $concurrency,
                    'rps' => 10.0,
                    'p50_ms' => 1.0,
                    'p95_ms' => 2.0,
                    'p99_ms' => 3.0,
                    'error_count' => 0,
                    'error_rate' => 0.0,
                    'timeout_count' => 0,
                    'timeout_rate' => 0.0,
                    'bytes' => 100,
                    'duration_s' => 0.1,
                ];
            }

            protected function runCommand(string $command, array $env = []): string
            {
                $this->commands[] = $command;
                return '';
            }
        };

        $runner->exposeWarmup();
        $profile = $runner->exposeLoadProfile();
        $runner->exposeRecreate();
        $runner->exposeRestore();
        $runner->exposeCompose();

        $this->assertSame('L1', $profile['profile']);
        $this->assertNotEmpty($runner->commands);
    }

    public function testHttpLoadHandlesHttpErrorsAndTimeoutsAndEmptyPercentile(): void
    {
        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $this->configFile,
            $this->outputDir,
            null,
            1,
            2,
            1
        ) extends RuntimeMatrixRunner {
            public function exposeHttpLoad(string $url, int $concurrency, int $requests): array
            {
                return $this->runHttpLoad($url, $concurrency, $requests, 'L1', 's1');
            }

            public function exposePercentile(array $samples, int $percent): float
            {
                return $this->percentile($samples, $percent);
            }
        };

        $httpError = $runner->exposeHttpLoad('http://127.0.0.1:8080/not-found', 1, 2);
        $timeout = $runner->exposeHttpLoad('http://10.255.255.1/', 1, 1);

        $this->assertGreaterThan(0, $httpError['error_count']);
        $this->assertGreaterThanOrEqual(0, $timeout['timeout_count']);
        $this->assertSame(0.0, $runner->exposePercentile([], 95));
    }

    public function testApplyConfigAndValidationHelpersCoverNegativePaths(): void
    {
        file_put_contents($this->configFile, '{invalid-json');

        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $this->configFile,
            $this->outputDir
        ) extends RuntimeMatrixRunner {
            public function exposeApplyConfig(array $scenario): void
            {
                $this->applyConfigScenario($scenario);
            }

            public function exposeAssertCompleteness(array $scenarioResults): void
            {
                $this->assertScenarioCompleteness($scenarioResults);
            }

            public function exposeAssertErrors(array $scenarioResults): void
            {
                $this->assertNoHttpErrors($scenarioResults);
            }

            public function exposeCollectStats(string $service): array
            {
                return $this->collectContainerStats($service);
            }

            protected function runDockerComposeCommand(string $arguments, array $env = []): string
            {
                return "container-id\n";
            }

            protected function runCommand(string $command, array $env = []): string
            {
                return 'malformed-stats';
            }
        };

        $runner->exposeApplyConfig(['runtime' => 'php-s', 'debug' => false, 'opcache' => true, 'redis' => true]);
        $updated = json_decode((string)file_get_contents($this->configFile), true);
        $this->assertFalse($updated['debug']);
        $this->assertTrue($updated['psfs.redis']);
        $stats = $runner->exposeCollectStats('php');
        $this->assertSame('n/a', $stats['cpu']);
        $this->assertSame('n/a', $stats['mem']);

        $this->expectException(RuntimeException::class);
        $runner->exposeAssertCompleteness([]);
    }

    public function testValidationHelpersThrowOnErrors(): void
    {
        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $this->configFile,
            $this->outputDir
        ) extends RuntimeMatrixRunner {
            public function exposeAssertErrors(array $scenarioResults): void
            {
                $this->assertNoHttpErrors($scenarioResults);
            }

            public function exposeRead(): string
            {
                return $this->readConfigRaw();
            }

            public function exposeWrite(string $content): void
            {
                $this->writeConfigRaw($content);
            }
        };

        $scenario = [
            'scenario' => ['runtime' => 'php-s', 'debug' => false, 'opcache' => false, 'redis' => false],
            'profiles' => [['error_rate' => 0.1]],
        ];
        $this->expectException(RuntimeException::class);
        $runner->exposeAssertErrors([$scenario]);
    }

    public function testReadAndWriteConfigHelpersThrowForInvalidPaths(): void
    {
        $badFile = $this->tmpRoot . DIRECTORY_SEPARATOR . 'missing' . DIRECTORY_SEPARATOR . 'config.json';
        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $badFile,
            $this->outputDir
        ) extends RuntimeMatrixRunner {
            public function exposeRead(): string
            {
                return $this->readConfigRaw();
            }

            public function exposeWrite(string $content): void
            {
                $this->writeConfigRaw($content);
            }
        };

        try {
            $runner->exposeRead();
            self::fail('Expected read exception');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        try {
            $runner->exposeWrite('{}');
            self::fail('Expected write exception');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }

    private function newFakeRunner(bool $failOnProfile = false): RuntimeMatrixRunner
    {
        $runner = new class(
            $this->tmpRoot,
            $this->composeFile,
            $this->configFile,
            $this->outputDir,
            [
                ['name' => 'L1', 'concurrency' => 1, 'requests' => 10],
                ['name' => 'L2', 'concurrency' => 2, 'requests' => 10],
                ['name' => 'L3', 'concurrency' => 3, 'requests' => 10],
            ],
            3,
            3,
            2,
        ) extends RuntimeMatrixRunner {
            public bool $failOnProfile = false;

            protected function recreateRuntimeService(string $service, bool $opcache): void
            {
            }

            protected function prepareBenchmarkRoutes(): void
            {
            }

            protected function waitForHealth(string $url, int $timeoutSeconds): void
            {
            }

            protected function warmup(string $url, int $concurrency, int $requests): void
            {
            }

            protected function runLoadProfile(string $url, string $profileName, string $scenarioId, int $concurrency, int $requests): array
            {
                if ($this->failOnProfile && $profileName === 'L2') {
                    throw new RuntimeException('forced profile failure');
                }
                return [
                    'profile' => $profileName,
                    'requests' => $requests,
                    'completed' => $requests,
                    'concurrency' => $concurrency,
                    'rps' => 1200.0,
                    'p50_ms' => 1.1,
                    'p95_ms' => 2.2,
                    'p99_ms' => 3.3,
                    'error_count' => 0,
                    'error_rate' => 0.0,
                    'timeout_count' => 0,
                    'timeout_rate' => 0.0,
                    'bytes' => 1024,
                    'duration_s' => 0.2,
                ];
            }

            protected function collectContainerStats(string $service): array
            {
                return ['cpu' => '1.20%', 'mem' => '32MiB / 1GiB'];
            }

            protected function restoreRuntimeDefaults(string $opcache): void
            {
            }
        };
        $runner->failOnProfile = $failOnProfile;
        return $runner;
    }

    private function deleteDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->deleteDir($full);
                continue;
            }
            @unlink($full);
        }
        @rmdir($path);
    }
}
