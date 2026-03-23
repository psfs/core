<?php

namespace PSFS\services\migration;

class MigrationExecutionContext
{
    public function __construct(
        private readonly string $module,
        private readonly string $configDir,
        private readonly string $migrationDir,
        private readonly bool $simulate = false
    ) {
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getModuleLower(): string
    {
        return strtolower($this->module);
    }

    public function getConfigDir(): string
    {
        return $this->configDir;
    }

    public function getMigrationDir(): string
    {
        return $this->migrationDir;
    }

    public function isSimulate(): bool
    {
        return $this->simulate;
    }
}
