<?php

namespace PSFS\base\command;

class CommandResult
{
    public function __construct(
        private bool $success,
        private int $registeredCommands = 0,
        private ?string $message = null
    ) {
    }

    public static function success(int $registeredCommands = 0, ?string $message = null): self
    {
        return new self(true, $registeredCommands, $message);
    }

    public static function failure(?string $message = null): self
    {
        return new self(false, 0, $message);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getRegisteredCommands(): int
    {
        return $this->registeredCommands;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}

