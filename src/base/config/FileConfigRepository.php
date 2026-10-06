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
        $content = json_encode($data, JSON_PRETTY_PRINT);
        if (false === $content) {
            return false;
        }

        if (file_exists($this->configPath) && !chmod($this->configPath, 0600)) {
            return false;
        }

        // New files inherit owner-only permissions even under a permissive process umask.
        $previousUmask = umask(0077);
        try {
            $written = file_put_contents($this->configPath, $content, LOCK_EX);
        } finally {
            umask($previousUmask);
        }
        if (false === $written) {
            return false;
        }

        clearstatcache(true, $this->configPath);
        return chmod($this->configPath, 0600);
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
