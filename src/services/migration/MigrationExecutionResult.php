<?php

namespace PSFS\services\migration;

class MigrationExecutionResult
{
    public function __construct(
        private readonly string $engine,
        private readonly bool $success,
        private readonly int $exitCode,
        private readonly string $output,
        private readonly ?string $command = null
    ) {
    }

    public static function success(string $engine, string $output = '', ?string $command = null): self
    {
        return new self($engine, true, 0, $output, $command);
    }

    public static function failure(string $engine, string $output, int $exitCode = 1, ?string $command = null): self
    {
        return new self($engine, false, $exitCode, $output, $command);
    }

    public function getEngine(): string
    {
        return $this->engine;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }
}
