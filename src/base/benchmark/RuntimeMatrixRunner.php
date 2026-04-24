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

            $scenarioResults = [];
            foreach ($scenarios as $scenario) {
                $runtime = (string)$scenario['runtime'];
                $service = $this->runtimeMap[$runtime]['service'];
                $baseUrl = $this->runtimeMap[$runtime]['base_url'];
                $scenarioId = $this->scenarioId($scenario);

                $this->applyConfigScenario($scenario);
                $this->recreateRuntimeService($service, (bool)$scenario['opcache']);
                $this->waitForHealth($baseUrl . '/_bench/ping', $this->healthTimeout);
                $this->warmup($baseUrl . '/_bench/metadata', min(20, $this->profiles[1]['concurrency'] ?? 20), $this->warmupRequests);

                $profileResults = [];
                foreach ($this->profiles as $profile) {
                    $profileResults[] = $this->runLoadProfile(
                        $baseUrl . '/_bench/metadata',
                        (string)$profile['name'],
                        $scenarioId,
                        (int)$profile['concurrency'],
                        (int)$profile['requests']
                    );
                }

                $stats = [
                    'runtime' => $this->collectContainerStats($service),
                    'redis' => $this->collectContainerStats('redis'),
                ];

                $scenarioResults[] = [
                    'scenario' => $scenario,
                    'profiles' => $profileResults,
                    'container_stats' => $stats,
                ];
            }

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
        $scenarios = [];
        foreach (array_keys($this->runtimeMap) as $runtime) {
            foreach ([false, true] as $debug) {
                foreach ([false, true] as $opcache) {
                    foreach ([false, true] as $redis) {
                        $scenarios[] = [
                            'runtime' => $runtime,
                            'debug' => $debug,
                            'opcache' => $opcache,
                            'redis' => $redis,
                        ];
                    }
                }
            }
        }
        return $scenarios;
    }

    /**
     * @param array<int, array<string, mixed>> $scenarioResults
     * @return array<string, mixed>
     */
    protected function buildSummary(array $scenarioResults): array
    {
        $rows = [];
        foreach ($scenarioResults as $scenarioResult) {
            $scenario = $scenarioResult['scenario'];
            foreach ($scenarioResult['profiles'] as $profile) {
                $rows[] = [
                    'runtime' => $scenario['runtime'],
                    'debug' => $scenario['debug'] ? 1 : 0,
                    'opcache' => $scenario['opcache'] ? 1 : 0,
                    'redis' => $scenario['redis'] ? 1 : 0,
                    'profile' => $profile['profile'],
                    'rps' => $profile['rps'],
                    'p95_ms' => $profile['p95_ms'],
                    'p99_ms' => $profile['p99_ms'],
                    'error_rate' => $profile['error_rate'],
                ];
            }
        }

        return [
            'scenario_count' => count($scenarioResults),
            'row_count' => count($rows),
            'rows' => $rows,
        ];
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
        $deadline = microtime(true) + max(1, $timeoutSeconds);
        while (microtime(true) < $deadline) {
            $response = @file_get_contents($url);
            if (is_string($response) && $response !== '') {
                $decoded = json_decode($response, true);
                if (is_array($decoded) && ($decoded['ok'] ?? false) === true) {
                    return;
                }
            }
            usleep(250000);
        }
        throw new RuntimeException('Health check failed for ' . $url);
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
        $current = json_decode($this->readConfigRaw(), true);
        if (!is_array($current)) {
            $current = [];
        }
        $current['debug'] = (bool)$scenario['debug'];
        $current['psfs.redis'] = (bool)$scenario['redis'];
        $current['redis.host'] = 'redis';
        $current['redis.port'] = 6379;
        $current['redis.timeout'] = 0.2;
        $current['metadata.engine.redis.enabled'] = (bool)$scenario['redis'];
        $current['metadata.engine.opcache.enabled'] = (bool)$scenario['opcache'];
        $current['psfs.cache.mode'] = $this->resolveCacheMode((bool)$scenario['opcache'], (bool)$scenario['redis']);
        $this->writeConfigRaw((string)json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    protected function recreateRuntimeService(string $service, bool $opcache): void
    {
        $this->runDockerComposeCommand(
            'up -d --force-recreate --no-deps ' . escapeshellarg($service),
            [
                'PHP_OPCACHE' => $opcache ? '1' : '0',
                'PSFS_BENCHMARK_ENABLED' => '1',
            ]
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
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptor, $pipes, $this->projectRoot, array_merge($_ENV, $env));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to execute command: ' . $command);
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = proc_close($process);
        if ($code !== 0) {
            throw new RuntimeException(trim('Command failed: ' . $command . PHP_EOL . $stderr));
        }
        return (string)$stdout;
    }

    protected function readConfigRaw(): string
    {
        $raw = @file_get_contents($this->configFile);
        if (!is_string($raw)) {
            throw new RuntimeException('Unable to read config file: ' . $this->configFile);
        }
        return $raw;
    }

    protected function writeConfigRaw(string $content): void
    {
        if (@file_put_contents($this->configFile, $content) === false) {
            throw new RuntimeException('Unable to write config file: ' . $this->configFile);
        }
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
        $timestamp = gmdate('Ymd_His');
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to serialize benchmark report');
        }
        $md = $this->buildMarkdownSummary($report);

        file_put_contents($this->outputDir . DIRECTORY_SEPARATOR . 'runtime-matrix-' . $timestamp . '.json', $json . PHP_EOL);
        file_put_contents($this->outputDir . DIRECTORY_SEPARATOR . 'runtime-matrix-' . $timestamp . '.md', $md);
        file_put_contents($this->outputDir . DIRECTORY_SEPARATOR . 'latest.json', $json . PHP_EOL);
        file_put_contents($this->outputDir . DIRECTORY_SEPARATOR . 'latest.md', $md);
    }

    /**
     * @param array<string, mixed> $report
     */
    protected function buildMarkdownSummary(array $report): string
    {
        $lines = [
            '# Runtime Matrix Benchmark',
            '',
            '| runtime | debug | opcache | redis | profile | rps | p95_ms | p99_ms | error_rate |',
            '|---|---:|---:|---:|---|---:|---:|---:|---:|',
        ];
        foreach ($report['summary']['rows'] as $row) {
            $lines[] = sprintf(
                '| %s | %d | %d | %d | %s | %.2f | %.3f | %.3f | %.6f |',
                $row['runtime'],
                $row['debug'],
                $row['opcache'],
                $row['redis'],
                $row['profile'],
                $row['rps'],
                $row['p95_ms'],
                $row['p99_ms'],
                $row['error_rate']
            );
        }
        $lines[] = '';
        return implode(PHP_EOL, $lines);
    }

    protected function ensureOutputDirectory(): void
    {
        if (!is_dir($this->outputDir) && !mkdir($this->outputDir, 0775, true) && !is_dir($this->outputDir)) {
            throw new RuntimeException('Unable to create output directory: ' . $this->outputDir);
        }
    }

    protected function assertEnvironment(): void
    {
        if (!file_exists($this->composeFile)) {
            throw new RuntimeException('docker-compose.yml not found: ' . $this->composeFile);
        }
        if (!file_exists($this->configFile)) {
            $this->initializeConfigFile();
        }
    }

    protected function initializeConfigFile(): void
    {
        $configDir = dirname($this->configFile);
        if (!is_dir($configDir) && !mkdir($configDir, 0775, true) && !is_dir($configDir)) {
            throw new RuntimeException('Unable to create config directory: ' . $configDir);
        }
        if (@file_put_contents($this->configFile, '{}' . PHP_EOL) === false) {
            throw new RuntimeException('Unable to initialize config file: ' . $this->configFile);
        }
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

    private function resolveCacheMode(bool $opcache, bool $redis): string
    {
        if ($opcache && !$redis) {
            return 'OPCACHE';
        }
        if (!$opcache && $redis) {
            return 'REDIS';
        }
        if (!$opcache && !$redis) {
            return 'MEMORY';
        }
        return 'NONE';
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
