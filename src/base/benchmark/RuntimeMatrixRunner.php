<?php

namespace PSFS\base\benchmark;

use RuntimeException;

class RuntimeMatrixRunner
{
    /**
     * @var array<string, array{service:string,base_url:string}>
     */
    private array $runtimeMap;

    /**
     * @var array<int, array{name:string,concurrency:int,requests:int}>
     */
    private array $profiles;

    private string $projectRoot;
    private string $composeFile;
    private string $configFile;
    private string $outputDir;
    private int $warmupRequests;
    private int $healthTimeout;
    private int $requestTimeout;
    private ?RuntimeMatrixEnvironment $environment = null;
    private ?RuntimeMatrixReportWriter $reportWriter = null;
    private ?RuntimeScenarioMatrix $scenarioMatrix = null;

    public function __construct(
        string $projectRoot,
        ?string $composeFile = null,
        ?string $configFile = null,
        ?string $outputDir = null,
        ?array $profiles = null,
        int $warmupRequests = 500,
        int $healthTimeout = 40,
        int $requestTimeout = 10
    ) {
        $this->projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $this->composeFile = $composeFile ?: $this->projectRoot . DIRECTORY_SEPARATOR . 'docker-compose.yml';
        $this->configFile = $configFile ?: $this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.json';
        $this->outputDir = $outputDir ?: $this->projectRoot . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'benchmark' . DIRECTORY_SEPARATOR . 'runtime-matrix';
        $this->runtimeMap = $this->buildRuntimeMap();
        $this->profiles = $profiles ?: [
            ['name' => 'L1', 'concurrency' => 1, 'requests' => 2000],
            ['name' => 'L2', 'concurrency' => 20, 'requests' => 4000],
            ['name' => 'L3', 'concurrency' => 100, 'requests' => 6000],
        ];
        $this->warmupRequests = max(1, $warmupRequests);
        $this->healthTimeout = max(5, $healthTimeout);
        $this->requestTimeout = max(2, $requestTimeout);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $this->assertEnvironment();
        $this->ensureOutputDirectory();
        $configBackup = $this->readConfigRaw();
        $initialOpcache = getenv('PHP_OPCACHE');

        try {
            $this->prepareBenchmarkRoutes();
            $scenarios = $this->buildScenarios();
            if (count($scenarios) !== 16) {
                throw new RuntimeException('Invalid scenario matrix size: expected 16');
            }
            $scenarioResults = $this->scenarioExecutor()->execute($scenarios);

            $this->assertScenarioCompleteness($scenarioResults);
            $this->assertNoHttpErrors($scenarioResults);

            $report = [
                'generated_at' => gmdate('c'),
                'project_root' => $this->projectRoot,
                'profiles' => $this->profiles,
                'scenarios' => $scenarioResults,
                'summary' => $this->buildSummary($scenarioResults),
            ];
            $this->writeReports($report);
            return $report;
        } finally {
            $this->writeConfigRaw($configBackup);
            $this->restoreRuntimeDefaults($initialOpcache === false ? '0' : (string)$initialOpcache);
        }
    }

    /**
     * @return array<int, array{runtime:string,debug:bool,opcache:bool,redis:bool}>
     */
    public function buildScenarios(): array
    {
        return $this->scenarioMatrix()->build($this->runtimeMap);
    }

    /**
     * @param array<int, array<string, mixed>> $scenarioResults
     * @return array<string, mixed>
     */
    protected function buildSummary(array $scenarioResults): array
    {
        return $this->reportWriter()->buildSummary($scenarioResults);
    }

    protected function warmup(string $url, int $concurrency, int $requests): void
    {
        $this->runHttpLoad($url, $concurrency, $requests, 'warmup', 'warmup');
    }

