<?php

namespace PSFS\base\benchmark;

final class RuntimeScenarioMatrix
{
    /**
     * @param array<string, array{service:string,base_url:string}> $runtimeMap
     * @return array<int, array{runtime:string,debug:bool,opcache:bool,redis:bool}>
     */
    public function build(array $runtimeMap): array
    {
        $scenarios = [];
        foreach (array_keys($runtimeMap) as $runtime) {
            array_push($scenarios, ...$this->runtimeScenarios((string)$runtime));
        }
        return $scenarios;
    }

    /**
     * @return array<int, array{runtime:string,debug:bool,opcache:bool,redis:bool}>
     */
    private function runtimeScenarios(string $runtime): array
    {
        $scenarios = [];
        foreach ([false, true] as $debug) {
            foreach ([false, true] as $opcache) {
                foreach ([false, true] as $redis) {
                    $scenarios[] = compact('runtime', 'debug', 'opcache', 'redis');
                }
            }
        }
        return $scenarios;
    }
}
