<?php

namespace PSFS\base\command;

use Symfony\Component\Console\Application;

class CommandContext
{
    public function __construct(private Application $console, private array $metadata = [])
    {
    }

    public function getConsole(): Application
    {
        return $this->console;
    }

    public function getMetadata(?string $key = null, mixed $default = null): mixed
    {
        if (null === $key) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? $default;
    }

    public function withMetadata(string $key, mixed $value): self
    {
        $metadata = $this->metadata;
        $metadata[$key] = $value;

        return new self($this->console, $metadata);
    }
}

