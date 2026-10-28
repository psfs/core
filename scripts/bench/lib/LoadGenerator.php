<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * Sends a number of requests with several connections open at once and checks every response
 */
final class LoadGenerator
{
    public function __construct(private readonly string $url, private readonly string $marker)
    {
    }

    /**
     * @return array{rps: float, p50: float, p95: float} requests per second and latency in milliseconds
     */
    public function run(int $requests, int $concurrency): array
    {
        $multi = curl_multi_init();
        $started = [];
        $latencies = [];
        $begin = hrtime(true);
        $sent = 0;
        for (; $sent < min($concurrency, $requests); $sent++) {
            $this->add($multi, $started);
        }
        while ([] !== $started) {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi, 0.05);
            while (false !== ($done = curl_multi_info_read($multi))) {
                $latencies[] = $this->finish($multi, $done['handle'], $started);
                if ($sent < $requests) {
                    $sent++;
                    $this->add($multi, $started);
                }
            }
        }
        $seconds = (hrtime(true) - $begin) / 1e9;
        curl_multi_close($multi);
        sort($latencies);
        return ['rps' => $requests / $seconds, 'p50' => Stats::percentile($latencies, 50), 'p95' => Stats::percentile($latencies, 95)];
    }

    /**
     * @param array<int,float> $started
     */
    private function add(\CurlMultiHandle $multi, array &$started): void
    {
        $handle = curl_init($this->url);
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ['Accept: text/html,application/json']]);
        curl_multi_add_handle($multi, $handle);
        $started[spl_object_id($handle)] = hrtime(true);
    }

    /**
     * @param array<int,float> $started
     * @return float milliseconds
     */
    private function finish(\CurlMultiHandle $multi, \CurlHandle $handle, array &$started): float
    {
        $milliseconds = (hrtime(true) - $started[spl_object_id($handle)]) / 1e6;
        unset($started[spl_object_id($handle)]);
        $body = (string)curl_multi_getcontent($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_multi_remove_handle($multi, $handle);
        if (200 !== $status || !str_contains($body, $this->marker)) {
            throw new \RuntimeException(sprintf('unexpected response (HTTP %d, marker "%s" missing)', $status, $this->marker));
        }
        return $milliseconds;
    }
}
