<?php

namespace PSFS\base\benchmark;

use RuntimeException;

final class RuntimeMatrixEnvironment
{
    /**
     * @param array<string, array{service:string,base_url:string}> $runtimeMap
     * @param callable(string, array<string, string>):string|null $commandRunner
     */
    public function __construct(
        private string $projectRoot,
        private string $composeFile,
        private string $configFile,
        private array $runtimeMap,
        private $commandRunner = null
    ) {
    }

    public function assertReady(): void
    {
        if (!file_exists($this->composeFile)) {
            throw new RuntimeException('docker-compose.yml not found: ' . $this->composeFile);
        }
        if (!file_exists($this->configFile)) {
            $this->initializeConfigFile();
        }
    }

    public function readConfigRaw(): string
    {
        $raw = @file_get_contents($this->configFile);
        if (!is_string($raw)) {
            throw new RuntimeException('Unable to read config file: ' . $this->configFile);
        }
        return $raw;
    }

    public function writeConfigRaw(string $content): void
    {
        if (@file_put_contents($this->configFile, $content) === false) {
            throw new RuntimeException('Unable to write config file: ' . $this->configFile);
        }
    }

    /**
     * @param array{runtime:string,debug:bool,opcache:bool,redis:bool} $scenario
     */
    public function applyConfigScenario(array $scenario): void
    {
        $current = json_decode($this->readConfigRaw(), true);
        $current = is_array($current) ? $current : [];
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

    public function recreateRuntimeService(string $service, bool $opcache): void
    {
        $this->runDockerComposeCommand(
            'up -d --force-recreate --no-deps ' . escapeshellarg($service),
            [
                'PHP_OPCACHE' => $opcache ? '1' : '0',
                'PSFS_BENCHMARK_ENABLED' => '1',
            ]
        );
    }

    public function restoreRuntimeDefaults(string $opcache): void
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

    public function prepareBenchmarkRoutes(): void
    {
        $this->runCommand('docker exec core-php-1 php src/bin/psfs psfs:deploy:project');
    }

    public function waitForHealth(string $url, int $timeoutSeconds): void
    {
        $deadline = microtime(true) + max(1, $timeoutSeconds);
        while (microtime(true) < $deadline) {
            if ($this->isHealthy($url)) {
                return;
            }
            usleep(250000);
        }
        throw new RuntimeException('Health check failed for ' . $url);
    }

    /**
     * @return array<string, string>
     */
    public function collectContainerStats(string $service): array
    {
        $containerId = trim($this->runDockerComposeCommand('ps -q ' . escapeshellarg($service)));
        if ($containerId === '') {
            return ['cpu' => 'n/a', 'mem' => 'n/a'];
        }
        return $this->parseStatsLine($this->dockerStatsLine($containerId));
    }

    public function runDockerComposeCommand(string $arguments, array $env = []): string
    {
        $command = 'docker compose -f ' . escapeshellarg($this->composeFile) . ' ' . $arguments;
        return $this->runCommand($command, $env);
    }

    public function runCommand(string $command, array $env = []): string
    {
        if (is_callable($this->commandRunner)) {
            return (string)($this->commandRunner)($command, $env);
        }
        $process = proc_open($command, $this->processDescriptor(), $pipes, $this->projectRoot, array_merge($_ENV, $env));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to execute command: ' . $command);
        }
        return $this->processOutput($command, $process, $pipes);
    }

    private function initializeConfigFile(): void
    {
        $configDir = dirname($this->configFile);
        if (!is_dir($configDir) && !mkdir($configDir, 0775, true) && !is_dir($configDir)) {
            throw new RuntimeException('Unable to create config directory: ' . $configDir);
        }
        if (@file_put_contents($this->configFile, '{}' . PHP_EOL) === false) {
            throw new RuntimeException('Unable to initialize config file: ' . $this->configFile);
        }
    }

    private function resolveCacheMode(bool $opcache, bool $redis): string
    {
        if ($opcache && !$redis) {
            return 'OPCACHE';
        }
        if (!$opcache && $redis) {
            return 'REDIS';
        }
        return !$opcache && !$redis ? 'MEMORY' : 'NONE';
    }

    private function isHealthy(string $url): bool
    {
        $response = @file_get_contents($url);
        if (!is_string($response) || $response === '') {
            return false;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) && ($decoded['ok'] ?? false) === true;
    }

    private function dockerStatsLine(string $containerId): string
    {
        return trim($this->runCommand(
            'docker stats --no-stream --format ' . escapeshellarg('{{.CPUPerc}}|{{.MemUsage}}') . ' ' . escapeshellarg($containerId)
        ));
    }

    /**
     * @return array<string, string>
     */
    private function parseStatsLine(string $statsLine): array
    {
        if ($statsLine === '' || !str_contains($statsLine, '|')) {
            return ['cpu' => 'n/a', 'mem' => 'n/a'];
        }
        [$cpu, $mem] = array_map('trim', explode('|', $statsLine, 2));
        return ['cpu' => $cpu, 'mem' => $mem];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function processDescriptor(): array
    {
        return [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
    }

    /**
     * @param resource $process
     * @param array<int, resource> $pipes
     */
    private function processOutput(string $command, $process, array $pipes): string
    {
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
}
