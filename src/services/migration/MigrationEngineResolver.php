<?php

namespace PSFS\services\migration;

use PSFS\base\config\Config;
use PSFS\base\Logger;

class MigrationEngineResolver
{
    /**
     * @var array<string, MigrationEngineInterface>
     */
    private array $engines = [];

    public function __construct(MigrationEngineInterface ...$engines)
    {
        foreach ($engines as $engine) {
            $this->engines[$engine->getName()] = $engine;
        }
    }

    public function resolve(?string $requestedEngine = null, ?string $module = null): MigrationEngineInterface
    {
        $engineName = $requestedEngine ?: (string)Config::getParam('migrations.engine', 'phinx', $module);
        $engineName = strtolower(trim($engineName));
        if (!isset($this->engines[$engineName])) {
            throw new \InvalidArgumentException(sprintf('Unknown migration engine: %s', $engineName));
        }

        $engine = $this->engines[$engineName];
        if ($engine->isAvailable()) {
            return $engine;
        }

        $fallbackEnabled = (bool)Config::getParam('migrations.legacy_fallback_enabled', true, $module);
        if (!$fallbackEnabled || !isset($this->engines['propel'])) {
            throw new \RuntimeException(sprintf('Migration engine "%s" is not available and fallback is disabled', $engineName));
        }

        $fallback = $this->engines['propel'];
        if (!$fallback->isAvailable()) {
            throw new \RuntimeException(sprintf('Migration engine "%s" is not available and fallback engine propel is unavailable', $engineName));
        }

        Logger::log(sprintf('[MigrationEngineResolver] Falling back from %s to propel', $engineName), LOG_WARNING);

        return $fallback;
    }
}
