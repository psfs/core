<?php

namespace PSFS\base\benchmark;

final class RuntimeScenarioExecutor
{
    /**
     * @param array<string, array{service:string,base_url:string}> $runtimeMap
     * @param array<int, array{name:string,concurrency:int,requests:int}> $profiles
     * @param callable(array{runtime:string,debug:bool,opcache:bool,redis:bool}):void $applyScenario
     * @param callable(string, bool):void $recreateRuntime
     * @param callable(string, int):void $waitForHealth
     * @param callable(string, int, int):void $warmup
     * @param callable(string, string, string, int, int):array<string, mixed> $runProfile
     * @param callable(string):array<string, string> $collectStats
     * @param callable(array{runtime:string,debug:bool,opcache:bool,redis:bool}):string $scenarioId
     */
    public function __construct(
        private array $runtimeMap,
        private array $profiles,
        private int $warmupRequests,
        private int $healthTimeout,
        private $applyScenario,
        private $recreateRuntime,
        private $waitForHealth,
        private $warmup,
        private $runProfile,
        private $collectStats,
        private $scenarioId
    ) {
    }

    /**
     * @param array<int, array{runtime:string,debug:bool,opcache:bool,redis:bool}> $scenarios
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $scenarios): array
    {
        $results = [];
        foreach ($scenarios as $scenario) {
            $results[] = $this->executeScenario($scenario);
        }
        return $results;
    }

    /**
     * @param array{runtime:string,debug:bool,opcache:bool,redis:bool} $scenario
     * @return array<string, mixed>
     */
    private function executeScenario(array $scenario): array
    {
        $runtime = (string)$scenario['runtime'];
        $service = $this->runtimeMap[$runtime]['service'];
        $baseUrl = $this->runtimeMap[$runtime]['base_url'];
        $scenarioId = ($this->scenarioId)($scenario);

        ($this->applyScenario)($scenario);
        ($this->recreateRuntime)($service, (bool)$scenario['opcache']);
        ($this->waitForHealth)($baseUrl . '/_bench/ping', $this->healthTimeout);
        ($this->warmup)($this->metadataUrl($baseUrl), $this->warmupConcurrency(), $this->warmupRequests);

        return [
            'scenario' => $scenario,
            'profiles' => $this->profileResults($baseUrl, $scenarioId),
            'container_stats' => [
                'runtime' => ($this->collectStats)($service),
                'redis' => ($this->collectStats)('redis'),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function profileResults(string $baseUrl, string $scenarioId): array
    {
        $results = [];
        foreach ($this->profiles as $profile) {
            $results[] = ($this->runProfile)(
                $this->metadataUrl($baseUrl),
                (string)$profile['name'],
                $scenarioId,
                (int)$profile['concurrency'],
                (int)$profile['requests']
            );
        }
        return $results;
    }

    private function warmupConcurrency(): int
    {
        return min(20, $this->profiles[1]['concurrency'] ?? 20);
    }

    private function metadataUrl(string $baseUrl): string
    {
        return $baseUrl . '/_bench/metadata';
    }
}
