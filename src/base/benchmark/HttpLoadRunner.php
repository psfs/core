<?php

namespace PSFS\base\benchmark;

class HttpLoadRunner
{
    public function __construct(private int $timeout = 10)
    {
        $this->timeout = max(2, $this->timeout);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(string $url, int $concurrency, int $requests, string $profile, string $scenario): array
    {
        $state = $this->initialState($concurrency, $requests, $profile, $scenario);
        $multi = curl_multi_init();

        $this->primeQueue($multi, $state, $url);
        $this->drainQueue($multi, $state, $url);
        curl_multi_close($multi);

        return $this->result($state);
    }

    public function percentile(array $samples, int $percent): float
    {
        if ($samples === []) {
            return 0.0;
        }
        $percent = max(0, min(100, $percent));
        $index = (int)floor((count($samples) - 1) * ($percent / 100));
        return (float)$samples[$index];
    }

    /**
     * @return array<string, mixed>
     */
    private function initialState(int $concurrency, int $requests, string $profile, string $scenario): array
    {
        return [
            'profile' => $profile,
            'scenario' => $scenario,
            'concurrency' => max(1, $concurrency),
            'requests' => max(1, $requests),
            'active' => [],
            'samples' => [],
            'errors' => 0,
            'timeouts' => 0,
            'bytes' => 0,
            'sent' => 0,
            'done' => 0,
            'started_at' => microtime(true),
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function primeQueue(\CurlMultiHandle $multi, array &$state, string $url): void
    {
        for ($i = 0; $i < $state['concurrency']; $i++) {
            $this->enqueue($multi, $state, $url);
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function drainQueue(\CurlMultiHandle $multi, array &$state, string $url): void
    {
        do {
            do {
                $status = curl_multi_exec($multi, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            $this->collectCompleted($multi, $state, $url);
            if ($running > 0) {
                curl_multi_select($multi, 0.5);
            }
        } while ($running > 0 || !empty($state['active']));
    }

    /**
     * @param array<string, mixed> $state
     */
    private function enqueue(\CurlMultiHandle $multi, array &$state, string $url): void
    {
        if ($state['sent'] >= $state['requests']) {
            return;
        }
        $ch = curl_init($this->requestUrl($url, $state));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        curl_multi_add_handle($multi, $ch);
        $state['active'][(int)$ch] = [
            'handle' => $ch,
            'start' => microtime(true),
        ];
        $state['sent']++;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function requestUrl(string $url, array $state): string
    {
        return $url
            . '?scenario=' . rawurlencode((string)$state['scenario'])
            . '&profile=' . rawurlencode((string)$state['profile'])
            . '&n=' . $state['sent'];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function collectCompleted(\CurlMultiHandle $multi, array &$state, string $url): void
    {
        while ($info = curl_multi_info_read($multi)) {
            $ch = $info['handle'];
            $meta = $this->activeMeta($state, $ch);
            if ($meta === null) {
                $this->closeHandle($multi, $ch);
                continue;
            }

            $this->recordHandleResult($state, $ch, $meta);
            unset($state['active'][(int)$ch]);
            $this->closeHandle($multi, $ch);
            $this->enqueue($multi, $state, $url);
        }
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>|null
     */
    private function activeMeta(array $state, \CurlHandle $ch): ?array
    {
        $meta = $state['active'][(int)$ch] ?? null;
        return is_array($meta) ? $meta : null;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $meta
     */
    private function recordHandleResult(array &$state, \CurlHandle $ch, array $meta): void
    {
        $state['samples'][] = (microtime(true) - (float)$meta['start']) * 1000;
        $state['bytes'] += strlen((string)curl_multi_getcontent($ch));
        $curlError = curl_errno($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($curlError !== 0) {
            $state['errors']++;
            $state['timeouts'] += $curlError === CURLE_OPERATION_TIMEDOUT ? 1 : 0;
        } elseif ($code !== 200) {
            $state['errors']++;
        }

        $state['done']++;
    }

    private function closeHandle(\CurlMultiHandle $multi, \CurlHandle $ch): void
    {
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function result(array $state): array
    {
        $samples = $state['samples'];
        sort($samples, SORT_NUMERIC);
        $elapsed = max(0.001, microtime(true) - (float)$state['started_at']);
        $done = (int)$state['done'];

        return [
            'profile' => $state['profile'],
            'requests' => $state['requests'],
            'completed' => $done,
            'concurrency' => $state['concurrency'],
            'rps' => round($done / $elapsed, 2),
            'p50_ms' => round($this->percentile($samples, 50), 3),
            'p95_ms' => round($this->percentile($samples, 95), 3),
            'p99_ms' => round($this->percentile($samples, 99), 3),
            'error_count' => $state['errors'],
            'error_rate' => round($done > 0 ? $state['errors'] / $done : 1.0, 6),
            'timeout_count' => $state['timeouts'],
            'timeout_rate' => round($done > 0 ? $state['timeouts'] / $done : 1.0, 6),
            'bytes' => $state['bytes'],
            'duration_s' => round($elapsed, 3),
        ];
    }
}
