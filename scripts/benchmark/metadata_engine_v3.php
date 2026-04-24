<?php

declare(strict_types=1);

use PSFS\base\config\Config;
use PSFS\base\types\helpers\MetadataReader;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$iterations = max(100, (int)($argv[1] ?? 2000));
$classLimit = max(1, (int)($argv[2] ?? 60));

$allClasses = [];
foreach (['src/controller', 'src/base/types/helpers', 'src/base/dto', 'src/base/api', 'src/service'] as $scope) {
    $root = dirname(__DIR__, 2) . '/' . $scope;
    if (!is_dir($root)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (str_contains($path, '/command/') || str_contains($path, '/bin/')) {
            continue;
        }
        $relative = str_replace(dirname(__DIR__, 2) . '/src/', '', $path);
        $fqcn = 'PSFS\\' . str_replace('/', '\\', substr($relative, 0, -4));
        if (!class_exists($fqcn)) {
            continue;
        }
        try {
            $reflection = new \ReflectionClass($fqcn);
            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }
            if (!is_string($reflection->getFileName())) {
                continue;
            }
            $allClasses[] = $fqcn;
        } catch (Throwable) {
        }
    }
}

sort($allClasses, SORT_STRING);
$classes = array_slice(array_values(array_unique($allClasses)), 0, $classLimit);
if ($classes === []) {
    fwrite(STDERR, "No PSFS classes discovered for benchmark.\n");
    exit(1);
}

$configBackup = Config::getInstance()->dumpConfig();

$configureEngine = static function (bool $enabled): void {
    $config = Config::getInstance()->dumpConfig();
    $config['debug'] = false;
    $config['metadata.attributes.enabled'] = true;
    $config['metadata.annotations.fallback.enabled'] = false;
    $config['metadata.engine.enabled'] = $enabled;
    $config['metadata.engine.redis.enabled'] = false;
    $config['metadata.engine.opcache.enabled'] = false;
    $config['metadata.engine.swr.enabled'] = true;
    $config['metadata.engine.soft_ttl'] = 300;
    $config['metadata.engine.hard_ttl'] = 900;
    Config::save($config, []);
    Config::getInstance()->loadConfigData(true);
    MetadataReader::resetEngineCaches();
};

$run = static function (array $classes, int $iterations): array {
    $samplesUs = [];
    $startedAt = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $class = $classes[$i % count($classes)];
        $reflection = new \ReflectionClass($class);

        $method = null;
        foreach ($reflection->getMethods() as $candidate) {
            if ($candidate->class === $class && !$candidate->isAbstract()) {
                $method = $candidate;
                break;
            }
        }

        $docClass = (string)$reflection->getDocComment();
        $loopStart = hrtime(true);
        MetadataReader::getTagValue('api', $docClass, null, $reflection);
        if ($method !== null) {
            $docMethod = (string)$method->getDocComment();
            MetadataReader::getTagValue('route', $docMethod, null, $method);
            MetadataReader::getTagValue('http', $docMethod, 'ALL', $method);
            MetadataReader::hasDeprecated($method, $docMethod);
            MetadataReader::extractReturnSpec($method, $docMethod);
        }
        $samplesUs[] = (hrtime(true) - $loopStart) / 1000;
    }

    sort($samplesUs, SORT_NUMERIC);
    $p95Index = (int)floor((count($samplesUs) - 1) * 0.95);
    $p99Index = (int)floor((count($samplesUs) - 1) * 0.99);

    return [
        'iterations' => $iterations,
        'classes' => count($classes),
        'total_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        'p95_us' => round($samplesUs[$p95Index] ?? 0.0, 2),
        'p99_us' => round($samplesUs[$p99Index] ?? 0.0, 2),
        'mean_us' => round(array_sum($samplesUs) / max(1, count($samplesUs)), 2),
        'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    ];
};

try {
    $configureEngine(false);
    $baseline = $run($classes, $iterations);

    $configureEngine(true);
    MetadataReader::getTagValue('api', '', null, new \ReflectionClass($classes[0]));
    $v3 = $run($classes, $iterations);
    $stats = MetadataReader::getEngineStats();

    $p95Boost = $v3['p95_us'] > 0 ? round($baseline['p95_us'] / $v3['p95_us'], 2) : null;

    echo json_encode([
        'scenario' => 'metadata_engine_v3',
        'iterations' => $iterations,
        'class_sample' => count($classes),
        'baseline' => $baseline,
        'engine_v3' => $v3,
        'improvement' => [
            'p95_x' => $p95Boost,
        ],
        'engine_stats' => $stats,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    Config::save($configBackup, []);
    Config::getInstance()->loadConfigData(true);
    MetadataReader::resetEngineCaches();
}
