<?php

namespace PSFS\controller;

use PSFS\base\runtime\RuntimeMode;
use PSFS\base\types\Controller;
use PSFS\base\types\helpers\MetadataReader;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Route;
use ReflectionMethod;

class BenchmarkController extends Controller
{
    public static function isBenchmarkEnabled(): bool
    {
        $value = getenv('PSFS_BENCHMARK_ENABLED');
        return is_string($value) && trim($value) === '1';
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildPingPayload(): array
    {
        return [
            'ok' => true,
            'runtime' => RuntimeMode::getCurrentMode(),
            'timestamp' => microtime(true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildMetadataPayload(): array
    {
        $method = new ReflectionMethod(ConfigController::class, 'config');
        $doc = (string)$method->getDocComment();

        return [
            'ok' => true,
            'route' => MetadataReader::getTagValue('route', $doc, null, $method),
            'http' => MetadataReader::getTagValue('http', $doc, 'ALL', $method),
            'deprecated' => MetadataReader::hasDeprecated($method, $doc),
            'engine_stats' => MetadataReader::getEngineStats(),
        ];
    }

    #[HttpMethod('GET')]
    #[Route('/_bench/ping')]
    public function ping()
    {
        if (!self::isBenchmarkEnabled()) {
            return $this->json(['error' => 'benchmark_disabled'], 404);
        }
        return $this->json(self::buildPingPayload());
    }

    #[HttpMethod('GET')]
    #[Route('/_bench/metadata')]
    public function metadata()
    {
        if (!self::isBenchmarkEnabled()) {
            return $this->json(['error' => 'benchmark_disabled'], 404);
        }
        return $this->json(self::buildMetadataPayload());
    }
}
