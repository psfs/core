<?php

declare(strict_types=1);

use PSFS\base\benchmark\HttpLoadRunner;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

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
    [
        'name' => 'L1',
        'concurrency' => max(1, (int)($opts['concurrency-l1'] ?? 1)),
        'requests' => max(1, (int)($opts['requests-l1'] ?? ($quick ? 200 : 2000))),
    ],
    [
        'name' => 'L2',
        'concurrency' => max(1, (int)($opts['concurrency-l2'] ?? ($quick ? 10 : 20))),
        'requests' => max(1, (int)($opts['requests-l2'] ?? ($quick ? 500 : 4000))),
    ],
    [
        'name' => 'L3',
        'concurrency' => max(1, (int)($opts['concurrency-l3'] ?? ($quick ? 25 : 100))),
        'requests' => max(1, (int)($opts['requests-l3'] ?? ($quick ? 800 : 6000))),
    ],
];

$pingUrl = $baseUrl . '/_bench/ping';
$metadataUrl = $baseUrl . '/_bench/metadata';
$loadRunner = new HttpLoadRunner($timeout);

waitForHealthy($pingUrl, 45);
$loadRunner->run($metadataUrl, min(20, $profiles[1]['concurrency']), $warmupRequests, 'warmup', $scenario);

$results = [];
foreach ($profiles as $profile) {
    $results[] = $loadRunner->run(
        $metadataUrl,
        $profile['concurrency'],
        $profile['requests'],
        $profile['name'],
        $scenario
    );
}

foreach ($results as $result) {
    if (($result['error_rate'] ?? 1.0) > $maxErrorRate) {
        fwrite(
            STDERR,
            sprintf('[run_http_profile] error_rate > %.6f for %s', $maxErrorRate, (string)$result['profile']) . PHP_EOL
        );
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