    /**
     * @return array<string, mixed>
     */
    protected function runLoadProfile(string $url, string $profileName, string $scenarioId, int $concurrency, int $requests): array
    {
        return $this->runHttpLoad($url, $concurrency, $requests, $profileName, $scenarioId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function runHttpLoad(
        string $url,
        int $concurrency,
        int $requests,
        string $profileName,
        string $scenarioId
    ): array {
        return (new HttpLoadRunner($this->requestTimeout))->run($url, $concurrency, $requests, $profileName, $scenarioId);
    }

    /**
     * @param array<int, float> $samples
     */
    protected function percentile(array $samples, int $percent): float
    {
        if ($samples === []) {
            return 0.0;
        }
        return (new HttpLoadRunner($this->requestTimeout))->percentile($samples, $percent);
    }

    protected function waitForHealth(string $url, int $timeoutSeconds): void
    {
        $this->environment()->waitForHealth($url, $timeoutSeconds);
    }

    /**
     * @return array<string, string>
     */
    protected function collectContainerStats(string $service): array
    {
        $containerId = trim($this->runDockerComposeCommand('ps -q ' . escapeshellarg($service)));
        if ($containerId === '') {
            return ['cpu' => 'n/a', 'mem' => 'n/a'];
        }
        $statsLine = trim($this->runCommand(
            'docker stats --no-stream --format ' . escapeshellarg('{{.CPUPerc}}|{{.MemUsage}}') . ' ' . escapeshellarg($containerId)
        ));
        if ($statsLine === '' || !str_contains($statsLine, '|')) {
            return ['cpu' => 'n/a', 'mem' => 'n/a'];
        }
        [$cpu, $mem] = array_map('trim', explode('|', $statsLine, 2));
        return ['cpu' => $cpu, 'mem' => $mem];
    }

    /**
     * @param array{runtime:string,debug:bool,opcache:bool,redis:bool} $scenario
     */
    protected function applyConfigScenario(array $scenario): void
    {
        $this->environment()->applyConfigScenario($scenario);
    }

    protected function recreateRuntimeService(string $service, bool $opcache): void
    {
        $this->runDockerComposeCommand(
            'up -d --force-recreate --no-deps ' . escapeshellarg($service),
            ['PHP_OPCACHE' => $opcache ? '1' : '0', 'PSFS_BENCHMARK_ENABLED' => '1']
        );
    }

    protected function restoreRuntimeDefaults(string $opcache): void
    {
        foreach ($this->runtimeMap as $runtime) {
            $this->runDockerComposeCommand(
                'up -d --force-recreate --no-deps ' . escapeshellarg($runtime['service']),
                [
                    'PHP_OPCACHE' => $opcache === '' ? '0' : $opcache,
                    'PSFS_BENCHMARK_ENABLED' => '0',
                ]
            );
        }
    }

    protected function runDockerComposeCommand(string $arguments, array $env = []): string
    {
        $command = 'docker compose -f ' . escapeshellarg($this->composeFile) . ' ' . $arguments;
        return $this->runCommand($command, $env);
    }

    protected function prepareBenchmarkRoutes(): void
    {
        $this->runCommand('docker exec core-php-1 php src/bin/psfs psfs:deploy:project');
    }

    protected function runCommand(string $command, array $env = []): string
    {
        return $this->environment()->runCommand($command, $env);
    }

    protected function readConfigRaw(): string
    {
        return $this->environment()->readConfigRaw();
    }

    protected function writeConfigRaw(string $content): void
    {
        $this->environment()->writeConfigRaw($content);
    }

    /**
     * @param array<int, array<string, mixed>> $scenarioResults
     */
    protected function assertScenarioCompleteness(array $scenarioResults): void
    {
        if (count($scenarioResults) !== 16) {
            throw new RuntimeException('Scenario execution is incomplete');
        }
        foreach ($scenarioResults as $scenarioResult) {
            if (count($scenarioResult['profiles'] ?? []) !== count($this->profiles)) {
                throw new RuntimeException('Profile execution is incomplete');
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $scenarioResults
     */
    protected function assertNoHttpErrors(array $scenarioResults): void
    {
        foreach ($scenarioResults as $scenarioResult) {
            foreach ($scenarioResult['profiles'] as $profile) {
                if (($profile['error_rate'] ?? 1.0) > 0.0) {
                    throw new RuntimeException('Benchmark errors detected in scenario ' . $this->scenarioId($scenarioResult['scenario']));
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $report
     */
    protected function writeReports(array $report): void
    {
        $this->reportWriter()->writeReports($report);
    }

    /**
     * @param array<string, mixed> $report
     */
    protected function buildMarkdownSummary(array $report): string
    {
        return $this->reportWriter()->buildMarkdownSummary($report);
    }

    protected function ensureOutputDirectory(): void
    {
        $this->reportWriter()->ensureOutputDirectory();
    }

    protected function assertEnvironment(): void
    {
        $this->environment()->assertReady();
    }

    protected function initializeConfigFile(): void
    {
        $this->environment()->assertReady();
    }

    /**
     * @param array{runtime:string,debug:bool,opcache:bool,redis:bool} $scenario
     */
    private function scenarioId(array $scenario): string
    {
        return implode('_', [
            $scenario['runtime'],
            'd' . ((int)$scenario['debug']),
            'o' . ((int)$scenario['opcache']),
            'r' . ((int)$scenario['redis']),
        ]);
    }

    private function environment(): RuntimeMatrixEnvironment
    {
        if (!$this->environment instanceof RuntimeMatrixEnvironment) {
            $this->environment = new RuntimeMatrixEnvironment(
                $this->projectRoot,
                $this->composeFile,
                $this->configFile,
                $this->runtimeMap
            );
        }
        return $this->environment;
    }

    private function reportWriter(): RuntimeMatrixReportWriter
    {
        if (!$this->reportWriter instanceof RuntimeMatrixReportWriter) {
            $this->reportWriter = new RuntimeMatrixReportWriter($this->outputDir);
        }
        return $this->reportWriter;
    }

    private function scenarioMatrix(): RuntimeScenarioMatrix
    {
        if (!$this->scenarioMatrix instanceof RuntimeScenarioMatrix) {
            $this->scenarioMatrix = new RuntimeScenarioMatrix();
        }
        return $this->scenarioMatrix;
    }

    private function scenarioExecutor(): RuntimeScenarioExecutor
    {
        return new RuntimeScenarioExecutor(
            $this->runtimeMap,
            $this->profiles,
            $this->warmupRequests,
            $this->healthTimeout,
            function (array $scenario): void {
                $this->applyConfigScenario($scenario);
            },
            function (string $service, bool $opcache): void {
                $this->recreateRuntimeService($service, $opcache);
            },
            function (string $url, int $timeout): void {
                $this->waitForHealth($url, $timeout);
            },
            function (string $url, int $concurrency, int $requests): void {
                $this->warmup($url, $concurrency, $requests);
            },
            fn (
                string $url,
                string $profile,
                string $scenarioId,
                int $concurrency,
                int $requests
            ): array => $this->runLoadProfile($url, $profile, $scenarioId, $concurrency, $requests),
            fn (string $service): array => $this->collectContainerStats($service),
            fn (array $scenario): string => $this->scenarioId($scenario)
        );
    }

    /**
     * @return array<string, array{service:string,base_url:string}>
     */
    private function buildRuntimeMap(): array
    {
        return [
            'php-s' => [
                'service' => 'php',
                'base_url' => 'http://127.0.0.1:' . $this->envValue('HOST_PORT', '8001'),
            ],
            'swoole' => [
                'service' => 'php-swoole',
                'base_url' => 'http://127.0.0.1:' . $this->envValue('HOST_PORT_SWOOLE', '8011'),
            ],
        ];
    }

    private function envValue(string $name, string $default): string
    {
        $value = getenv($name);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $envFile = $this->projectRoot . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envFile)) {
            return $default;
        }

        $values = parse_ini_file($envFile, false, INI_SCANNER_RAW);
        $fileValue = is_array($values) ? ($values[$name] ?? null) : null;
        return is_string($fileValue) && $fileValue !== '' ? $fileValue : $default;
    }
}
