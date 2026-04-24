<?php

declare(strict_types=1);

$opts = getopt('', [
    'runtime::',
    'base-url::',
    'scenario::',
    'quick',
    'requests-l1::',
    'requests-l2::',
    'requests-l3::',
    'concurrency-l1::',
    'concurrency-l2::',
    'concurrency-l3::',
    'warmup::',
    'timeout::',
    'max-error-rate::',
]);

$runtime = (string)($opts['runtime'] ?? 'php-s');
$defaultBaseUrl = $runtime === 'swoole' ? 'http://php-swoole:8080' : 'http://127.0.0.1:8080';
$baseUrl = rtrim((string)($opts['base-url'] ?? $defaultBaseUrl), '/');
$scenario = (string)($opts['scenario'] ?? $runtime);
$quick = array_key_exists('quick', $opts);
$timeout = max(2, (int)($opts['timeout'] ?? ($quick ? 6 : 10)));
$warmupRequests = max(1, (int)($opts['warmup'] ?? ($quick ? 100 : 500)));
$maxErrorRate = max(0.0, (float)($opts['max-error-rate'] ?? 0.0));

$profiles = [
    ['name' => 'L1', 'concurrency' => max(1, (int)($opts['concurrency-l1'] ?? ($quick ? 1 : 1))), 'requests' => max(1, (int)($opts['requests-l1'] ?? ($quick ? 200 : 2000)))],
    ['name' => 'L2', 'concurrency' => max(1, (int)($opts['concurrency-l2'] ?? ($quick ? 10 : 20))), 'requests' => max(1, (int)($opts['requests-l2'] ?? ($quick ? 500 : 4000)))],
    ['name' => 'L3', 'concurrency' => max(1, (int)($opts['concurrency-l3'] ?? ($quick ? 25 : 100))), 'requests' => max(1, (int)($opts['requests-l3'] ?? ($quick ? 800 : 6000)))],
];

$pingUrl = $baseUrl . '/_bench/ping';
$metadataUrl = $baseUrl . '/_bench/metadata';

waitForHealthy($pingUrl, 45);
runLoad($metadataUrl, min(20, $profiles[1]['concurrency']), $warmupRequests, 'warmup', $scenario, $timeout);

$results = [];
foreach ($profiles as $profile) {
    $results[] = runLoad(
        $metadataUrl,
        $profile['concurrency'],
        $profile['requests'],
        $profile['name'],
        $scenario,
        $timeout
    );
}

foreach ($results as $result) {
    if (($result['error_rate'] ?? 1.0) > $maxErrorRate) {
        fwrite(STDERR, sprintf('[run_http_profile] error_rate > %.6f for %s', $maxErrorRate, (string)$result['profile']) . PHP_EOL);
        echo json_encode([
            'ok' => false,
            'runtime' => $runtime,
            'scenario' => $scenario,
            'base_url' => $baseUrl,
            'max_error_rate' => $maxErrorRate,
            'profiles' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(1);
    }
}

echo json_encode([
    'ok' => true,
    'runtime' => $runtime,
    'scenario' => $scenario,
    'base_url' => $baseUrl,
    'max_error_rate' => $maxErrorRate,
    'profiles' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(0);

function waitForHealthy(string $url, int $timeoutSeconds): void
{
    $deadline = microtime(true) + max(1, $timeoutSeconds);
    while (microtime(true) < $deadline) {
        $body = @file_get_contents($url);
        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && ($decoded['ok'] ?? false) === true) {
                return;
            }
        }
        usleep(250000);
    }
    throw new RuntimeException('Health timeout for ' . $url);
}

/**
 * @return array<string, mixed>
 */
function runLoad(string $url, int $concurrency, int $requests, string $profile, string $scenario, int $timeout): array
{
    $concurrency = max(1, $concurrency);
    $requests = max(1, $requests);

    $multi = curl_multi_init();
    $active = [];
    $latency = [];
    $errors = 0;
    $timeouts = 0;
    $bytes = 0;
    $sent = 0;
    $done = 0;
    $startedAt = microtime(true);

    $enqueue = static function () use (&$sent, $requests, $url, $profile, $scenario, $timeout, $multi, &$active): void {
        if ($sent >= $requests) {
            return;
        }
        $requestUrl = $url . '?scenario=' . rawurlencode($scenario) . '&profile=' . rawurlencode($profile) . '&n=' . $sent;
        $ch = curl_init($requestUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        curl_multi_add_handle($multi, $ch);
        $active[(int)$ch] = ['handle' => $ch, 'start' => microtime(true)];
        $sent++;
    };

    for ($i = 0; $i < $concurrency; $i++) {
        $enqueue();
    }

    do {
        do {
            $status = curl_multi_exec($multi, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        while ($info = curl_multi_info_read($multi)) {
            $ch = $info['handle'];
            $id = (int)$ch;
            $meta = $active[$id] ?? null;
            if ($meta === null) {
                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
                continue;
            }

            $latency[] = (microtime(true) - (float)$meta['start']) * 1000;
            $body = (string)curl_multi_getcontent($ch);
            $bytes += strlen($body);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_errno($ch);

            if ($curlError !== 0) {
                $errors++;
                if ($curlError === CURLE_OPERATION_TIMEDOUT) {
                    $timeouts++;
                }
            } elseif ($code !== 200) {
                $errors++;
            }

            $done++;
            unset($active[$id]);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
            $enqueue();
        }

        if ($running > 0) {
            curl_multi_select($multi, 0.5);
        }
    } while ($running > 0 || !empty($active));

    curl_multi_close($multi);

    sort($latency, SORT_NUMERIC);
    $elapsed = max(0.001, microtime(true) - $startedAt);
    $errorRate = $done > 0 ? $errors / $done : 1.0;
    $timeoutRate = $done > 0 ? $timeouts / $done : 1.0;

    return [
        'profile' => $profile,
        'concurrency' => $concurrency,
        'requests' => $requests,
        'completed' => $done,
        'rps' => round($done / $elapsed, 2),
        'p50_ms' => round(percentile($latency, 50), 3),
        'p95_ms' => round(percentile($latency, 95), 3),
        'p99_ms' => round(percentile($latency, 99), 3),
        'error_count' => $errors,
        'error_rate' => round($errorRate, 6),
        'timeout_count' => $timeouts,
        'timeout_rate' => round($timeoutRate, 6),
        'bytes' => $bytes,
        'duration_s' => round($elapsed, 3),
    ];
}

/**
 * @param array<int, float> $samples
 */
function percentile(array $samples, int $percent): float
{
    if ($samples === []) {
        return 0.0;
    }
    $index = (int)floor((count($samples) - 1) * (max(0, min(100, $percent)) / 100));
    return (float)$samples[$index];
}
