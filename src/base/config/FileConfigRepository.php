<?php

namespace PSFS\base\config;

class FileConfigRepository implements ConfigRepositoryInterface
{
    protected string $configPath;

    public function __construct(string $configPath)
    {
        $this->configPath = $configPath;
    }

    public function read(): array
    {
        if (!file_exists($this->configPath)) {
            return [];
        }
        $content = file_get_contents($this->configPath);
        return json_decode($content ?: '', true) ?: [];
    }

    public function save(array $data): bool
    {
        return false !== file_put_contents($this->configPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function refresh(): array
    {
        return $this->read();
    }

    public function invalidate(): void
    {
    }

    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    public function getFileSignature(): string
    {
        $mtime = file_exists($this->configPath) ? (string)filemtime($this->configPath) : 'missing';
        $hash = file_exists($this->configPath) ? sha1_file($this->configPath) : 'missing';
        return $mtime . ':' . $hash;
    }
}

